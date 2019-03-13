<?php
/**
 * Created by PhpStorm.
 * User: guochen
 * Date: 12/03/2019
 * Time: 2:10 PM
 */

$host = '127.0.0.1:2181';
$root = '/app/module';


class ZookeeperTree
{
    public function toArray()
    {

    }
}

class CacheZk
{
    protected $redis = null;

    protected function redis()
    {
        if (empty($this->redis)) {
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379);
        }
        return $this->redis;
    }

    public function mset(array $config)
    {
        $this->redis()->mset($config);
    }

    public function set($zkPath, $value)
    {
        $this->redis()->set($zkPath, $value);
    }

    public function del($zkPath)
    {
        $this->redis()->del($zkPath);
    }

}


class WatcherEvent
{

    protected $cacheZk;
    protected $zookeeper;

    public function __construct(CacheZk $cacheZk, $zkHost)
    {
        $this->cacheZk = $cacheZk;
        $timeout = 3999;
        $this->zookeeper = new Zookeeper($zkHost, [$this, 'watch'], $timeout);
    }

    public function run($root)
    {
        $this->getAndCacheChild($root);
    }

    protected function getAndCacheChild($root)
    {
        $nodes = $this->zookeeper->getChildren($root, [$this, 'watch']);
        foreach ($nodes as $node) {
            $this->getAndCacheNode($root . '/' . $node);
        }
    }

    protected function getAndCacheNode($nodePath)
    {
//     public exists( string $path [, callable $watcher_cb = NULL ] ) : bool
        if ($this->zookeeper->exists($nodePath)) {
            // 更新缓存
            $stat = [];
            $tempValue = $this->zookeeper->get($nodePath, [$this, 'watch'], $stat);
            $this->cacheZk->set($nodePath, $tempValue);
        } else {
            // 删除缓存
            $this->cacheZk->del($nodePath);
        }
    }

    public function watch($eventType, $connectionState, $path)
    {
        echo "ChildWatcher \n";
        echo 'eventType: ' . $eventType . "\n";
        echo 'connectionState: ' . $connectionState . "\n";
        echo 'path: ' . $path . "\n";

        switch ($eventType) {
            case Zookeeper::CREATED_EVENT:
                // 1 数据监控返回,节点创建,需要watch一个不存在的节点,通过exists监控,通过create操作触发

            case Zookeeper::DELETED_EVENT:
                // 2 数据监控返回,节点删除,通过 exists 和 get 监控,通过delete操作触发
            case Zookeeper::CHANGED_EVENT:
                // 3 数据监控返回, 节点数据改变, 通过 exists 和 get 监控, 通过set操作触发
                $this->getAndCacheNode($path);
                break;

            case Zookeeper::CHILD_EVENT:
                // 4 节点监控返回,通过 getchild 监控, 通过子节点的 delete 和 create 操作触发
                $this->getAndCacheChild($path);
                break;

            case Zookeeper::SESSION_EVENT:
                // -1 会话监控返回,客户端与服务端断开或重连时触发

            case Zookeeper::NOTWATCHING_EVENT:
                // -2 watch移除事,服务端不再回调客户端

            default:
        }
    }


}

$watcherEvent = new WatcherEvent(new CacheZk(), $host);
$watcherEvent->run($root);


while (true) {
    sleep(1);
}





