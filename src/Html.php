<?php
namespace Tk\DomTemplate;

/**
 * HTML5 parsing helpers.
 *
 * HTML5 parses a fragment relative to a context element, and drops nodes that
 * aren't allowed there: a bare <tr> or <option> parsed inside a <div> is lost.
 * Fragments are therefore parsed inside a context element chosen from their first tag.
 *
 * @internal modifiers trust elements with Modifier\Context::trust()
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
     * A per-process random value. Markers the library writes into the tree carry it, so
     * the same attributes in inserted HTML strings are recognised as forged.
     */
    public static function secret(): string
    {
        return self::$secret ??= bin2hex(random_bytes(16));
    }

    /**
     * Allow the nonce on this element. Returns the element.
     */
    public static function trust(\Dom\Element $el): \Dom\Element
    {
        $el->setAttribute(self::TRUST_ATTR, self::secret());
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
        $trusted = self::$secret !== null && hash_equals(self::$secret, (string)$el->getAttribute(self::TRUST_ATTR));
        $el->removeAttribute(self::TRUST_ATTR);
        return $trusted;
    }

    /**
     * JavaScript (or JSON) safe inside <script>: `<!--` becomes `\u003C!--` and `</script`
     * becomes `<\/script`. Both mean the same inside strings, regexes and JSON, where these
     * sequences occur; code outside strings (`i<scripts.length`) is left alone.
     */
    public static function scriptText(string $code): string
    {
        $code = (string)preg_replace('#<!--#', '\\u003C!--', $code);
        return (string)preg_replace('#</(script)(?=[\s/>])#i', '<\\/$1', $code);
    }

    /**
     * True when text in a <script> would end it early or hide its end (`</script`, `<!--`)
     */
    public static function breaksScript(string $text): bool
    {
        return (bool)preg_match('#<!--|</script(?=[\s/>])#i', $text);
    }

    /**
     * CSS safe inside <style>: `</style` can't end the element
     */
    public static function styleText(string $css): string
    {
        return (string)preg_replace('#</(style)#i', '<\\/$1', $css);
    }

    /** Characters HTML allows in an attribute name (Alpine's @click and :class included), as valid UTF-8 */
    private const string ATTR_NAME = '/^[^\s"\'>\/=\x00-\x1F\x7F]+$/u';

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
     * The string as valid UTF-8: invalid byte sequences become U+FFFD, as the parser
     * does for template source. Output is always UTF-8.
     */
    public static function utf8(string $s): string
    {
        if (!preg_match('//u', $s)) {
            $s = htmlspecialchars_decode(htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), ENT_QUOTES);
        }
        // The DOM stops at a NUL, which would silently cut the text short
        return str_replace("\0", "\u{FFFD}", $s);
    }

    /**
     * Report a <meta> that declares a charset other than UTF-8, then make it UTF-8 (when
     * the report doesn't throw). Templates are always read and written as UTF-8, so
     * another declared charset would make browsers decode the page wrongly.
     *
     * @param callable(string): void $fail
     */
    public static function checkCharset(\Dom\Element|\Dom\Document $root, string $label, callable $fail): void
    {
        foreach ($root->querySelectorAll('meta[charset], meta[http-equiv][content]') as $meta) {
            if ($meta->hasAttribute('charset')) {
                $charset = trim((string)$meta->getAttribute('charset'));
            } elseif (strcasecmp((string)$meta->getAttribute('http-equiv'), 'content-type') === 0
                && preg_match('/charset\s*=\s*["\']?([^\s;"\']+)/i', (string)$meta->getAttribute('content'), $m)) {
                $charset = $m[1];
            } else {
                continue;
            }
            if ($charset === '' || in_array(strtolower($charset), ['utf-8', 'utf8'], true)) {
                continue;
            }
            $fail(sprintf('%s declares charset %s; templates are read and written as UTF-8', $label, $charset));
            if ($meta->hasAttribute('charset')) {
                $meta->setAttribute('charset', 'utf-8');
            } else {
                $meta->setAttribute('content', 'text/html; charset=utf-8');
            }
        }
    }

    /**
     * Read a template file
     *
     * @throws TemplateException when it can't be read
     */
    public static function readFile(string $path): string
    {
        $html = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if ($html === false) {
            throw new TemplateException('Cannot read template file: ' . $path);
        }
        return $html;
    }

    /**
     * Parse a whole page or a fragment (a leading UTF-8 BOM is dropped). The root is the
     * document for a page, or the detached context element holding a fragment.
     *
     * @return array{\Dom\HTMLDocument, \Dom\Element|\Dom\Document}
     */
    public static function parse(string $html): array
    {
        if (str_starts_with($html, "\xEF\xBB\xBF")) {
            $html = substr($html, 3);
        }
        if (self::isFullDocument($html)) {
            $doc = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
            return [$doc, $doc];
        }
        $doc = \Dom\HTMLDocument::createEmpty();
        return [$doc, self::parseFragment($doc, $html)];
    }

    /**
     * Copy a whole document. It is imported into an empty one: cloneNode() on a whole
     * HTMLDocument crashes PHP 8.5 once <template> content is copied into it.
     */
    public static function copyDocument(\Dom\HTMLDocument $from): \Dom\HTMLDocument
    {
        $doc = \Dom\HTMLDocument::createEmpty();
        foreach ($from->childNodes as $node) {
            $doc->append($doc->importNode($node, true));
        }
        self::copyTemplates($from, $doc);
        return $doc;
    }

    /**
     * Each element of a tree mapped to its copy in a tree of the same shape
     * (the roots included when they are elements)
     *
     * @return \WeakMap<\Dom\Element, \Dom\Element>
     */
    public static function mapElements(\Dom\Element|\Dom\Document $old, \Dom\Element|\Dom\Document $new): \WeakMap
    {
        $from = iterator_to_array($old->getElementsByTagName('*'), false);
        $to = iterator_to_array($new->getElementsByTagName('*'), false);
        if ($old instanceof \Dom\Element && $new instanceof \Dom\Element) {
            array_unshift($from, $old);
            array_unshift($to, $new);
        }
        /** @var \WeakMap<\Dom\Element, \Dom\Element> $map */
        $map = new \WeakMap();
        foreach ($from as $i => $el) {
            $map[$el] = $to[$i];
        }
        return $map;
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
