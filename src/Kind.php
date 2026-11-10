<?php
namespace Tk\DomTemplate;

/**
 * The kind of node a var attribute marks, set by its suffix
 */
enum Kind
{
    /** var="name": a node PHP changes, visible by default */
    case Var;
    /** var="name?": removed at render unless shown */
    case Optional;
    /** var="name[]": a sample row, cloned per item and removed at render */
    case Repeat;
}
