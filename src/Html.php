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

    private static function copyTemplates(\Dom\Element $from, \Dom\Element $to): void
    {
        $src = iterator_to_array($from->querySelectorAll('template'), false);
        $dst = iterator_to_array($to->querySelectorAll('template'), false);
        if ($from->tagName === 'TEMPLATE') {
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
