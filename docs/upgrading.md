# Upgrading from 8.x

!!! danger "Not a drop-in upgrade"
    v9 is a rewrite with breaking changes. Projects on 8.x keep working as they are. Moving one to v9
    means refactoring every template call, so plan it as a project of its own.

## Staying on 8.x

8.x is maintained on the [`8.1` branch](https://github.com/tropotek/tk-domtemplate/tree/8.1).
Keep your constraint on 8.x and Composer will never pick up v9:

```json
{
  "require": {
    "ttek/tk-domtemplate": "^8.1"
  }
}
```

Older lines are still available on their own branches: `8.0`, `7.0`, `3.2` and `3.0`.

The 8.x documentation is still at <https://domtemplate.tropotek.com/> (no longer updated).

## What changes in v9

Changes already on `main`:

| | 8.x | 9.x |
| --- | --- | --- |
| Namespace | `Dom\` | `Tk\DomTemplate\` |
| PHP | 8.3+ | 8.4+ |
| Source folder | `Dom/` | `src/` |

The namespace moved because PHP 8.4 added its own `Dom\` classes (`Dom\HTMLDocument`, `Dom\Element`, ...).

The rest of the rewrite is still being designed. Expect changes to how vars, repeats and head assets
are set, and to the bundled parsers and modifiers. This page will list each change as it lands.

## Moving a project to v9 today

1. Change the constraint to `"9.0.x-dev"` and run `composer update ttek/tk-domtemplate`.
2. Replace `Dom\` with `Tk\DomTemplate\` in `use` statements and fully qualified class names.
3. Check that the project runs on PHP 8.4.

Later v9 changes will need more work than this.
