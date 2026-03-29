<?php
// config/database.php
class Database {
    private $host = "127.0.0.1";   // ✅ force TCP
    private $db_name = "cog_management_system";
    private $username = "cog_user";
    private $password = "yourpassword";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};port=3306;dbname={$this->db_name}",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            die("Database connection failed. Please try again later.");
        }
        return $this->conn;
    }
}
?>