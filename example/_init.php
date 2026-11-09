<?php
/**
 * Shared setup for the example pages.
 *
 * Each page builds its own content Template, then calls renderPage() to
 * insert it into the Bootstrap layout (templates/layout.html).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tk\DomTemplate\Template;

const PAGES = [
    'index.php'  => 'Home',
    'var.php'    => 'Vars',
    'repeat.php' => 'Choices & repeats',
    'form.php'   => 'Forms',
    'assets.php' => 'Head assets',
];

/**
 * @param string[] $sources files (relative to example/) to show under the page
 */
function renderPage(string $title, Template $content, array $sources = []): void
{
    $page = Template::loadFile(__DIR__ . '/templates/layout.html');
    $page->setTitleText($title . ' - DomTemplate examples');
    $page->setText('heading', $title);

    // Navigation: one repeat row per page, highlighting the current one
    $current = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    foreach (PAGES as $file => $label) {
        $row = $page->getRepeat('nav');
        $row->setText('navLink', $label);
        $row->setAttr('navLink', 'href', $file);
        if ($file === $current) {
            $row->addCss('navLink', 'active');
            $row->setAttr('navLink', 'aria-current', 'page');
        }
        $row->appendRepeat();
    }

    // Any CSS/JS the content template added is merged into the layout <head>
    $page->insertTemplate('content', $content);

    // Show the source behind the page
    foreach ($sources as $i => $file) {
        $row = $page->getRepeat('source');
        $row->setText('sourceName', $file);
        $row->setText('sourceCode', (string)file_get_contents(__DIR__ . '/' . $file));
        $row->setAttr('sourceToggle', 'data-bs-target', '#src' . $i);
        $row->setAttr('sourceBody', 'id', 'src' . $i);
        $row->appendRepeat();
    }
    $page->setVisible('sources', count($sources) > 0);

    echo $page->toString();
}
