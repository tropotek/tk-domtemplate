<?php
namespace Dom\Mvc\Event;

use Dom\Mvc\Loader\AdapterInterface;
use Dom\Template;

/**
 * @author Tropotek <http://www.tropotek.com/>
 */
class LoadEvent extends TemplateEvent
{

    private AdapterInterface $adapter;


    public function __construct(Template $template, AdapterInterface $adapter)
    {
        parent::__construct($template);
        $this->adapter = $adapter;
    }

    /**
     * @return AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

}