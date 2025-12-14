<?php
session_start();
require_once "../classes/Student.php";

if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['reset_email'])) {
    header("Location: loginAccount.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (strlen($pass) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($pass !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $student = new Student();
        if ($student->updatePassword($_SESSION['reset_email'], $pass)) {
            session_destroy();
            echo "<script>alert('Password updated successfully!'); window.location='loginAccount.php';</script>";
        } else {
            $error = "Failed to update password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Password</title>
    <link rel="stylesheet" href="../styles/login.css">
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h1>New Password</h1>
            <p>Create a new secure password</p>
        </div>
        
        <?php if ($error): ?><div class="alert-danger"><?= $error ?></div><?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" required placeholder="Min. 8 chars">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required placeholder="Confirm password">
            </div>
            <button type="submit" class="btn-submit">Update Password</button>
        </form>
    </div>
</body>
</html>