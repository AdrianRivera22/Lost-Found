<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: loginAccount.php");
    exit();
}

require_once "../classes/Student.php";
require_once "../classes/MailService.php"; 

$studentObj = new Student();
$mailService = new MailService();

$user_id = $_SESSION['user_id'];
$student_data = $studentObj->getStudentById($user_id); 

$message = "";
$message_type = "";
$showVerifyModal = false; 


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    

    if (isset($_POST['update_phone'])) {
        $new_phone = trim(htmlspecialchars($_POST['phone']));
        
        if (empty($new_phone)) {
            $message = "Phone number cannot be empty.";
            $message_type = "error";
        } else {
            if ($studentObj->updateContactNumber($user_id, $new_phone)) {
                $message = "Contact number updated successfully!";
                $message_type = "success";
                
                $student_data = $studentObj->getStudentById($user_id);
            } else {
                $message = "Failed to update contact number.";
                $message_type = "error";
            }
        }
    }


    if (isset($_POST['request_password_change'])) {
        $current_pass = $_POST['current_password'];
        $new_pass     = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];


        if (!$studentObj->verifyCurrentPassword($user_id, $current_pass)) {
            $message = "Current password is incorrect.";
            $message_type = "error";
        } 

        elseif (strlen($new_pass) < 8) {
            $message = "New password must be at least 8 characters.";
            $message_type = "error";
        }
        elseif ($new_pass !== $confirm_pass) {
            $message = "New passwords do not match.";
            $message_type = "error";
        } 
 
        else {
            $otp = rand(100000, 999999);
            
            $_SESSION['temp_new_password'] = $new_pass;
            $_SESSION['profile_otp'] = $otp;
            $_SESSION['profile_otp_expiry'] = time() + 300; 
            
            
            $fullName = $student_data['First_Name'] . " " . $student_data['Last_Name'];
            $subject = "Verify Password Change - WMSU Lost & Found";

            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;'>
                <div style='background-color: #A40404; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>WMSU Lost & Found</h1>
                </div>

                <div style='border: 1px solid #e0e0e0; border-top: none; padding: 30px; border-radius: 0 0 8px 8px; background-color: #ffffff;'>
                    <h2 style='color: #2c3e50; margin-top: 0;'>Password Change Request</h2>
                    <p>Hello <strong>$fullName</strong>,</p>
                    <p>We received a request to change the password for your account. Use the verification code below to confirm this action:</p>
                    
                    <div style='background-color: #f8f9fa; border: 1px dashed #A40404; padding: 15px; text-align: center; margin: 25px 0; border-radius: 5px;'>
                        <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #A40404;'>$otp</span>
                    </div>

                    <p><strong>This code will expire in 5 minutes.</strong></p>
                    <p style='font-size: 13px; color: #666; margin-top: 30px;'>If you did not initiate this request, your account may be compromised. Please secure your account or contact support immediately.</p>
                </div>

                <div style='text-align: center; margin-top: 20px; font-size: 12px; color: #888;'>
                    <p>&copy; " . date("Y") . " WMSU Lost & Found. All rights reserved.</p>
                </div>
            </div>";


            if ($mailService->sendEmail($student_data['Email'], $fullName, $subject, $body)) {
                $showVerifyModal = true; 
            } else {
                $message = "Failed to send verification code. Please try again.";
                $message_type = "error";
            }
        }
    }

    if (isset($_POST['verify_otp'])) {
        $entered_code = trim($_POST['otp_code']); 

    
        if (isset($_SESSION['profile_otp']) && (time() < $_SESSION['profile_otp_expiry'])) {
            if ($entered_code == $_SESSION['profile_otp']) {
                $new_pass = $_SESSION['temp_new_password'];
                
                if ($studentObj->changePassword($user_id, $new_pass)) {
                    $message = "Password changed successfully!";
                    $message_type = "success";
                    unset($_SESSION['temp_new_password']);
                    unset($_SESSION['profile_otp']);
                    unset($_SESSION['profile_otp_expiry']);
                } else {
                    $message = "Database error. Could not update password.";
                    $message_type = "error";
                }
            } else {
                $message = "Invalid verification code.";
                $message_type = "error";
                $showVerifyModal = true; 
            }
        } else {
            $message = "Verification session expired. Please try again.";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - WMSU Lost & Found</title>
    <link rel="stylesheet" href="../styles/myReports.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../styles/profile.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .password-container { position: relative !important; display: block; width: 100%; }
        .password-container input { width: 100% !important; padding-right: 45px !important; box-sizing: border-box !important; height: 45px; }
        .toggle-password { position: absolute !important; top: 50% !important; right: 10px !important; transform: translateY(-50%) !important; cursor: pointer; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 5px; background: transparent; border: none; }
        .toggle-password svg { stroke: #6c757d; transition: stroke 0.2s; width: 20px; height: 20px; }
        .toggle-password:hover svg { stroke: #A40404; }
        input[type="password"]::-ms-reveal, input[type="password"]::-ms-clear { display: none; }

        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px);
            display: flex; justify-content: center; align-items: center; z-index: 9999;
            animation: fadeIn 0.3s ease-out;
        }
        .modal-box {
            background: white; padding: 30px; width: 90%; max-width: 400px;
            border-radius: 15px; text-align: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2); border-top: 5px solid #A40404;
            animation: popIn 0.4s;
        }
        .otp-container { display: flex; justify-content: center; gap: 8px; margin: 20px 0; }
        .otp-box {
            width: 45px; height: 50px; text-align: center; font-size: 24px; font-weight: bold;
            color: #A40404; border: 2px solid #ddd; border-radius: 8px;
        }
        .otp-box:focus { border-color: #A40404; outline: none; box-shadow: 0 5px 15px rgba(164,4,4,0.1); }
        .btn-verify { width: 100%; padding: 12px; background: #A40404; color: white; border: none; border-radius: 50px; font-weight: bold; cursor: pointer; }
        @keyframes fadeIn { from {opacity:0;} to {opacity:1;} }
        @keyframes popIn { from {transform:scale(0.8); opacity:0;} to {transform:scale(1); opacity:1;} }
    </style>
</head>
<body>

    <nav class="wmsu-navbar">
        <a href="../landingpage/index.php" class="brand-container">
            <img src="../images/wmsu_logo.jpg" alt="WMSU Logo" class="brand-logo">
            <span class="brand-text">Lost & Found</span>
        </a>
        <div class="nav-buttons">
            <a href="myReports.php" class="btn-nav-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </nav>

    <div class="main-container">

        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar"><i class="fas fa-user"></i></div>
                <h2><?= htmlspecialchars($student_data['First_Name'] . ' ' . $student_data['Last_Name']) ?></h2>
                <p><?= htmlspecialchars($student_data['StudentID']) ?></p>
            </div>

            <div class="profile-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="info-group">
                    <span class="info-label">Full Name</span>
                    <div class="info-value"><?= htmlspecialchars($student_data['First_Name'] . ' ' . ($student_data['Middle_Name'] ? $student_data['Middle_Name'] . ' ' : '') . $student_data['Last_Name']) ?></div>
                </div>
                <div class="info-group">
                    <span class="info-label">Email Address</span>
                    <div class="info-value"><?= htmlspecialchars($student_data['Email']) ?></div>
                </div>
                <div class="info-group">
                    <span class="info-label">Department / College</span>
                    <div class="info-value"><?= htmlspecialchars($student_data['CourseName'] ?? 'N/A') ?></div>
                </div>
            </div>

            <div class="edit-form">
                <h3 style="margin-top:0; color:#2c3e50; font-size:1.1rem; border-bottom:1px solid #ddd; padding-bottom:10px; margin-bottom:15px;">
                    <i class="fas fa-address-book"></i> Update Contact Info
                </h3>
                <form method="POST">
                    <input type="hidden" name="update_phone" value="1">
                    <label for="phone" class="info-label">Phone Number</label>
                    <input type="text" name="phone" id="phone" class="form-control" 
                           value="<?= htmlspecialchars($student_data['PhoneNo']) ?>" 
                           placeholder="Enter your mobile number" required>
                    <button type="submit" class="btn-update">Save Contact Info</button>
                </form>
            </div>

            <div class="edit-form" style="border-top: none; background-color: #fff; padding-top: 10px;">
                <h3 style="margin-top:0; color:#2c3e50; font-size:1.1rem; border-bottom:1px solid #ddd; padding-bottom:10px; margin-bottom:15px;">
                    <i class="fas fa-lock"></i> Change Password
                </h3>
                
                <form method="POST">
                    <input type="hidden" name="request_password_change" value="1">

                    <div style="margin-bottom: 15px;">
                        <label class="info-label">Current Password</label>
                        <div class="password-container">
                            <input type="password" name="current_password" id="current_password" class="form-control" required>
                            <span class="toggle-password" onclick="togglePassword('current_password', this)" title="Show Password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <div style="flex:1; min-width: 200px;">
                            <label class="info-label">New Password</label>
                            <div class="password-container">
                                <input type="password" name="new_password" id="new_password" class="form-control" minlength="8" required>
                                <span class="toggle-password" onclick="togglePassword('new_password', this)" title="Show Password">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </span>
                            </div>
                        </div>
                        <div style="flex:1; min-width: 200px;">
                            <label class="info-label">Confirm Password</label>
                            <div class="password-container">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" minlength="8" required>
                                <span class="toggle-password" onclick="togglePassword('confirm_password', this)" title="Show Password">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-update" style="background: #A40404;">Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($showVerifyModal): ?>
    <div class="modal-overlay">
        <div class="modal-box">
            <h2 style="color: #2c3e50; margin-bottom: 5px;">Verify Identity</h2>
            <p style="color: #666; font-size: 0.9rem;">Enter the code sent to your email.</p>
            
            <form method="POST" id="otpForm">
                <input type="hidden" name="verify_otp" value="1">
                <input type="hidden" name="otp_code" id="final_otp_code">
                
                <div class="otp-container">
                    <input type="text" class="otp-box" maxlength="1" pattern="\d*" required>
                    <input type="text" class="otp-box" maxlength="1" pattern="\d*" required>
                    <input type="text" class="otp-box" maxlength="1" pattern="\d*" required>
                    <input type="text" class="otp-box" maxlength="1" pattern="\d*" required>
                    <input type="text" class="otp-box" maxlength="1" pattern="\d*" required>
                    <input type="text" class="otp-box" maxlength="1" pattern="\d*" required>
                </div>
                
                <button type="submit" class="btn-verify">Verify & Update</button>
                <a href="profile.php" style="display:block; margin-top:15px; color:#666; text-decoration:none;">Cancel</a>
            </form>
        </div>
    </div>
    <script>

        const otpInputs = document.querySelectorAll('.otp-box');
        const finalOtp = document.getElementById('final_otp_code');
        const otpForm = document.getElementById('otpForm');

        otpInputs[0].focus(); 

        otpInputs.forEach((input, index) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/[^0-9]/g, '');
                if (input.value.length === 1 && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && input.value === '' && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });
        });

        otpForm.addEventListener('submit', () => {
            let code = '';
            otpInputs.forEach(input => code += input.value);
            finalOtp.value = code;
        });
    </script>
    <?php endif; ?>

    <script>
    function togglePassword(fieldId, icon) {
        const input = document.getElementById(fieldId);
        const svg = icon.querySelector('svg');
        if (input.type === "password") {
            input.type = "text";
            svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            icon.setAttribute('title', 'Hide Password');
        } else {
            input.type = "password";
            svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            icon.setAttribute('title', 'Show Password');
        }
    }
    </script>
</body>
</html>