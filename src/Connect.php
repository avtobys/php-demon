<?php

/**
 * @file
 * @brief подключение к БД
 * 
 */

namespace Demon;

/**
 * @brief Connect создает подключение к базе данных
 * 
 */

final class Connect
{
    private static $instance = null;
    private static $database;

    /**
     * создает новый объект PDO и сохраняет его в свойстве $dbh
     * 
     * @param ignore_error Если установлено значение true, сценарий будет продолжать выполняться даже в
     * случае сбоя подключения к базе данных.
     * 
     * @return object PDO Обработчик базы данных.
     */
    private function __construct($ignore_error = false)
    {
        $dsn = 'mysql:dbname=' . self::$database['dbname'] . ';host=' . self::$database['host'] . ';port=' . self::$database['port'] . ';charset=' . self::$database['charset'];
        try {
            $this->dbh = new \PDO($dsn, self::$database['dbuser'], self::$database['password'], [\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ]);
        } catch (\PDOException $e) {
            if (!$ignore_error) {
                exit($e->getMessage());
            }
        }
    }

    /**
     * Получает экземпляр подключения к базе данных
     *
     * @param bool $ignore_error Если установлено значение true, сценарий будет продолжать выполняться даже в
     * случае сбоя подключения к базе данных.
     * 
     * @return object Connect
     */
    public static function getInstance($database, $ignore_error = false)
    {
        if (self::$instance === null || self::$database != $database) {
            self::$database = $database;
            self::$instance = new self($ignore_error);
        } else {
            try {
                // Попытаемся выполнить произвольный запрос, чтобы проверить подключение
                self::$instance->dbh->query('SELECT 1');
            } catch (\PDOException $e) {
                // Если произошла ошибка, значит соединение утрачено и его нужно восстановить
                self::$instance = new self($ignore_error);
            }
        }

        return self::$instance->dbh;
    }


    /**
     * Запрещает клонирование экземпляра
     */
    public function __clone()
    {
        throw new \RuntimeException('Clone is not allowed');
    }

    /**
     * Запрещает десериализацию экземпляра
     */
    public function __wakeup()
    {
        throw new \RuntimeException('Deserialization is not allowed');
    }
}
