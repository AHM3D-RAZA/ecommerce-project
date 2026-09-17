<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Small wrapper around MySQLi so pages don't repeat
 * prepare/bind/execute boilerplate everywhere.
 */
class Database
{
    private $conn;

    public function __construct()
    {
        $this->conn = db_connect();
    }

    public function getConnection()
    {
        return $this->conn;
    }

    // Works out bind_param type string automatically (i, d, s)
    private function paramTypes($params)
    {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    // Returns an array of rows for SELECT queries
    public function select($sql, $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Query prepare failed: ' . $this->conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($this->paramTypes($params), ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    // Returns a single row, or null if nothing found
    public function selectOne($sql, $params = [])
    {
        $rows = $this->select($sql, $params);
        return $rows[0] ?? null;
    }

    // For INSERT / UPDATE / DELETE. Returns affected rows.
    public function run($sql, $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Query prepare failed: ' . $this->conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($this->paramTypes($params), ...$params);
        }

        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    // Same as run(), but returns the new auto-increment id (for INSERTs)
    public function insert($sql, $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die('Query prepare failed: ' . $this->conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($this->paramTypes($params), ...$params);
        }

        $stmt->execute();
        $id = $this->conn->insert_id;
        $stmt->close();

        return $id;
    }
}
