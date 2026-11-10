<?php
namespace Tk\DomTemplate;

/**
 * State shared by one top-level render: the templates being built, for cycle detection
 *
 * @internal
 */
final class BuildContext
{
    /** @var list<Template> */
    private array $stack = [];

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
