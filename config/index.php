<?php

use NGSOFT\Facades\Container;

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/app.php';
$fn = require_once __DIR__ . '/definitions.php';
$fn(Container::getFacadeRoot());
