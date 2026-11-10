<?php
namespace Tk\DomTemplate;

/**
 * Index of the var nodes in one subtree.
 *
 * Nodes inside a repeat sample belong to that repeat's own scope, so they are
 * left out here. When the root itself is a repeat sample (a row's scope), the
 * root is indexed as a plain var under its name, so a row can change its own
 * element, unless the row already uses that name inside (a recursive menu).
 *
 * Var markers inside <template> content can't be reached in PHP's DOM, so they
 * are reported as a mistake.
 */
final class Scope
{
    /** @var array<string, Kind> */
    private array $kinds = [];

    /** @var array<string, list<\Dom\Element>> */
    private array $nodes = [];

    /** @var list<\Dom\Element> */
    private array $rejected = [];

    /** @var array<string, bool> names whose visibility was set explicitly */
    private array $visible = [];

    private function __construct() {}

    /**
     * @param callable(string): void $fail reports a markup mistake; when it returns, the node is skipped
     */
    public static function build(\Dom\Element|\Dom\Document $root, string $label, callable $fail): self
    {
        $scope = new self();

        foreach ($root->querySelectorAll('[var]') as $el) {
            if ($scope->insideNestedRepeat($el, $root)) {
                continue;
            }
            try {
                $name = Name::parse((string)$el->getAttribute('var'), $label);
            } catch (TemplateException $e) {
                $scope->reject($el, $e->getMessage(), $fail);
                continue;
            }
            $scope->add($name, $el, $label, $fail);
        }

        foreach ($root->querySelectorAll('template') as $tpl) {
            if (preg_match('/<[^>]*\svar\s*=/', $tpl->innerHTML)) {
                $fail(sprintf('var markers inside <template> are not supported in %s', $label));
            }
        }

        // A row's own root: indexed as a plain var, unless the row already uses that name inside
        if ($root instanceof \Dom\Element && $root->hasAttribute('var')) {
            try {
                $name = Name::parse((string)$root->getAttribute('var'), $label);
                if (!isset($scope->kinds[$name->name])) {
                    $scope->kinds[$name->name] = Kind::Var;
                    $scope->nodes[$name->name] = [$root];
                }
            } catch (TemplateException $e) {
                $fail($e->getMessage());
            }
        }

        return $scope;
    }

    /**
     * Nodes that failed to index but must still be removed at render:
     * their marker asked for an optional or repeat node, so they fail closed
     *
     * @return list<\Dom\Element>
     */
    public function rejected(): array
    {
        return $this->rejected;
    }

    public function kind(string $name): ?Kind
    {
        return $this->kinds[$name] ?? null;
    }

    /**
     * @return list<\Dom\Element>
     */
    public function nodes(string $name): array
    {
        return $this->nodes[$name] ?? [];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->kinds);
    }

    /**
     * Plain vars start visible and optional nodes start hidden, until set
     */
    public function isVisible(string $name): bool
    {
        return $this->visible[$name] ?? ($this->kind($name) !== Kind::Optional);
    }

    public function setVisible(string $name, bool $visible): void
    {
        $this->visible[$name] = $visible;
    }

    public function sample(string $name): ?\Dom\Element
    {
        return ($this->kinds[$name] ?? null) === Kind::Repeat ? $this->nodes[$name][0] : null;
    }

    /**
     * @param callable(string): void $fail
     */
    private function add(Name $name, \Dom\Element $el, string $label, callable $fail): void
    {
        $known = $this->kinds[$name->name] ?? null;
        if ($known !== null && $known !== $name->kind) {
            $this->reject($el, sprintf('`%s` is used as both %s and %s in %s',
                $name->name, self::describe($known), self::describe($name->kind), $label), $fail);
            return;
        }
        if ($known === Kind::Repeat) {
            $this->reject($el, sprintf('Repeat `%s` has more than one sample in %s', $name->name, $label), $fail);
            return;
        }
        $this->kinds[$name->name] = $name->kind;
        $this->nodes[$name->name][] = $el;
    }

    /**
     * @param callable(string): void $fail
     */
    private function reject(\Dom\Element $el, string $message, callable $fail): void
    {
        $value = trim((string)$el->getAttribute('var'));
        if (str_ends_with($value, '?') || str_ends_with($value, '[]')) {
            $this->rejected[] = $el;
        }
        $fail($message);
    }

    /**
     * True when the nearest repeat ancestor of $el lies strictly inside $root
     */
    private function insideNestedRepeat(\Dom\Element $el, \Dom\Element|\Dom\Document $root): bool
    {
        $repeat = $el->parentElement?->closest('[var$="[]"]');
        return $repeat !== null && $repeat !== $root && $root->contains($repeat);
    }

    private static function describe(Kind $kind): string
    {
        return match ($kind) {
            Kind::Var => 'a var',
            Kind::Optional => 'an optional',
            Kind::Repeat => 'a repeat',
        };
    }
}
