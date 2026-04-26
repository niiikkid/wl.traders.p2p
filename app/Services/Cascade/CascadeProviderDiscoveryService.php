<?php

declare(strict_types=1);

namespace App\Services\Cascade;

use App\Services\Cascade\Providers\CascadeProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class CascadeProviderDiscoveryService
{
    /**
     * @return Collection<int, array{code: string, class: class-string<CascadeProviderInterface>, name: string}>
     */
    public function implementedProviders(): Collection
    {
        return collect(File::files(app_path('Services/Cascade/Providers')))
            ->map(fn ($file) => $this->providerMetaFromFile($file->getFilenameWithoutExtension()))
            ->filter()
            ->sortBy('code')
            ->values();
    }

    /**
     * @return array<string, class-string<CascadeProviderInterface>>
     */
    public function classMap(): array
    {
        return $this->implementedProviders()
            ->mapWithKeys(fn (array $provider) => [$provider['code'] => $provider['class']])
            ->all();
    }

    /**
     * @return array{code: string, class: class-string<CascadeProviderInterface>, name: string}|null
     */
    private function providerMetaFromFile(string $className): ?array
    {
        $class = 'App\\Services\\Cascade\\Providers\\'.$className;

        if (! class_exists($class) || ! is_subclass_of($class, CascadeProviderInterface::class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return null;
        }

        return [
            'code' => $this->resolveCode($reflection),
            'class' => $class,
            'name' => Str::headline(Str::beforeLast($reflection->getShortName(), 'CascadeProvider')),
        ];
    }

    private function resolveCode(ReflectionClass $reflection): string
    {
        if ($reflection->hasConstant('CODE')) {
            return (string) $reflection->getConstant('CODE');
        }

        return Str::of($reflection->getShortName())
            ->beforeLast('CascadeProvider')
            ->snake()
            ->toString();
    }
}
