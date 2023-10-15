<?php

namespace Demon;

class Goals
{
    public static $status;

    public static function set($arr)
    {
        foreach ($arr as $item) {
            $file = new \SplFileObject(__DIR__ . '/../temp/goals/' . $item, 'a+b');
            $file->flock(LOCK_EX);
            $file->rewind();
            $data = json_decode($file->fgets(), true) ?: ['n' => 0, 'g' => 0, 's' => time(), 'e' => time()];
            $data['n']++;
            $file->ftruncate(0);
            $file->fwrite(json_encode($data));
            $file->flock(LOCK_UN);
        }
    }

    public static function goal($goal)
    {
        $file = new \SplFileObject(__DIR__ . '/../temp/goals/' . $goal, 'a+b');
        $file->flock(LOCK_EX);
        $file->rewind();
        $data = json_decode($file->fgets(), true) ?: ['n' => 0, 'g' => 0, 's' => time(), 'e' => time()];
        $data['g']++;
        $data['e'] = time();
        $file->ftruncate(0);
        $file->fwrite(json_encode($data));
        $file->flock(LOCK_UN);
    }

    public static function clean()
    {
        $files = glob(__DIR__ . '/../temp/goals/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private static function get()
    {
        $files = glob(__DIR__ . '/../temp/goals/*');
        $data = [];
        foreach ($files as $item) {
            if (is_file($item)) {
                $file = new \SplFileObject($item, 'a+b');
                $file->flock(LOCK_EX);
                $file->rewind();
                $data[] = array_merge(json_decode($file->fgets(), true), ['file' => basename($item)]);
                $file->flock(LOCK_UN);
            }
        }
        return $data;
    }

    public static function status()
    {
        if (self::$status) {
            return self::$status;
        }
        $data = [];
        $speed_total = [];
        $reject_total = [];
        foreach (self::get() as $item) {
            $item['speed'] = $item['g'] ? ($item['e'] - $item['s']) / $item['g'] : 0;
            $item['reject'] = $item['n'] ? 100 - ($item['g'] / $item['n'] * 100) : 100;
            $speed_total[] = $item['speed'];
            $reject_total[] = $item['reject'];
            $data[] = $item;
        }
        $data['speed'] = number_format($speed_total ? array_sum($speed_total) / count($speed_total) : 0, 2) . ' goals/sec';
        $data['reject'] = number_format($reject_total ? array_sum($reject_total) / count($reject_total) : 100, 2) . ' %';
        self::$status = (object)$data;
        return self::$status;
    }
}
