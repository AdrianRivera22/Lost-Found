<?php
require_once "Database.php"; 
require_once "Student.php";
require_once "MailService.php";

class Claim extends Database {
    public $ClaimantUserID; 
    public $FoundItemID; 
    public $SecurityQuestionAnswers; 
    public $NewClaimID; 
    protected $db;

    public function __construct() { $this->db = new Database(); }

    public function addClaim() {
        $conn = $this->db->connect();
        if (!$conn) return false;
        try {
            $sql = "INSERT INTO CLAIM (ClaimantUserID, FoundItemID, ClaimDate, SecurityQuestionAnswers, VerificationStatus) VALUES (:claimant_id, :item_id, NOW(), :answers, 'Pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":claimant_id", $this->ClaimantUserID);
            $stmt->bindParam(":item_id", $this->FoundItemID);
            $stmt->bindParam(":answers", $this->SecurityQuestionAnswers); 
            $result = $stmt->execute();
            if ($result) { $this->NewClaimID = $conn->lastInsertId(); return true; }
            return false;
        } catch (Exception $e) { return false; }
    }
    
    public function viewPendingClaims() {
        $conn = $this->db->connect();
        if (!$conn) return null;

        $sql = "SELECT 
                    c.ClaimID, c.ClaimDate, c.SecurityQuestionAnswers, c.VerificationStatus,
                    i.ItemID, i.ItemName, i.Description AS ItemDescription, i.Category AS ItemCategory,
                    f.PhotoURL AS FoundItemPhoto, f.DateFound, 
                    i.ReportLocation,
                    claimant.StudentID AS ClaimantStudentID, claimant.First_Name AS ClaimantFirstName, claimant.Last_Name AS ClaimantLastName, claimant.Email AS ClaimantEmail, claimant.PhoneNo AS ClaimantPhone,
                    reporter.StudentID AS ReporterStudentID, reporter.First_Name AS ReporterFirstName, reporter.Last_Name AS ReporterLastName, reporter.Email AS ReporterEmail, reporter.PhoneNo AS ReporterPhone,
                    sd.ProofType AS OriginalProofType, sd.ProofValue AS OriginalProofValue
                FROM CLAIM c
                JOIN FOUND_ITEM f ON c.FoundItemID = f.ItemID
                JOIN ITEM i ON f.ItemID = i.ItemID
                JOIN users claimant ON c.ClaimantUserID = claimant.UserID
                JOIN users reporter ON i.ReporterUserID = reporter.UserID
                LEFT JOIN SECURITY_DETAIL sd ON i.ItemID = sd.ItemID 
                WHERE c.VerificationStatus = 'Pending'
                ORDER BY c.ClaimDate ASC";
        
        try {
            $query = $conn->prepare($sql);
            if ($query->execute()) return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return null; }
    }

    public function countPendingClaims() {
        $conn = $this->db->connect();
        $sql = "SELECT COUNT(*) FROM CLAIM WHERE VerificationStatus = 'Pending'";
        $stmt = $conn->query($sql);
        return $stmt->fetchColumn();
    }
 
