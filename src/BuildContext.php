<?php
namespace Tk\DomTemplate;

/**
 * State shared by one top-level render: the asset URLs already written (for de-duplication)
 *
 * @internal
 */
final class BuildContext
{
    /** @var array<string, true> asset URLs already written to the page */
    private array $seen = [];

    /**
     * True the first time an asset URL is seen during this render
     */
    public function first(string $id): bool
    {
        if (isset($this->seen[$id])) {
            return false;
        }
        $this->seen[$id] = true;
        return true;
    }
}
