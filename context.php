<?php

ini_set('display_errors', 0);

class Context
{
    private $mysql_host;
    private $mysql_user;
    private $mysql_pass;
    private $proxy_host;
    private $row_count;

    public function __construct(
        string $mysql_host,
        string $mysql_user,
        string $mysql_pass,
        string $proxy_host,
        int $row_count
    ) {
        $this->mysql_host = $mysql_host;
        $this->mysql_user = $mysql_user;
        $this->mysql_pass = $mysql_pass;
        $this->proxy_host = $proxy_host;
        $this->row_count = $row_count;
    }

    public function runTest(
        $buffered,
        int $limit,
        \Closure $test
    ) {
        self::setupTest(
            $this->createPDO($this->mysql_host),
            $this->row_count
        );

        self::configureProxy(
            $this->proxy_host,
            $limit
        );

        try {
            $test($this->createPDO(
                $this->proxy_host,
                'data',
                $buffered
            ));
        } finally {
            self::configureProxy($this->proxy_host);
        }
    }

    private static function configureProxy(
        string $host,
        int $limit = -1
    ) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://{$host}:8080/api/proxy");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "limit={$limit}");
        if (curl_exec($ch) === false) {
            throw new Exception("unable to configure proxy");
        }
    }

    private static function setupTest(
        PDO $pdo,
        int $n
    ) {
        $pdo->exec('CREATE DATABASE IF NOT EXISTS data');
        $pdo->exec('use data');
        $pdo->exec('CREATE TABLE IF NOT EXISTS data (id bigint, data text)');

        $stmt = $pdo->query(
            'SELECT COUNT(*) FROM data',
            PDO::FETCH_ASSOC
        );
        try {
            if ($stmt->fetchColumn(0) == $n) {
                return;
            }
        } finally {
            $stmt->closeCursor();
        }

        try {
            $pdo->exec('DELETE FROM data');
            $stmt = $pdo->prepare('INSERT INTO data (id, data) VALUES (?, ?)');
            for ($i = 0; $i < $n; $i++) {
                $stmt->execute([$i, str_repeat('x', 1024)]);
            }
        } finally {
            $stmt->closeCursor();
        }
    }

    private function createPDO(
        string $host,
        string $dbname = "",
        $buffered = true
    ): PDO {
        $pdo = new PDO(
            "mysql:dbname={$dbname};host={$host}",
            $this->mysql_user,
            $this->mysql_pass
        );
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 2);
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, boolval($buffered));
        return $pdo;
    }
}