    public function updateClaimStatus($claim_id, $new_status, $item_id = null, $new_item__status = null) {
        $conn = $this->db->connect();
        if (!$conn) return false;
        
        try {
            $conn->beginTransaction();

            $sql_info = "SELECT c.ClaimantUserID, i.ItemName, i.ReporterUserID, f.PhotoURL AS Photo
                         FROM CLAIM c 
                         JOIN ITEM i ON c.FoundItemID = i.ItemID
                         JOIN FOUND_ITEM f ON i.ItemID = f.ItemID 
                         WHERE c.ClaimID = :claim_id";
            
            $stmt_info = $conn->prepare($sql_info);
            $stmt_info->bindParam(':claim_id', $claim_id);
            $stmt_info->execute();
            $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
            
            $claimant_user_id = $info['ClaimantUserID'] ?? null;
            $reporter_user_id = $info['ReporterUserID'] ?? null;
            $item_name = $info['ItemName'] ?? null;
            $item_photo_path = $info['Photo'] ?? null;

            $sql_claim = "UPDATE CLAIM SET VerificationStatus = :new_status WHERE ClaimID = :claim_id";
            $stmt_claim = $conn->prepare($sql_claim);
            $stmt_claim->bindParam(':new_status', $new_status);
            $stmt_claim->bindParam(':claim_id', $claim_id);
            $stmt_claim->execute();

            if ($item_id && $new_item__status) {
                 $sql_item = "UPDATE ITEM SET ItemStatus = :new_item_status WHERE ItemID = :item_id";
                 $stmt_item = $conn->prepare($sql_item);
                 $stmt_item->bindParam(':new_item_status', $new_item__status);
                 $stmt_item->bindParam(':item_id', $item_id);
                 $stmt_item->execute();
            }

            // 1. Notify the Claimant
            if ($claimant_user_id && $item_name) {
                $notification_message = "Your claim for item '{$item_name}' (Item ID: {$item_id}) has been {$new_status}.";
                $sql_notify = "INSERT INTO NOTIFICATION (UserID, Message, RelatedClaimID, RelatedItemID) VALUES (:user_id, :message, :claim_id, :item_id)";
                $stmt_notify = $conn->prepare($sql_notify);
                $stmt_notify->execute([':user_id' => $claimant_user_id, ':message' => $notification_message, ':claim_id' => $claim_id, ':item_id' => $item_id]);
                
                try {
                    $studentObj = new Student();
                    $student_data = $studentObj->getStudentById($claimant_user_id);
                    
                    if ($student_data && !empty($student_data['Email'])) {
                        $student_email = $student_data['Email'];
                        $student_name = $student_data['First_Name'];
    
                        $full_image_path = null; $image_cid = null;
                        if (!empty($item_photo_path)) {
                            $full_image_path = __DIR__ . '/../' . $item_photo_path;
                            $image_cid = 'item_image_claim'; 
                        }

                        $subject = "Update on your claim for: " . htmlspecialchars($item_name);
                        $body = "Hi " . htmlspecialchars($student_name) . ",<br><br>" .
                                "Your claim (ID: {$claim_id}) for the item '" . htmlspecialchars($item_name) . "' has been <strong>{$new_status}</strong>.<br><br>";
                        
                        if ($full_image_path && file_exists($full_image_path)) {
                            $body .= '<p><strong>Item in question:</strong><br><img src="cid:' . $image_cid . '" alt="' . htmlspecialchars($item_name) . '" style="max-width: 250px; height: auto; border: 1px solid #ddd;"></p>';
                        }
                        
                        $body .= "You can review this update by logging into your account.<br><br>Thank you,<br>Lost & Found System";
    
                        $mailService = new MailService();
                        $mailService->sendEmail($student_email, $student_name, $subject, $body, $full_image_path, $image_cid);
                    }
                } catch (Exception $e) {}
            }

            // 2. Notify the Reporter
            if ($reporter_user_id && $item_name && ($new_status == 'Verified' || $new_status == 'Accepted')) {
                $notification_message = "The item '{$item_name}' you reported as found has been successfully claimed and verified by the Admin.";
                $sql_notify_rep = "INSERT INTO NOTIFICATION (UserID, Message, RelatedClaimID, RelatedItemID) VALUES (:user_id, :message, :claim_id, :item_id)";
                $stmt_notify_rep = $conn->prepare($sql_notify_rep);
                $stmt_notify_rep->execute([':user_id' => $reporter_user_id, ':message' => $notification_message, ':claim_id' => $claim_id, ':item_id' => $item_id]);
            }

            $conn->commit();
            return true;

        } catch (Exception $e) {
             if ($conn->inTransaction()) { $conn->rollBack(); }
            return false;
        }
    }
}
?>