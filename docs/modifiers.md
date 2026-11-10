# Modifiers

A modifier is a final pass over the rendered page or fragment: rewriting wiki links, building a table of contents, removing comments.

```php
use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Modifier;
use Tk\DomTemplate\Modifier\BaseModifier;
use Tk\DomTemplate\Modifier\ClearComments;
use Tk\DomTemplate\Modifier\Context;

final class WikiLinks extends BaseModifier
{
    public function selector(): ?string
    {
        return 'a[href^="page://"]';
    }

    public function element(\Dom\Element $el, Context $ctx): void
    {
        $el->setAttribute('href', '/' . substr((string)$el->getAttribute('href'), 7));
    }
}

$engine = new Engine(modifier: new Modifier([new WikiLinks(), new ClearComments()]));
```

## How they run

- Modifiers run once per render of a top-level template, after assets are placed and before the CSP nonce is added. Inserted child templates are not processed separately.
- They run one after another; each sees the previous one's output.
- `element()` receives the nodes matching the modifier's `selector()`. The default `null` means **every** element, so set a selector when you can; comment-only modifiers can use `':not(*)'`.
- `comment()` receives every comment, then `done()` is called once.
- Nodes can be removed or replaced directly; the lists are fixed before the modifier starts.
- Content inside `<template>` elements is not visited, so Alpine templates are left as they are.

Selector queries run inside PHP's DOM, which makes this much faster than one PHP walk over every node: six modifiers over 20,000 elements take about 6 ms.

## State

One engine, and its modifiers, can serve many requests in a worker runtime. Keep per-render data in the context, never in properties:

```php
public function element(\Dom\Element $el, Context $ctx): void
{
    $ctx->store($this)['headings'][] = $el->textContent;
}

public function done(Context $ctx): void
{
    $headings = $ctx->store($this)['headings'] ?? [];
    // build the table of contents …
}
```

## Caveats

- A template rendered on its own and then inserted into a page is processed again with the page.
- A modifier that renders sub-templates should load them without an engine (the default engine has no modifiers), so the list doesn't run on them twice.

`ClearComments` is the only modifier the library ships.
