<?php
session_start();
require_once "../classes/Student.php";
require_once "../classes/MailService.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["Email"]);
    $studentObj = new Student();

    if ($studentObj->isEmailRegistered($email)) {
        $otp = rand(100000, 999999);
        $_SESSION['reset_otp'] = $otp;
        $_SESSION['reset_email'] = $email;

        $mail = new MailService();
        $subject = "Password Reset Code - WMSU Lost & Found";
        $body = "<h2>Password Reset</h2><p>Your verification code is: <strong>$otp</strong></p>";

        if ($mail->sendEmail($email, "Student", $subject, $body)) {
            header("Location: verifyResetCode.php");
            exit();
        } else {
            $error = "Failed to send email. Try again.";
        }
    } else {
        $error = "Email not found in our records.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../styles/login.css">
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h1>Reset Password</h1>
            <p>Enter your email to receive a code</p>
        </div>
        
        <?php if ($error): ?><div class="alert-danger"><?= $error ?></div><?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="Email" required placeholder="Enter your registered email">
            </div>
            <button type="submit" class="btn-submit">Send Code</button>
            <a href="loginAccount.php" class="back-home">Cancel</a>
        </form>
    </div>
</body>
</html>