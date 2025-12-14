<?php
require_once "Database.php"; 
require_once "MailService.php"; 

class FoundItems extends Database {
    public $UserID;           
    public $ItemName;         
    public $Description;      
    public $Category;         
    public $DateFound;        
    public $LocationFound;             
    public $SecretDetailType; 
    public $SecretDetailValue;
    
    public $PhotoFile; 
    public $Photo;     

    public $NewItemID;        

    protected $db; 

    public function __construct() {
        $this->db = new Database(); 
    }

    public function addFoundReport() {
        $conn = $this->db->connect(); 
        if (!$conn) return false;
        
        $file_error = "";
        $this->Photo = $this->handleFileUpload($this->PhotoFile, $file_error); 
        
        if ($file_error) throw new Exception($file_error); 

        try {
            $conn->beginTransaction();

            $sql_item = "INSERT INTO ITEM (ItemName, Description, Category, DateReported, ReportLocation, ItemStatus, ReporterUserID)
                         VALUES (:item_name, :description, :category, NOW(), :report_location, 'Reported', :reporter_user_id)";
            
            $stmt_item = $conn->prepare($sql_item);
            $stmt_item->bindParam(":item_name", $this->ItemName);
            $stmt_item->bindParam(":description", $this->Description);
            $stmt_item->bindParam(":category", $this->Category);
            $stmt_item->bindParam(":report_location", $this->LocationFound); 
            $stmt_item->bindParam(":reporter_user_id", $this->UserID);
            
            $stmt_item->execute();
            $this->NewItemID = $conn->lastInsertId(); 

            $sql_found = "INSERT INTO FOUND_ITEM (ItemID, DateFound, PhotoURL) 
                          VALUES (:item_id, :date_found, :photo_url)";
            
            $stmt_found = $conn->prepare($sql_found); 
            $stmt_found->bindParam(":item_id", $this->NewItemID);
            $stmt_found->bindParam(":date_found", $this->DateFound);
            $stmt_found->bindParam(":photo_url", $this->Photo); 

            $stmt_found->execute(); 

            $sql_security = "INSERT INTO SECURITY_DETAIL (ItemID, ProofType, ProofValue)
                             VALUES (:item_id, :proof_type, :proof_value)";
            $stmt_security = $conn->prepare($sql_security);
            $stmt_security->bindParam(":item_id", $this->NewItemID);
            $stmt_security->bindParam(":proof_type", $this->SecretDetailType);
            $stmt_security->bindParam(":proof_value", $this->SecretDetailValue);
            $stmt_security->execute();

            $conn->commit();
            return true;

        } catch (PDOException $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            error_log("Found Item Report Error: " . $e->getMessage());
            return false;
        } catch (Exception $e) { return false; }
    }

    public function updateFoundReport($item_id) {
        $conn = $this->db->connect();
        if (!$conn) return false;

        $file_error = "";
        $new_photo = $this->handleFileUpload($this->PhotoFile, $file_error);
        if ($file_error) throw new Exception($file_error);

        try {
            $conn->beginTransaction();

            $sql_item = "UPDATE ITEM SET 
                            ItemName = :item_name, 
                            Description = :description, 
                            Category = :category, 
                            ReportLocation = :report_location 
                         WHERE ItemID = :item_id AND ReporterUserID = :user_id";
            $stmt_item = $conn->prepare($sql_item);
            $stmt_item->execute([
                ':item_name' => $this->ItemName,
                ':description' => $this->Description,
                ':category' => $this->Category,
                ':report_location' => $this->LocationFound,
                ':item_id' => $item_id,
                ':user_id' => $this->UserID
            ]);

            $sql_found = "UPDATE FOUND_ITEM SET DateFound = :date_found";
            $params_found = [':date_found' => $this->DateFound, ':item_id' => $item_id];

            if ($new_photo) {
                $sql_found .= ", PhotoURL = :photo_url";
                $params_found[':photo_url'] = $new_photo;
            }
            $sql_found .= " WHERE ItemID = :item_id";

            $stmt_found = $conn->prepare($sql_found);
            $stmt_found->execute($params_found);

            $sql_sec = "UPDATE SECURITY_DETAIL SET 
                            ProofType = :proof_type, 
                            ProofValue = :proof_value 
                        WHERE ItemID = :item_id";
            $stmt_sec = $conn->prepare($sql_sec);
            $stmt_sec->execute([
                ':proof_type' => $this->SecretDetailType,
                ':proof_value' => $this->SecretDetailValue,
                ':item_id' => $item_id
            ]);

            $conn->commit();
            return true;

        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log("Update Found Report Error: " . $e->getMessage());
            return false;
        }
    }

