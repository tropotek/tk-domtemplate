<?php
namespace Dom\Mvc;

use Dom\Renderer\Renderer;
use Tk\Traits\SystemTrait;

abstract class PageController extends Renderer
{
    use SystemTrait;

    protected Page $page;


    public function __construct(Page $page)
    {
        $this->page = $page;
        $page->addRenderer($this);
    }

    public function getPage(): Page
    {
        return $this->page;
    }

}