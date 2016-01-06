<?php

namespace Acabin\Imagist\Http;

use Illuminate\Http\Request;

use Composer\Console\Application;
use Composer\IO\ConsoleIO;
use Symfony\Component\Console\Helper\HelperSet;
use Composer\Factory;
use Composer\Repository\CompositeRepository;

use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Composer\DependencyResolver\Pool;

class Imagist
{
    protected $request;
    protected $response;
    protected $pathinfo;
    public function __construct($request, $response)
    {
        $this->request = $request;
        $this->response = $response;
        $this->pathinfo = $request->getPathInfo();

    }

    public function run()
    {
        $pathinfo = rawurldecode($this->pathinfo);
        switch (true) {
            case preg_match('{^/packages.json$}i', $pathinfo):
                $output = $this->generate();
                break;
            case preg_match('{^/p/(?<name>.*)\$(?<hash>.*)\.json$}i', $pathinfo, $matches):
                $output = $this->package($matches);
                break;
            default:
                echo $pathinfo;exit;
        }
        if (isset($output)) {
            if (is_string($output)) {
                echo $output;
                return;
            } elseif (is_object($output)) {
                if (is_callable([$output, '__toString'])) {
                    echo $output->__toString();
                    return;
                }
            }
            echo json_encode($output);
        }
    }

    protected function generate()
    {
        $packages = [
            'packages' => [],
            'notify' => 'https://packagist.org/downloads/%package%',
            'notify-batch' => 'https://packagist.org/downloads/',
            'providers-url' => '/p/%package%$%hash%.json',
            'search' => 'https://packagist.org/search.json?q=%query%',
            'provider-includes' => [
            ],

            'sync-time' => date('c'),
        ];

        $repos = $this->getRepos();
        foreach ($repos as $repo) {
            $repoName = $repo->name;
            if (!is_callable([$repo, 'getProviderNames'])) {
                continue;
            }
            $repo->getProviderNames();
            // do some hack
            $ref = new \ReflectionProperty($repo, 'providerListing');
            $ref->setAccessible(true);
            $all = $ref->getValue($repo);

            $json = json_encode(['providers' => $all]);
            $sha256 = hash('sha256', $json);
            $path = "p/{$repoName}";
            $file = "{$path}/all\${$sha256}.json";

            mkdir($path, 0755, true);

            file_put_contents($file, $json);

            $packages['provider-includes'][$file] = ['sha256' => $sha256];
        }

        file_put_contents("packages.json", json_encode($packages));
        return $packages;
    }

    protected function package($matches)
    {

        $hash = $matches['hash'];
        $name = $matches['name'];
        $filename = 'p/hash/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . hash('md5', $hash . $name) . '.json';
        if (!file_exists($filename)) {
            $repos = $this->getRepos();
            $pool = new Pool('dev');
            foreach ($repos as $repo) {
                try {
                    $pool->addRepository($repo);
                } catch (\RuntimeException $e) {}

            }
            $matches = $pool->whatProvides($name, null);
            if (!$matches) {
                return '{}';
            } else {
                $match = $matches[0];
                $repo = $match->getRepository();

                $ref = new \ReflectionProperty($repo, 'cache');
                $ref->setAccessible(true);
                $cache = $ref->getValue($repo);

                $cacheKey = 'provider-' . strtr($name, '/', '$') . '.json';
                $packages = $cache->read($cacheKey);
                if (empty($packages)) {
                    throw new \Exception("Cache should exists, please report this issue on github", 1);
                }
                mkdir(dirname($filename), 0755, true);
                file_put_contents($filename, $packages);
            }

        }
        return file_get_contents($filename);
    }

    protected function getRepos()
    {
        $input = new StringInput('');
        $output = new BufferedOutput;
        $helperSet = new HelperSet;

        $io = new ConsoleIO($input, $output, $helperSet);
        if (!file_exists('composer.json')) {
            putenv('COMPOSER=../composer.json');
        }

        $composer = Factory::create($io);
        $config = $composer->getConfig();
        $repos = $config->getRepositories();

        foreach ($repos as &$repo) {
            $type = ucfirst($repo['type']);
            $type = "Composer\\Repository\\{$type}Repository";
            $repo = new $type($repo, $io, $config);
            $ref = new \ReflectionProperty($repo, 'url');
            $ref->setAccessible(true);
            $url = $ref->getValue($repo);
            $repo->name = preg_replace(['{^https?://}i', '{[^a-z0-9._]}i'], ['', '-'], $url);
        }
        return $repos;
    }
}
