<?php

spl_autoload_register(function ($className) {
    // Префикс пространства имен
    $namespacePrefix = 'Demon\\';

    // Удаляем префикс пространства имен из имени класса
    $className = str_replace($namespacePrefix, '', $className);

    // Путь к каталогу с классами
    $classPath = __DIR__ . '/../src/' . str_replace('\\', '/', $className) . '.php';

    // Проверяем, существует ли файл
    if (file_exists($classPath)) {
        // Загружаем файл с классом
        require_once $classPath;
    }
});

if (!in_array(PHP_SAPI, array('cli', 'cli-server', 'phpdbg'))) {
    echo "Only CLI mode is supported\n";
    exit(1);
}

if (!file_exists(__DIR__ . '/../temp/goals') && !mkdir(__DIR__ . '/../temp/goals', 0755, true)) {
    echo "Can't create temp directory\n";
    exit(1);
}

/**
 * отладочная функция, которая принимает любое количество аргументов и выводит их дамп на экран, а затем
 * завершает работу всей программы.
 * 
 * @param mixed ...$vars 
 */
function dd(...$vars)
{
    rd(...$vars);
    exit(1);
}


/**
 * выполяет ту же функцию что и rd() но не завершает работу программы
 * Это var_dump() с отображением текущего времени выполнения программы и компактным форматированием pre тегами
 */
function rd(...$vars)
{
    if (empty($vars)) {
        $vars[0] = null;
    }
    foreach ($vars as $v) {
        echo '<pre style="display:block;font-size:12px;color:#dcdcaa;background:#1e1e1e;line-height:16px;padding:10px;font-family:monospace;margin:8px 0;overflow:auto;position:relative;">';
        var_dump($v);
        echo '<pre style="display:inline-block;font-size:9px;color:#000000;background:rgba(255, 255, 255, 0.6);padding:2px;font-family:monospace;margin:0;overflow:auto;position:absolute;top:0;right:0;">exec time: ' . round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']), 5) . '</pre>';
        echo '</pre>';
    }
}


/**
 * отладочная функция, которая принимает любое количество аргументов и записывает их дамп в лог файл.
 * Запись прекращается если файл превысил размер 50 мб.
 * 
 * Вызов функции без аргументов, прекращает работу программы и выводит все записанные дампы на экран
 * 
 * Вызов функции с аргументом string == 'rm', прекращает работу программы, выводит все записанные дампы на экран, и удаляет файл с дампами.
 * 
 * @param mixed ...$vars 
 */
function ld(...$vars)
{
    $filename = __DIR__ . '/../temp/ld';
    if (empty($vars) || $vars[0] == 'rm') {
        header("Content-Type: text/plain; charset=utf-8");
        echo file_get_contents($filename);
        if ($vars[0] == 'rm') {
            unlink($filename);
        }
        exit(0);
    }
    if (file_exists($filename) && filesize($filename) > 52428800) {
        return;
    }
    $str = '';
    foreach ($vars as $v) {
        $str .= "---------- ";
        $str .= (date('r')) . '; exec time: ' . round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']), 5);
        $str .= " ----------\n\n";
        $str .= var_export($v, true) . "\n\n";
    }
    $str .= "\n\n*************************************************************************\n\n\n\n";

    $file = new \SplFileObject($filename, 'a+b');
    $file->flock(LOCK_EX);
    $file->fwrite($str);
    $file->flock(LOCK_UN);
}
