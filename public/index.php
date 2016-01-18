<?php

if (file_exists(__DIR__.'/../../autoload.php')) {
    require __DIR__.'/../../autoload.php';
} else {
    require __DIR__.'/../vendor/autoload.php';
}
define('ROOT_PATH', __DIR__);
chdir(__DIR__);

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if(PHP_SAPI == 'cli') {
	$argv[0] = '';
	$request = Request::create(implode('/', $argv));
} else {
	$request = Request::createFromGlobals();
}

$response = new Response;
$imagist = new Acabin\Imagist\Http\Imagist($request, $response);
$imagist->run();

