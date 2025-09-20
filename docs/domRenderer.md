# Template Renderer


## Using a Template Renderer

The template Dom\Renderer is a utility to create self-contained Template Renderer Objects.

This is useful for creating controllers within a MVC (Model View Controller) frameworks, like Symfony for example.

```php
<?php

class MyController extends Renderer
{

    /**
     * The MVC controller entry point.
     */
    public function doDefault(): string
    {
        // controller logic...
        
        return $this->show()->toString();
    }
    
    /**
     * The renderer show method.
     */
    public function show(): ?Template
    {
        $template = $this->getTemplate();
        $template->appendText('title', $this->getPage()->getTitle());
        $template->addCss('icon', $this->getPage()->getIcon());

        return $template;
    }

    /**
     * The renderer magic makeTemplate method.
     * Called when a template is null for the renderer and the getTemplate() method is called. 
     */
    public function __makeTemplate(): ?Template
    {
        $html = <<<HTML
<div class="card mb-3">
  <div class="card-header"><i var="icon"></i> <span var="title"></span></div>
  <div class="card-body" var="content">
    <h3>Welcome To Default Template</h3>
    <p>This site is currently using a Bootstrap 5 template for the public facing pages.</p>
    <p>
      We recommend using Bootstrap based templates. To find a Bootstrap template that would suite you
      business needs check out the following template sites:
    </p>
  </div>
</div>
HTML;
        return $this->loadTemplate($html);
    }
}
```

!!! Note
    If you cannot extend your class, look at the implementation of the \Dom\Renderer\Renderer class.
    You can use the \Dom\Renderer interfaces and traits to create your own renderer objects.




