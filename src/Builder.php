<?php
namespace Tk\DomTemplate;

/**
 * A component library: HTML files holding named components (data-tpl="form.input"),
 * served as fresh templates.
 *
 * - Later files override earlier ones, component by component.
 * - A dotted name falls back to its parents: form.input.email → form.input → form.
 *   Use exists() for an exact check and match() to see what a name resolves to.
 * - A name defined twice in one file is a mistake (the first is kept).
 * - data-opt-* attributes on a component are settings for PHP, read with options().
 *
 * Each component is parsed and checked once, at load, and get() returns a copy, so
 * markup mistakes are reported at load and a Builder can be shared across requests.
 */
final class Builder
{
    private const string NAME_ATTR = 'data-tpl';
    private const string OPT_PREFIX = 'data-opt-';

    /** @var array<string, array{Template, array<string, string>}> component name => [template, options] */
    private array $components = [];

    /** @var list<string> */
    private array $files = [];

    /**
     * @param list<string> $paths
     */
    private function __construct(array $paths, private readonly ?Engine $engine)
    {
        if (!$paths) {
            throw new TemplateException('Builder::load() needs at least one file');
        }
        foreach ($paths as $path) {
            $file = basename($path);
            $this->files[] = $file;
            [, $root] = Html::parse(Html::readFile($path));
            $inFile = [];
            foreach ($root->querySelectorAll('[' . self::NAME_ATTR . ']') as $el) {
                $name = trim((string)$el->getAttribute(self::NAME_ATTR));
                if (isset($inFile[$name])) {
                    // Keep the first, as v8's getElementById() did
                    ($this->engine ?? new Engine())->fail(sprintf('Component `%s` is defined more than once in %s', $name, $file));
                    continue;
                }
                $inFile[$name] = true;
                $this->components[$name] = [$this->prepare($el, $file . '#' . $name), self::optionsOf($el)];
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
        return clone $this->resolve($name)[0];
    }

    /**
     * The component as a template, without data-tpl and data-opt-* attributes
     * (on nested components too)
     */
    private function prepare(\Dom\Element $el, string $label): Template
    {
        $copy = Html::cloneDeep($el);
        foreach ([$copy, ...iterator_to_array($copy->querySelectorAll('[' . self::NAME_ATTR . ']'), false)] as $node) {
            $node->removeAttribute(self::NAME_ATTR);
            foreach (array_keys(self::optionsOf($node)) as $opt) {
                $node->removeAttribute(self::OPT_PREFIX . $opt);
            }
        }
        return Template::fromNode($copy, $this->engine, $label);
    }

    /**
     * True when get() would find the name or one of its dotted parents
     */
    public function has(string $name): bool
    {
        return $this->find($name) !== null;
    }

    /**
     * True only when a component with exactly this name exists (no fallback)
     */
    public function exists(string $name): bool
    {
        return isset($this->components[trim($name)]);
    }

    /**
     * The component name get() would use for $name after fallback, or null
     */
    public function match(string $name): ?string
    {
        return $this->find($name);
    }

    /**
     * The component's data-opt-* settings, without the prefix
     *
     * @return array<string, string>
     */
    public function options(string $name): array
    {
        return $this->resolve($name)[1];
    }

    /**
     * @return array{Template, array<string, string>}
     */
    private function resolve(string $name): array
    {
        $found = $this->find($name);
        if ($found === null) {
            throw new TemplateException(sprintf('No component `%s` in %s', $name, implode(', ', $this->files)));
        }
        return $this->components[$found];
    }

    private function find(string $name): ?string
    {
        for ($n = trim($name); $n !== ''; $n = (string)substr($n, 0, max(0, (int)strrpos($n, '.')))) {
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
    private static function optionsOf(\Dom\Element $el): array
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
