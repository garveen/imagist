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
    protected $dumps = [];
    public function __construct()
    {
        if (!file_exists('p')) {
            mkdir('p', 0755);
        }
        set_error_handler([$this, 'handleError']);
    }

    public function runCli($argc, $argv)
    {
        switch (strtolower($argv[1])) {
            case 'dumpindex':
                $this->dumpIndex();
                return 'done';
            case 'dumpall':
                $this->dumpAll();
                return 'done';
            case 'packages':
                $this->dumpPackages();
                return;
            case 'cleanindex':
                $this->cleanIndex();
                return 'done';
            case 'cleanpackages':
                $this->cleanPackages();
                return 'done';
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

    protected function dumpPackages()
    {
        $packagesJson = $this->get('packages.json', '', true);
        $packages = json_decode($packagesJson);
        $packages->updated = date(DATE_W3C);
        file_put_contents('packages.json', json_encode($packages));
        $this->providers_url = $packages->{"providers-url"};
        return $packages;

    }

    protected function dumpIndex()
    {
        $packages = $this->dumpPackages();
        $names = [];
        foreach ($packages->{"provider-includes"} as $key => $hash) {
            $name = str_replace('%hash%', reset($hash), $key);
            $names[] = $name;
        }
        $this->dumps = [];
        $this->multiDump($names);
        return $this->dumps;
    }

    protected function dumpAll()
    {
        $names = $this->dumpIndex();
        $names = array_reverse($names);

        foreach ($names as $name) {
            $packages = [];
            $cleans = [];
            $include = json_decode($this->get($name));
            foreach ($include->providers as $key => $hash) {
                $name = ltrim(str_replace(['%hash%', '%package%'], [reset($hash), $key], $this->providers_url), '/');
                $packages[] = $name;
                $cleans[$name] = $key;
            }
            $this->dumps = [];
            $this->multiDump($packages);
            $providers = [];
            foreach($this->dumps as $name) {
                if(isset($cleans[$name])) {
                    $providers[] = 'p/' . dirname($cleans[$name]);
                }
            }
            $this->cleanPackages($providers);
        }
        $this->cleanIndex();
    }

    protected function cleanIndex()
    {
        foreach (glob('p/provider-*') as $provider) {
            if (is_file($provider)) {
                $providers[strstr($provider, '$', true)][$provider] = filectime($provider);
            }
        }
        $this->filterUnlink($providers);
    }

    protected function cleanPackages($dir = 'p/*')
    {
        if (!is_array($dir)) {
            $dir = glob($dir);
        }
        foreach ($dir as $provider) {
            if (is_dir($provider)) {
                $packages = [];
                foreach (glob($provider . '/*') as $package) {
                    $packages[strstr($package, '$', true)][$package] = filectime($package);
                }
                $this->filterUnlink($packages);
            }
        }
    }

    protected function filterUnlink($set)
    {
        foreach ($set as $list) {
            arsort($list);
            foreach (array_slice($list, 2) as $name => $time) {
                echo "unlink {$name}\n";
                unlink($name);
            }
        }
    }

    protected function multiDump($names, $force = false)
    {
        $curl = extension_loaded('curl');
        if ($curl) {
            $this->multiCurl = new MultiCurl(100);
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
            $this->dumpFile($name, '', $force, $curl);
        }

        if ($curl) {
            $this->multiCurl->execute();
        }
    }

    protected function get($name, $hash = '', $force = false)
    {
        if (!$force && file_exists($name)) {
            $content = file_get_contents($name);
            if (!$hash || $hash == hash($this->encrypt, $content)) {
                return $content;
            }
        }
        $this->dumpFile($name, $hash, $force);
        return file_get_contents($name);
    }

    protected function dumpFile($name, $hash = '', $force = false, $curl = false)
    {
        if (!$force && file_exists($name)) {
            return false;
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
                            $this->dumps[] = $name;
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
                    return false;
                }
                if ($hash && $hash !== hash($this->encrypt, $content)) {
                    $this->error(500, 'hash error');
                }
                file_put_contents($name, $content);
                echo $name . " wrote\n";
                return true;
            }
        } catch (\Exception $e) {
            if (isset($http_response_header)) {
                $code = preg_match('~\d{3}~', $http_response_header[0], $matches);
                if (!$code) {
                    $this->error(404, 'Not Found');
                } else {
                    $this->error($code, strstr($http_response_header[0], ' '));
                }
            } else {
                $this->error(500, 'Internel Error');
            }
        }

    }

    protected function url($uri)
    {
        return $this->base . $uri;
    }

    protected function error($code, $message)
    {
        header("HTTP/1.0 {$code}");
        echo $code . ' ' . $message;
        exit;
    }

}
