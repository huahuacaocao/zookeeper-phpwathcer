<?php

/**
 * Created by PhpStorm.
 * User: guochen
 * Date: 12/03/2019
 * Time: 2:10 PM
 */
class WatcherEvent
{

    protected $zookeeper;
    protected $root = '';
    protected $zkHost = '';
    protected $connectionStatus = false;

    public function __construct($zkHost, string $root)
    {
        $this->root = $root;
        $this->zkHost = $zkHost;
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
            ZookeeperTool::setCacheConf($nodePath, $tempValue);
        } else {
            echo "delete cache: $nodePath \r\n";
            // 删除缓存
            ZookeeperTool::delCacheConf($nodePath);
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
                // 1 数据监控返回,节点创建,需要watch一个不存在的节点,通过exists监控,通过create操作触发
            case \Zookeeper::DELETED_EVENT:
                // 2 数据监控返回,节点删除,通过 exists 和 get 监控,通过 delete 操作触发
            case \Zookeeper::CHANGED_EVENT:
                // 3 数据监控返回, 节点数据改变, 通过 exists 和 get 监控, 通过set操作触发
                $this->getAndCacheNode($path);
                break;
            case \Zookeeper::CHILD_EVENT:
                // 4 节点监控返回,通过 getchild 监控, 通过子节点的 delete 和 create 操作触发
                $this->getAndCacheChild($path);
                break;
            case \Zookeeper::SESSION_EVENT:
                // -1 会话监控返回,客户端与服务端断开或重连时触发
                if (3 == $connectionState) {
                    $this->getAndCacheChild($this->root);
                }
                break;
            case \Zookeeper::NOTWATCHING_EVENT:
                // -2 watch移除事,服务端不再回调客户端
            default:
        }
    }
}





