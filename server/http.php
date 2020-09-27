<?php

require __DIR__ . '/functions.php';

use Swoole\Http\Server;
use Swoole\Http\Response;
use Swoole\Http\Request;

//$http = new Server("127.0.0.1", 9501);
$http = new Server("127.0.0.1", 9502, SWOOLE_BASE);

$pool = new SplQueue();

$http->set(
    [
        'worker_num' => 4,
        'hook_flags' => SWOOLE_HOOK_ALL,
        'enable_reuse_port' => true,
    ]
);

$http->on(
    'request',
    function (Request $request, Response $response) use (&$pool, $http) {
//    var_dump($request->server['request_uri']);
        if ($request->server['request_uri'] == '/') {
            $response->header('Last-Modified', 'Thu, 18 Jun 2015 10:24:27 GMT');
            $response->header('E-Tag', '55829c5b-17');
            $response->header('Accept-Ranges', 'bytes');
            $response->end(SwooleBench\get_response($request->getContent()));
        } elseif ($request->server['request_uri'] == '/redis') {
            if (count($pool) > 0) {
                $redis = $pool->pop();
            }
            else {
                $redis = new redis;
                $redis->connect('127.0.0.1', 6379);
            }
            $value = $redis->get('key');
            $pool->push($redis);
            $response->end("<h1>Value=" . $value . "</h1>");
        } elseif ($request->server['request_uri'] == '/redis_pool_status') {
            $response->end("<pre> Worker#".$http->getWorkerId().', PoolSize=' . var_export(count($pool), 1) . "</pre>\n");
        }
    }
);

$http->start();
