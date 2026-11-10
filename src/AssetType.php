<?php
namespace Tk\DomTemplate;

/**
 * What an asset writes. The value names the Template method that adds it.
 *
 * @internal
 */
enum AssetType: string
{
    case Js = 'js';
    case Css = 'css';
    case JsUrl = 'jsUrl';
    case CssUrl = 'cssUrl';
    case Meta = 'meta';
    case Title = 'title';
}
