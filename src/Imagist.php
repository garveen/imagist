<?php
namespace Garveen\Imagist;

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class Imagist
{
    protected $base = 'https://packagist.org/';
    protected $uri;
    protected $requestUri;
    public function __construct($uri = null)
    {
        $this->requestUri = $uri;
        if (!file_exists('p')) {
            mkdir('p', 0755);
        }
    }

    public function runCli($argc, $argv)
    {
        switch($argv[1]) {
            case 'dumpall':
                $this->dumpAll();
                return 'done';
            case 'packages':
                $this->get('packages.json', true);
                return;
        }
    }

    public function run()
    {
        $filename = ltrim($this->requestUri, '/');
        return $this->get($filename);
    }

    protected function dumpAll()
    {
        $packagesJson = $this->get('packages.json');
        $packages = json_decode($packagesJson);
        $providers_url = $packages->{"providers-url"};
        foreach ($packages->{"provider-includes"} as $key => $hash) {
            $name = str_replace('%hash%', reset($hash), $key);
            $include = json_decode($this->get($name));
            foreach ($include->providers as $key => $hash) {
                $name = ltrim(str_replace(['%hash%', '%package%'], [reset($hash), $key], $providers_url), '/');
                $this->get($name);

            }
        }
        file_put_contents('packages.json', $packagesJson);
        return $packagesJson;
    }

    protected function get($name, $force = false)
    {
        if (!$force && file_exists($name)) {
            return file_get_contents($name);
        }
        if (!file_exists(dirname($name))) {
            mkdir(dirname($name), 0755);
        }
        $aContext = array(
            'http' => array(
                'proxy' => 'tcp://127.0.0.1:4123',
                'request_fullname' => true,
            ),
        );
        $cxContext = stream_context_create($aContext);

        $content = file_get_contents($this->url($name), false, $cxContext);
        if(!$content) {
            return '';
        }
        file_put_contents($name, $content);
        return $content;


    }

    protected function url($uri)
    {
        return $this->base . ltrim($uri, '/');
    }

}
