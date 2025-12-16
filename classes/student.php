<?php
require_once "Database.php"; 

class Student extends Database {
    public $UserID; 
    public $StudentID; 
    public $Last_Name;
    public $First_Name;
    public $Middle_Name;
    public $PhoneNo;
    public $Email;
    public $CourseID; 
    public $Password; 
    
    protected $db;

    public function __construct() {
        $this->db = new Database(); 
    }

    public function getStudentById($user_id) {
        $conn = $this->db->connect(); 
        if (!$conn) {
            error_log("Student::getStudentById: Database connection failed.");
            return false;
        }

        try {
           
            $sql = "SELECT s.UserID, s.StudentID, s.First_Name, s.Last_Name, s.Middle_Name, 
                           s.Email, s.PhoneNo, s.Role, c.CourseName 
                    FROM users s
                    LEFT JOIN COURSE c ON s.CourseID = c.CourseID
                    WHERE s.UserID = :user_id 
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC); 

        } catch (PDOException $e) {
            error_log("Student::getStudentById: Database query failed for UserID {$user_id} - " . $e->getMessage());
            return false; 
        }
    }

    public function updateContactNumber($user_id, $new_phone) {
        $conn = $this->db->connect();
        if (!$conn) return false;

        try {
            
            $sql = "UPDATE users SET PhoneNo = :phone WHERE UserID = :user_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":phone", $new_phone, PDO::PARAM_STR);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Student::updateContactNumber error: " . $e->getMessage());
            return false;
        }
    }
 
    public function getCourses() { 
        $conn = $this->db->connect();
        if (!$conn) {
            error_log("Student::getCourses: Database connection failed.");
            return null;
        }
        try {
            $sql = "SELECT CourseID, CourseName FROM COURSE ORDER BY CourseName ASC"; 
            $query = $conn->prepare($sql);
            if ($query->execute()) {
                return $query->fetchAll(PDO::FETCH_ASSOC); 
            }
            error_log("Student::getCourses: Query execution failed.");
            return null;
        } catch (PDOException $e) {
            error_log("Student::getCourses: Database error - " . $e->getMessage());
            return null;
        }
    }

    public function addStudent() {
        $conn = $this->db->connect();
        if (!$conn) {
             error_log("Student::addStudent: Database connection failed.");
             return false;
        }
        try {
            $hashed_password = password_hash($this->Password, PASSWORD_DEFAULT);
        
            $sql = "INSERT INTO users (StudentID, Last_Name, First_Name, Middle_Name, PhoneNo, Email, CourseID, Password)
                    VALUES (:student_id, :last_name, :first_name, :middle_name, :phone, :email, :course_id, :password)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":student_id", $this->StudentID);
            $stmt->bindParam(":last_name", $this->Last_Name);
            $stmt->bindParam(":first_name", $this->First_Name);
            $stmt->bindParam(":middle_name", $this->Middle_Name);
            $stmt->bindParam(":phone", $this->PhoneNo);
            $stmt->bindParam(":email", $this->Email);
            $stmt->bindParam(":course_id", $this->CourseID, PDO::PARAM_INT); 
            $stmt->bindParam(":password", $hashed_password); 
            
            $result = $stmt->execute();
            if ($result) {
                $this->UserID = $conn->lastInsertId(); 
                return true;
            }
             error_log("Student::addStudent: Execute failed.");
            return false;
        } catch (PDOException $e) {
            error_log("Student Registration Failed: " . $e->getMessage());
            return false;
        }
    }

     public function isStudentExist($studentId) {
        $conn = $this->db->connect();
        if (!$conn) {
            error_log("Student::isStudentExist: Database connection failed.");
            return false;
        }

      
        $sql = "SELECT COUNT(*) FROM users WHERE StudentID = :student_id"; 
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":student_id", $studentId, PDO::PARAM_STR);
        
        try {
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
             error_log("Student::isStudentExist: Query failed - " . $e->getMessage());
             return false; 
        }
    }

    public function viewAccount($search="", $course_filter_id= ""){ 
        $conn = $this->db->connect();
        if (!$conn) return null;
        
       
        $sql = "SELECT s.UserID, s.StudentID, s.Last_Name, s.First_Name, s.Middle_Name, 
                       s.PhoneNo, s.Email, s.Role, cr.CourseName 
                FROM users s
                LEFT JOIN COURSE cr ON s.CourseID = cr.CourseID 
                WHERE 1=1";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (s.Last_Name LIKE CONCAT('%', :search, '%') OR s.First_Name LIKE CONCAT('%', :search, '%'))";
            $params[":search"] = $search;
        }

        if (!empty($course_filter_id)) {
            $sql .= " AND s.CourseID = :course_id";
            $params[":course_id"] = $course_filter_id;
        }
        
        $sql .= " ORDER BY s.Last_Name ASC";

        $query = $conn->prepare($sql);
        
        foreach ($params as $key => $value) {
             $query->bindValue($key, $value);
        }

        if ($query->execute()) {
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } else {
             error_log("Student::viewAccount: Query execution failed.");
            return null;
        }
    }
    
    public function login() {
        $conn = $this->db->connect(); 
        if (!$conn) {
            error_log("Student::login: Database connection failed.");
            return false;
        }

        try {
          
            $sql = "SELECT UserID, StudentID, Password, Role
                    FROM users
                    WHERE Email = :email
                    LIMIT 1"; 
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":email", $this->Email, PDO::PARAM_STR);
            $stmt->execute();

            $student_data = $stmt->fetch(PDO::FETCH_ASSOC);


            if ($student_data && password_verify($this->Password, $student_data['Password'])) {
                $this->UserID = $student_data['UserID'];
                $this->StudentID = $student_data['StudentID'];
                $this->Role = $student_data['Role'];
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            error_log("Student::login: Database query failed - " . $e->getMessage());
            return false;
        }
    }

    public function isEmailRegistered($email) {
        $conn = $this->db->connect();

        $sql = "SELECT COUNT(*) FROM users WHERE Email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    public function updatePassword($email, $new_password) {
        $conn = $this->db->connect();
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        

        $sql = "UPDATE users SET Password = :password WHERE Email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":email", $email);
        
        return $stmt->execute();
    }

    public function verifyCurrentPassword($user_id, $password) {
        $conn = $this->db->connect();
   
        $sql = "SELECT Password FROM users WHERE UserID = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $stored_hash = $stmt->fetchColumn();
        
        if ($stored_hash && password_verify($password, $stored_hash)) {
            return true;
        }
        return false;
    }

    public function changePassword($user_id, $new_password) {
        $conn = $this->db->connect();
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
 
        $sql = "UPDATE users SET Password = :password WHERE UserID = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":password", $hashed);
        $stmt->bindParam(":user_id", $user_id);
        
        return $stmt->execute();
    }
}
?>