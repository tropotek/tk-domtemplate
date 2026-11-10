<?php
namespace Tk\DomTemplate;

use Tk\DomTemplate\Modifier\Context;

/**
 * A list of modifiers, run one after another over a rendered page or fragment.
 *
 * Each modifier sees the previous one's output. Elements are found with the modifier's
 * own selector, so a modifier only visits what it asks for. Immutable, so an Engine
 * holding one can be shared across requests.
 */
final readonly class Modifier
{
    /**
     * @param list<ModifierInterface> $modifiers
     */
    public function __construct(
        public array $modifiers = [],
    ) {}

    /**
     * A new list with $modifier added at the end
     */
    public function with(ModifierInterface $modifier): self
    {
        return new self([...$this->modifiers, $modifier]);
    }

    public function process(Context $ctx): void
    {
        $xpath = new \Dom\XPath($ctx->document);
        foreach ($this->modifiers as $modifier) {
            // querySelectorAll returns a static list, so modifiers may remove nodes as they go
            foreach ($ctx->root->querySelectorAll($modifier->selector() ?? '*') as $el) {
                $modifier->element($el, $ctx);
            }
            foreach ($xpath->query('.//comment()', $ctx->root) as $comment) {
                if ($comment instanceof \Dom\Comment) {
                    $modifier->comment($comment, $ctx);
                }
            }
            $modifier->done($ctx);
        }
    }
}
