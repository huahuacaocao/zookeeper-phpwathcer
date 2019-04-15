<?php

namespace App;

class WatcherEvent
{

    protected $zookeeper;
    protected $root = '';
    protected $zkHost = '';
    protected $client = null;
    protected $cacheClient = null;

    public function __construct($zkHost, string $root, $cacheConf)
    {
        $this->root = $root;
        $this->zkHost = $zkHost;
        $this->cacheClient = ZkCacheClient::getInstance($cacheConf);
    }

    public function run()
    {
        $this->zookeeper = new \Zookeeper($this->zkHost, [$this, 'watch'], 10000);
    }

    /**
     * 获取子节点缓存并监控
     * @param $root
     */
    protected function getAndCacheChild($root)
    {
        if ($this->zookeeper->exists($root)) {
            echo "watch child: $root \r\n";
            $nodes = $this->zookeeper->getChildren($root, [$this, 'watch']);
            if (empty($nodes)) {
                $this->getAndCacheNode($root);
            } else {
                foreach ($nodes as $node) {
                    $this->getAndCacheChild($root . '/' . $node);
                }
            }
        }
    }

    /**
     * 获取节点值 缓存并监控
     * @param $nodePath
     */
    protected function getAndCacheNode($nodePath)
    {
        if ($this->zookeeper->exists($nodePath)) {
            $stat = [];
            echo "watch node : $nodePath \r\n";
            $tempValue = $this->zookeeper->get($nodePath, [$this, 'watch'], $stat);
            echo "cache node : $nodePath \r\n";
            $this->cacheClient->setCacheConf($nodePath, $tempValue);
        } else {
            echo "delete cache: $nodePath \r\n";
            // 删除缓存
            $this->cacheClient->delCacheConf($nodePath);
        }
    }

    /**
     * 监控回调事件{连接事件 节点事件 子节点事件}
     * @param $eventType
     * @param $connectionState
     * @param $path
     */
    public function watch($eventType, $connectionState, $path)
    {
        switch ($eventType) {
            case \Zookeeper::CREATED_EVENT:
            case \Zookeeper::DELETED_EVENT:
            case \Zookeeper::CHANGED_EVENT:
                $this->getAndCacheNode($path);
                break;
            case \Zookeeper::CHILD_EVENT:
                $this->getAndCacheChild($path);
                break;
            case \Zookeeper::SESSION_EVENT:
                if (3 == $connectionState) {
                    $this->getAndCacheChild($this->root);
                }
                break;
            case \Zookeeper::NOTWATCHING_EVENT:
            default:
        }
    }

}





