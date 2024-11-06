<?php

use Swoole\Coroutine\Http\Client;
use function Swoole\Coroutine\run;

run(function () {
    $cli = new Client('sg.sobot.com', 443, true);
    $cli->set(['timeout' => 10]);
    $count = 0;
    while (true) {
        $start = microtime(true);
        $cli->get('/');
        $end = microtime(true);
        echo "usage: " . ($end - $start) . PHP_EOL;
        if ($cli->statusCode != 200) {
            var_dump($cli->statusCode);
            die;
        }
        echo 'count: ' . $count++ . ', resp size：' . strlen($cli->body) . PHP_EOL;
        sleep(5);
    }
});
