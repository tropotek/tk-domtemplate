<?php
namespace Tk\DomTemplate;

/**
 * One asset request: a script, style, URL, meta tag or title
 *
 * @internal
 */
final readonly class Asset
{
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
            default:
                $el = $doc->createElement(match ($this->type) { 'js' => 'script', 'css' => 'style', 'title' => 'title' });
                $el->textContent = $this->value;
        }
        foreach ($this->attrs as $attr => $value) {
            if ($value !== null && $value !== false) {
                $el->setAttribute($attr, $value === true ? '' : $value);
            }
        }
        return $el;
    }
}
