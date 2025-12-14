<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        header("Location: ../admin/dashboard.php"); 
    } else {
        header("Location: ../landingpage/index.php"); 
    }
    exit();
}

require_once "../classes/Student.php"; 
$studentObj = new Student(); 

$login_data = [ "Email" => "", "Password" => "" ];
$errors = [ "Email" => "", "Password" => "", "general" => "" ];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_data["Email"] = trim(htmlspecialchars($_POST["Email"] ?? ''));
    $login_data["Password"] = trim($_POST["Password"] ?? '');

    if (empty($login_data["Email"])) {
        $errors["Email"] = "Email is required.";
    } elseif (!filter_var($login_data["Email"], FILTER_VALIDATE_EMAIL)) {
        $errors["Email"] = "Invalid email format.";
    }

    if (empty($login_data["Password"])) {
        $errors["Password"] = "Password is required.";
    }

    if (empty(array_filter($errors))) {
        $studentObj->Email = $login_data["Email"]; 
        $studentObj->Password = $login_data["Password"];
       
        if ($studentObj->login()) {
            $_SESSION['user_id'] = $studentObj->UserID;
            $_SESSION['student_id'] = $studentObj->StudentID; 
            $_SESSION['user_role'] = $studentObj->Role;

            if ($studentObj->Role === 'admin') {
                header("Location: ../admin/dashboard.php"); 
            } else {
                header("Location: ../landingpage/index.php"); 
            }
            exit();
        } else {
            $errors["general"] = "Invalid Email or Password.";
        }
    } else {
        if(empty($errors["general"])) {
             $errors["general"] = "Please check your input.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WMSU Lost & Found</title>
    <link rel="stylesheet" href="../styles/login.css">
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <img src="../images/wmsu_logo.jpg" alt="WMSU Logo" class="brand-logo">
            <h1>Welcome Back</h1>
            <p>Sign in to your account</p>
        </div>

        <?php if (!empty($errors["general"])): ?>
            <div class="alert-danger"><?= htmlspecialchars($errors["general"]) ?></div>
        <?php endif; ?>

        <form action="" method="post">
            <div class="form-group">
                <label for="Email">Email Address</label>
                <input type="email" name="Email" id="Email" 
                       value="<?= htmlspecialchars($login_data["Email"]) ?>" 
                       required placeholder="e.g. student@wmsu.edu.ph">
                <?php if (!empty($errors["Email"])): ?>
                    <span class="error-msg"><?= htmlspecialchars($errors["Email"]) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="Password">Password</label>
                <input type="password" name="Password" id="Password" 
                       required placeholder="Enter your password">
                <?php if (!empty($errors["Password"])): ?>
                    <span class="error-msg"><?= htmlspecialchars($errors["Password"]) ?></span>
                <?php endif; ?>
            </div>
            <div style="text-align: right; margin-bottom: 15px;">
                <a href="forgotPassword.php" style="color: var(--text-muted); font-size: 0.9rem; text-decoration: none;">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-submit">Login</button>

            <div class="auth-links">
                Don't have an account? <a href="registerAccount.php">Register Here</a>
            </div>
            
            <a href="../landingpage/index.php" class="back-home">← Back to Home</a>
        </form>
    </div>

</body>
</html>