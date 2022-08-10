<?php
namespace Dom\Event;

use Dom\Template;


/**
 * @author Michael Mifsud <http://www.tropotek.com/>
 * @see http://www.tropotek.com/
 * @license Copyright 2016 Michael Mifsud
 * @notes Adapted from Symfony
 */
class DomEvent extends \Tk\Event\Event
{

    /**
     * @var Template
     */
    private $template = null;


    /**
     * DomEvent constructor.
     *
     * @param Template $template
     */
    public function __construct($template)
    {
        $this->template = $template;
    }

    /**
     * @return Template
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param Template $template
     * @return DomEvent
     */
    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

}