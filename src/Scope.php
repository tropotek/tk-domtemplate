<?php
namespace Tk\DomTemplate;

/**
 * Index of the var nodes in one subtree.
 *
 * Nodes inside a repeat sample belong to that repeat's own scope, so they are
 * left out here. When the root itself is a repeat sample (a row's scope), the
 * root is indexed as a plain var under its name, so a row can change its own element.
 */
final class Scope
{
    /** @var array<string, Kind> */
    private array $kinds = [];

    /** @var array<string, list<\Dom\Element>> */
    private array $nodes = [];

    private function __construct() {}

    /**
     * @param callable(string): void $fail reports a markup mistake; when it returns, the node is skipped
     */
    public static function build(\Dom\Element|\Dom\Document $root, string $label, callable $fail): self
    {
        $scope = new self();

        if ($root instanceof \Dom\Element && $root->hasAttribute('var')) {
            try {
                $name = Name::parse((string)$root->getAttribute('var'), $label);
                $scope->add($name->kind === Kind::Repeat ? new Name($name->name, Kind::Var) : $name, $root, $label, $fail);
            } catch (TemplateException $e) {
                $fail($e->getMessage());
            }
        }

        foreach ($root->querySelectorAll('[var]') as $el) {
            if ($scope->insideNestedRepeat($el, $root)) {
                continue;
            }
            try {
                $name = Name::parse((string)$el->getAttribute('var'), $label);
            } catch (TemplateException $e) {
                $fail($e->getMessage());
                continue;
            }
            $scope->add($name, $el, $label, $fail);
        }

        return $scope;
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
            $fail(sprintf('`%s` is used as both %s and %s in %s',
                $name->name, self::describe($known), self::describe($name->kind), $label));
            return;
        }
        if ($known === Kind::Repeat) {
            $fail(sprintf('Repeat `%s` has more than one sample in %s', $name->name, $label));
            return;
        }
        $this->kinds[$name->name] = $name->kind;
        $this->nodes[$name->name][] = $el;
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
