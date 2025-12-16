<?php
session_start();

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_otp'])) {
    header("Location: loginAccount.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $entered_code = trim($_POST['code'] ?? '');

    if ($entered_code == $_SESSION['reset_otp']) {
        $_SESSION['otp_verified'] = true;
        header("Location: resetPassword.php");
        exit();
    } else {
        $error = "Invalid verification code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - WMSU Lost & Found</title>
    <link rel="stylesheet" href="../styles/login.css?v=<?php echo time(); ?>">
    
    <style>

        .verify-text { 
            text-align: center; 
            margin-bottom: 25px; 
            color: #6c757d; 
            font-size: 0.95rem;
        }

        .otp-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }

        .otp-box {
            width: 45px;
            height: 55px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: var(--wmsu-red);
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background-color: #fff;
            transition: all 0.2s ease;
            appearance: none; 
            -webkit-appearance: none;
        }

        .otp-box:focus {
            border-color: #A40404;
            outline: none;
            box-shadow: 0 5px 15px rgba(164, 4, 4, 0.15);
            transform: translateY(-2px);
        }


        .otp-box::-webkit-outer-spin-button,
        .otp-box::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h1>Verify Code</h1>
            <p>Check your email for the 6-digit code</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" id="otpForm">
            <p class="verify-text">Enter the code sent to <strong><?= htmlspecialchars($_SESSION['reset_email']) ?></strong></p>
            
            <div class="otp-container">
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
            </div>

            <input type="hidden" name="code" id="final_code">

            <button type="submit" class="btn-submit">Verify Code</button>
            
            <div style="text-align: center; margin-top: 15px;">
                <a href="loginAccount.php" class="back-home">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        const inputs = document.querySelectorAll('.otp-box');
        const finalCode = document.getElementById('final_code');
        const form = document.getElementById('otpForm');

        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                input.value = input.value.replace(/[^0-9]/g, '');
                if (input.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && input.value === '' && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').slice(0, 6).split('');
                if (pasteData.length > 0) {
                    pasteData.forEach((char, i) => {
                        if (inputs[i]) inputs[i].value = char;
                    });
                    const lastIndex = Math.min(pasteData.length, inputs.length) - 1;
                    inputs[lastIndex].focus();
                }
            });
        });

        form.addEventListener('submit', (e) => {
            let code = '';
            inputs.forEach(input => code += input.value);
            finalCode.value = code;
        });
    </script>
</body>
</html>