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
     * @param array<string, string|bool|null> $attrs
     */
    public function __construct(
        public AssetType $type,
        public string $value,
        public Place $place = Place::Head,
        public ?string $key = null,
        public array $attrs = [],
        public ?string $name = null,
        /** When the asset was asked for: placement follows call order across templates */
        public int $seq = 0,
        /** The attribute a meta tag is named by: name, property, http-equiv or itemprop */
        public string $metaAttr = 'name',
    ) {}

    /**
     * Written where the template that added it ends (a title never is)
     */
    public function isInline(): bool
    {
        return $this->type !== AssetType::Title && $this->place === Place::Inline;
    }

    /**
     * The element this asset writes
     */
    public function toElement(\Dom\Document $doc): \Dom\Element
    {
        switch ($this->type) {
            case AssetType::JsUrl:
                $el = $doc->createElement('script');
                $el->setAttribute('src', $this->value);
                Html::trust($el);
                break;
            case AssetType::CssUrl:
                $el = $doc->createElement('link');
                $el->setAttribute('rel', 'stylesheet');
                $el->setAttribute('href', $this->value);
                Html::trust($el);
                break;
            case AssetType::Meta:
                $el = $doc->createElement('meta');
                $el->setAttribute($this->metaAttr, (string)$this->name);
                $el->setAttribute('content', $this->value);
                break;
            case AssetType::Js:
                $el = Html::trust($doc->createElement('script'));
                $el->textContent = Html::scriptText($this->value);
                break;
            case AssetType::Css:
                $el = Html::trust($doc->createElement('style'));
                $el->textContent = Html::styleText($this->value);
                break;
            default:
                $el = $doc->createElement('title');
                $el->textContent = $this->value;
        }
        foreach ($this->attrs as $attr => $value) {
            if ($value !== null && $value !== false) {
                Html::setAttribute($el, $attr, $value === true ? '' : $value);
            }
        }
        if ($this->key !== null) {
            // Marks keyed assets so the page keeps only the first of each (see Template::dedupeKeys())
            $el->setAttribute(self::KEY_ATTR, Html::secret() . '|' . $this->type->value . ':' . $this->key);
        }
        return $el;
    }
}
