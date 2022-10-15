<?php
namespace Dom\Mvc\Event;

use Dom\Template;
use Symfony\Contracts\EventDispatcher\Event;


/**
 * @author Tropotek <http://www.tropotek.com/>
 */
class TemplateEvent extends Event
{

    private Template $template;


    public function __construct(Template $template)
    {
        $this->template = $template;
    }

    /**
     * @return Template
     */
    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function setTemplate(Template $template): TemplateEvent
    {
        $this->template = $template;
        return $this;
    }

}