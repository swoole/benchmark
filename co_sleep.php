<?php

Co::set(['c_stack_size' => '128k']);

const N = 4096;
const MAX_DIFF = 20;
$count = 0;

function check_duration($count, $real_ms, $ms)
{
    if (abs($real_ms - $ms) > MAX_DIFF) {
        echo("unexpect: count=$count, real_ms=$real_ms, ms=$ms\n");
    }
}

Co\run(function () use (&$count) {
    $children = [];
    for ($i = 0; $i < N; $i++) {
        $children[] = Co\go(function () use (&$count) {
            while (1) {
                $ms = random_int(1, 10000);
                $s = microtime(true);
                usleep($ms * 1000);
                $count++;
                $real_ms = intval((microtime(true) - $s) * 1000);
                check_duration($count, $real_ms, $ms);
            }
        });
    }
    Co::join($children);
});

