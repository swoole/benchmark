<?php
$table = new swoole_table(8 * 1024 * 1024);
$table->column('id', swoole_table::TYPE_INT, 4);
$table->column('name', swoole_table::TYPE_STRING, 256);
$table->column('num', swoole_table::TYPE_FLOAT);
$table->create();

define('N', 1000000);
define('C', 4);

if (empty($argv[1])) {
    echo "Usage: php {$argv[0]} [test_func]\n";
    exit(0);
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
function array_random_key()
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

/**
 * @throws Exception
 */
function table_random_int_key()
{
    global $table;

    $keys = [];
    $s1 = microtime(true);
    $n = N;

    while ($n--) {
        $k = rand(1, 1000000000);
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
function table_random_int_key_delete()
{
    global $table;

    $keys = [];
    $s1 = microtime(true);
    $n = N;

    /**
     * 插入数据
     */
    echo "SET ".N." keys\n";
    while ($n--) {
        $k = rand(1, 1000000000);
        $result = $table->set(
            $k,
            array('id' => $n, 'name' => $k, 'num' => 3.1415)
        );
        if ($result == false) {
            echo "set key[$k] failed\n";
            continue;
        }
        $keys[$k] = true;
    }
    $s2 = microtime(true);
    echo "Table::set() [random_int_key], time=" . ($s2 - $s1) . "s\n";

    var_dump(count($keys), $table->count());

    /**
     * 获取数据
     */
    echo "GET ".N." keys\n";
    foreach ($keys as $k => $v) {
        $str = $table->get($k);
        if ($str == false) {
            echo "key[$k] not exists\n";
        }
        if ($str['name'] != $k) {
            var_dump($k, $str);
        }
        assert($str['name'] == $k);
    }

    $s3 = microtime(true);
    echo "Table::set() [random_int_key], time=" . ($s3 - $s2) . "s\n";

    /**
     * 删除数据
     */
    echo "DEL ".N." keys\n";
    $n = N / 10;
    $del_keys = [];
    while ($n--) {
        $k = array_rand($keys);
        if ($table->del($k) == false) {
            echo "[DEL] key[$k] not exists\n";
            var_dump(array_key_exists($k, $keys), $table->exists($k));
        } else {
            unset($keys[$k]);
            $del_keys[] = $k;
        }
    }

    echo 'DEL='.count($del_keys).', KEYS='.count($keys).', COUNT='.$table->count()."\n";

    $s4 = microtime(true);
    echo "Table::del() [random_int_key], time=" . ($s4 - $s3) . "s\n";
}
