<?php
/**
 * Clase de Conexión a Base de Datos
 * Sistema ICASE
 */

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

            if ($this->connection->connect_error) {
                throw new Exception("Error de conexión: " . $this->connection->connect_error);
            }

            $this->connection->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Error al conectar con la base de datos: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql) {
        $result = $this->connection->query($sql);
        if (!$result) {
            throw new Exception("Error en consulta: " . $this->connection->error);
        }
        return $result;
    }

    public function prepare($sql) {
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error en prepared statement: " . $this->connection->error);
        }
        return $stmt;
    }

    public function escape($value) {
        return $this->connection->real_escape_string($value);
    }

    public function lastInsertId() {
        return $this->connection->insert_id;
    }

    public function __destruct() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
