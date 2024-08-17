<?php
namespace Dom\Mvc\Event;

use Dom\Loader\AdapterInterface;
use Dom\Template;

/**
 * @author Tropotek <http://www.tropotek.com/>
 * @deprecated
 */
class LoadEvent extends TemplateEvent
{
    private AdapterInterface $adapter;

    public function __construct(Template $template, AdapterInterface $adapter)
    {
        parent::__construct($template);
        $this->adapter = $adapter;
    }

    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

}