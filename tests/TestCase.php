<?php
namespace Tk\DomTemplate\Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Compare HTML ignoring whitespace between tags and collapsing other runs of whitespace
     */
    protected function assertHtml(string $expected, string $actual): void
    {
        $this->assertSame($this->normalise($expected), $this->normalise($actual));
    }

    private function normalise(string $html): string
    {
        $html = (string)preg_replace('/>\s+</', '><', trim($html));
        return (string)preg_replace('/\s+/', ' ', $html);
    }
}
