<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

class PanelUrl
{
    public static function root(): string
    {
        $url = config('app.panel_url') ?: config('app.url');

        return rtrim((string) $url, '/');
    }

    /**
     * Genera rutas del panel con la URL base correcta (también en cola / artisan).
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        $root = self::root();
        $previous = config('app.url');

        URL::forceRootUrl($root);

        try {
            return route($name, $parameters, $absolute);
        } finally {
            URL::forceRootUrl($previous);
        }
    }
}
