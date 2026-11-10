<?php
namespace Tk\DomTemplate;

/**
 * HTML5 parsing helpers.
 *
 * HTML5 parses a fragment relative to a context element, and drops nodes that
 * aren't allowed there: a bare <tr> or <option> parsed inside a <div> is lost.
 * Fragments are therefore parsed inside a context element chosen from their first tag.
 */
final class Html
{
    private const array CONTEXT = [
        'tr' => 'tbody',
        'td' => 'tr',
        'th' => 'tr',
        'thead' => 'table',
        'tbody' => 'table',
        'tfoot' => 'table',
        'caption' => 'table',
        'colgroup' => 'table',
        'col' => 'colgroup',
        'option' => 'select',
        'optgroup' => 'select',
    ];

    /**
     * Marks elements the CSP nonce may be added to: scripts, styles and stylesheet links
     * from template source, assets, and elements a modifier trusts. The value is a
     * per-process secret, so a marker in inserted HTML strings can't be forged.
     */
    public const string TRUST_ATTR = 'data-tk-trust';

    private static ?string $secret = null;

    /**
     * Allow the nonce on this element. Returns the element.
     */
    public static function trust(\Dom\Element $el): \Dom\Element
    {
        $el->setAttribute(self::TRUST_ATTR, self::$secret ??= bin2hex(random_bytes(16)));
        return $el;
    }

    /**
     * Trust the scripts, styles and stylesheet links written in template source
     */
    public static function trustSource(\Dom\Element|\Dom\Document $root): void
    {
        foreach ($root->querySelectorAll('script, style, link[rel~="stylesheet"]') as $el) {
            self::trust($el);
        }
    }

    /**
     * Remove the trust marker; true when it was genuine
     */
    public static function untrust(\Dom\Element $el): bool
    {
        $trusted = self::$secret !== null && $el->getAttribute(self::TRUST_ATTR) === self::$secret;
        $el->removeAttribute(self::TRUST_ATTR);
        return $trusted;
    }

    /**
     * JavaScript (or JSON) safe inside <script>: `<!--`, `<script` and `</script` get `\u003C`
     * for `<`, which means the same inside strings, regexes and JSON
     */
    public static function scriptText(string $code): string
    {
        return (string)preg_replace('#<(!--|/?script)#i', '\\u003C$1', $code);
    }

    /**
     * CSS safe inside <style>: `</style` can't end the element
     */
    public static function styleText(string $css): string
    {
        return (string)preg_replace('#</(style)#i', '<\\/$1', $css);
    }

    /** Characters HTML allows in an attribute name (Alpine's @click and :class included) */
    private const string ATTR_NAME = '/^[^\s"\'>\/=\x00-\x1F\x7F]+$/';

    public static function isAttrName(string $name): bool
    {
        return (bool)preg_match(self::ATTR_NAME, $name);
    }

    /**
     * Set an attribute. setAttribute() rejects names the HTML parser accepts, such as
     * Alpine's @click; those are created by parsing them, then attached as attribute nodes.
     *
     * @throws TemplateException when the name is invalid
     */
    public static function setAttribute(\Dom\Element $node, string $name, string $value): void
    {
        try {
            $node->setAttribute($name, $value);
        } catch (\DOMException) {
            $scratch = ($node->ownerDocument ?? throw new TemplateException('Node has no document'))->createElement('div');
            $scratch->innerHTML = '<i ' . $name . '=""></i>';
            $holder = $scratch->firstElementChild;
            $attr = $holder?->getAttributeNode($name);
            if ($holder === null || $attr === null) {
                throw new TemplateException(sprintf('Invalid attribute name `%s`', $name));
            }
            $holder->removeAttributeNode($attr);
            $attr->value = $value;
            $node->setAttributeNode($attr);
        }
    }

    /**
     * True when the markup is a whole page: it starts with a doctype or an <html> element,
     * ignoring leading whitespace and comments
     */
    public static function isFullDocument(string $html): bool
    {
        return (bool)preg_match('/^\s*(?:<!--.*?-->\s*)*(?:<!doctype|<html[\s>])/is', $html);
    }

    /**
     * The tag name of the element a fragment starting with $tagName must be parsed inside
     */
    public static function contextFor(string $tagName): string
    {
        return self::CONTEXT[strtolower($tagName)] ?? 'div';
    }

    /**
     * Deep-clone an element, including the content of any <template> elements.
     * PHP's cloneNode() leaves template content behind, which would break Alpine's x-for and x-if.
     */
    public static function cloneDeep(\Dom\Element $el): \Dom\Element
    {
        $clone = $el->cloneNode(true);
        assert($clone instanceof \Dom\Element);
        self::copyTemplates($el, $clone);
        return $clone;
    }

    /**
     * Import a node into another document, including <template> content
     */
    public static function import(\Dom\Document $doc, \Dom\Node $node): \Dom\Node
    {
        $copy = $doc->importNode($node, true);
        if ($node instanceof \Dom\Element && $copy instanceof \Dom\Element) {
            self::copyTemplates($node, $copy);
        }
        return $copy;
    }

    /**
     * Copy <template> content between two trees of the same shape
     */
    public static function copyTemplates(\Dom\Element|\Dom\Document $from, \Dom\Element|\Dom\Document $to): void
    {
        $src = iterator_to_array($from->querySelectorAll('template'), false);
        $dst = iterator_to_array($to->querySelectorAll('template'), false);
        if ($from instanceof \Dom\Element && $to instanceof \Dom\Element && $from->tagName === 'TEMPLATE') {
            array_unshift($src, $from);
            array_unshift($dst, $to);
        }
        foreach ($src as $i => $template) {
            $dst[$i]->innerHTML = $template->innerHTML;
        }
    }

    /**
     * Parse a fragment into a detached context element owned by $doc.
     * The context element's children are the parsed nodes; the element itself is never output.
     */
    public static function parseFragment(\Dom\HTMLDocument $doc, string $html): \Dom\Element
    {
        $tag = preg_match('/^(?:\s|<!--.*?-->)*<([a-zA-Z][a-zA-Z0-9-]*)/s', $html, $m) ? $m[1] : '';
        $context = $doc->createElement(self::contextFor($tag));
        $context->innerHTML = $html;
        return $context;
    }
}
