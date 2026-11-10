<?php
namespace Tk\DomTemplate\Modifier;

/**
 * What a modifier is processing: the document, the root (the document for a full page,
 * a detached container for a fragment) and whether it is a fragment
 */
final readonly class Context
{
    public function __construct(
        public \Dom\HTMLDocument $document,
        public \Dom\Element|\Dom\Document $root,
        public bool $fragment,
    ) {}
}