    public function fetchFoundItemDetails($item_id) {
        $conn = $this->db->connect();
        if (!$conn) return null;

        $sql = "SELECT
                    i.ItemID, i.ItemName, i.Description, i.Category, i.DateReported, i.ReportLocation, i.ItemStatus,
                    i.ReporterUserID, 
                    f.DateFound, f.PhotoURL AS Photo, 
                    s.ProofType,
                    st.StudentID AS ReporterStudentID, st.First_Name AS ReporterFirstName, st.Last_Name AS ReporterLastName, st.Email AS ReporterEmail, st.PhoneNo AS ReporterPhone,
                    c.ClaimID, c.ClaimantUserID, c.VerificationStatus AS ClaimStatus,
                    cl.StudentID AS ClaimantStudentID, cl.First_Name AS ClaimantFirstName, cl.Last_Name AS ClaimantLastName, cl.Email AS ClaimantEmail, cl.PhoneNo AS ClaimantPhone
                FROM ITEM i
                JOIN FOUND_ITEM f ON i.ItemID = f.ItemID
                LEFT JOIN SECURITY_DETAIL s ON i.ItemID = s.ItemID
                JOIN users st ON i.ReporterUserID = st.UserID
                LEFT JOIN CLAIM c ON i.ItemID = c.FoundItemID AND c.VerificationStatus IN ('Verified', 'Accepted')
                LEFT JOIN users cl ON c.ClaimantUserID = cl.UserID
                WHERE i.ItemID = :item_id"; 

        try {
            $query = $conn->prepare($sql);
            $query->bindParam(":item_id", $item_id, PDO::PARAM_INT);
            if ($query->execute()) return $query->fetch(PDO::FETCH_ASSOC) ?: false;
        } catch (PDOException $e) { return null; }
        return null;
    }

    // --- ACTIONS FOR FOUND ITEMS ---

    // 1. Claimant confirms they received the item
    public function markItemAsReturned($item_id, $user_id) {
        $conn = $this->db->connect();
        if (!$conn) return false;

        try {
            // UPDATED: Added 'Claimed' to the IN clause to match Admin dashboard status
            $check_sql = "SELECT i.ItemID 
                          FROM ITEM i
                          LEFT JOIN CLAIM c ON i.ItemID = c.FoundItemID
                          WHERE i.ItemID = :item_id 
                          AND i.ItemStatus IN ('Accepted', 'Verified', 'Claimed')
                          AND (c.ClaimantUserID = :user_id OR i.ReporterUserID = :user_id)";
            
            $stmt = $conn->prepare($check_sql);
            $stmt->execute([':item_id' => $item_id, ':user_id' => $user_id]);
            
            if ($stmt->rowCount() === 0) return false;

            // Update status
            $sql_upd = "UPDATE ITEM SET ItemStatus = 'Returned' WHERE ItemID = :item_id";
            $conn->prepare($sql_upd)->execute([':item_id' => $item_id]);
            return true;

        } catch (PDOException $e) { return false; }
    }

