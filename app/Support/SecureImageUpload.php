<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final class SecureImageUpload
{
    public const MAX_KB = 2048;

  /** @var list<string> */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

  /** @var list<string> */
    public const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function rules(string $field = 'image', bool $required = false): array
    {
        return [
            $field => [
                $required ? 'required' : 'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:'.self::MAX_KB,
            ],
        ];
    }

    public static function store(UploadedFile $file, string $publicSubdir): string
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException('Tipo de archivo no permitido.');
        }

        $extension = strtolower((string) ($file->guessExtension() ?? ''));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Extensión no permitida.');
        }

        $destination = public_path($publicSubdir);
        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $filename = $file->hashName();
        $file->move($destination, $filename);

        return $filename;
    }
}
