<?php
class Database{
    private $host ="localhost";
    private $username ="root";
    private $password ="";
    private $dbname= "lost&found";
    private $charset = 'utf8mb4'; 
    private $driver = 'mysql'; 

    protected $conn; 

    public function connect(){
        if ($this->conn) { return $this->conn; }
        $dsn = "{$this->driver}:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            return $this->conn;
        } catch (PDOException $e) {
            error_log("Database Connection Failed: " . $e->getMessage());
            die("Database connection failed. Please check logs.");
        }
    }

    protected function handleFileUpload($file, &$error) {
        if (empty($file) || $file['error'] == UPLOAD_ERR_NO_FILE) { return null; }
        if ($file['error'] !== UPLOAD_ERR_OK) { $error = "File upload error (Code: " . $file['error'] . ")."; return null; }
        $upload_dir = __DIR__ . '/../uploads/'; 
        if (!is_dir($upload_dir)) { if (!mkdir($upload_dir, 0755, true)) { $error = "Failed to create uploads directory."; return null; } }
        if (!is_writable($upload_dir)) { $error = "Uploads directory is not writable."; return null; }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime_type, $allowed_types)) { $error = "Invalid file type (JPG, PNG, GIF, WEBP allowed)."; return null; }
        if ($file['size'] > 5 * 1024 * 1024) { $error = "File too large (Max 5MB)."; return null; }
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $unique_filename = uniqid('item_', true) . '.' . $file_extension;
        $target_path = $upload_dir . $unique_filename;
        if (move_uploaded_file($file['tmp_name'], $target_path)) { return 'uploads/' . $unique_filename; } 
        else { $error = "Failed to move uploaded file. Check permissions."; return null; }
    }

    public function countItemsByStatus($status) {
        $conn = $this->connect();
        if (!$conn) {
            error_log("Database::countItemsByStatus: Database connection failed.");
            return 0;
        }
        try {
            $sql = "SELECT COUNT(ItemID) FROM ITEM WHERE ItemStatus = :status";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Database::countItemsByStatus: Query failed - " . $e->getMessage());
            return 0;
        }
    }

    public function getReturnedItemsReport($date_start = "", $date_end = "") {
        $conn = $this->connect();
        if (!$conn) {
            error_log("Database::getReturnedItemsReport: Database connection failed.");
            return null;
        }

        // UPDATED: JOIN users r_st, JOIN users f_st
        $sql = "SELECT 
                    i.ItemID, i.ItemName, i.Category, i.DateReported, i.ItemStatus,
                    i.ReporterUserID,
                    r_st.First_Name AS ReporterFirstName,
                    r_st.Last_Name AS ReporterLastName,
                    i.FinderUserID,
                    f_st.First_Name AS FinderFirstName,
                    f_st.Last_Name AS FinderLastName
                FROM ITEM i
                JOIN users r_st ON i.ReporterUserID = r_st.UserID
                LEFT JOIN users f_st ON i.FinderUserID = f_st.UserID
                WHERE i.ItemStatus = 'Returned'";
        
        $params = [];

        if (!empty($date_start)) {
            $sql .= " AND i.DateReported >= :date_start";
            $params[":date_start"] = $date_start;
        }
        if (!empty($date_end)) {
            $sql .= " AND i.DateReported < DATE_ADD(:date_end, INTERVAL 1 DAY)";
            $params[":date_end"] = $date_end;
        }

        $sql .= " ORDER BY i.DateReported DESC";

        try {
            $query = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $query->bindValue($key, $value);
            }
            $query->execute();
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database::getReturnedItemsReport: Query failed - " . $e->getMessage());
            return null;
        }
    }

    public function deleteItem($item_id) {
        $conn = $this->connect(); if (!$conn) return false;
        try {
            $conn->beginTransaction();
            $sql_claim_delete = "DELETE FROM CLAIM WHERE FoundItemID = :item_id";
             $stmt_claim = $conn->prepare($sql_claim_delete);
             $stmt_claim->bindParam(':item_id', $item_id, PDO::PARAM_INT);
             $stmt_claim->execute(); 
            $sql_item_delete = "DELETE FROM ITEM WHERE ItemID = :item_id";
            $stmt_item = $conn->prepare($sql_item_delete);
            $stmt_item->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            $deleted = $stmt_item->execute() && ($stmt_item->rowCount() > 0);
            $conn->commit();
            return $deleted;
        } catch (PDOException $e) {
             if ($conn->inTransaction()) { $conn->rollBack(); }
            error_log("Database::deleteItem: Query failed for ItemID {$item_id} - " . $e->getMessage());
             if ($e->getCode() == '23000') { error_log("Potential FK issue: Ensure ON DELETE CASCADE is set."); }
            return false;
        }
    }
}
?>