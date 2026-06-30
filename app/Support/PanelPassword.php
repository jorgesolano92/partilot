<?php

namespace App\Support;

use Illuminate\Support\Str;

final class PanelPassword
{
    public static function generate(int $length = 16): string
    {
        return Str::password($length);
    }
}
