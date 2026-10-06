<?php

namespace Tests\Unit;

use App\Support\HtmlText;
use App\Support\SafeXml;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecuritySupportTest extends TestCase
{
    #[Test]
    public function html_text_strips_tags_from_comments(): void
    {
        $this->assertSame(
            'Hola mundo',
            HtmlText::sanitizePlainText('<script>alert(1)</script>Hola <b>mundo</b>')
        );
    }

    #[Test]
    public function safe_xml_loads_valid_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xml');
        file_put_contents($path, '<?xml version="1.0"?><root><numeros><numero>1</numero></numeros></root>');

        try {
            $xml = SafeXml::loadFromFile($path);
            $this->assertInstanceOf(\SimpleXMLElement::class, $xml);
            $this->assertSame('1', (string) $xml->numeros->numero);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function safe_xml_rejects_malformed_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xml');
        file_put_contents($path, 'not xml');

        try {
            $this->assertFalse(SafeXml::loadFromFile($path));
        } finally {
            @unlink($path);
        }
    }
}
