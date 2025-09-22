<?php

namespace Dom;

use Dom\Parser\ParserInterface;

class Parser
{

    protected static ?self $_instance = null;

    /**
     * The list of parsers attached to this template
     * @var array<string,ParserInterface>
     */
    protected array $parsers = [];

    protected ?Template $template = null;


    public static function instance(?Template $template = null): self
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        if ($template instanceof Template) {
            self::$_instance->template = $template;
        }
        return self::$_instance;
    }

    public function prepare(\DOMNode $node, string $form = ''): void
    {
        foreach ($this->parsers as $parser) {
            $parser->prepare($node, $form);
        }
    }

    public function preParse(): void
    {
        foreach ($this->parsers as $parser) {
            $parser->preParse();
        }
    }

    public function postParse(): void
    {
        foreach ($this->parsers as $parser) {
            $parser->postParse();
        }
    }

    /**
     * reset the parser, removing any registered parsers
     */
    public function clear(): void
    {
        $this->parsers = [];
    }

    /**
     * Register a parser to add functionality to the template
     */
    public function registerParser(ParserInterface $parser): self
    {
        if (isset($this->parsers[get_class($parser)])) {
            throw new Exception('Parser already registered: ' . get_class($parser));
        }
        $this->parsers[get_class($parser)] = $parser;
        return $this;
    }

    public function getParser(string $class): ?ParserInterface
    {
        return $this->parsers[$class] ?? null;
    }

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

}