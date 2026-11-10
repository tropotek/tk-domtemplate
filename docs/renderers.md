# Renderers

The renderer types are the base that tk-form, tk-table and tk-base build on.

```php
use Tk\DomTemplate\Renderer\Renderer;
use Tk\DomTemplate\Template;

final class UserCard extends Renderer
{
    public function __construct(private User $user) {}

    protected function makeTemplate(): ?Template
    {
        return Template::file(__DIR__ . '/user-card.html');
    }

    public function show(): ?Template
    {
        return $this->getTemplate()?->text('name', $this->user->name);
    }
}
```

| Type | Provides |
|---|---|
| `DisplayInterface` | `show(): ?Template` |
| `RendererInterface` | adds `getTemplate()` and `setTemplate()` |
| `RendererTrait` | the template handling, for classes that can't extend `Renderer` |
| `Renderer` | abstract base using the trait |

- `getTemplate()` calls `makeTemplate()` on first use. Override it, or call `setTemplate()`.
- Cloning a `Renderer` clones its template. A rendered template can't be copied, so a clone made after rendering starts without one and calls `makeTemplate()` again on first use. A class that only uses `RendererTrait` shares its template between clones unless it adds its own `__clone()`.
- Nothing is final; override what you need.
