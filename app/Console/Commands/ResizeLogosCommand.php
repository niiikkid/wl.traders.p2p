<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickException;
use ImagickPixel;

class ResizeLogosCommand extends Command
{
    private const TARGET_SIZE = 128;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:resize-logos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Привести все логотипы в storage/app/public/logos к размеру 128x128';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! class_exists(Imagick::class)) {
            $this->error('Расширение Imagick недоступно. Установите его перед запуском.');

            return self::FAILURE;
        }

        $files = collect(Storage::allFiles('public/logos'))
            ->filter(fn (string $path): bool => Str::endsWith(Str::lower($path), ['.png', '.jpg', '.jpeg', '.webp']));

        $total = $files->count();
        $processed = 0;

        foreach ($files as $path) {
            try {
                $this->resizeFile($path);
                $processed++;
            } catch (ImagickException $e) {
                $this->warn("Пропущен {$path}: {$e->getMessage()}");
            }
        }

        $this->info("Готово: {$processed} из {$total}");

        return self::SUCCESS;
    }

    /**
     * Resize and pad single image to target canvas.
     *
     * @throws ImagickException
     */
    private function resizeFile(string $storagePath): void
    {
        $fullPath = storage_path('app/'.$storagePath);
        $image = new Imagick($fullPath);
        $format = strtolower($image->getImageFormat());

        $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $image->thumbnailImage(self::TARGET_SIZE, self::TARGET_SIZE, true, true);

        $backgroundColor = in_array($format, ['png', 'webp'], true) ? 'transparent' : 'white';

        $canvas = new Imagick();
        $canvas->newImage(self::TARGET_SIZE, self::TARGET_SIZE, new ImagickPixel($backgroundColor));
        $canvas->setImageFormat($image->getImageFormat());

        $offsetX = (int) floor((self::TARGET_SIZE - $image->getImageWidth()) / 2);
        $offsetY = (int) floor((self::TARGET_SIZE - $image->getImageHeight()) / 2);

        $canvas->compositeImage($image, Imagick::COMPOSITE_DEFAULT, $offsetX, $offsetY);
        $canvas->writeImage($fullPath);

        $image->clear();
        $canvas->clear();
    }
}