    // 2. Claimant cancels their claim
    public function claimantCancelClaim($item_id, $user_id) {
        $conn = $this->db->connect();
        if (!$conn) return false;

        try {
            $conn->beginTransaction();

            $sql_get = "SELECT c.ClaimID, i.ReporterUserID, i.ItemName FROM CLAIM c
                        JOIN ITEM i ON c.FoundItemID = i.ItemID
                        WHERE c.FoundItemID = :item_id AND c.ClaimantUserID = :user_id AND c.VerificationStatus IN ('Verified', 'Accepted')";
            $stmt = $conn->prepare($sql_get);
            $stmt->execute([':item_id' => $item_id, ':user_id' => $user_id]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$info) return false;

            // Cancel Claim
            $conn->prepare("UPDATE CLAIM SET VerificationStatus = 'Cancelled' WHERE ClaimID = :cid")
                 ->execute([':cid' => $info['ClaimID']]);

            // Reset Item
            $conn->prepare("UPDATE ITEM SET ItemStatus = 'Reported' WHERE ItemID = :iid")
                 ->execute([':iid' => $item_id]);

            // Notify Finder (Reporter)
            $msg = "Update: The claimant for '{$info['ItemName']}' has cancelled their request. The item is marked as 'Reported' again.";
            $conn->prepare("INSERT INTO NOTIFICATION (UserID, Message, RelatedItemID) VALUES (:uid, :msg, :iid)")
                 ->execute([':uid' => $info['ReporterUserID'], ':msg' => $msg, ':iid' => $item_id]);

            $conn->commit();
            return true;
        } catch (Exception $e) { if($conn->inTransaction()) $conn->rollBack(); return false; }
    }

    // 3. Finder (Reporter) confirms return
    public function finderConfirmReturn($item_id, $finder_id) {
        return $this->markItemAsReturned($item_id, $finder_id);
    }

    // 4. Finder (Reporter) cancels transaction (e.g., claimant didn't show up)
    public function finderCancelTransaction($item_id, $finder_id, $reason) {
        $conn = $this->db->connect();
        if (!$conn) return false;

        try {
            $conn->beginTransaction();

            $sql_get = "SELECT c.ClaimID, c.ClaimantUserID, i.ItemName FROM ITEM i
                        JOIN CLAIM c ON i.ItemID = c.FoundItemID
                        WHERE i.ItemID = :item_id AND i.ReporterUserID = :finder_id AND i.ItemStatus IN ('Accepted', 'Verified', 'Claimed')";
            $stmt = $conn->prepare($sql_get);
            $stmt->execute([':item_id' => $item_id, ':finder_id' => $finder_id]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$info) return false;

            // Cancel Claim
            $conn->prepare("UPDATE CLAIM SET VerificationStatus = 'Cancelled' WHERE ClaimID = :cid")
                 ->execute([':cid' => $info['ClaimID']]);

            // Reset Item
            $conn->prepare("UPDATE ITEM SET ItemStatus = 'Reported' WHERE ItemID = :iid")
                 ->execute([':iid' => $item_id]);

            // Notify Claimant
            $msg = "Update: The finder has cancelled the return transaction for '{$info['ItemName']}'. Reason: {$reason}.";
            $conn->prepare("INSERT INTO NOTIFICATION (UserID, Message, RelatedItemID) VALUES (:uid, :msg, :iid)")
                 ->execute([':uid' => $info['ClaimantUserID'], ':msg' => $msg, ':iid' => $item_id]);

            $conn->commit();
            return true;
        } catch (Exception $e) { if($conn->inTransaction()) $conn->rollBack(); return false; }
    }

