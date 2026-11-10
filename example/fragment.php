<?php
// An HTMX response: a fragment, not a page. Its assets stay inline so they arrive with it.
require dirname(__DIR__) . '/vendor/autoload.php';

use Tk\DomTemplate\Place;
use Tk\DomTemplate\Template;

$fragment = Template::load(<<<HTML
<div x-data="{ count: 0 }">
  <p class="mb-2">Rendered at <strong var="time"></strong>.</p>
  <button class="btn btn-sm btn-success" @click="count++">Clicked <span x-text="count">0</span> times</button>
  <p class="mt-2 mb-0 small" id="fragmentScript">The fragment's script hasn't run.</p>
</div>
HTML);

$fragment->text('time', date('H:i:s'));
$fragment->js("document.getElementById('fragmentScript').textContent = 'The fragment\\'s own script ran after the swap.';");
$fragment->cssUrl('css/example.css');  // no <head> in a fragment, so this is written inline
$fragment->js('/* Head scripts fall back to inline in a fragment */', Place::Head);

echo $fragment->render();
