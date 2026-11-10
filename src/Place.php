<?php
namespace Tk\DomTemplate;

/**
 * Where a js() or css() asset is written
 */
enum Place
{
    /** At the end of the template that added it, so it travels with that markup (the default) */
    case Inline;
    /** At the end of the page's <head>; in place when there is none */
    case Head;
    /** At the end of the page's <body>; in place when there is none */
    case End;
}
