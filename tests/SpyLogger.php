<?php
namespace Tk\DomTemplate\Tests;

/**
 * Records log calls for assertions
 */
final class SpyLogger extends \Psr\Log\AbstractLogger
{
    /** @var list<array{mixed, string}> */
    public array $lines = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->lines[] = [$level, (string)$message];
    }
}
