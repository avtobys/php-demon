<?php

namespace Demon;

class Controls
{
    const LOCK_FILE = __DIR__ . '/../temp/demon.lock';

    private static $demon_filename;
    private static $lock;
    public static $proc;
    public static $iteration;
    public static $runned = false;

    public function __construct($argv)
    {
        self::$demon_filename = dirname(__DIR__) . '/demon.php';

        if (isset($argv[1]) && method_exists($this, $argv[1]) && ($methodName = $argv[1]) && in_array($methodName, ['stop', 'start', 'restart', 'kill', 'status', 'help', 'log'])) {
            $this->$methodName();
        }

        if (!getenv('EXEC_CALL')) {
            $this->help();
        }

        if (shell_exec("echo $(nproc) $(cat /proc/loadavg | awk '{print $1}') | awk '{print $2/$1*100}'") > MAX_SYSLOAD_PERCENT) {
            echo "Error: system load too high\n";
            if (intval(shell_exec('pgrep -a php$ | grep -F "' . self::$demon_filename . '" | wc -l 2>/dev/null')) <= 1) {
                sleep(60);
                exec("export EXEC_CALL=1 && php -f " . self::$demon_filename . " >> " . LOG_FILE . " 2>&1 &");
            }
            exit(1);
        }

        $this->proc();
    }

    private function start()
    {
        usleep(500000);
        if (intval(shell_exec('pgrep -a php$ | grep -F "' . self::$demon_filename . '" | wc -l 2>/dev/null')) > 1) {
            echo "Error: demon already running\n";
            exit(1);
        }
        if (file_exists(self::LOCK_FILE)) {
            $attempts = 0;
            do {
                usleep(200000);
                exec('pgrep -a php$ | grep -F "' . self::$demon_filename . '"', $output, $result_code);
                if ($output && ($pids = array_map(function ($item) {
                    return explode(' ', $item)[0];
                }, array_filter($output, function ($item) {
                    return !preg_match('/^[\d]+.+ (stop|start|restart|status|help|log)$/', $item);
                })))) {
                    echo "Error: demon already running\n";
                    exit(1);
                }
                unset($output, $result_code);
            } while ($attempts++ < 100);
            @unlink(self::LOCK_FILE);
        }
        touch(self::LOCK_FILE);
        Goals::clean();
        exec("export EXEC_CALL=1 && php -f " . self::$demon_filename . " > " . LOG_FILE . " 2>&1 &");
        echo "Success: demon started\n";
        exit(0);
    }

    private function restart()
    {
        $this->stop(true);
    }

    private function stop($is_restart = false)
    {
        @unlink(self::LOCK_FILE);
        $attempts = 0;
        $killed = 0;
        do {
            exec('pgrep -a php$ | grep -F "' . self::$demon_filename . '"', $output, $result_code);
            $pids = [];
            if ($output && ($pids = array_map(function ($item) {
                return explode(' ', $item)[0];
            }, array_filter($output, function ($item) {
                return !preg_match('/^[\d]+.+ (stop|start|restart|status|help|log)$/', $item);
            })))) {
                $killed += count($pids);
                exec('kill ' . implode(' ', $pids) . ' > /dev/null 2>&1');
            }
            unset($output, $result_code);
            if ($attempts >= 10 && empty($pids) && $killed) {
                @unlink(self::LOCK_FILE);
                echo "Success: all demon processes($killed) killed\n";
                if ($is_restart) {
                    $this->start();
                }
                exit(0);
            }
            usleep(300000);
        } while ($attempts++ < 100);
        if (!$killed) {
            echo "Error: demon not running. Processes not found...\n";
            if ($is_restart) {
                $this->start();
            }
            exit(1);
        }
        echo "Error: can't kill all demon processes\n";
        exit(1);
    }

