<?php
namespace Tk\DomTemplate;

/**
 * The assets of one template, and the page-level steps that write them.
 *
 * A template's own assets and those of the rows added to it are kept here until it is
 * built. Building writes the inline ones in place and keeps the rest (plus those its
 * children handed up) for the template it is inserted into, or for the top-level render.
 *
 * @internal
 */
final class Assets
{
    /** @var list<Asset> assets the template (or a row added to it) asked for */
    private array $own = [];

    /** @var list<array{\Dom\Element, Asset}> inline assets of added rows, written at the end of each row element */
    private array $inlineAt = [];

    /**
     * Head/End assets of added rows, with the row elements they came from: kept only
     * while one of those elements is still in the page
     *
     * @var list<array{list<\Dom\Element>, Asset}>
     */
    private array $rowAssets = [];

    /** @var list<Asset> handed up to the template this one is inserted into, kept for every later build */
    private array $bubbled = [];

    public function add(Asset $asset): void
    {
        $this->own[] = $asset;
    }

    /**
     * Take over a row's assets when it is added. Its inline assets go at the end of each
     * inserted row element; the rest follow those elements. A hidden row ($nodes empty)
     * contributes none.
     *
     * @param list<\Dom\Element> $nodes
     */
    public function adopt(Assets $row, array $nodes): void
    {
        if ($nodes) {
            foreach ($row->own as $asset) {
                if ($asset->isInline()) {
                    foreach ($nodes as $node) {
                        $this->inlineAt[] = [$node, $asset];
                    }
                } else {
                    $this->rowAssets[] = [$nodes, $asset];
                }
            }
            array_push($this->inlineAt, ...$row->inlineAt);
            array_push($this->rowAssets, ...$row->rowAssets);
        }
        $row->own = [];
        $row->inlineAt = [];
        $row->rowAssets = [];
    }

    /**
     * A copy for a cloned tree, with every element swapped for its copy
     *
     * @param \WeakMap<\Dom\Element, \Dom\Element> $map
     */
    public function remap(\WeakMap $map): self
    {
        $copy = clone $this;
        $copy->inlineAt = [];
        foreach ($this->inlineAt as [$node, $asset]) {
            if (isset($map[$node])) {
                $copy->inlineAt[] = [$map[$node], $asset];
            }
        }
        $copy->rowAssets = [];
        foreach ($this->rowAssets as [$nodes, $asset]) {
            $copy->rowAssets[] = [array_values(array_filter(array_map(fn($n) => $map[$n] ?? null, $nodes))), $asset];
        }
        return $copy;
    }

    /**
     * Elements copied for another target (a row added to several) get the same assets
     *
     * @param \WeakMap<\Dom\Element, \Dom\Element> $map
     */
    public function follow(\WeakMap $map): void
    {
        foreach ($this->inlineAt as [$anchor, $asset]) {
            if (isset($map[$anchor])) {
                $this->inlineAt[] = [$map[$anchor], $asset];
            }
        }
        foreach ($this->rowAssets as $i => [$nodes, $asset]) {
            foreach ($nodes as $n) {
                if (isset($map[$n])) {
                    $this->rowAssets[$i][0][] = $map[$n];
                }
            }
        }
    }

    /**
     * The build step: write the template's own inline assets at $end and added rows' inline
     * assets at the end of each row still in $root. Keep the rest, with the assets the
     * children handed up ($collected), for bubbled(). Keyed assets are marked and reduced
     * to one per page at render.
     *
     * @param list<Asset> $collected
     */
    public function build(\Dom\Document $doc, \Dom\Element|\Dom\Document $root, ?\Dom\Element $end, array $collected): void
    {
        foreach ($this->own as $asset) {
            if ($asset->isInline()) {
                $end?->append($asset->toElement($doc));
            }
        }
        foreach ($this->inlineAt as [$node, $asset]) {
            if ($root->contains($node)) {
                $node->append($asset->toElement($doc));
            }
        }
        $fromRows = [];
        foreach ($this->rowAssets as [$nodes, $asset]) {
            foreach ($nodes as $node) {
                if ($root->contains($node)) {
                    $fromRows[] = $asset;
                    break;
                }
            }
        }
        $this->inlineAt = [];
        $this->rowAssets = [];
        $this->bubbled = [
            ...array_values(array_filter($this->own, fn(Asset $a) => !$a->isInline())),
            ...$fromRows,
            ...$collected,
        ];
    }

    /**
     * @return list<Asset>
     */
    public function bubbled(): array
    {
        return $this->bubbled;
    }

    /**
     * Place the assets handed up to the top level, in call order: <head>, end of <body>, meta and title.
     * Without a <head> or <body> (a fragment) they are written at $end and the title is dropped.
     *
     * @param list<Asset> $assets
     */
    public static function place(array $assets, \Dom\HTMLDocument $doc, \Dom\Element|\Dom\Document $root, ?\Dom\Element $end, BuildContext $ctx): void
    {
        $head = $root instanceof \Dom\Document ? $doc->head : null;
        $body = $root instanceof \Dom\Document ? $doc->body : null;
        // URLs the page already links count as written
        foreach ($root->querySelectorAll('link[rel="stylesheet"][href]') as $link) {
            $ctx->first('cssUrl:' . $link->getAttribute('href'));
        }
        foreach ($root->querySelectorAll('script[src]') as $script) {
            $ctx->first('jsUrl:' . $script->getAttribute('src'));
        }
        usort($assets, fn(Asset $a, Asset $b) => $a->seq <=> $b->seq);
        foreach ($assets as $asset) {
            if (in_array($asset->type, [AssetType::JsUrl, AssetType::CssUrl], true) && !$ctx->first($asset->type->value . ':' . $asset->value)) {
                continue;
            }
            if ($asset->type === AssetType::Title) {
                if ($head !== null) {
                    $title = $head->querySelector('title');
                    $title ? $title->textContent = $asset->value : $head->append($asset->toElement($doc));
                }
                continue;
            }
            $el = $asset->toElement($doc);
            if ($asset->type === AssetType::Meta && $head !== null) {
                $existing = null;
                foreach ($head->querySelectorAll('meta[' . $asset->metaAttr . ']') as $meta) {
                    if (strcasecmp((string)$meta->getAttribute($asset->metaAttr), (string)$asset->name) === 0) {
                        $existing = $meta;
                        break;
                    }
                }
                $existing ? $existing->replaceWith($el) : $head->append($el);
                continue;
            }
            $target = $asset->place === Place::End ? $body : $head;
            ($target ?? $end)?->append($el);
        }
    }

    /**
     * Keep the first asset of each key in the page and drop the rest
     */
    public static function dedupeKeys(\Dom\Element|\Dom\Document $root): void
    {
        $seen = [];
        foreach ($root->querySelectorAll('[' . Asset::KEY_ATTR . ']') as $el) {
            $key = (string)$el->getAttribute(Asset::KEY_ATTR);
            if (isset($seen[$key])) {
                $el->remove();
                continue;
            }
            $seen[$key] = true;
            $el->removeAttribute(Asset::KEY_ATTR);
        }
    }

    /**
     * Add the CSP nonce to the trusted elements that lack one (see Html::trust()),
     * and remove every trust marker
     */
    public static function applyNonce(\Dom\Element|\Dom\Document $root, ?string $nonce): void
    {
        foreach ($root->querySelectorAll('[' . Html::TRUST_ATTR . ']') as $el) {
            if (Html::untrust($el) && $nonce !== null && !$el->hasAttribute('nonce')) {
                $el->setAttribute('nonce', $nonce);
            }
        }
    }
}
