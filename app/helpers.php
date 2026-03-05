<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

if (! function_exists('make')) {
    /**
     * @template T
     * @param T $abstract
     * @return T
     */
    function make($abstract, array $parameters = [])
    {
        return app()->make($abstract, $parameters);
    }
}

if (! function_exists('queries')) {
    function queries(): \App\Contracts\QueriesBuilderContract
    {
        return make(\App\Contracts\QueriesBuilderContract::class);
    }
}

if (! function_exists('services')) {
    function services(): \App\Contracts\ServiceBuilderContract
    {
        return make(\App\Contracts\ServiceBuilderContract::class);
    }
}

if (! function_exists('is_local')) {
    function is_local(): bool
    {
        return app()->environment('local');
    }
}

if (! function_exists('is_production')) {
    function is_production(): bool
    {
        return app()->environment('production');
    }
}

if (! function_exists('mask_card')) {
    function format_card($detail, $seperator = ' ')
    {
        return implode($seperator, str_split($detail,4));
    }
}

if (! function_exists('sign_request')) {
    function sign_request(array $data, string $secret): string
    {
        foreach ($data as $key => $value) {//TODO подписывать запрос вместе с файлом
            if ($value instanceof UploadedFile) {
                unset($data[$key]);
            }
        }

        $data['secret_key'] = $secret;
        ksort($data, SORT_STRING);
        $query = implode('{np}', $data);
        return hash('sha256', $query);
    }
}

if (! function_exists('nestedLowercase')) {
    function nestedLowercase($value) {
        if (is_array($value)) {
            return array_map('nestedLowercase', $value);
        }
        return mb_strtolower($value);
    }
}

if (! function_exists('isRouteFor')) {
    function isRouteFor($role) {
        return collect(Route::current()->gatherMiddleware())->contains(function ($middleware) use ($role) {
            return \Illuminate\Support\Str::startsWith($middleware, 'role:') && \Illuminate\Support\Str::contains($middleware, $role);
        });
    }
}

if (! function_exists('convertBytes')) {
    function convertBytes($size)
    {
        $i = 0;
        while (floor($size / 1024) > 0) {
            ++$i;
            $size /= 1024;
        }

        $size = str_replace('.', ',', round($size, 1));

        return match ($i) {
            0 => $size .= ' байт',
            1 => $size .= ' КБ',
            2 => $size .= ' МБ',
            default => $size,
        };
    }
}
