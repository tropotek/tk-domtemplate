<?php
/**
 * Shared setup for the example pages.
 *
 * Each page builds its own content template, then calls renderPage() to insert
 * it into the Bootstrap layout (templates/layout.html).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tk\DomTemplate\Row;
use Tk\DomTemplate\Template;

const PAGES = [
    'index.php'  => 'Home',
    'var.php'    => 'Vars',
    'repeat.php' => 'Repeats',
];

/**
 * @param string[] $sources files (relative to example/) to show under the page
 */
function renderPage(string $title, Template $content, array $sources = []): void
{
    $page = Template::file(__DIR__ . '/templates/layout.html');
    $page->title($title . ' - DomTemplate examples');
    $page->text('heading', $title);

    // Navigation: one row per page, the current one marked active
    $current = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $page->each('nav', PAGES, function (Row $row, string $label, string $file) use ($current) {
        $row->var('link')
            ->text($label)
            ->attr(['href' => $file, 'aria-current' => $file === $current ? 'page' : null])
            ->addClass($file === $current ? 'active' : []);
    });

    // The page's content template is rendered when the layout renders
    $page->html('content', $content);

    // The source behind the page
    $page->each('source', array_values($sources), function (Row $row, string $file, int $i) {
        $row->text('toggle', $file)->attr('toggle', 'data-bs-target', "#src$i");
        $row->attr('body', 'id', "src$i");
        $row->text('code', (string)file_get_contents(__DIR__ . '/' . $file));
    });
    $page->show('sources', count($sources) > 0);

    echo $page->render();
}
