<?php

require_once __DIR__ . '/vendor/autoload.php';

use function Swoole\Coroutine\run;
use GuzzleHttp\Client;

run(function () {
    global $argv;
    loop_n(swoole_array_default_value($argv, 1, 10000), function () {
        $client = new Client();
        $r = $client->request('GET', 'http://www.baidu.com/');
        var_dump(strlen($r));
        usleep(100000);
    });
});
