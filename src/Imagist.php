<?php
namespace Garveen\Imagist;

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class Imagist
{
    public $base = 'https://packagist.org/';
    public $encrypt = 'sha256';
    protected $uri;
    public function __construct()
    {
        if (!file_exists('p')) {
            mkdir('p', 0755);
        }
        set_error_handler([$this, 'handleError']);
    }

    public function runCli($argc, $argv)
    {
        switch ($argv[1]) {
            case 'dumpindex':
                $this->dumpIndex();
                return 'done';
            case 'dumpall':
                $this->dumpAll();
                return 'done';
            case 'packages':
                $this->get('packages.json', '', true);
                return;
        }
    }

    public function run($uri)
    {
        if ($uri == '') {
            header('Location: https://github.com/garveen/imagist');
            return;
        }
        $count = preg_match('~^(.*?)\$([A-Za-z0-9]+)(.+)~', $uri, $matches);
        $hash = '';
        if ($count) {
            $name = $matches[1];
            $hash = $matches[2];
            $suffix = $matches[3];
        }

        return $this->get($uri, $hash);
    }

    public function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }
    }

    protected function dumpIndex()
    {
        $packagesJson = $this->get('packages.json', '', true);
        $packages = json_decode($packagesJson);
        $providers_url = $packages->{"providers-url"};
        $names = [];
        foreach ($packages->{"provider-includes"} as $key => $hash) {
            $name = str_replace('%hash%', reset($hash), $key);
            $names[] = $name;
        }
        $this->multiGet($names);
        return $names;
    }

    protected function dumpAll()
    {
        $names = $this->dumpIndex();
        array_reverse($names);

        foreach ($names as $name) {
            $packages = [];
            $include = json_decode($this->get($name));
            foreach ($include->providers as $key => $hash) {
                $name = ltrim(str_replace(['%hash%', '%package%'], [reset($hash), $key], $providers_url), '/');
                $packages[] = $name;
            }
            $this->multiGet($packages);
        }
    }

    protected function multiGet($names, $force = false)
    {
        $curl = extension_loaded('curl');
        if ($curl) {
            $this->multiCurl = new MultiCurl(10);
            $options = [
                CURLOPT_SSL_VERIFYPEER => false,
            ];
            if ($proxy = getenv('http_proxy')) {
                $options[CURLOPT_PROXY] = $proxy;
            }

            $this->multiCurl->setOptions($options);
            $this->multiCurl->setTimeout(60000);
        }

        foreach ($names as $name) {
            $this->get($name, '', $force, $curl);
        }

        if ($curl) {
            $this->multiCurl->execute();
        }
    }

    protected function get($name, $hash = '', $force = false, $curl = false)
    {
        if (!$force && file_exists($name)) {
            if (!$hash || $hash == hash($this->encrypt, $content)) {
                return file_get_contents($name);
            }
        }
        if (!file_exists(dirname($name))) {
            mkdir(dirname($name), 0755);
        }
        try {
            if ($curl) {
                $this->multiCurl->addRequest(
                    $this->url($name),
                    null,
                    function ($response, $url, $request_info, $user_data, $time) use ($name) {
                        if ($response) {
                            file_put_contents($name, $response);
                            echo $name . " wrote\n";
                        }
                    }
                );

            } else {
                if ($proxy = getenv('http_proxy')) {
                    $aContext = array(
                        'http' => array(
                            'proxy' => $proxy,
                            'request_fullname' => true,
                        ),
                    );
                    $cxContext = stream_context_create($aContext);

                    $content = file_get_contents($this->url($name), false, $cxContext);

                } else {
                    $content = file_get_contents($this->url($name));
                }

                if (!$content) {
                    return '';
                }
                if ($hash && $hash !== hash($this->encrypt, $content)) {
                    header('HTTP/1.0 500');
                    return '500 hash error';

                }
                file_put_contents($name, $content);
                return $content;
            }
        } catch (\Exception $e) {
            echo $e->getTraceAsString();
            $count = preg_match('~\d{3}~', $http_response_header[0], $matches);
            if (!$count) {
                header('HTTP/1.0 404 Not Found');
                return '404 Not Found';
            } else {
                header($http_response_header[0]);
                return strstr($http_response_header[0], ' ');
            }
        }

    }

    protected function url($uri)
    {
        return $this->base . $uri;
    }

}
