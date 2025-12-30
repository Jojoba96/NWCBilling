<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'nwc_billing';
    private $user = 'root';
    private $pass = '';
    private $conn;

    public function connect() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->db_name);
        
        if ($this->conn->connect_error) {
            die('Database Connection Error: ' . $this->conn->connect_error);
        }
        
        $this->conn->set_charset("utf8mb4");
        return $this->conn;
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function lastInsertId() {
        return $this->conn->insert_id;
    }

    public function affectedRows() {
        return $this->conn->affected_rows;
    }

    public function close() {
        $this->conn->close();
    }
}
?>
