<?php

namespace Demon;

class Goals
{
    public static $status;
    public static $goals;

    public function __construct(...$goals)
    {
        if (empty($goals)) {
            $goals = ['demon'];
        }
        self::$goals = $goals;
    }

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

    public static function goal(...$goals)
    {
        foreach ($goals as $goal) {
            if (!in_array($goal, self::$goals)) continue;
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
        $finished = [0];
        $goals_total = 0;
        foreach (self::get() as $item) {
            $item['speed'] = $item['g'] ? 60 / (($item['e'] - $item['s']) / $item['g']) : 0;
            $item['reject'] = $item['n'] ? 100 - ($item['g'] / $item['n'] * 100) : 100;
            $speed_total[] = $item['speed'];
            $reject_total[] = $item['reject'];
            $finished[] = $item['e'];
            $goals_total += $item['g'];
            $data[] = $item;
        }
        $data['reject'] = number_format($reject_total ? array_sum($reject_total) / count($reject_total) : 100, 2) . ' %';
        $data['started'] = date('Y-m-d H:i:s', $data[0]['s']);
        $data['finished'] = date('Y-m-d H:i:s', max($finished));
        $data['speed'] = number_format($goals_total ? 60 / ((max($finished) - $data[0]['s']) / $goals_total) : 0, 2) . ' goals/min';
        self::$status = (object)$data;
        return self::$status;
    }
}
