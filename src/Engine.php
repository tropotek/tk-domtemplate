<?php
namespace Tk\DomTemplate;

use Psr\Log\LoggerInterface;

/**
 * Optional site-wide settings, configured once and used to load templates.
 *
 * Immutable, so one instance can be shared across requests in worker mode.
 * Templates loaded without an engine use the defaults: strict, no logger, no nonce, no modifiers.
 */
final readonly class Engine
{
    public function __construct(
        /** Throw on template mistakes (true) or log them and carry on (false) */
        public bool $strict = true,
        /** Where template mistakes are logged when not strict; error_log() when null */
        public ?LoggerInterface $logger = null,
        /**
         * CSP nonce for the scripts, styles and stylesheet links in templates and assets
         * (not those in inserted HTML strings). A closure is called at each render, so a
         * shared engine can use each request's nonce.
         *
         * @var string|(\Closure(): ?string)|null
         */
        public string|\Closure|null $nonce = null,
        /** Modifiers run over every top-level render, after assets are placed */
        public ?Modifier $modifier = null,
    ) {}

    /**
     * The nonce for the render in progress, or null for none
     */
    public function currentNonce(): ?string
    {
        return $this->nonce instanceof \Closure ? ($this->nonce)() : $this->nonce;
    }

    public function load(string $html): Template
    {
        return Template::load($html, $this);
    }

    public function file(string $path): Template
    {
        return Template::file($path, $this);
    }

    /**
     * Report a template mistake: throw when strict, otherwise log a warning
     *
     * @throws TemplateException when strict
     */
    public function fail(string $message): void
    {
        if ($this->strict) {
            throw new TemplateException($message);
        }
        if ($this->logger) {
            $this->logger->warning($message);
        } else {
            error_log('tk-domtemplate: ' . $message);
        }
    }
}
