<?php
require __DIR__ . '/../src/Imagist.php';

ini_set('open_basedir', dirname(__DIR__));
chdir(__DIR__);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = ltrim($uri, '/');
$uri = str_replace('%24', '$', $uri);
if(file_exists($uri)) {
	return false;
}
$imagist = new Garveen\Imagist\Imagist($uri);
echo $imagist->run();

