<?php


const N = 300000;
const C = 4;

use Swoole\Process;
use Swoole\Table;

if (empty($argv[1])) {
    echo "Usage: php {$argv[0]} [test_func]\n";
    exit(0);
} else {
    $test_func = trim($argv[1]);
    $test_func();
}

function create_big_table()
{
    $table = new Table(8 * 1024 * 1024);
    $table->column('id', Table::TYPE_INT, 4);
    $table->column('name', Table::TYPE_STRING, 256);
    $table->column('num', Table::TYPE_FLOAT);
    $table->create();

    return $table;
}

function test1()
{
    $table = create_big_table();

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
    $table = create_big_table();
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
    $table = create_big_table();
    for ($i = C; $i--;) {
        (new swoole_process(
            function () use ($i, $table) {
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
    $table = create_big_table();
    for ($i = C; $i--;) {
        (new swoole_process(
            function () use ($i, $table) {
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
    $table = create_big_table();

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
    $table = create_big_table();

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
        $str = $array[$k];
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
    $table = create_big_table();

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

function shuffle_assoc(&$array)
{
    $keys = array_keys($array);

    shuffle($keys);

    foreach ($keys as $key) {
        $new[$key] = $array[$key];
    }

    $array = $new;

    return true;
}

/**
 * @throws Exception
 */
function table_random_int_key_delete()
{
    $table = create_big_table();

    $keys = [];
    $s1 = microtime(true);
    $n = N;

    /**
     * 插入数据
     */
    echo "SET " . N . " keys\n";
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
    echo "GET " . N . " keys\n";
    shuffle_assoc($keys);
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
    echo "Table::get() [random_int_key], time=" . ($s3 - $s2) . "s\n";

    /**
     * 删除数据
     */
    $n = N / 10;
    echo "DEL " . $n . " keys\n";
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

    echo 'DEL=' . count($del_keys) . ', KEYS=' . count($keys) . ', COUNT=' . $table->count() . "\n";

    $s4 = microtime(true);
    echo "Table::del() [random_int_key], time=" . ($s4 - $s3) . "s\n";
}

function table_delete_and_incr()
{
    $table = new Table(256 * 1024);
    $table->column('request_count', Table::TYPE_INT);
    $table->column('howlong', Table::TYPE_FLOAT);
    $table->create();

    var_dump($table->getMemorySize());
    $KEY_COUNT = 100000;

    // Init Table
    for ($i = 0; $i < $KEY_COUNT; $i++) {
        $key = 'key_' . $i;
        $table->set($key, ['request_count' => rand(1000, 9999), 'howlong' => rand(1000, 9999)]);
    }

    var_dump($table->count());

    $del_keys = [];
    $n = 1000_0000;
    while ($n--) {
        $key = 'key_' . rand(0, $KEY_COUNT);
        // Del Key
        if (rand(0, 99999) % 10 == 1) {
            $table->del($key);
            $del_keys[$key] = 1;
        } else {
            // create or delete
            if (rand(0, 99999) % 5 == 1 and count($del_keys) > 0) {
                $key = array_rand($del_keys);
                unset($del_keys[$key]);
            }
            $table->incr($key, 'request_count');
        }
    }
}

function table_parallel_delete()
{
    $table = new Table(131072);
    $table->column('col0', Swoole\Table::TYPE_INT, 4);
    $table->column('col1', Swoole\Table::TYPE_INT, 4);
    $table->column('col100', Swoole\Table::TYPE_INT, 8);
    $table->column('col2', Swoole\Table::TYPE_STRING, 32);
    $table->column('col3', Swoole\Table::TYPE_STRING, 32);
    $table->column('col4', Swoole\Table::TYPE_STRING, 4);
    $table->column('col5', Swoole\Table::TYPE_STRING, 24);
    $table->column('col6', Swoole\Table::TYPE_STRING, 255);
    $table->column('col7', Swoole\Table::TYPE_INT, 1);
    $table->column('col8', Swoole\Table::TYPE_INT, 4);
    $table->column('col9', Swoole\Table::TYPE_INT, 4);
    $table->column('col10', Swoole\Table::TYPE_INT, 4);
    $table->column('col11', Swoole\Table::TYPE_INT, 4);
    $table->column('col12', Swoole\Table::TYPE_INT, 4);
    $table->column('col13', Swoole\Table::TYPE_INT, 4);
    $table->create();

    echo "测试开始\n";
    echo "模拟插入65535个记录\n";
    $s = microtime(true);
    $NUM = 65536;
    //模拟插入65535个记录
    for ($i = 1; $i <= $NUM; $i++) {
        $data = array(
            'col0' => $i,
            'col1' => $i - 1,
            'col100' => mt_rand(10000000000, 19999999999),
            'col2' => md5("asfsadfasdfsda"),
            'col3' => md5("asfsadfasdfsda"),
            'col4' => 'ew45',
            'col5' => 'wahaha',
            'col6' => 'asdjasdasdasdasdsfdfsdfsdfdsfjlskdfjldkjfasjdhaskjdhakdjfhksjdfhkjsdhfksjdfhjksdfhskfhskjdfhksdjhfkjsdhffsdddsjdhfkjsdhlksdhjlsdkjfh',
            'col7' => 1,
            'col8' => 11231231232,
            'col9' => 15123123123,
            'col10' => 2352342342,
            'col11' => 14232,
            'col12' => 13123123,
            'col13' => 30,
        );
        $table->set("u" . $i, $data);
    }
    echo "插入完成, 用时：" . (microtime(true) - $s) . "\n";
    echo "实际共享内存: " . $table->count() . "个key\n";

    $workerNum = C;
    $NUM = N;

    while ($workerNum--) {
        (new Process(function () use ($table, $workerNum, $NUM) {
            //【请手动调整这个值，从小到大】
            // 从共享内存中删除的key的个数，保证每个worker都删除同样的key，
            // 当这个值日从小增大到5000+ 以后，删除key的结果变得越来越不稳定，大于65000后，结果很诡异
            $num_to_del = rand($NUM / 2, $NUM * 2);
            $s = microtime(true);
            for ($i = 1; $i <= $num_to_del; $i++) {
                $table->del("u" . $i);
            }
            $expectNum = $num_to_del > $NUM ? 0 : $NUM - $num_to_del;
            echo "worker " . $workerNum . " 从共享内存删除" . $num_to_del . "个key后， 期望剩余{$expectNum}实际共享内存还剩"
                . $table->count() . "个key, 用时：" . (microtime(true) - $s) . "\n";
        }))->start();
    }

    $workerNum = C;
    while ($workerNum--) {
        Swoole\Process::wait();
    }
    echo "实际共享内存: " . $table->count() . "个key\n";
    $table->destroy();
}

function random_rw()
{
    ini_set('memory_limit', '1024M');
    $table = create_big_table();
    $n = N;

    $array = [];
    while ($n--) {
        $keys[] = random_bytes(random_int(1, 63));
    }
    echo "gen random keys done\n";

    // 保存 keys 以便于 debug
    file_put_contents('/tmp/table_keys', serialize($keys));

    foreach($keys as $key) {
        $array[$key] = random_bytes(random_int(1, 255));
    }

    echo "gen random values done\n";
    $st = microtime(true);

    $workerNum = C;
    while ($workerNum--) {
        (new Process(function () use ($table, $array, $keys) {
            $n = 100_000_000;
            while ($n--) {
                $seed = random_int(1, 999999999);
                $key = $keys[array_rand($keys)];
                if (!$table->exists($key)) {
                    $retval = $table->set($key, ['name' => $array[$key]]);
                    if ($retval === false) {
                        echo "failed\n";
                    }
                } else {
                    $table->set($key, ['id' => $seed, 'name' => $array[$key]]);
                }
                if ($seed % 40 == 1) {
                    $table->del($key);
                } else {
                    $elem = $table->get($key);
                    if ($elem) {
                        if ($elem['name'] != $array[$key]) {
                            var_dump('diff', bin2hex($elem['name']), bin2hex($array[$key]));
                            var_dump($elem);
                            echo "error\n";
                        }
                    }
                }
            }

        }))->start();
    }

    $workerNum = C;
    while ($workerNum--) {
        Process::wait();
    }

    echo "usage: " . (microtime(true) - $st) . "\n";
}