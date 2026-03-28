<?php

namespace TemplateEngine;

enum EscapeStrategy: string
{
    case HTML_ATTRIBUTE         = 'html_attr';
    case HTML_ATTRIBUTE_RELAXED = 'html_attr_relaxed';
    case HTML                   = 'html';
    case CSS                    = 'css';
    case JS                     = 'js';
    case URL                    = 'url';
    case RAW                    = 'raw';
}
