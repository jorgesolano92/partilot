<?php

namespace App\Support;

use SimpleXMLElement;

final class SafeXml
{
    public static function loadFromFile(string $path): SimpleXMLElement|false
    {
        if (! is_readable($path)) {
            return false;
        }

        return self::loadFromString((string) file_get_contents($path));
    }

    public static function loadFromString(string $xml): SimpleXMLElement|false
    {
        $useInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $previousLoader = libxml_set_external_entity_loader(static fn () => null);

        try {
            return simplexml_load_string(
                $xml,
                SimpleXMLElement::class,
                LIBXML_NONET | LIBXML_NOCDATA
            );
        } finally {
            if ($previousLoader === null || is_callable($previousLoader)) {
                libxml_set_external_entity_loader($previousLoader);
            } else {
                libxml_set_external_entity_loader(null);
            }
            libxml_use_internal_errors($useInternalErrors);
        }
    }
}
