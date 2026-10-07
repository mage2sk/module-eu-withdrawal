<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class StorefrontMarkupTest extends TestCase
{
    private const PALETTE = [
        '0F766E', '115E59', '171717', '525252', 'E5E5E5', 'D4D4D4', 'FFFFFF', 'FFF',
        'FAFAFA', '15803D', 'B45309', 'B91C1C', 'F0FDFA', 'FEF3C7', '000',
    ];

    private function moduleDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function testPageLayoutsRemoveThemeTitle(): void
    {
        $handles = ['withdrawal_index_index', 'withdrawal_index_confirm', 'withdrawal_index_status', 'withdrawal_index_success'];
        foreach ($handles as $handle) {
            $file = $this->moduleDir() . '/view/frontend/layout/' . $handle . '.xml';
            $this->assertFileExists($file);
            $xml = simplexml_load_string((string) file_get_contents($file));
            $this->assertNotFalse($xml);
            $nodes = $xml->xpath('//referenceBlock[@name="page.main.title"][@remove="true"]');
            $this->assertCount(1, $nodes, $handle);
        }
    }

    public function testModuleTemplatesKeepOneHeadingEach(): void
    {
        foreach (['page', 'confirm', 'status', 'success'] as $name) {
            $file = $this->moduleDir() . '/view/frontend/templates/' . $name . '.phtml';
            $this->assertSame(1, substr_count((string) file_get_contents($file), '<h1'), $name);
        }
    }

    public function testStylesheetUsesPaletteColoursOnly(): void
    {
        $css = (string) file_get_contents($this->moduleDir() . '/view/frontend/web/css/withdrawal.css');
        preg_match_all('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', $css, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $hex) {
            $this->assertContains(strtoupper($hex), self::PALETTE, '#' . $hex);
        }
    }

    public function testFormFieldsMeetSizeSpec(): void
    {
        $css = (string) file_get_contents($this->moduleDir() . '/view/frontend/web/css/withdrawal.css');
        $this->assertStringContainsString('min-height: 46px;', $css);
        $this->assertStringContainsString('border: 1px solid var(--euw-field-border);', $css);
        $this->assertStringContainsString('--euw-field-border: #D4D4D4;', $css);
    }
}
