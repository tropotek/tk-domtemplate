<?php
namespace Dom\Mvc;

use Dom\Renderer\Renderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
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

    /**
     * Forwards the request to another controller.
     *
     * @param callable|string|array $controller The controller name (a string like Bundle\BlogBundle\Controller\PostController::indexAction)
     * @todo: we may need somewhere else for this method. But this is how we call a controller from another, request stacking.
     *        This will be great if we create controllers for each separate dynamic element and use HTMX to bring it all together
     */
    protected function forward(callable|string|array $controller, array $path = null, array $query = null, array $request = null): Response
    {
        $requestObj = $this->getFactory()->getRequest();
        $path['_controller'] = $controller;
        $subRequest = $requestObj->duplicate($query, $request, $path);
        $kernel = $this->getFactory()->getFrontController();
        return $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}