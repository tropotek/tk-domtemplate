<?php
namespace Dom;

use Dom\Modifier\ModifierInterface;
use DOMComment;

/**
 * This class is designed to take a DOMDocument, traverse it and pass each Node to
 * the filters attached.
 *
 * The main aim of this class is to make a final pass over the dom document before rendering.
 * Allowing you to make any final changes to the dom document before rendering.
 *
 * ---
 * This modifier class traverses each DOMElement/DOMComment node in a DOMDocument and runs all the
 * attached modifiers during the traversal.
 *
 * NOTE: Do not use the DOMNode functions to remove a node from the DOMDocument during traversed.
 *       Use $myModifier->getDomModifier()->removeNode($node);
 *       Then the modifier will remove the node after traversal.
 *
 * Example:
 * <code>
 *      $dm = new \Tk\Dom\Modifier();
 *      $dm->add(new \Tk\Dom\Modifier\Path($apUrl, $templateUrl));
 *      $dm->execute($template->getDocument());
 * </code>
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class Modifier
{

    /**
     * @var array<string,ModifierInterface>
     */
    protected array $modifiers = [];
    protected array $nodeTrash = [];
    protected bool  $inHead    = false;
    protected bool  $inBody    = false;

    protected ?\DOMElement $head = null;
    protected ?\DOMElement $body = null;


    /**
     * Add a DOM modifier filter object to the queue
     * The name is optional, only required when adding multiple filters of the same class
     */
    public function addModifier(ModifierInterface $mod, ?string $name = null): ModifierInterface
    {
        if (is_null($name)) $name = get_class($mod);
        if (isset($this->modifiers[$name])) {
            throw new \Exception("Modifier $name already exists");
        }
        $mod->setDomModifier($this);
        $this->modifiers[$name] = $mod;
        return $mod;
    }

    public function getModifier(string $name): ?ModifierInterface
    {
        return $this->modifiers[$name] ?? null;
    }

    public function getHead(): ?\DOMElement
    {
        return $this->head;
    }

    public function inHead(): bool
    {
        return $this->inHead;
    }

    public function getBody(): ?\DOMElement
    {
        return $this->body;
    }

    public function inBody(): bool
    {
        return $this->inBody;
    }

    /**
     * Use this method to delete nodes,
     * They will be added to a queue for removal after traversal of template
     * NOTE: If you used the DOMDocument to remove a node while traversing
     * the DOM tree, unexpected errors may occur due to the DOMDocument
     * being in an inconsistent state.
     */
    public function removeNode(\DOMNode $node): Modifier
    {
        $this->nodeTrash[] = $node;
        return $this;
    }

    /**
     * Call this method to start traversing a document
     */
    public function execute(\DOMDocument $doc): \DOMDocument
    {
        $doc->normalizeDocument();
        foreach ($this->modifiers as $mod) {
            $mod->init($doc);
        }
        $this->traverse($doc->documentElement);
        foreach ($this->modifiers as $mod) {
            $mod->postTraverse($doc);
        }

        // Clear trash
        foreach ($this->nodeTrash as $node) {
            $node->parentNode?->removeChild($node);
        }
        gc_collect_cycles();

        return $doc;
    }

    /**
     * Traverse a document converting element attributes.
     */
    private function traverse(\DOMNode $node): void
    {
        if ($node instanceof \DOMElement) {
            if ($node->nodeName == 'head') {
                $this->head = $node;
                $this->inHead = true;
            }
            if ($node->nodeName == 'body') {
                $this->body = $node;
                $this->inBody = true;
            }
            foreach ($this->modifiers as $mod) {
                if (!$mod->isEnabled()) continue;
                $mod->executeNode($node);
            }
        }
        if ($node instanceof DOMComment) {
            foreach ($this->modifiers as $mod) {
                if (!$mod->isEnabled()) continue;
                if (method_exists($mod, 'executeComment')) {
                    $mod->executeComment($node);
                }
            }
        }

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->traverse($child);
            }
        }

        if ($node->nodeType == \XML_ELEMENT_NODE) {
            if ($node->nodeName == 'head') {
                $this->inHead = false;
            }
            if ($node->nodeName == 'body') {
                $this->inBody = false;
            }
        }
    }

}