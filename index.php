<?php
require __DIR__ . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\Request;

Router::load('app/routes.php')
    ->direct(Request::uri(), Request::method());