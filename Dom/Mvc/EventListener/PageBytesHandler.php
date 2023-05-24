<?php
namespace Dom\Mvc\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Dom\Mvc\Modifier\PageBytes;
use Tk\Mvc\EventListener\StartupHandler;

class PageBytesHandler implements EventSubscriberInterface
{

    private LoggerInterface $logger;

    protected PageBytes $pageBytes;


    function __construct(LoggerInterface $logger, PageBytes $pageBytes)
    {
        $this->logger = $logger;
        $this->pageBytes = $pageBytes;
    }

    /**
     * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
     */
    public function onTerminate($event)
    {
        if (!StartupHandler::$SCRIPT_CALLED) return;

        if ($this->pageBytes) {
            foreach (explode("\n", $this->pageBytesToString()) as $line) {
                $this->logger->info($line);
            }
        }
    }

    private function pageBytesToString(): string
    {
        $str = '';
        $j = $this->pageBytes->getJsBytes();
        $c = $this->pageBytes->getCssBytes();
        $h = $this->pageBytes->getHtmlBytes();
        $t = $j + $c +$h;

        if ($t > 0) {
            $str .= 'Page Sizes:' . \PHP_EOL;
            $str .= sprintf('  JS:      %6s', \Tk\FileUtil::bytes2String($j)) . \PHP_EOL;
            $str .= sprintf('  CSS:     %6s', \Tk\FileUtil::bytes2String($c)) . \PHP_EOL;
            $str .= sprintf('  HTML:    %6s', \Tk\FileUtil::bytes2String($h)) . \PHP_EOL;
            $str .= sprintf('  TOTAL:   %6s', \Tk\FileUtil::bytes2String($t));
        }
        return $str;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['onTerminate', -100]
        ];
    }

}