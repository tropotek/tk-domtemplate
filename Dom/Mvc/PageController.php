<?php
namespace Dom\Mvc;

use Dom\Renderer\Renderer;
use Tk\Traits\SystemTrait;

/**
 * @deprecated use \Bs\ControllerDomInterface
 */
abstract class PageController extends Renderer
{
    use SystemTrait;

    protected Page $page;


    public function __construct(?Page $page = null)
    {
        $this->setPage($page);
    }

    protected function setPage(Page $page): static
    {
        $this->page = $page;
        // add this controller content to page 'content' var
        $page->addRenderer($this);
        return $this;
    }

    /**
     * @return Page|\App\Page
     */
    public function getPage(): Page
    {
        return $this->page;
    }

}