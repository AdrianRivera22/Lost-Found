<?php
require_once "Database.php"; 

class Notification extends Database { 
    protected $db;

    public function __construct() {
        $this->db = new Database(); 
    }

    public function getUnreadNotifications($user_id) {
        $conn = $this->db->connect(); 
        if (!$conn) {
            error_log("Notification::getUnreadNotifications: Database connection failed.");
            return null;
        }

        try {

            $sql = "SELECT NotificationID, Message, Timestamp 
                    FROM NOTIFICATION 
                    WHERE UserID = :user_id AND IsRead = FALSE 
                    ORDER BY Timestamp DESC"; 
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                error_log("Notification::getUnreadNotifications: Query execution failed for UserID " . $user_id);
                return null;
            }
        } catch (PDOException $e) {
             error_log("Notification::getUnreadNotifications: Database error - " . $e->getMessage());
             return null;
        }
    }

    public function markNotificationsAsRead($user_id, $notification_ids) {
        if (empty($notification_ids)) {
            return true; 
        }
        $conn = $this->db->connect(); //
        if (!$conn) {
            error_log("Notification::markNotificationsAsRead: Database connection failed.");
            return false;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
            
            $sql = "UPDATE NOTIFICATION 
                    SET IsRead = TRUE 
                    WHERE UserID = ? AND NotificationID IN ({$placeholders})";
            
            $stmt = $conn->prepare($sql);
            
            $params = array_merge([$user_id], $notification_ids);
            
            return $stmt->execute($params);

        } catch (PDOException $e) {
             error_log("Notification::markNotificationsAsRead: Query failed - " . $e->getMessage());
             return false;
        }
    }
}
?>