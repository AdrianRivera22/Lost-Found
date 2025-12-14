<?php
session_start();
require_once "../classes/Student.php"; 
require_once "../classes/MailService.php"; 

$studentObj = new Student(); 

// Fetch Courses for Dropdown
$courses = $studentObj->getCourses(); 
if ($courses === null) { 
    $courses = []; 
    $errors["general"] = "Could not load course list from database."; 
} 

// Initialize Variables
$student = [
    "StudentID" => "", "Last_Name" => "", "First_Name" => "", "Middle_Name" => "",
    "PhoneNo" => "", "Email" => "", "CourseID" => "",
    "Password" => "", "ConfirmPassword" => ""
];

$errors = [
    "StudentID" => "", "Last_Name" => "", "First_Name" => "", "Middle_Name" => "",
    "PhoneNo" => "", "Email" => "", "CourseID" => "", 
    "Password" => "", "ConfirmPassword" => "", 
    "general" => "" 
];

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student["StudentID"] = trim(htmlspecialchars($_POST["StudentID"] ?? ''));
    $student["Last_Name"] = trim(htmlspecialchars($_POST["Last_Name"] ?? ''));
    $student["First_Name"] = trim(htmlspecialchars($_POST["First_Name"] ?? ''));
    $student["Middle_Name"] = trim(htmlspecialchars($_POST["Middle_Name"] ?? '')); 
    $student["PhoneNo"] = trim(htmlspecialchars($_POST["PhoneNo"] ?? ''));
    $student["Email"] = trim(htmlspecialchars($_POST["Email"] ?? ''));
    $student["CourseID"] = trim(htmlspecialchars($_POST["CourseID"] ?? '')); 
    $student["Password"] = trim($_POST["Password"] ?? ''); 
    $student["ConfirmPassword"] = trim($_POST["ConfirmPassword"] ?? ''); 

    // --- Validation Logic ---
    if (empty($student["StudentID"])) { $errors["StudentID"] = "Student ID is required"; } 
    elseif ($studentObj->isStudentExist($student["StudentID"])) { $errors["StudentID"] = "Student ID already registered"; }
    
    if (empty($student["Last_Name"])) { $errors["Last_Name"] = "Last Name is required"; }
    if (empty($student["First_Name"])) { $errors["First_Name"] = "First Name is required"; }
    
    if (empty($student["PhoneNo"])) { $errors["PhoneNo"] = "Contact Number is required"; } 
    elseif (!is_numeric($student["PhoneNo"]) || strlen($student["PhoneNo"]) != 11) { $errors["PhoneNo"] = "Must be 11 digits (e.g., 09123456789)"; }
    
    if (empty($student["Email"])) { $errors["Email"] = "Email is required"; } 
    elseif (!filter_var($student["Email"], FILTER_VALIDATE_EMAIL)) { $errors["Email"] = "Invalid email format."; }
    
    if (empty($student["Password"])) { $errors["Password"] = "Password is required."; } 
    elseif (strlen($student["Password"]) < 8) { $errors["Password"] = "Must be at least 8 characters."; }
    
    if ($student["Password"] !== $student["ConfirmPassword"]) { $errors["ConfirmPassword"] = "Passwords do not match."; }

    if (empty($student["CourseID"])) {
        $errors["CourseID"] = "Please select a course";
    } else {
        $validCourse = false;
        foreach($courses as $course) {
            if ($course['CourseID'] == $student["CourseID"]) {
                $validCourse = true; break;
            }
        }
        if (!$validCourse && !empty($courses)) $errors["CourseID"] = "Invalid course selected.";
    }

    // --- Verification & Email Sending Logic ---
    if (empty(array_filter($errors))) {
        
        $verification_code = rand(100000, 999999);

        // Store registration data in SESSION
        $_SESSION['registration_data'] = $student;
        $_SESSION['verification_code'] = $verification_code;

        $mailService = new MailService();
        $fullName = $student["First_Name"] . " " . $student["Last_Name"];
        $subject = "Verify Your Account - WMSU Lost & Found";

        // --- PROFESSIONAL HTML EMAIL TEMPLATE ---
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;'>
            <div style='background-color: #A40404; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>WMSU Lost & Found</h1>
            </div>

            <div style='border: 1px solid #e0e0e0; border-top: none; padding: 30px; border-radius: 0 0 8px 8px; background-color: #ffffff;'>
                <h2 style='color: #2c3e50; margin-top: 0;'>Verify Your Email Address</h2>
                <p>Hello <strong>$fullName</strong>,</p>
                <p>Thank you for joining the WMSU Lost & Found community! To complete your registration, please use the verification code below:</p>
                
                <div style='background-color: #f8f9fa; border: 1px dashed #A40404; padding: 15px; text-align: center; margin: 25px 0; border-radius: 5px;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #A40404;'>$verification_code</span>
                </div>

                <p>Enter this code on the verification page to activate your account.</p>
                <p style='font-size: 13px; color: #666; margin-top: 30px;'>If you did not create an account, please ignore this email.</p>
            </div>

            <div style='text-align: center; margin-top: 20px; font-size: 12px; color: #888;'>
                <p>&copy; " . date("Y") . " WMSU Lost & Found. All rights reserved.</p>
            </div>
        </div>";

        if ($mailService->sendEmail($student["Email"], $fullName, $subject, $body)) {
            header("Location: verifyCode.php");
            exit();
        } else {
            $errors["general"] = "Failed to send verification email. Please check your email address or try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - WMSU Lost & Found</title>
    <link rel="stylesheet" href="../styles/registerAccount.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="register-card">
        <div class="register-header">
            <h1>Create Account</h1>
            <p>Join the WMSU Lost & Found Community</p>
        </div>

        <?php if (!empty($errors["general"])): ?>
            <div class="alert-error">
                <?= htmlspecialchars($errors["general"]) ?>
            </div>
        <?php endif; ?>

        <form action="" method="post">
            
            <span class="section-title">Student Information</span>
            
            <div class="form-group">
                <label for="StudentID">Student ID <span class="required">*</span></label>
                <input type="text" name="StudentID" id="StudentID" class="form-control" 
                       value="<?= htmlspecialchars($student["StudentID"]) ?>" 
                       placeholder="e.g., 2023-12345">
                <?php if (!empty($errors["StudentID"])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors["StudentID"]) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="First_Name">First Name <span class="required">*</span></label>
                        <input type="text" name="First_Name" id="First_Name" class="form-control" 
                               value="<?= htmlspecialchars($student["First_Name"]) ?>">
                        <?php if (!empty($errors["First_Name"])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors["First_Name"]) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="Last_Name">Last Name <span class="required">*</span></label>
                        <input type="text" name="Last_Name" id="Last_Name" class="form-control" 
                               value="<?= htmlspecialchars($student["Last_Name"]) ?>">
                        <?php if (!empty($errors["Last_Name"])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors["Last_Name"]) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="Middle_Name">Middle Name <span style="font-weight:normal; color:#888;">(Optional)</span></label>
                <input type="text" name="Middle_Name" id="Middle_Name" class="form-control" 
                       value="<?= htmlspecialchars($student["Middle_Name"]) ?>">
            </div>

            <div class="form-group">
                <label for="CourseID">Course / Department <span class="required">*</span></label>
                <select name="CourseID" id="CourseID" class="form-control">
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= htmlspecialchars($course['CourseID']) ?>" 
                                <?= ($student["CourseID"] == $course['CourseID']) ? "selected" : "" ?>>
                            <?= htmlspecialchars($course['CourseName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors["CourseID"])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors["CourseID"]) ?></span>
                <?php endif; ?>
            </div>

            <hr>
            <span class="section-title">Contact & Security</span>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="Email">Email Address <span class="required">*</span></label>
                        <input type="email" name="Email" id="Email" class="form-control" 
                               value="<?= htmlspecialchars($student["Email"]) ?>">
                        <?php if (!empty($errors["Email"])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors["Email"]) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="PhoneNo">Phone Number <span class="required">*</span></label>
                        <input type="text" name="PhoneNo" id="PhoneNo" class="form-control" 
                               value="<?= htmlspecialchars($student["PhoneNo"]) ?>" placeholder="09xxxxxxxxx">
                        <?php if (!empty($errors["PhoneNo"])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors["PhoneNo"]) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="Password">Password <span class="required">*</span></label>
                        <div class="password-container">
                            <input type="password" name="Password" id="Password" class="form-control">
                            <span class="toggle-password" onclick="togglePassword('Password', this)" title="Show Password">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </span>
                        </div>
                        <?php if (!empty($errors["Password"])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors["Password"]) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="ConfirmPassword">Confirm Password <span class="required">*</span></label>
                        <div class="password-container">
                            <input type="password" name="ConfirmPassword" id="ConfirmPassword" class="form-control">
                            <span class="toggle-password" onclick="togglePassword('ConfirmPassword', this)" title="Show Password">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </span>
                        </div>
                        <?php if (!empty($errors["ConfirmPassword"])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors["ConfirmPassword"]) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-register">Next: Verify Email</button>

            <div class="footer-links">
                <p>Already have an account? <a href="loginAccount.php">Log In</a></p>
                <a href="loginAccount.php" class="back-home">← Back to Login</a>
            </div>
        </form>
    </div>

    <script>
    function togglePassword(fieldId, icon) {
        const input = document.getElementById(fieldId);
        const svg = icon.querySelector('svg');
        
        if (input.type === "password") {
            input.type = "text";
            // Switch to "Eye Off" Icon (Slash)
            svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            icon.setAttribute('title', 'Hide Password');
        } else {
            input.type = "password";
            // Switch back to "Eye" Icon
            svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            icon.setAttribute('title', 'Show Password');
        }
    }
    </script>
</body>
</html>