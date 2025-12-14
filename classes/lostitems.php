<?php
require_once "Database.php"; 
require_once "Student.php";      
require_once "MailService.php"; 

class LostItems extends Database {
    public $UserID;             
    public $StudentID;          
    public $ItemName;           
    public $Description;        
    public $Category;           
    public $DateLost;           
    public $LastKnownLocation;
    public $SecretDetailType;   
    public $SecretDetailValue;  
    
    public $PhotoFile; 
    public $PhotoURL;  

    public $NewItemID; 

    protected $db;

    public function __construct() {
        $this->db = new Database(); 
    }

    public function addLostReport() {
        $conn = $this->db->connect(); 
        if (!$conn) {
            error_log("LostItems::addLostReport: Database connection failed.");
            return false;
        }

        $file_error = "";
        $this->PhotoURL = $this->handleFileUpload($this->PhotoFile, $file_error); 
        
        if ($file_error) {
            throw new Exception("File Upload Error: " . $file_error); 
        }

        try {
            $conn->beginTransaction();
            
            $sql_item = "INSERT INTO ITEM (ItemName, Description, Category, DateReported, ReportLocation, ItemStatus, ReporterUserID)
                         VALUES (:item_name, :description, :category, NOW(), :report_location, 'Reported', :reporter_user_id)";
            
            $stmt_item = $conn->prepare($sql_item);
            
            $stmt_item->bindParam(":item_name", $this->ItemName, PDO::PARAM_STR);
            $stmt_item->bindParam(":description", $this->Description, PDO::PARAM_STR);
            $stmt_item->bindParam(":category", $this->Category, PDO::PARAM_STR);
            $stmt_item->bindParam(":report_location", $this->LastKnownLocation, PDO::PARAM_STR); 
            $stmt_item->bindParam(":reporter_user_id", $this->UserID, PDO::PARAM_INT);
            
            $stmt_item->execute();
            $this->NewItemID = $conn->lastInsertId(); 
            
            if (!$this->NewItemID) { 
                 throw new Exception("Failed to get ItemID after inserting into ITEM table.");
            }

            $sql_lost = "INSERT INTO LOST_ITEM (ItemID, DateLost, PhotoURL)
                         VALUES (:item_id, :date_lost, :photourl)";
            
            $stmt_lost = $conn->prepare($sql_lost);
            $stmt_lost->bindParam(":item_id", $this->NewItemID, PDO::PARAM_INT);
            $stmt_lost->bindParam(":date_lost", $this->DateLost, PDO::PARAM_STR);
            $stmt_lost->bindValue(":photourl", $this->PhotoURL, $this->PhotoURL === null ? PDO::PARAM_NULL : PDO::PARAM_STR); 
            
            $stmt_lost->execute();

            $sql_security = "INSERT INTO SECURITY_DETAIL (ItemID, ProofType, ProofValue)
                             VALUES (:item_id, :proof_type, :proof_value)";
            
            $stmt_security = $conn->prepare($sql_security);
            $stmt_security->bindParam(":item_id", $this->NewItemID, PDO::PARAM_INT);
            $stmt_security->bindParam(":proof_type", $this->SecretDetailType, PDO::PARAM_STR);
            $stmt_security->bindParam(":proof_value", $this->SecretDetailValue, PDO::PARAM_STR);
            
            $stmt_security->execute();

            $conn->commit();
            return true;

        } catch (PDOException $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            error_log("Lost Item Report Submission Failed (PDO): " . $e->getMessage());
            return false;
        } catch (Exception $e) {
             if ($conn->inTransaction()) { $conn->rollBack(); }
             error_log("Lost Item Report Submission Failed (General): " . $e->getMessage()); 
             throw $e; 
        }
    }

