<?php

// Deployment-only bootstrap. The legacy system installer destroys tables and is never called.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    new DateTimeZone(config('app.timezone'));
    if (str_starts_with(config('app.url'), 'https://')) {
        $certificate = file_get_contents('/run/wl-config/tls/origin.pem');
        $key = file_get_contents('/run/wl-config/tls/origin.key');
        $details = openssl_x509_parse($certificate);
        if ($details === false || ! openssl_x509_check_private_key($certificate, $key)) {
            throw new RuntimeException('Origin certificate is invalid or does not match its private key.');
        }
        if ($details['validFrom_time_t'] > time() || $details['validTo_time_t'] <= time()) {
            throw new RuntimeException('Origin certificate is expired or not yet valid.');
        }
        $hostname = parse_url(config('app.url'), PHP_URL_HOST);
        $names = explode(', ', $details['extensions']['subjectAltName'] ?? '');
        $matches = false;
        foreach ($names as $name) {
            if (str_starts_with($name, 'DNS:')) {
                $pattern = '/^'.str_replace('\\*', '[^.]+', preg_quote(substr($name, 4), '/')).'$/i';
                $matches = $matches || preg_match($pattern, $hostname) === 1;
            }
        }
        if (! $matches) {
            throw new RuntimeException('Origin certificate SAN does not cover the selected hostname.');
        }
    }
    $state = json_decode(file_get_contents('/run/wl-config/state.json'), true, 512, JSON_THROW_ON_ERROR);
    $marker = storage_path('.initialized');
    $lock = fopen(storage_path('.bootstrap.lock'), 'c+');
    if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another bootstrap is still running; wait before retrying.');
    }
    foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
        if (! is_dir(storage_path($directory))) {
            mkdir(storage_path($directory), 0775, true);
        }
    }
    $run = function (string $command, array $arguments = []): void {
        $status = Artisan::call($command, $arguments + ['--no-interaction' => true]);
        fwrite(STDOUT, Artisan::output());
        if ($status !== 0) {
            throw new RuntimeException('Bootstrap command failed: '.$command);
        }
    };
    $run('migrate', ['--force' => true]);
    if (! file_exists($marker)) {
        $run('system:settings:install');
        DB::transaction(function () use ($run, $state): void {
            $run('db:seed', ['--force' => true]);
            $admin = App\Models\User::query()->where('email', 'admin')->role('Super Admin')->firstOrFail();
            $admin->update(['password' => Hash::make($state['admin_password'])]);
        });
        file_put_contents($marker, gmdate(DATE_ATOM)."\n", LOCK_EX);
    }
    // Root initializes the shared volume; services run unprivileged afterwards.
    $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(storage_path(), FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    chown(storage_path(), 'www-data');
    chgrp(storage_path(), 'www-data');
    foreach ($paths as $path) {
        if (! $path->isLink()) {
            chown($path->getPathname(), 'www-data');
            chgrp($path->getPathname(), 'www-data');
        }
    }
    fwrite(STDOUT, "Safe bootstrap complete. Existing credentials preserved.\n");
} catch (Throwable $exception) {
    // Avoid exception SQL/context containing credentials in installer output.
    fwrite(STDERR, 'Bootstrap failed: '.get_class($exception).': '.preg_replace('/(password|token|secret)[^,;]*/i', '$1=[redacted]', $exception->getMessage())."\n");
    exit(1);
}
