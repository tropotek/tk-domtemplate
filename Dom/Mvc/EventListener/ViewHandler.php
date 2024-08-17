<?php
namespace Dom\Mvc\EventListener;

use Bs\ControllerInterface;
use Dom\Modifier;
use Dom\Renderer\DisplayInterface;
use Dom\Template;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @deprecated
 */
class ViewHandler implements EventSubscriberInterface
{

    protected ?Modifier $domModifier;
    protected ?ControllerInterface $controller = null;


    public function __construct(?Modifier $domModifier = null)
    {
        $this->domModifier = $domModifier;
    }

    /**
     * @Event("Symfony\Component\HttpKernel\Event\ControllerEvent")
     */
    public function onController(ControllerEvent $event): void
    {
        if (!is_array($event->getController())) return;
        if (!($event->getController()[0] instanceof ControllerInterface)) return;
        $this->controller = $event->getController()[0];
    }

    /**
     * Execute the Dom Modifier before the template is converted to a response
     * The dom modifier will execute any attached filters as a last post render iteration
     * over the dom tree
     */
    public function onDomModify(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if ($result instanceof DisplayInterface) {
            $result = $result->show();
        }

        if ($result instanceof Template) {
            $result = $result->getDocument();
        }

        if ($result instanceof \DOMDocument) {
            $this->domModifier?->execute($result);
        }
    }

    /**
     * kernel.view
     * NOTE: if you want to modify the template using its API
     * you must add the listeners before this one its priority is set to -100
     * make sure your handlers have a priority > -100 so this is run last
     *
     * Convert controller return types to a request
     * Once this event is fired and a response is set it will stop propagation,
     * so other events using this name must be run with a priority > -100
     *
     */
    public function onView(ViewEvent $event): void
    {
        $result = $event->getControllerResult();
        if (is_null($result)) {
            $result = $this->controller;
        }

        if ($result instanceof Template) {
            $event->setResponse(new Response($result->toString()));
        } else if ($result instanceof DisplayInterface) {
            $event->setResponse(new Response($result->show()->toString()));
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
            KernelEvents::VIEW => [
                ['onDomModify', -80],
                ['onView', -100]
            ]
        ];
    }
}