    private function status()
    {
        exec('ps aux | grep -F "' . self::$demon_filename . '" | grep -v grep', $output, $result_code);
        $output = array_filter($output, function ($item) {
            return preg_match('/\.php$/', $item);
        });
        echo "\n";
        echo "Started at              : " . Goals::status()->started . "\n";
        echo "Finished at             : " . (count($output) ? 'still running' : Goals::status()->finished) . "\n";
        echo "Speed                   : " . Goals::status()->speed . "\n";
        echo "Reject                  : " . Goals::status()->reject . "\n\n";
        printf("%26s%-18s%-12s%-16s%-16s\n", "", "Speed", "Reject", "Iterations", "Goals");
        foreach (Goals::status() as $key => $item) {
            if (is_numeric($key)) {
                printf("%-5s%-19s: %-18s%-12s%-16s%-11s\n", "", $item['file'], number_format($item['speed'], 2) . ' goals/min', number_format($item['reject'], 2) . ' %', $item['n'], $item['g']);
            }
        }

        echo "\nCurrent demon processes : " . count($output) . "\n";
        echo implode("\n", $output);
        echo "\n";
        exit(0);
    }

    private function help()
    {
        $path = getcwd() == dirname(__DIR__) ? basename(self::$demon_filename) : self::$demon_filename;
        echo "\nUsage: php -f " . $path . " [stop|start|restart|status|help|log]\n\n";
        echo "Available arguments:\n";
        echo "  start    - starts the daemon and its processes\n";
        echo "  restart  - restarts the daemon and its processes\n";
        echo "  stop     - kills all processes of the daemon at once\n";
        echo "  status   - shows the status of the daemon and its processes\n";
        echo "  help     - displays help on how to use the script\n";
        echo "  log      - displays the logs of the daemon and its processes\n\n";
        exit(0);
    }

    private function log()
    {
        echo file_get_contents(LOG_FILE);
        exit(0);
    }

    private function proc()
    {
        if (!file_exists(self::LOCK_FILE)) {
            exit(1);
        }
        $attempts = 0;
        do {
            usleep(200000);
        } while ($attempts++ < 100 && !(self::$proc = intval(shell_exec('pgrep -a php$ | grep -F "' . self::$demon_filename . '" | wc -l 2>/dev/null'))));
        if (self::$proc == 0) exit(1);
        if (self::$proc > MAX_PROCESSES) exit(1);
        self::$lock = new \SplFileObject(self::LOCK_FILE, 'a+b');
        self::$lock->flock(LOCK_EX);
        self::$lock->rewind();
        self::$iteration = (int)self::$lock->fgets();
        self::$iteration++;
        if (MAX_ITERATIONS && self::$iteration > MAX_ITERATIONS) {
            self::$lock->ftruncate(0);
            self::$lock->fwrite(self::$iteration);
            self::$lock->flock(LOCK_UN);
            exit(0);
        }
        Goals::set(GOALS ?: ['demon']);
    }

    public function run()
    {
        if (self::$proc < MIN_PROCESSES) {
            exec("export EXEC_CALL=1 && php -f " . self::$demon_filename . " >> " . LOG_FILE . " 2>&1 &");
        }
        self::$lock->ftruncate(0);
        self::$lock->fwrite(self::$iteration);
        self::$lock->flock(LOCK_UN);
        self::$runned = true;
    }

    public function goal(...$goals)
    {
        $goals = empty($goals) ? ['demon'] : $goals;
        foreach ($goals as $item) {
            Goals::goal($item);
        }
    }

    public function __destruct()
    {
        if (self::$runned) {
            if (!file_exists(self::LOCK_FILE)) {
                exit(1);
            }
            $proc = intval(shell_exec('pgrep -a php$ | grep -F "' . self::$demon_filename . '" | wc -l 2>/dev/null'));
            if ($proc > MAX_PROCESSES) {
                exit(0);
            }
            $file = new \SplFileObject(self::LOCK_FILE, 'a+b');
            $file->flock(LOCK_EX);
            $file->rewind();
            $iteration = (int)$file->fgets();
            if (MAX_ITERATIONS && $iteration > MAX_ITERATIONS) {
                exit(0);
            }
            if ($proc <= MIN_PROCESSES) {
                exec("export EXEC_CALL=1 && php -f " . self::$demon_filename . " >> " . LOG_FILE . " 2>&1 &");
            }
            $file->flock(LOCK_UN);
        }
    }
}
