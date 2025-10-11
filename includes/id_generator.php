<?php
/**
 * MANUAL ID GENERATOR
 * 
 * This class provides a robust fallback system for generating unique IDs
 * when AUTO_INCREMENT fails in production environments.
 */

class IdGenerator {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Generate the next available ID for a table
     * Uses transaction locking to prevent race conditions
     * 
     * @param string $table The table name
     * @param string $id_column The ID column name (default: 'id')
     * @return int The next available ID
     */
    public function getNextId($table, $id_column = 'id') {
        // Start transaction for atomic operation
        $this->conn->autocommit(false);
        
        try {
            // Lock the table to prevent race conditions
            $this->conn->query("LOCK TABLES `$table` WRITE");
            
            // Get the maximum ID with explicit filtering for valid IDs
            $stmt = $this->conn->prepare("SELECT COALESCE(MAX(`$id_column`), 0) as max_id FROM `$table` WHERE `$id_column` > 0");
            $stmt->execute();
            $result = $stmt->get_result();
            $max_id = $result->fetch_assoc()['max_id'];
            
            // Calculate next ID
            $next_id = intval($max_id) + 1;
            
            // Verify this ID doesn't exist (extra safety check)
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM `$table` WHERE `$id_column` = ?");
            $stmt->bind_param("i", $next_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->fetch_assoc()['count'] > 0;
            
            // If ID exists, find the next available one
            while ($exists) {
                $next_id++;
                $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM `$table` WHERE `$id_column` = ?");
                $stmt->bind_param("i", $next_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $exists = $result->fetch_assoc()['count'] > 0;
            }
            
            // Unlock tables
            $this->conn->query("UNLOCK TABLES");
            
            // Commit transaction
            $this->conn->commit();
            $this->conn->autocommit(true);
            
            return $next_id;
            
        } catch (Exception $e) {
            // Rollback on error
            $this->conn->query("UNLOCK TABLES");
            $this->conn->rollback();
            $this->conn->autocommit(true);
            throw new Exception("Failed to generate next ID: " . $e->getMessage());
        }
    }
    
    /**
     * Insert a record with manual ID generation
     * Attempts AUTO_INCREMENT first, falls back to manual ID if needed
     * 
     * @param string $table The table name
     * @param array $data Associative array of column => value
     * @param string $id_column The ID column name (default: 'id')
     * @return int The inserted ID
     */
    public function insertWithId($table, $data, $id_column = 'id') {
        // First attempt: Try normal INSERT without specifying ID (AUTO_INCREMENT)
        $columns = array_keys($data);
        $placeholders = str_repeat('?,', count($data) - 1) . '?';
        $values = array_values($data);
        
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";
        $stmt = $this->conn->prepare($sql);
        
        // Bind parameters dynamically
        $types = str_repeat('s', count($values)); // Default to string, adjust if needed
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            $insert_id = $this->conn->insert_id;
            
            // Check if AUTO_INCREMENT worked (ID > 0)
            if ($insert_id > 0) {
                return $insert_id;
            }
        }
        
        // AUTO_INCREMENT failed or returned 0, use manual ID generation
        return $this->insertWithManualId($table, $data, $id_column);
    }
    
    /**
     * Insert a record with manually generated ID
     * 
     * @param string $table The table name
     * @param array $data Associative array of column => value
     * @param string $id_column The ID column name
     * @return int The inserted ID
     */
    private function insertWithManualId($table, $data, $id_column) {
        // Generate next ID
        $next_id = $this->getNextId($table, $id_column);
        
        // Add the ID to the data
        $data[$id_column] = $next_id;
        
        // Prepare INSERT with explicit ID
        $columns = array_keys($data);
        $placeholders = str_repeat('?,', count($data) - 1) . '?';
        $values = array_values($data);
        
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";
        $stmt = $this->conn->prepare($sql);
        
        // Bind parameters dynamically
        $types = str_repeat('s', count($values));
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            return $next_id;
        } else {
            throw new Exception("Failed to insert record with manual ID: " . $stmt->error);
        }
    }
    
    /**
     * Fix existing records with ID 0
     * Assigns proper sequential IDs to records that have ID 0
     * 
     * @param string $table The table name
     * @param string $id_column The ID column name
     * @return int Number of records fixed
     */
    public function fixZeroIds($table, $id_column = 'id') {
        // Get records with ID 0
        $result = $this->conn->query("SELECT * FROM `$table` WHERE `$id_column` = 0 ORDER BY created_at ASC");
        $zero_records = [];
        
        while ($row = $result->fetch_assoc()) {
            $zero_records[] = $row;
        }
        
        if (empty($zero_records)) {
            return 0; // No records to fix
        }
        
        $fixed_count = 0;
        
        foreach ($zero_records as $record) {
            try {
                // Generate next available ID
                $next_id = $this->getNextId($table, $id_column);
                
                // Update the record with the new ID
                $stmt = $this->conn->prepare("UPDATE `$table` SET `$id_column` = ? WHERE `$id_column` = 0 AND created_at = ? LIMIT 1");
                $stmt->bind_param("is", $next_id, $record['created_at']);
                
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $fixed_count++;
                }
                
            } catch (Exception $e) {
                // Log error but continue with other records
                error_log("Failed to fix record ID: " . $e->getMessage());
            }
        }
        
        return $fixed_count;
    }
    
    /**
     * Test the ID generation system
     * 
     * @param string $table The table name
     * @param string $id_column The ID column name
     * @return array Test results
     */
    public function testIdGeneration($table, $id_column = 'id') {
        $results = [];
        
        try {
            // Test 1: Get next ID
            $next_id = $this->getNextId($table, $id_column);
            $results['next_id_test'] = [
                'success' => true,
                'next_id' => $next_id
            ];
            
            // Test 2: Check for ID conflicts
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM `$table` WHERE `$id_column` = ?");
            $stmt->bind_param("i", $next_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $conflict = $result->fetch_assoc()['count'] > 0;
            
            $results['conflict_test'] = [
                'success' => !$conflict,
                'has_conflict' => $conflict
            ];
            
        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
}
?>