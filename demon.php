<?php

const MAX_SYSLOAD_PERCENT = 99;
const MIN_PROCESSES = 1;
const MAX_PROCESSES = 1;
const MAX_ITERATIONS = 1000;

const GOALS = ['goal-1', 'goal-test-2']; // объявление любого множества целей для рассчета скорости их обработки и отказов по ним

const LOG_FILE = __DIR__ . '/temp/demon.log'; // для отключения логгирования заменить значение на /dev/null

const DATABASE = [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'YOU_DB_NAME',
    'dbuser' => 'YOU_DB_USER',
    'password' => 'YOU_DB_PASSWORD',
    'charset' => 'utf8mb4'
];


require __DIR__ . '/src/autoload.php';

use \Demon\Connect;
use \Demon\Controls;
use \Demon\Goals;

$demon = new Controls($argv);

// операции которые должны быть гарантированно выполнены только в 1 потоке, до вызова $demon->run() другие потоки ждут освобождения блокировки Controls::$lock 
// $dbh = Connect::getInstance(DATABASE);
// $id = $dbh->query("SELECT id FROM my_table WHERE status = 'new' LIMIT 1")->fetchColumn();
// $dbh->exec("UPDATE my_table SET status = 'processing' WHERE id = $id");
// $dbh = null;

$demon->run(); // распараллеливание и запуск параллельных потоков




usleep(rand(10000, 1000000)); // для тестирования задержки работы demon





$demon->goal('goal-1', 'goal-test-2'); // фиксация достижения определённых целей если они были достигнуты в текущем процессе
echo date('M d H:i:s') . " " . getenv('USER') . " Iteration: " . Controls::$iteration . "; Speed: " . Goals::status()->speed . "; Reject: " . Goals::status()->reject . "; Current processes: " . Controls::$proc . "; Time: " . (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) . "\n"; // пишем в лог
