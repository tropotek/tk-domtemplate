<?php
namespace Tk\DomTemplate;

/**
 * One asset request: a script, style, URL, meta tag or title
 *
 * @internal
 */
final readonly class Asset
{
    public const string KEY_ATTR = 'data-tk-key';

    /**
     * @param 'js'|'css'|'jsUrl'|'cssUrl'|'meta'|'title' $type
     * @param array<string, string|bool|null> $attrs
     */
    public function __construct(
        public string $type,
        public string $value,
        public Place $place = Place::Head,
        public ?string $key = null,
        public array $attrs = [],
        public ?string $name = null,
        /** When the asset was asked for: placement follows call order across templates */
        public int $seq = 0,
    ) {}

    /**
     * The element this asset writes
     */
    public function toElement(\Dom\Document $doc): \Dom\Element
    {
        switch ($this->type) {
            case 'jsUrl':
                $el = $doc->createElement('script');
                $el->setAttribute('src', $this->value);
                break;
            case 'cssUrl':
                $el = $doc->createElement('link');
                $el->setAttribute('rel', 'stylesheet');
                $el->setAttribute('href', $this->value);
                break;
            case 'meta':
                $el = $doc->createElement('meta');
                $el->setAttribute('name', (string)$this->name);
                $el->setAttribute('content', $this->value);
                break;
            case 'js':
                $el = $doc->createElement('script');
                // A literal </script> would end the element early
                $el->textContent = (string)preg_replace('#</(script)#i', '<\\/$1', $this->value);
                break;
            case 'css':
                $el = $doc->createElement('style');
                $el->textContent = (string)preg_replace('#</(style)#i', '<\\/$1', $this->value);
                break;
            default:
                $el = $doc->createElement('title');
                $el->textContent = $this->value;
        }
        foreach ($this->attrs as $attr => $value) {
            if ($value !== null && $value !== false) {
                $el->setAttribute($attr, $value === true ? '' : $value);
            }
        }
        if ($this->key !== null) {
            // Marks keyed assets so the page keeps only the first of each (see Template::dedupeKeys())
            $el->setAttribute(self::KEY_ATTR, $this->key);
        }
        return $el;
    }
}