    public function viewAllFoundReports($search = "", $category_filter = "", $status_filter = "") {
        $conn = $this->db->connect(); 
        if (!$conn) return null;

        $sql = "SELECT 
                    i.ItemID, i.ItemName, i.Description, i.Category, i.DateReported, 
                    i.ReportLocation, i.ItemStatus,
                    f.DateFound, f.PhotoURL AS Photo,
                    st.StudentID AS ReporterStudentID, st.First_Name AS ReporterFirstName, st.Last_Name AS ReporterLastName
                FROM ITEM i
                JOIN FOUND_ITEM f ON i.ItemID = f.ItemID
                JOIN users st ON i.ReporterUserID = st.UserID
                WHERE 1=1";
        
        $params = [];
        if (!empty($search)) { $sql .= " AND (i.ItemName LIKE CONCAT('%', :search, '%') OR i.Description LIKE CONCAT('%', :search, '%'))"; $params[":search"] = $search; }
        if (!empty($category_filter)) { $sql .= " AND i.Category = :category"; $params[":category"] = $category_filter; }
        if (!empty($status_filter)) { $sql .= " AND i.ItemStatus = :status"; $params[":status"] = $status_filter; }

        $sql .= " ORDER BY i.DateReported DESC";

        try {
            $query = $conn->prepare($sql);
            foreach ($params as $key => $value) { $query->bindValue($key, $value); }
            if ($query->execute()) return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return null; }
    }

    public function viewMyFoundReports($user_id) {
        $conn = $this->db->connect(); 
        if (!$conn) return null;

        $sql = "SELECT 
                    i.ItemID, i.ItemName, i.Description, i.Category, i.DateReported, i.ReportLocation, i.ItemStatus,
                    f.DateFound, f.PhotoURL AS Photo
                FROM ITEM i
                JOIN FOUND_ITEM f ON i.ItemID = f.ItemID
                WHERE i.ReporterUserID = :user_id
                ORDER BY i.DateReported DESC"; 
        
        try {
            $query = $conn->prepare($sql);
            $query->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            if ($query->execute()) return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return null; }
    }

    public function viewActiveFoundReports($search = "", $category_filter = "") { 
        $conn = $this->db->connect(); 
        if (!$conn) return null;

        $sql = "SELECT 
                    i.ItemID, i.ItemName, i.Description, i.Category, i.DateReported, i.ReportLocation,
                    f.DateFound, f.PhotoURL AS Photo, 
                    s.ProofType, s.ProofValue,
                    st.StudentID, st.First_Name, st.Last_Name
                FROM ITEM i
                JOIN FOUND_ITEM f ON i.ItemID = f.ItemID
                LEFT JOIN SECURITY_DETAIL s ON i.ItemID = s.ItemID
                JOIN users st ON i.ReporterUserID = st.UserID
                WHERE i.ItemStatus = 'Reported'";
        
        $params = [];
        if (!empty($search)) { $sql .= " AND (i.ItemName LIKE CONCAT('%', :search, '%') OR i.Description LIKE CONCAT('%', :search, '%'))"; $params[":search"] = $search; }
        if (!empty($category_filter)) { $sql .= " AND i.Category = :category"; $params[":category"] = $category_filter; }

        $sql .= " ORDER BY f.DateFound DESC";

        try {
            $query = $conn->prepare($sql);
            foreach ($params as $key => $value) { $query->bindValue($key, $value); }
            if ($query->execute()) return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return null; }
    }
    
    protected function handleFileUpload($file, &$error) {
        if (empty($file) || $file['error'] == UPLOAD_ERR_NO_FILE) return null;
        if ($file['error'] !== UPLOAD_ERR_OK) { $error = "Upload Error"; return null; }
        $upload_dir = __DIR__ . '/../uploads/'; 
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid('item_', true) . '.' . $file_extension;
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $unique_filename)) return 'uploads/' . $unique_filename;
        return null;
    }
    
    public function countFoundItemsByStatus($status) {
         $conn = $this->db->connect();
         $sql = "SELECT COUNT(*) FROM ITEM WHERE ItemStatus = :status AND ItemID IN (SELECT ItemID FROM FOUND_ITEM)";
         $stmt = $conn->prepare($sql);
         $stmt->bindParam(':status', $status);
         $stmt->execute();
         return $stmt->fetchColumn();
    }
}
?>