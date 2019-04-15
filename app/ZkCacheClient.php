<?php
/**
 * Created by PhpStorm.
 * User: guochen
 * Date: 15/04/2019
 * Time: 11:20 AM
 */

namespace App;


use Predis\Client;

class ZkCacheClient
{
    private static $instance = null;
    private $client = null;

    private function __construct($conf)
    {
        $this->client = new Client(
            [
                'host' => $conf['REDIS_HOST'],
                'port' => $conf['REDIS_PORT'],
                'database' => $conf['REDIS_DATABASE'],
            ]
        );
    }

    public static function getInstance(array $conf)
    {
        if (empty(self::$instance)) {
            self::$instance = new self($conf);
        }
        return self::$instance;
    }

    public function setCacheConf(string $zkFullPath, ?string $value)
    {
        $this->client->set(str_replace('/', ':', trim($zkFullPath, '/')), $value);
    }

    public function delCacheConf(string $zkFullPath)
    {
        $this->client->del(str_replace('/', ':', trim($zkFullPath, '/')));
    }

    /**
     * 从缓存读取配置信息
     * @param string $shortPath
     * @param string $default
     * @return string
     */
    public function getConf(string $basePath, string $shortPath, $default = '')
    {
        $cachePath = str_replace('/', ':', trim($basePath, '/')) . ':' .
            str_replace('/', ':', trim($shortPath, '/'));
//        var_dump($cachePath);
//        die;
        return $this->client->get($cachePath) ?? $default;
    }


}