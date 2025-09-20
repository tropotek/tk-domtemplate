<?php
namespace Dom\Renderer;

use Dom\Renderer\Traits\RendererTrait;

/**
 * For classes that render \Dom\Templates.
 *
 * If you extend this class, you can create a renderable template object.
 *
 * ```
 * class MyClass extends Renderer
 * {
 *     public function show(): Template
 *     {
 *         $template = $this->getTemplate();
 *
 *         $template->setVariable('foo', 'bar');
 *
 *         return $template;
 *     }
 *
 *     public function __makeTemplate(): ?Template
 *     {
 *          return Template::load('<div><p>Foo: <span var="foo"></span></p></div>');
 *     }
 * }
 *
 * $myClass = new MyClass();
 * $myClass->show();
 * echo $myClass;
 *
 * ```
 *
 * If the current template is null then
 * the magic method __makeTemplate() will be called to create an internal template.
 * This is a good way to create a default template.
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
abstract class Renderer implements RendererInterface
{
    use RendererTrait;

    public function __clone()
    {
        $this->template = clone $this->template;
    }

    // Example magic method
    // public function __makeTemplate(): ?Template
    // {
    //     $html = "...";
    //     return Template::load($html);
    // }
}