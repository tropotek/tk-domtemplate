<?php
namespace Dom;

/**
 * Use this object to take a HTML page and extract
 * sections by their id attribute as \Dom\Templates.
 *
 * Eg:
 *   <div class="row g-3">
 *     <input type="hidden" class="form-control" var="element" id="tpl-hidden">
 *
 *     <div var="field" id="tpl-input">
 *       <label class="form-label" var="label"></label>
 *       <span class="form-control" var="element"></span>
 *       <div class="form-text" var="notes"></div>
 *       <div class="invalid-feedback" var="error"></div>
 *     </div>
 *   </div>
 *
 *  <code>
 *     $builder = new Builder($templatePath);
 *     $hiddenTemplate = $builder->getTemplate('tpl-hidden');
 *
 *     $fieldTemplate1 = $builder->getTemplate('tpl-input');
 *     $fieldTemplate2 = $builder->getTemplate('tpl-input');
 *     $fieldTemplate3 = $builder->getTemplate('tpl-input');
 *  </code>
 *
 * This is a way that a front end devs can create a single HTML
 * page with all the templates required for system objects.
 *
 * Great for creating forms and tables with all their elements.
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class Builder
{

    private string $path = '';

    private \DOMDocument $document;

    private array $sectionCache = [];

    protected bool $removeId = true;


    public function __construct(string $path)
    {
        $this->document = new \DOMDocument();
        $this->getDocument()->loadHTMLFile($path);
        $this->path = $path;
    }

    public function getTemplate($id): ?Template
    {
        if (!$this->getSection($id)) {
            $el = $this->getDocument()->getElementById($id);
            if (!$el) return null;
            if ($this->isRemoveId()) {
                $el->removeAttribute('id');
            }
            $section = $this->getDocument()->saveHTML($el);

            $this->setSection($id, $section);
        }
        $section = $this->getSection($id);
        return Template::load($section);
    }

    public function hasTemplate($id)
    {
        if (!isset($this->sectionCache[$id])) $this->getTemplate($id);
        return isset($this->sectionCache[$id]);
    }

    protected function getSection($id): string
    {
        return $this->sectionCache[$id] ?? '';
    }

    protected function setSection(string $id, string $section): Builder
    {
        $this->sectionCache[$id] = $section;
        return $this;
    }


    public function getPath(): string
    {
        return $this->path;
    }

    public function getDocument(): \DOMDocument
    {
        return $this->document;
    }

    /**
     * If true then the original section id tag is remove
     * (default: true)
     */
    public function isRemoveId(): bool
    {
        return $this->removeId;
    }

    public function setRemoveId(bool $removeId): Builder
    {
        $this->removeId = $removeId;
        return $this;
    }

}
