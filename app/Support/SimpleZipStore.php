<?php

namespace App\Support;

/**
 * ZIP mínimo (método STORE) sin extensión ext-zip.
 * Suficiente para empaquetar PDFs ya comprimidos.
 */
class SimpleZipStore
{
    /**
     * @param  array<string, string>  $entries  nombreDentroDelZip => rutaAbsoluta
     */
    public static function create(string $zipPath, array $entries): void
    {
        $dir = dirname($zipPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $out = fopen($zipPath, 'wb');
        if ($out === false) {
            throw new \RuntimeException('No se pudo crear el ZIP: '.$zipPath);
        }

        $central = '';
        $offset = 0;
        $count = 0;

        try {
            foreach ($entries as $name => $absolutePath) {
                if (! is_file($absolutePath)) {
                    throw new \RuntimeException('Falta el archivo para el ZIP: '.$absolutePath);
                }
                $name = str_replace('\\', '/', (string) $name);
                $data = file_get_contents($absolutePath);
                if ($data === false) {
                    throw new \RuntimeException('No se pudo leer: '.$absolutePath);
                }
                $size = strlen($data);
                $crc = crc32($data);
                $nameLen = strlen($name);

                $local = pack('VvvvvvVVVvv',
                    0x04034b50, // local file header signature
                    20,         // version needed
                    0,          // general purpose
                    0,          // compression = STORE
                    0,          // mod time
                    0,          // mod date
                    $crc,
                    $size,
                    $size,
                    $nameLen,
                    0           // extra len
                ).$name.$data;

                fwrite($out, $local);

                $central .= pack('VvvvvvvVVVvvvvvVV',
                    0x02014b50, // central directory header
                    20,         // version made by
                    20,         // version needed
                    0,          // general purpose
                    0,          // STORE
                    0,          // time
                    0,          // date
                    $crc,
                    $size,
                    $size,
                    $nameLen,
                    0,          // extra
                    0,          // comment
                    0,          // disk start
                    0,          // int attr
                    0,          // ext attr
                    $offset
                ).$name;

                $offset += strlen($local);
                $count++;
            }

            $centralOffset = $offset;
            $centralSize = strlen($central);
            fwrite($out, $central);
            fwrite($out, pack('VvvvvVVv',
                0x06054b50, // end of central directory
                0,
                0,
                $count,
                $count,
                $centralSize,
                $centralOffset,
                0
            ));
        } finally {
            fclose($out);
        }
    }
}
