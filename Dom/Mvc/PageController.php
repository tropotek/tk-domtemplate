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

    /**
     * @return Page|\App\Page
     */
    public function getPage(): Page
    {
        return $this->page;
    }

    /**
     * Forwards the request to another controller.
     * NOTE: If you are using Dom\Template to generate the response, keep in mind you will lose any template headers, scripts and style tags
     *       because this will return the response as a string and not the actual template object.
     *
     * @param callable|string|array $controller The controller name (a string like Bundle\BlogBundle\Controller\PostController::indexAction)
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