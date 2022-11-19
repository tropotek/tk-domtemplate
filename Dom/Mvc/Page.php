<?php

namespace Dom\Mvc;

use Dom\Renderer\Renderer;
use Dom\Template;
use Tk\Traits\SystemTrait;

class Page extends Renderer
{
    use SystemTrait;

    private string $title = '';

    private string $templatePath = '';

    /**
     * Use Page::addRenderer(...) to add Renderer`s that will get appended to the selec
     * @var array|Renderer[$var][]
     */
    private array $renderList = [];


    public function __construct(string $templatePath)
    {
        $this->templatePath = $templatePath;
    }

    public static function create(string $templatePath): static
    {
        $obj = new static($templatePath);
        return $obj;
    }

    public function addRenderer(Renderer $renderer, string $var = 'content')
    {
        $var = $var ?: 'content';
        $this->renderList[$var][] = $renderer;
    }

    protected function getRenderList(): array
    {
        return $this->renderList;
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): Page
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Execute the rendering of a template.
     * This method must return a Template object
     */
    public function show(): ?Template
    {
        $template = $this->getTemplate();

        foreach ($this->getRenderList() as $var => $list) {
            foreach ($list as $renderer) {
                $this->getTemplate()->appendTemplate($var, $renderer->show());
            }
        }

        return $template;
    }

    public function __makeTemplate(): ?Template
    {
        $template = $this->loadTemplateFile($this->getTemplatePath());
        if (!$template) {
            $html = <<<HTML
<html>
<head>
  <title></title>
</head>
<body>
  <div var="content"></div>
</body>
</html>
HTML;
            $template = $this->loadTemplate($html);
        }

        return $template;
    }
}