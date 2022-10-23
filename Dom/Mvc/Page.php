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
     * @var array|Renderer[][]
     */
    private array $renderList = [];


    public function __construct(string $templatePath)
    {
        $this->templatePath = $templatePath;
    }

    public static function create(string $templatePath)
    {
        $obj = new static($templatePath);
        return $obj;
    }

    public function addRenderer(Renderer $renderer, string $var = 'content')
    {
        $var = $var ?: 'content';
        $this->renderList[$var][] = $renderer;
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
     *
     * @return null|Template
     */
    public function show(): ?Template
    {
        $template = $this->getTemplate();

        foreach ($this->renderList as $var => $list) {
            foreach ($list as $renderer) {
                $this->getTemplate()->appendTemplate($var, $renderer->show());
            }
        }

        return $template;
    }

    public function __makeTemplate()
    {
        if (!is_file($this->getTemplatePath())) {
            // Default template if error loading template file
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
            return $this->getFactory()->getTemplateLoader()->loadFile($html);
        } else {
            return $this->getFactory()->getTemplateLoader()->loadFile($this->getTemplatePath());
        }
    }
}