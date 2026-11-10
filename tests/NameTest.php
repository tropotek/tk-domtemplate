<?php
namespace Tk\DomTemplate\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Tk\DomTemplate\Kind;
use Tk\DomTemplate\Name;
use Tk\DomTemplate\TemplateException;

class NameTest extends TestCase
{
    public function testPlainName(): void
    {
        $n = Name::parse('title', 'x');
        $this->assertSame(['title', Kind::Var], [$n->name, $n->kind]);
    }

    public function testOptional(): void
    {
        $n = Name::parse('admin?', 'x');
        $this->assertSame(['admin', Kind::Optional], [$n->name, $n->kind]);
    }

    public function testRepeat(): void
    {
        $n = Name::parse('users[]', 'x');
        $this->assertSame(['users', Kind::Repeat], [$n->name, $n->kind]);
    }

    public function testTrimsWhitespace(): void
    {
        $this->assertSame('title', Name::parse(' title ', 'x')->name);
    }

    #[DataProvider('badNames')]
    public function testMalformedThrows(string $value): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageMatches('/page\.html/');
        Name::parse($value, 'page.html');
    }

    /**
     * @return list<array{string}>
     */
    public static function badNames(): array
    {
        return [[''], ['a b'], ['x[]?'], ['x?[]'], ['?'], ['[]'], ['a/b']];
    }

    public function testAssertHtmlIgnoresLayoutWhitespace(): void
    {
        $this->assertHtml("<ul>\n  <li>a  b</li>\n</ul>", '<ul><li>a b</li></ul>');
    }
}
