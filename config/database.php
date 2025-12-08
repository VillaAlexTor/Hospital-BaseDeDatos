<?php
/**
 * config/database.php
 * Clase Database
 * Maneja la conexión y operaciones con la base de datos
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    private $conn;
    private static $instance = null;
    
    /**
     * Constructor privado para patrón Singleton
     */
    private function __construct() {
        $this->host = defined('DB_HOST') ? DB_HOST : 'localhost:3307';
        $this->db_name = defined('DB_NAME') ? DB_NAME : 'hospital_db';
        $this->username = defined('DB_USER') ? DB_USER : 'root';
        $this->password = defined('DB_PASS') ? DB_PASS : '';
        $this->charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
        $this->conn = null;
    }
    
    /**
     * Obtener instancia única de Database (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtener conexión PDO
     */
    public function getConnection() {
        if ($this->conn === null) {
            try {
                $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $this->charset
                ];
                
                $this->conn = new PDO($dsn, $this->username, $this->password, $options);
                
            } catch(PDOException $e) {
                $this->handleError($e);
            }
        }
        
        return $this->conn;
    }
    
    /**
     * Ejecutar consulta SELECT
     */
    public function select($query, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            $this->handleError($e);
            return false;
        }
    }
    
    /**
     * Ejecutar consulta SELECT y obtener un solo registro
     */
    public function selectOne($query, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch(PDOException $e) {
            $this->handleError($e);
            return false;
        }
    }
    
    /**
     * Ejecutar consulta INSERT
     */
    public function insert($query, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                return $this->getConnection()->lastInsertId();
            }
            return false;
        } catch(PDOException $e) {
            $this->handleError($e);
            return false;
        }
    }
    
    /**
     * Ejecutar consulta UPDATE
     */
    public function update($query, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($query);
            $result = $stmt->execute($params);
            return $stmt->rowCount();
        } catch(PDOException $e) {
            $this->handleError($e);
            return false;
        }
    }
    
    /**
     * Ejecutar consulta DELETE
     */
    public function delete($query, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($query);
            $result = $stmt->execute($params);
            return $stmt->rowCount();
        } catch(PDOException $e) {
            $this->handleError($e);
            return false;
        }
    }
    
    /**
     * Ejecutar cualquier consulta
     */
    public function execute($query, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($query);
            return $stmt->execute($params);
        } catch(PDOException $e) {
            $this->handleError($e);
            return false;
        }
    }
    
    /**
     * Contar registros
     */
    public function count($table, $where = '', $params = []) {
        try {
            $query = "SELECT COUNT(*) as total FROM $table";
            if (!empty($where)) {
                $query .= " WHERE $where";
            }
            
            $stmt = $this->getConnection()->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return $result['total'] ?? 0;
        } catch(PDOException $e) {
            $this->handleError($e);
            return 0;
        }
    }
    
    /**
     * Verificar si existe un registro
     */
    public function exists($table, $where, $params = []) {
        return $this->count($table, $where, $params) > 0;
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }
    
    /**
     * Confirmar transacción
     */
    public function commit() {
        return $this->getConnection()->commit();
    }
    
    /**
     * Revertir transacción
     */
    public function rollback() {
        return $this->getConnection()->rollBack();
    }
    
    /**
     * Obtener último ID insertado
     */
    public function lastInsertId() {
        return $this->getConnection()->lastInsertId();
    }
    
    /**
     * Escapar string para SQL
     */
    public function escape($string) {
        return $this->getConnection()->quote($string);
    }
    
    /**
     * Construir cláusula WHERE desde array
     */
    public function buildWhere($conditions) {
        if (empty($conditions)) {
            return ['', []];
        }
        
        $where = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                // Para operadores como IN, BETWEEN, etc.
                $operator = $value[0];
                $val = $value[1];
                
                if ($operator === 'IN') {
                    $placeholders = str_repeat('?,', count($val) - 1) . '?';
                    $where[] = "$field IN ($placeholders)";
                    $params = array_merge($params, $val);
                } elseif ($operator === 'BETWEEN') {
                    $where[] = "$field BETWEEN ? AND ?";
                    $params[] = $val[0];
                    $params[] = $val[1];
                } else {
                    $where[] = "$field $operator ?";
                    $params[] = $val;
                }
            } else {
                $where[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        return [implode(' AND ', $where), $params];
    }
    
    /**
     * Insertar múltiples registros
     */
    public function insertBatch($table, $data) {
        if (empty($data)) {
            return false;
        }
        
        try {
            $this->beginTransaction();
            
            $keys = array_keys($data[0]);
            $fields = implode(', ', $keys);
            $placeholders = '(' . str_repeat('?,', count($keys) - 1) . '?)';
            
            $query = "INSERT INTO $table ($fields) VALUES $placeholders";
            $stmt = $this->getConnection()->prepare($query);
            
            foreach ($data as $row) {
                $values = array_values($row);
                $stmt->execute($values);
            }
            
            $this->commit();
            return true;
        } catch(PDOException $e) {
            $this->rollback();
            $this->handleError($e);
            return false;
        }
    }
    
    /**
     * Actualizar múltiples registros
     */
    public function updateBatch($table, $data, $key) {
        if (empty($data)) {
            return false;
        }
        
        try {
            $this->beginTransaction();
            
            foreach ($data as $row) {
                $keyValue = $row[$key];
                unset($row[$key]);
                
                $sets = [];
                $params = [];
                
                foreach ($row as $field => $value) {
                    $sets[] = "$field = ?";
                    $params[] = $value;
                }
                
                $params[] = $keyValue;
                
                $query = "UPDATE $table SET " . implode(', ', $sets) . " WHERE $key = ?";
                $this->execute($query, $params);
            }
            
            $this->commit();
            return true;
        } catch(PDOException $e) {
            $this->rollback();
            $this->handleError($e);
            return false;
        }
    }
    
    /**
     * Truncar tabla
     */
    public function truncate($table) {
        try {
            return $this->execute("TRUNCATE TABLE $table");
        } catch(PDOException $e) {
            $this->handleError($e);
            return false;
        }
    }
    
    /**
     * Obtener estructura de tabla
     */
    public function getTableStructure($table) {
        return $this->select("DESCRIBE $table");
    }
    
    /**
     * Verificar si tabla existe
     */
    public function tableExists($table) {
        try {
            $query = "SHOW TABLES LIKE ?";
            $result = $this->selectOne($query, [$table]);
            return !empty($result);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Obtener listado de tablas
     */
    public function getTables() {
        return $this->select("SHOW TABLES");
    }
    
    /**
     * Backup de base de datos
     */
    public function backup($filename = null) {
        if ($filename === null) {
            $filename = $this->db_name . '_' . date('Y-m-d_H-i-s') . '.sql';
        }
        
        $backupPath = defined('BACKUP_PATH') ? BACKUP_PATH : BASE_PATH . '/backups';
        
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }
        
        $filepath = $backupPath . '/' . $filename;
        
        // Usar mysqldump si está disponible
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            escapeshellarg($this->host),
            escapeshellarg($this->db_name),
            escapeshellarg($filepath)
        );
        
        exec($command, $output, $return);
        
        if ($return === 0 && file_exists($filepath)) {
            return $filepath;
        }
        
        return false;
    }
    
    /**
     * Manejo de errores
     */
    private function handleError($e) {
        $error_message = "Database Error: " . $e->getMessage();
        
        // Log del error
        error_log($error_message);
        
        // En desarrollo mostrar error, en producción mensaje genérico
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            throw new Exception($error_message);
        } else {
            throw new Exception("Error en la base de datos. Por favor contacte al administrador.");
        }
    }
    
    /**
     * Cerrar conexión
     */
    public function closeConnection() {
        $this->conn = null;
    }
    
    /**
     * Prevenir clonación
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->closeConnection();
    }
}