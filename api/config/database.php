<?php
// api/config/database.php
class Database {
    private static $instance = null;
    private $connection = null;
    
    private function __construct() {
        try {
            $db_path = __DIR__ . '/../../database/eventdesign.sqlite';
            $db_dir = dirname($db_path);
            
            if (!file_exists($db_dir)) {
                mkdir($db_dir, 0777, true);
            }
            
            $this->connection = new PDO("sqlite:" . $db_path);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->exec("PRAGMA foreign_keys = ON");
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed");
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
}
?>