<?php
namespace Tk\DomTemplate;

/**
 * State shared by one top-level render: the templates being built (for cycle detection)
 * and the asset keys and URLs already written (for de-duplication)
 *
 * @internal
 */
final class BuildContext
{
    /** @var list<Template> */
    private array $stack = [];

    /** @var array<string, true> asset keys and URLs already written to the page */
    private array $seen = [];

    /**
     * True the first time an asset key or URL is seen during this render
     */
    public function first(string $id): bool
    {
        if (isset($this->seen[$id])) {
            return false;
        }
        $this->seen[$id] = true;
        return true;
    }

    /**
     * @throws \LogicException when the template is already being built (a circular insert)
     */
    public function enter(Template $template): void
    {
        if (in_array($template, $this->stack, true)) {
            $labels = array_map(fn(Template $t) => $t->label(), [...$this->stack, $template]);
            throw new \LogicException('Circular template insert: ' . implode(' → ', $labels));
        }
        $this->stack[] = $template;
    }

    public function leave(): void
    {
        array_pop($this->stack);
    }
}
