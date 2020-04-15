<?php
$table = new swoole_table(8 * 1024 * 1024);
$table->column('id', swoole_table::TYPE_INT, 4);
$table->column('name', swoole_table::TYPE_STRING, 256);
$table->column('num', swoole_table::TYPE_FLOAT);
$table->create();

define('N', 1000000);
define('C', 4);

if (empty($argv[1])) {
    test1();
    test2();
    test3();
    test4();
    test5();
} else {
    $test_func = trim($argv[1]);
    $test_func();
}

function test1()
{
    global $table;

    /**
     * table_size = 1M
     */
    $s = microtime(true);
    $n = N;
    while ($n--) {
        $table->set(
            'key_' . $n,
            array('id' => $n, 'name' => "swoole, value=$n\r\n", 'num' => 3.1415 * rand(10000, 99999))
        );
    }
    echo "set " . N . " keys, use: " . round((microtime(true) - $s) * 1000, 2) . "ms\n";
}

function test2()
{
    global $table;
    $n = N;
    $s = microtime(true);
    while ($n--) {
        $key = rand(0, N);
        $data = $table->get('key_' . $key);
    }
    echo "get " . N . " keys, use: " . round((microtime(true) - $s) * 1000, 2) . "ms\n";
}

function test3()
{
    for ($i = C; $i--;) {
        (new swoole_process(
            function () use ($i) {
                global $table;
                $n = N;
                $s = microtime(true);
                while ($n--) {
                    $key = rand(0, N);
                    $data = $table->get('key_' . $key);
                }
                echo "[Worker#$i]get " . N . " keys, use: " . round((microtime(true) - $s) * 1000, 2) . "ms\n";
            }
        ))->start();
    }
    for ($i = C; $i--;) {
        swoole_process::wait();
    }
}

function test4()
{
    for ($i = C; $i--;) {
        (new swoole_process(
            function () use ($i) {
                global $table;
                $n = N;
                $s = microtime(true);
                while ($n--) {
                    $key = rand(0, N);
                    $table->set(
                        'key_' . $key,
                        array('id' => $key, 'name' => "php, value=$n\r\n", 'num' => 3.1415 * rand(10000, 99999))
                    );
                }
                echo "[Worker#$i]set " . N . " keys, use: " . round((microtime(true) - $s) * 1000, 2) . "ms\n";
            }
        ))->start();
    }
    for ($i = C; $i--;) {
        swoole_process::wait();
    }
}

function table_random_read()
{
    global $table;

    $s1 = microtime(true);
    $n = N;

    while ($n--) {
        $k = 'key_' . $n;
        $result = $table->set(
            $k,
            array('id' => $n, 'name' => "swoole, value=$n\r\n", 'num' => 3.1415)
        );
        if ($result == false) {
            echo "set key[$k] failed\n";
        }
    }
    $s2 = microtime(true);

    echo "Table::set() time=" . ($s2 - $s1) . "s\n";

    $n = N;
    while ($n--) {
        $i = rand(0, $n);
        $str = $table->get('key_' . $i);
        if ($str == false) {
            echo "key[$i] not exists\n";
        }
        if ($str['id'] != $i) {
            var_dump($i, $str);
        }
        assert($str['id'] == $i);
    }

    $s3 = microtime(true);
    echo "Table::get() [random_key], time=" . ($s3 - $s2) . "s\n";

    $n = N;
    $i = rand(0, N);
    while ($n--) {
        $str = $table->get('key_' . $i);
        if ($str == false) {
            echo "key[$i] not exists\n";
        }
        if ($str['id'] != $i) {
            var_dump($i, $str);
        }
        assert($str['id'] == $i);
    }

    $s4 = microtime(true);
    echo "Table::get() [fixed_key], time=" . ($s4 - $s3) . "s\n";
}

/**
 * @throws Exception
 */
function table_random_key()
{
    global $table;

    $keys = [];
    $s1 = microtime(true);
    $n = N;

    while ($n--) {
        $k = random_bytes(rand(1, 63));
        $result = $table->set(
            $k,
            array('id' => $n, 'name' => $k, 'num' => 3.1415)
        );
        if ($result == false) {
            echo "set key[$k] failed\n";
        }
        $keys[] = $k;
    }
    $s2 = microtime(true);
    echo "Table::set() time=" . ($s2 - $s1) . "s\n";

    $n = N;
    while ($n--) {
        $i = array_rand($keys);
        $k = $keys[$i];
        $str = $table->get($k);
        if ($str == false) {
            echo "key[$i] not exists\n";
        }
        if ($str['name'] != $k) {
            var_dump($i, $str);
        }
        assert($str['name'] == $k);
    }

    $s3 = microtime(true);
    echo "Table::get() [random_key], time=" . ($s3 - $s2) . "s\n";
}


/**
 * @throws Exception
 */
function php_array_random_key()
{
    $keys = [];
    $array = [];
    $s1 = microtime(true);
    $n = N;

    while ($n--) {
        $k = random_bytes(rand(1, 63));
        $array[$k] = array('id' => $n, 'name' => $k, 'num' => 3.1415);
        $keys[] = $k;
    }
    $s2 = microtime(true);
    echo "Array::set() [random_key], time=" . ($s2 - $s1) . "s\n";

    $n = N;
    while ($n--) {
        $i = array_rand($keys);
        $k = $keys[$i];
        $str =  $array[$k];
        if ($str == false) {
            echo "key[$i] not exists\n";
        }
        if ($str['name'] != $k) {
            var_dump($i, $str);
        }
        assert($str['name'] == $k);
    }

    $s3 = microtime(true);
    echo "Array::get() [random_key], time=" . ($s3 - $s2) . "s\n";
}