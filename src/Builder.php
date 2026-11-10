<?php
namespace Tk\DomTemplate;

/**
 * A component library: HTML files holding named components (data-tpl="form.input"),
 * served as fresh templates.
 *
 * - Later files override earlier ones, component by component.
 * - A dotted name falls back to its parents: form.input.email → form.input → form.
 * - data-opt-* attributes on a component are settings for PHP, read with options().
 *
 * Each file is parsed once and never changed, so a Builder can be shared across requests.
 */
final class Builder
{
    private const string NAME_ATTR = 'data-tpl';
    private const string OPT_PREFIX = 'data-opt-';

    /** @var array<string, array{\Dom\Element, string}> component name => [element, file name] */
    private array $components = [];

    /** @var list<string> */
    private array $files = [];

    /**
     * @param list<string> $paths
     */
    private function __construct(array $paths, private readonly ?Engine $engine)
    {
        foreach ($paths as $path) {
            $html = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
            if ($html === false) {
                throw new TemplateException('Cannot read template file: ' . $path);
            }
            $file = basename($path);
            $this->files[] = $file;
            if (Html::isFullDocument($html)) {
                $doc = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
                $root = $doc;
            } else {
                $doc = \Dom\HTMLDocument::createEmpty();
                $root = Html::parseFragment($doc, $html);
            }
            foreach ($root->querySelectorAll('[' . self::NAME_ATTR . ']') as $el) {
                $this->components[trim((string)$el->getAttribute(self::NAME_ATTR))] = [$el, $file];
            }
        }
    }

    /**
     * @param string|list<string> $paths library files, later ones overriding earlier ones
     * @param ?Engine $engine the engine components are loaded with
     */
    public static function load(string|array $paths, ?Engine $engine = null): self
    {
        return new self(is_array($paths) ? $paths : [$paths], $engine);
    }

    /**
     * A fresh template of the named component (or its nearest dotted parent)
     *
     * @throws TemplateException when neither the name nor any parent exists
     */
    public function get(string $name): Template
    {
        [$el, $file, $found] = $this->resolve($name);
        $copy = Html::cloneDeep($el);
        foreach ([$copy, ...iterator_to_array($copy->querySelectorAll('[' . self::NAME_ATTR . ']'), false)] as $node) {
            $node->removeAttribute(self::NAME_ATTR);
        }
        foreach (array_keys($this->optionsOf($copy)) as $opt) {
            $copy->removeAttribute(self::OPT_PREFIX . $opt);
        }
        return Template::fromNode($copy, $this->engine, $file . '#' . $found);
    }

    /**
     * True when get() would find the name or one of its dotted parents
     */
    public function has(string $name): bool
    {
        return $this->find($name) !== null;
    }

    /**
     * The component's data-opt-* settings, without the prefix
     *
     * @return array<string, string>
     */
    public function options(string $name): array
    {
        return $this->optionsOf($this->resolve($name)[0]);
    }

    /**
     * @return array{\Dom\Element, string, string} element, file name, matched name
     */
    private function resolve(string $name): array
    {
        $found = $this->find($name);
        if ($found === null) {
            throw new TemplateException(sprintf('No component `%s` in %s', $name, implode(', ', $this->files)));
        }
        return [...$this->components[$found], $found];
    }

    private function find(string $name): ?string
    {
        for ($n = $name; $n !== ''; $n = (string)substr($n, 0, max(0, (int)strrpos($n, '.')))) {
            if (isset($this->components[$n])) {
                return $n;
            }
            if (!str_contains($n, '.')) {
                break;
            }
        }
        return null;
    }

    /**
     * @return array<string, string>
     */
    private function optionsOf(\Dom\Element $el): array
    {
        $opts = [];
        foreach ($el->attributes as $attr) {
            if (str_starts_with($attr->name, self::OPT_PREFIX)) {
                $opts[substr($attr->name, strlen(self::OPT_PREFIX))] = $attr->value;
            }
        }
        return $opts;
    }
}
