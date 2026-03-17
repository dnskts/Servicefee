<?php
/**
 * Класс для работы с базой данных SQLite.
 *
 * Реализует паттерн «одиночка» (singleton): во всём приложении существует
 * только один экземпляр подключения к БД. Это экономит ресурсы и гарантирует
 * единообразную работу с базой.
 *
 * При первом подключении:
 * - включается режим исключений (ошибки БД выбрасывают исключения);
 * - включается WAL-режим для лучшей производительности;
 * - включается поддержка внешних ключей (PRAGMA foreign_keys).
 *
 * Метод initializeDatabase() создаёт файл БД, если его нет, и выполняет
 * schema.sql и seed.sql для первоначальной настройки.
 */

namespace App;

use PDO;
use PDOException;

class Database
{
    /** @var Database|null Единственный экземпляр класса */
    private static $instance = null;

    /** @var PDO Подключение к базе данных */
    private $connection = null;

    /** @var array Настройки из config/database.php */
    private $config = [];

    /**
     * Закрытый конструктор — создание экземпляра только через getInstance().
     */
    private function __construct()
    {
        $this->config = require __DIR__ . '/../config/database.php';
    }

    /**
     * Запрет клонирования (для сохранения единственности экземпляра).
     */
    private function __clone()
    {
    }

    /**
     * Возвращает единственный экземпляр класса Database.
     *
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Возвращает объект PDO для выполнения запросов.
     * При первом вызове создаёт подключение и настраивает его.
     *
     * @return PDO
     * @throws PDOException при ошибке подключения
     */
    public function getConnection()
    {
        if ($this->connection === null) {
            $dbPath = $this->config['db_path'];

            $dsn = 'sqlite:' . $dbPath;
            $this->connection = new PDO($dsn);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->exec('PRAGMA journal_mode=WAL');
            $this->connection->exec('PRAGMA foreign_keys = ON');
        }
        return $this->connection;
    }

    /**
     * Проверяет, существует ли файл базы данных.
     * Если нет — создаёт его, выполняет schema.sql, затем seed.sql.
     *
     * @return void
     * @throws PDOException при ошибке выполнения SQL
     */
    public function initializeDatabase()
    {
        $dbPath = $this->config['db_path'];
        $schemaPath = $this->config['schema_path'];
        $seedPath = $this->config['seed_path'];

        if (!file_exists($dbPath)) {
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            touch($dbPath);
            $pdo = $this->getConnection();
            if (file_exists($schemaPath)) {
                $schema = file_get_contents($schemaPath);
                $pdo->exec($schema);
            }
            if (file_exists($seedPath)) {
                $seed = file_get_contents($seedPath);
                $pdo->exec($seed);
            }
        }
    }

    /**
     * Выполняет запрос с параметрами и возвращает объект PDOStatement.
     *
     * @param string $sql    SQL-запрос (можно с плейсхолдерами ? или :name)
     * @param array  $params Массив параметров для подстановки
     * @return \PDOStatement
     */
    public function query($sql, array $params = [])
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Выполняет запрос и возвращает все строки результата в виде массива.
     *
     * @param string $sql    SQL-запрос
     * @param array  $params Параметры запроса
     * @return array
     */
    public function fetchAll($sql, array $params = [])
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: [];
    }

    /**
     * Выполняет запрос и возвращает одну строку результата (или null).
     *
     * @param string $sql    SQL-запрос
     * @param array  $params Параметры запроса
     * @return array|null
     */
    public function fetchOne($sql, array $params = [])
    {
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Выполняет запрос без возврата результата (INSERT/UPDATE/DELETE и т.д.).
     *
     * @param string $sql    SQL-запрос
     * @param array  $params Параметры запроса
     * @return int Количество затронутых строк
     */
    public function execute($sql, array $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Возвращает ID последней вставленной строки (после INSERT).
     *
     * @return string
     */
    public function lastInsertId()
    {
        return $this->getConnection()->lastInsertId();
    }
}
