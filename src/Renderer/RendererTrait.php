<?php
namespace Tk\DomTemplate\Renderer;

use Tk\DomTemplate\Template;

/**
 * The template handling of RendererInterface, for classes that can't extend Renderer.
 *
 * Override makeTemplate() to create the template on first use.
 */
trait RendererTrait
{
    protected ?Template $template = null;

    public function setTemplate(Template $template): void
    {
        $this->template = $template;
    }

    /**
     * The template, created by makeTemplate() on first use
     */
    public function getTemplate(): ?Template
    {
        return $this->template ??= $this->makeTemplate();
    }

    /**
     * Override to create the template on first use
     */
    protected function makeTemplate(): ?Template
    {
        return null;
    }

    public function hasTemplate(): bool
    {
        return $this->template !== null;
    }
}
