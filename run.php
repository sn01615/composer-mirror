<?php

// https://repo.packagist.org/packages.json

include __DIR__ . "/vendor/autoload.php";

use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

$cacheDir = 'public';
if (!file_exists($cacheDir))
    mkdir($cacheDir);

$client = new Client([
    'base_uri' => 'https://repo.packagist.org',
    RequestOptions::TIMEOUT => 600,
    RequestOptions::VERIFY => CaBundle::getSystemCaRootBundlePath(),
]);

$index = 'packages.json';

$promise = $client->getAsync($index);
$promise->then(
    function (ResponseInterface $res) use ($index, $cacheDir, $client) {
        if ($res->getStatusCode() == 200) {
            $content = $res->getBody();
            $content = json_decode($content);
            foreach ($content->{"provider-includes"} as $key => $hash) {
                $url = str_replace('%hash%', $hash->sha256, $key);
                echo "Download $url\n";
                $promise = $client->getAsync($url);
                $promise->then(
                    function (ResponseInterface $res) use ($url, $cacheDir, $client) {
                        if ($res->getStatusCode() == 200) {
                            $content = $res->getBody();
                            $dir = $cacheDir . '/' . dirname($url);
                            if (!file_exists($dir))
                                mkdir($dir);
                            $contentObj = json_decode($content);
                            if (!$contentObj) {
                                exit('json_decode fail');
                            }
                            echo "Write {$url}\n";
                            file_put_contents("{$cacheDir}/{$url}", $content);
                        }
                    },
                    function (RequestException $e) {
                        echo $e->getMessage() . "\n";
                        echo $e->getRequest()->getMethod();
                    }
                );
                $promise->wait();
            }
            echo "Write {$index}\n";
            file_put_contents("{$cacheDir}/{$index}", $content);
        }
    },
    function (RequestException $e) {
        echo $e->getMessage() . "\n";
        echo $e->getRequest()->getMethod();
    }
);
$promise->wait();
