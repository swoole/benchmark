<?php declare(strict_types=1);

function loop_n(int $n, callable $fn)
{
    while ($n--) {
        $fn();
    }
}