    public function updateLostReport($item_id) {
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
                ':report_location' => $this->LastKnownLocation,
                ':item_id' => $item_id,
                ':user_id' => $this->UserID
            ]);

            $sql_lost = "UPDATE LOST_ITEM SET DateLost = :date_lost";
            $params_lost = [':date_lost' => $this->DateLost, ':item_id' => $item_id];

            if ($new_photo) {
                $sql_lost .= ", PhotoURL = :photo_url";
                $params_lost[':photo_url'] = $new_photo;
            }
            $sql_lost .= " WHERE ItemID = :item_id";
            
            $stmt_lost = $conn->prepare($sql_lost);
            $stmt_lost->execute($params_lost);

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
            error_log("Update Lost Report Error: " . $e->getMessage());
            return false;
        }
    }

    public function fetchLostItemDetails($item_id) {
        $conn = $this->db->connect(); 
        if (!$conn) return null; 

        $sql = "SELECT 
                    i.ItemID, i.ItemName, i.Description, i.Category, i.DateReported, i.ReportLocation, i.ItemStatus, i.ReporterUserID, i.FinderUserID,
                    l.DateLost, l.PhotoURL,
                    s.ProofType, s.ProofValue,
                    st.StudentID, st.First_Name AS ReporterFirstName, st.Last_Name AS ReporterLastName, st.Email AS ReporterEmail, st.PhoneNo AS ReporterPhone,
                    f.First_Name AS FinderFirstName, f.Last_Name AS FinderLastName, f.Email AS FinderEmail, f.PhoneNo AS FinderPhone
                FROM ITEM i
                JOIN LOST_ITEM l ON i.ItemID = l.ItemID
                LEFT JOIN SECURITY_DETAIL s ON i.ItemID = s.ItemID
                JOIN users st ON i.ReporterUserID = st.UserID
                LEFT JOIN users f ON i.FinderUserID = f.UserID
                WHERE i.ItemID = :item_id";
        
        try {
            $query = $conn->prepare($sql);
            $query->bindParam(":item_id", $item_id, PDO::PARAM_INT);

            if ($query->execute()) {
                return $query->fetch(PDO::FETCH_ASSOC) ?: false;
            } else {
                return null;
            }
        } catch (PDOException $e) {
             error_log("LostItems::fetchLostItemDetails: " . $e->getMessage());
             return null;
        }
    } 

    public function markAsFoundByFinder($item_id, $finder_user_id, $photo_file) {
        $conn = $this->db->connect();
        if (!$conn) return false;

        $file_error = "";
        $proof_path = $this->handleFileUpload($photo_file, $file_error);

        if ($file_error || !$proof_path) {
            throw new Exception("Proof photo upload failed: " . $file_error);
        }

        try {
            $conn->beginTransaction(); 

            $check_sql = "SELECT i.ReporterUserID, i.ItemStatus, i.ItemName, l.PhotoURL 
                          FROM ITEM i
                          JOIN LOST_ITEM l ON i.ItemID = l.ItemID
                          WHERE i.ItemID = :item_id FOR UPDATE"; 
                          
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            $check_stmt->execute();
            $item_info = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            $item_name = $item_info['ItemName'] ?? 'Unknown Item';
            $item_photo_path = $item_info['PhotoURL'] ?? null;

            if (!$item_info) throw new Exception("Item not found."); 
            if ($item_info['ReporterUserID'] == $finder_user_id) throw new Exception("You cannot mark your own lost item as found."); 
            if ($item_info['ItemStatus'] !== 'Reported') throw new Exception("Item status is not 'Reported'."); 

            // 1. Update ITEM table status
            $sql = "UPDATE ITEM SET ItemStatus = 'Pending Return', FinderUserID = :finder_id WHERE ItemID = :item_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':finder_id', $finder_user_id, PDO::PARAM_INT);
            $stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            
            if ($stmt->execute() && ($stmt->rowCount() > 0)) {
                
                // 2. Update LOST_ITEM table with Proof Photo
                $sql_proof = "UPDATE LOST_ITEM SET FinderProofPhotoURL = :proof_url WHERE ItemID = :item_id";
                $stmt_proof = $conn->prepare($sql_proof);
                $stmt_proof->bindParam(':proof_url', $proof_path);
                $stmt_proof->bindParam(':item_id', $item_id, PDO::PARAM_INT);
                $stmt_proof->execute();

                // 3. Notify Owner
                $notification_message = "Someone has reported finding your lost item: '{$item_name}'. Check your email or wait for Admin contact.";
                $sql_notify = "INSERT INTO NOTIFICATION (UserID, Message, RelatedItemID) VALUES (:user_id, :message, :item_id)";
                $stmt_notify = $conn->prepare($sql_notify);
                $stmt_notify->execute([
                    ':user_id' => $item_info['ReporterUserID'],
                    ':message' => $notification_message,
                    ':item_id' => $item_id
                ]);

                $conn->commit();
                
                // 4. Send Email
                try {
                    $studentObj = new Student();
                    $owner_data = $studentObj->getStudentById($item_info['ReporterUserID']);
                    
                    if ($owner_data && !empty($owner_data['Email'])) {
                        $owner_email = $owner_data['Email'];
                        $owner_name = $owner_data['First_Name'];
    
                        $full_image_path = null;
                        $image_cid = null;
                        if (!empty($item_photo_path)) {
                            $full_image_path = __DIR__ . '/../' . $item_photo_path;
                            $image_cid = 'item_image_found'; 
                        }

                        $subject = "Good news about your lost item: " . htmlspecialchars($item_name);
                        $body = "Hi " . htmlspecialchars($owner_name) . ",<br><br>" .
                                "Someone has reported finding your lost item: '" . htmlspecialchars($item_name) . "' (Item ID: {$item_id}).<br><br>";

                        if ($full_image_path && file_exists($full_image_path)) {
                            $body .= '<p><strong>Your item:</strong><br>' .
                                     '<img src="cid:' . $image_cid . '" alt="' . htmlspecialchars($item_name) . '" style="max-width: 250px; height: auto; border: 1px solid #ddd;"></p>';
                        }

                        $body .= "An administrator will be in touch soon to help facilitate the return.<br><br>" .
                                 "Thank you,<br>Lost & Found System";
    
                        $mailService = new MailService();
                        $mailService->sendEmail($owner_email, $owner_name, $subject, $body, $full_image_path, $image_cid);
                    }
                } catch (Exception $e) {
                     error_log("Error during 'Item Found' email sending: " . $e->getMessage());
                }

                return true;
            } else {
                 throw new Exception("Could not update item status.");
            }

        } catch (PDOException $e) {
             if ($conn->inTransaction()) { $conn->rollBack(); }
            error_log("LostItems::markAsFoundByFinder: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
             if ($conn->inTransaction()) { $conn->rollBack(); }
            throw $e; 
        }
    }

    public function getPendingReturnsForUser($user_id) {
        $conn = $this->db->connect();
        if (!$conn) return [];
        
        $sql1 = "SELECT 
                    i.ItemID, i.ItemName, i.Category, i.ReportLocation, i.ItemStatus, 
                    'Lost' as ReportType,
                    COALESCE(s.First_Name, 'N/A') as ContactFirst, 
                    COALESCE(s.Last_Name, '') as ContactLast, 
                    COALESCE(s.Email, 'N/A') as ContactEmail, 
                    COALESCE(s.PhoneNo, 'N/A') as ContactPhone,
                    l.PhotoURL
                 FROM ITEM i
                 LEFT JOIN users s ON i.FinderUserID = s.UserID
                 JOIN LOST_ITEM l ON i.ItemID = l.ItemID
                 WHERE i.ReporterUserID = :user_id_1 
                 AND i.ItemStatus IN ('Pending Return', 'Accepted', 'Verified')";

        $sql2 = "SELECT 
                    i.ItemID, i.ItemName, i.Category, i.ReportLocation, i.ItemStatus,
                    'Found' as ReportType,
                    COALESCE(s.First_Name, 'N/A') as ContactFirst, 
                    COALESCE(s.Last_Name, '') as ContactLast, 
                    COALESCE(s.Email, 'N/A') as ContactEmail, 
                    COALESCE(s.PhoneNo, 'N/A') as ContactPhone,
                    fi.PhotoURL
                 FROM CLAIM c
                 JOIN ITEM i ON c.FoundItemID = i.ItemID
                 JOIN FOUND_ITEM fi ON i.ItemID = fi.ItemID
                 LEFT JOIN users s ON i.ReporterUserID = s.UserID
                 WHERE c.ClaimantUserID = :user_id_2 
                 AND c.VerificationStatus IN ('Verified', 'Accepted') 
                 AND i.ItemStatus != 'Returned'";
        
        $sql = "($sql1) UNION ($sql2)";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id_1', $user_id); 
            $stmt->bindParam(':user_id_2', $user_id); 
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getPendingReturnsForUser Error: " . $e->getMessage());
            return [];
        }
    }

    public function getOutgoingReturnsForUser($user_id) {
        $conn = $this->db->connect();
        if (!$conn) return [];

        $sql1 = "SELECT 
                    i.ItemID, i.ItemName, i.Category, i.ReportLocation, i.ItemStatus, 
                    'Lost' as ReportType,
                    owner.First_Name as ContactFirst, owner.Last_Name as ContactLast, owner.Email as ContactEmail, owner.PhoneNo as ContactPhone,
                    l.PhotoURL
                 FROM ITEM i
                 JOIN users owner ON i.ReporterUserID = owner.UserID
                 JOIN LOST_ITEM l ON i.ItemID = l.ItemID
                 WHERE i.FinderUserID = :user_id_1 
                 AND i.ItemStatus != 'Returned' 
                 AND i.ItemStatus != 'Reported'";

        $sql2 = "SELECT 
                    i.ItemID, i.ItemName, i.Category, i.ReportLocation, i.ItemStatus,
                    'Found' as ReportType,
                    claimant.First_Name as ContactFirst, claimant.Last_Name as ContactLast, claimant.Email as ContactEmail, claimant.PhoneNo as ContactPhone,
                    fi.PhotoURL
                 FROM ITEM i
                 JOIN FOUND_ITEM fi ON i.ItemID = fi.ItemID
                 JOIN CLAIM c ON i.ItemID = c.FoundItemID
                 JOIN users claimant ON c.ClaimantUserID = claimant.UserID
                 WHERE i.ReporterUserID = :user_id_2 
                 AND c.VerificationStatus IN ('Verified', 'Accepted') 
                 AND i.ItemStatus != 'Returned'";
        
        $sql = "($sql1) UNION ($sql2)";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id_1', $user_id); 
            $stmt->bindParam(':user_id_2', $user_id); 
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getOutgoingReturnsForUser Error: " . $e->getMessage());
            return [];
        }
    }

    public function markItemAsReturned($item_id, $user_id) {
        $conn = $this->db->connect();
        if (!$conn) return false;

        try {
            $check_sql = "SELECT i.ItemID, i.ItemName, i.ReporterUserID 
                          FROM ITEM i
                          LEFT JOIN CLAIM c ON i.ItemID = c.FoundItemID
                          WHERE i.ItemID = :item_id 
                          AND i.ItemStatus != 'Returned' 
                          AND i.ItemStatus IN ('Accepted', 'Verified') 
                          AND (i.ReporterUserID = :user_id OR c.ClaimantUserID = :user_id)";
            
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([':item_id' => $item_id, ':user_id' => $user_id]);
            
            if ($check_stmt->rowCount() === 0) {
                return false; 
            }
            
            $item_data = $check_stmt->fetch(PDO::FETCH_ASSOC);

            return $this->updateItemStatus($item_id, 'Returned');

        } catch (PDOException $e) {
            error_log("markItemAsReturned Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function ownerCancelReturn($item_id, $user_id) {
        $conn = $this->db->connect();
        if (!$conn) return false;

        try {
            $conn->beginTransaction();

            $sql_check_lost = "SELECT ItemID FROM ITEM 
                               WHERE ItemID = :item_id 
                               AND ReporterUserID = :user_id 
                               AND ItemStatus IN ('Pending Return', 'Accepted', 'Verified')";
            $stmt_check_lost = $conn->prepare($sql_check_lost);
            $stmt_check_lost->execute([':item_id' => $item_id, ':user_id' => $user_id]);

            if ($stmt_check_lost->rowCount() > 0) {
                 $sql_upd = "UPDATE ITEM SET ItemStatus = 'Reported', FinderUserID = NULL WHERE ItemID = :item_id";
                 $conn->prepare($sql_upd)->execute([':item_id' => $item_id]);

                 $sql_upd_lost = "UPDATE LOST_ITEM SET FinderProofPhotoURL = NULL WHERE ItemID = :item_id";
                 $conn->prepare($sql_upd_lost)->execute([':item_id' => $item_id]);

                 $conn->commit();
                 return true;
            }

            $sql_check_claim = "SELECT c.ClaimID FROM CLAIM c
                                JOIN ITEM i ON c.FoundItemID = i.ItemID
                                WHERE c.FoundItemID = :item_id 
                                AND c.ClaimantUserID = :user_id
                                AND c.VerificationStatus IN ('Verified', 'Accepted')
                                AND i.ItemStatus != 'Returned'";
            $stmt_check_claim = $conn->prepare($sql_check_claim);
            $stmt_check_claim->execute([':item_id' => $item_id, ':user_id' => $user_id]);
            $claim = $stmt_check_claim->fetch(PDO::FETCH_ASSOC);

            if ($claim) {
                $sql_upd_claim = "UPDATE CLAIM SET VerificationStatus = 'Cancelled' WHERE ClaimID = :claim_id";
                $conn->prepare($sql_upd_claim)->execute([':claim_id' => $claim['ClaimID']]);

                $sql_upd_item = "UPDATE ITEM SET ItemStatus = 'Reported' WHERE ItemID = :item_id";
                $conn->prepare($sql_upd_item)->execute([':item_id' => $item_id]);

                $conn->commit();
                return true;
            }
            
            return false;

        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            return false;
        }
    }

    public function viewPendingReturnItems() {
        $conn = $this->db->connect(); 
        if (!$conn) return null;

        $sql = "SELECT 
                    i.ItemID, i.ItemName, i.Description, i.Category, i.DateReported, i.ReportLocation, i.ItemStatus,
                    l.DateLost, l.PhotoURL, l.FinderProofPhotoURL, 
                    s.ProofType, s.ProofValue,
                    owner.StudentID AS OwnerStudentID, owner.First_Name AS OwnerFirstName, owner.Last_Name AS OwnerLastName, owner.Email AS OwnerEmail, owner.PhoneNo AS OwnerPhone,
                    finder.StudentID AS FinderStudentID, finder.First_Name AS FinderFirstName, finder.Last_Name AS FinderLastName, finder.Email AS FinderEmail, finder.PhoneNo AS FinderPhone
                FROM ITEM i
                JOIN LOST_ITEM l ON i.ItemID = l.ItemID
                LEFT JOIN SECURITY_DETAIL s ON i.ItemID = s.ItemID
                JOIN users owner ON i.ReporterUserID = owner.UserID 
                LEFT JOIN users finder ON i.FinderUserID = finder.UserID
                WHERE i.ItemStatus = 'Pending Return'
                ORDER BY i.DateReported DESC"; 
        
        try {
            $query = $conn->prepare($sql);
            if ($query->execute()) return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return null; }
    }

    public function updateItemStatus($item_id, $new_status) {
        $conn = $this->db->connect();
        if (!$conn) return false;
        try {
            $info = null;
            if ($new_status == 'Returned' || $new_status == 'Accepted') {
                $sql_info = "SELECT i.ReporterUserID, i.ItemName, l.PhotoURL 
                             FROM ITEM i
                             LEFT JOIN LOST_ITEM l ON i.ItemID = l.ItemID 
                             WHERE i.ItemID = :item_id";
                $stmt_info = $conn->prepare($sql_info);
                $stmt_info->bindParam(':item_id', $item_id);
                $stmt_info->execute();
                $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
            }

            $sql = "UPDATE ITEM SET ItemStatus = :new_status WHERE ItemID = :item_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':new_status', $new_status);
            $stmt->bindParam(':item_id', $item_id);
            $result = $stmt->execute();
            
            if ($result && isset($info) && $info['ReporterUserID']) {
                $this->sendStatusEmail($info['ReporterUserID'], $info['ItemName'], $info['PhotoURL'], $new_status, $item_id);
            }
            
            return $result;
        } catch (PDOException $e) { return false; }
    }

    private function sendStatusEmail($user_id, $item_name, $photo_path, $status_type, $item_id) {
        try {
            $studentObj = new Student();
            $owner_data = $studentObj->getStudentById($user_id);

            if ($owner_data && !empty($owner_data['Email'])) {
                $mailService = new MailService();
                $full_image_path = (!empty($photo_path)) ? __DIR__ . '/../' . $photo_path : null;
                $image_cid = 'item_image_' . strtolower($status_type);

                if ($status_type == 'Accepted') {
                    $msg = "Update: The Admin has ACCEPTED the return request for '{$item_name}'. Please retrieve your item.";
                    $subject = "Return Request ACCEPTED: " . htmlspecialchars($item_name);
                    $body = "Hi " . htmlspecialchars($owner_data['First_Name']) . ",<br><br>The administrator has <strong>ACCEPTED</strong> the return request for your lost item: <strong>" . htmlspecialchars($item_name) . "</strong>.<br>This confirms that the found item matches your report.<br><br><strong>Next Steps:</strong><br>1. Please proceed to the Admin Office/Lost & Found Center to retrieve your item.<br>2. Once you have it, please mark the item as <strong>RECEIVED</strong> in your dashboard.<br><br>";
                } elseif ($status_type == 'Returned') {
                    $msg = "Success! Your lost item '{$item_name}' has been marked as RETURNED.";
                    $subject = "Item Returned: " . htmlspecialchars($item_name);
                    $body = "Hi " . htmlspecialchars($owner_data['First_Name']) . ",<br><br>This confirms that your lost item <strong>" . htmlspecialchars($item_name) . "</strong> has been successfully <strong>RETURNED</strong> to you.<br><br>We are glad we could help!<br><br>";
                }

                $conn = $this->db->connect();
                $sql_notif = "INSERT INTO NOTIFICATION (UserID, Message, RelatedItemID) VALUES (:uid, :msg, :iid)";
                $st_notif = $conn->prepare($sql_notif);
                $st_notif->execute([':uid'=>$user_id, ':msg'=>$msg, ':iid'=>$item_id]);

                if ($full_image_path && file_exists($full_image_path)) {
                    $body .= '<p><strong>Item:</strong><br><img src="cid:' . $image_cid . '" alt="Item" style="max-width: 250px; height: auto; border: 1px solid #ddd;"></p>';
                }
                $body .= "Thank you,<br>Lost & Found System";
                $mailService->sendEmail($owner_data['Email'], $owner_data['First_Name'], $subject, $body, $full_image_path, $image_cid);
            }
        } catch (Exception $e) { error_log("Error sending notification: " . $e->getMessage()); }
    }

    public function viewAllLostReports($search = "", $category_filter = "", $status_filter = "") {
        $conn = $this->db->connect(); 
        if (!$conn) return null;
        
        $sql = "SELECT 
                    i.ItemID, i.ItemName, i.Description, i.Category, i.DateReported, 
                    i.ReportLocation, i.ItemStatus, i.ReporterUserID,
                    l.DateLost, l.PhotoURL,
                    st.StudentID AS ReporterStudentID, st.First_Name AS ReporterFirstName, st.Last_Name AS ReporterLastName
                FROM ITEM i
                JOIN LOST_ITEM l ON i.ItemID = l.ItemID
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

    public function viewMyLostReports($user_id) {
        $conn = $this->db->connect(); 
        if (!$conn) return null;
        $sql = "SELECT i.ItemID, i.ItemName, i.Description, i.Category, i.DateReported, i.ReportLocation, i.ItemStatus, l.DateLost, l.PhotoURL
                FROM ITEM i JOIN LOST_ITEM l ON i.ItemID = l.ItemID
                WHERE i.ReporterUserID = :user_id ORDER BY i.DateReported DESC"; 
        try {
            $query = $conn->prepare($sql);
            $query->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            if ($query->execute()) return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return null; }
    } 

    public function viewActiveLostReports($search = "", $category_filter = "") { 
        $conn = $this->db->connect(); 
        if (!$conn) return null;
        
        $sql = "SELECT 
                    i.ItemID, i.ItemName, i.Description, i.Category, i.DateReported, i.ReportLocation, 
                    i.ReporterUserID, i.ItemStatus, 
                    l.DateLost, l.PhotoURL,
                    st.StudentID, st.First_Name, st.Last_Name 
                FROM ITEM i
                JOIN LOST_ITEM l ON i.ItemID = l.ItemID
                JOIN users st ON i.ReporterUserID = st.UserID
                WHERE i.ItemStatus = 'Reported'"; 
        
        $params = [];
        if (!empty($search)) { $sql .= " AND (i.ItemName LIKE CONCAT('%', :search, '%') OR i.Description LIKE CONCAT('%', :search, '%'))"; $params[":search"] = $search; }
        if (!empty($category_filter)) { $sql .= " AND i.Category = :category"; $params[":category"] = $category_filter; }
        $sql .= " ORDER BY l.DateLost DESC";

        try {
            $query = $conn->prepare($sql);
            foreach ($params as $key => $value) { $query->bindValue($key, $value); }
            if ($query->execute()) return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return null; }
    } 

    protected function handleFileUpload($file, &$error) {
        if (empty($file) || $file['error'] == UPLOAD_ERR_NO_FILE) return null;
        if ($file['error'] !== UPLOAD_ERR_OK) { $error = "Upload error"; return null; }
        $upload_dir = __DIR__ . '/../uploads/'; 
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $unique_filename = uniqid('item_', true) . '.' . $file_extension;
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $unique_filename)) return 'uploads/' . $unique_filename;
        return null;
    } 
    
    public function countLostItemsByStatus($status) {
         $conn = $this->db->connect();
         $sql = "SELECT COUNT(*) FROM ITEM WHERE ItemStatus = :status AND ItemID IN (SELECT ItemID FROM LOST_ITEM)";
         $stmt = $conn->prepare($sql);
         $stmt->bindParam(':status', $status);
         $stmt->execute();
         return $stmt->fetchColumn();
    }
    
    public function getMostCommonLostCategory() {
        $conn = $this->db->connect();
        $sql = "SELECT Category, COUNT(*) as ItemCount FROM ITEM WHERE ItemID IN (SELECT ItemID FROM LOST_ITEM) GROUP BY Category ORDER BY ItemCount DESC LIMIT 1";
        $stmt = $conn->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getLostItemReport($filters = []) {
        $conn = $this->db->connect();
        if (!$conn) return null;
        
        $sql = "SELECT 
                    i.ItemID, i.ItemName, i.Category, i.DateReported, 
                    i.ReportLocation, i.ItemStatus,
                    l.DateLost,
                    st.StudentID AS ReporterStudentID, st.First_Name AS ReporterFirstName, st.Last_Name AS ReporterLastName
                FROM ITEM i
                JOIN LOST_ITEM l ON i.ItemID = l.ItemID
                JOIN users st ON i.ReporterUserID = st.UserID
                WHERE 1=1";
        
        $params = [];
        if (!empty($filters['search'])) { $sql .= " AND (i.ItemName LIKE CONCAT('%', :search, '%') OR i.Description LIKE CONCAT('%', :search, '%'))"; $params[":search"] = $filters['search']; }
        if (!empty($filters['category'])) { $sql .= " AND i.Category = :category"; $params[":category"] = $filters['category']; }
        if (!empty($filters['status'])) { $sql .= " AND i.ItemStatus = :status"; $params[":status"] = $filters['status']; }
        if (!empty($filters['date_start'])) { $sql .= " AND i.DateReported >= :date_start"; $params[":date_start"] = $filters['date_start']; }
        if (!empty($filters['date_end'])) { $sql .= " AND i.DateReported < DATE_ADD(:date_end, INTERVAL 1 DAY)"; $params[":date_end"] = $filters['date_end']; }
        $sql .= " ORDER BY i.DateReported DESC";

        try {
            $query = $conn->prepare($sql);
            foreach ($params as $key => $value) { $query->bindValue($key, $value); }
            $query->execute();
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return null; }
    }
    
    public function countPendingReturns() {
         return $this->countLostItemsByStatus('Pending Return');
    }

    // --- FINDER ACTIONS ---
    public function finderConfirmReturn($item_id, $finder_id) {
        $conn = $this->db->connect();
        if (!$conn) return false;
        try {
            $check_sql = "SELECT ItemID FROM ITEM WHERE ItemID = :item_id AND FinderUserID = :finder_id AND ItemStatus = 'Accepted'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([':item_id' => $item_id, ':finder_id' => $finder_id]);
            if ($check_stmt->rowCount() === 0) return false;
            return $this->updateItemStatus($item_id, 'Returned');
        } catch (PDOException $e) { return false; }
    }

    public function finderCancelReturn($item_id, $finder_id, $reason) {
        $conn = $this->db->connect();
        if (!$conn) return false;
        try {
            $conn->beginTransaction();
            $check_sql = "SELECT ItemID, ReporterUserID, ItemName FROM ITEM WHERE ItemID = :item_id AND FinderUserID = :finder_id AND ItemStatus IN ('Accepted', 'Pending Return', 'Verified')";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([':item_id' => $item_id, ':finder_id' => $finder_id]);
            $item = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) return false;

            $sql = "UPDATE ITEM SET ItemStatus = 'Reported', FinderUserID = NULL WHERE ItemID = :item_id";
            $conn->prepare($sql)->execute([':item_id' => $item_id]);

            $sql_lost = "UPDATE LOST_ITEM SET FinderProofPhotoURL = NULL WHERE ItemID = :item_id";
            $conn->prepare($sql_lost)->execute([':item_id' => $item_id]);

            $msg = "Update: The return request for '{$item['ItemName']}' was cancelled by the finder. Reason: {$reason}. The item is listed as 'Reported' again.";
            $conn->prepare("INSERT INTO NOTIFICATION (UserID, Message, RelatedItemID) VALUES (:uid, :msg, :iid)")->execute([':uid'=>$item['ReporterUserID'], ':msg'=>$msg, ':iid'=>$item_id]);

            $conn->commit();
            return true;
        } catch (PDOException $e) { if ($conn->inTransaction()) $conn->rollBack(); return false; }
    }
} 
?>