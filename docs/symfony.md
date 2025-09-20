# Intergrate DomTemplate into Symfony

## Symfony Response Handler

This is an untested example, but it should give you a starting point if you want to implement
templates into your own symfony project. It allows either a template or a DOMDocument to 
be returned from a Controller object to be used in a Response.

With this you can also setup a Template Modifier object to manipulate all templates
and apply any required final formatting to the final output.

```php
<?php
use Dom\Modifier;
use Dom\Renderer\DisplayInterface;
use Dom\Template;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles DOM and view modifications by listening to Symfony Kernel events.
 * This class subscribes to the CONTROLLER and VIEW events to manipulate
 * controller data, apply DOM modifications, and generate appropriate responses.
 */
class DomViewHandler implements EventSubscriberInterface
{

    protected ?Modifier $domModifier;
    protected mixed $controller = null;


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
        if ($event->getController()[0] instanceof ControllerInterface) {
            $this->controller = $event->getController()[0];
        }
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
     * NOTE: if you want to modify the template using its API
     * you must add the listeners before this one its priority is set to -100
     * make sure your handlers have a priority > -100 so this is run last
     *
     * Convert controller return types to a request
     * Once this event is fired and a response is set, it will stop propagation,
     * so other events using this name must be run with a priority > -100
     *
     * @Event("Symfony\Component\HttpKernel\Event\ViewEvent")
     */
    public function onView(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if ($result instanceof Template) {
            $event->setResponse(new Response($result->toString()));
        } else if ($result instanceof DisplayInterface) {
            $event->setResponse(new Response($result->show()->toString()));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
            KernelEvents::VIEW => [
                ['onDomModify', -80],   // first
                ['onView', -100]        // second
            ]
        ];
    }
}
```


