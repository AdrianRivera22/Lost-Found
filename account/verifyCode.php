<?php
session_start();
require_once "../classes/Student.php";

// 1. Security Check: Kick them out if no registration session exists
if (!isset($_SESSION['registration_data']) || !isset($_SESSION['verification_code'])) {
    header("Location: registerAccount.php");
    exit();
}

$error_msg = "";

// 2. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // The hidden input 'code' will contain the full 6 digits combined
    $entered_code = trim($_POST['code'] ?? '');

    if ($entered_code == $_SESSION['verification_code']) {
        // --- Success: Create Account ---
        $data = $_SESSION['registration_data'];
        $studentObj = new Student();

        $studentObj->StudentID   = $data['StudentID'];
        $studentObj->Last_Name   = $data['Last_Name'];
        $studentObj->First_Name  = $data['First_Name'];
        $studentObj->Middle_Name = $data['Middle_Name'];
        $studentObj->PhoneNo     = $data['PhoneNo'];
        $studentObj->Email       = $data['Email'];
        $studentObj->CourseID    = $data['CourseID'];
        $studentObj->Password    = $data['Password']; 

        if ($studentObj->addStudent()) {
            $_SESSION['user_id'] = $studentObj->UserID;
            $_SESSION['student_id'] = $studentObj->StudentID;

            // Cleanup
            unset($_SESSION['registration_data']);
            unset($_SESSION['verification_code']);

            header("Location: ../landingpage/index.php");
            exit();
        } else {
            $error_msg = "System error: Could not create account. Please try again.";
        }
    } else {
        $error_msg = "Invalid code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - WMSU Lost & Found</title>
    <link rel="stylesheet" href="../styles/registerAccount.css?v=<?php echo time(); ?>">
    
    <style>
        /* INLINE STYLES FOR THE 6-BOX LAYOUT */
        
        .verify-text { 
            text-align: center; 
            margin-bottom: 25px; 
            color: #6c757d; 
            font-size: 0.95rem;
        }

        /* Container for the 6 boxes */
        .otp-container {
            display: flex;
            justify-content: center;
            gap: 10px; /* Space between boxes */
            margin-bottom: 30px;
        }

        /* Individual Box Style */
        .otp-box {
            width: 45px;
            height: 55px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: var(--wmsu-red);
            
            background-color: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.2s ease;
            
            /* Remove standard input styles */
            appearance: none;
            -webkit-appearance: none;
        }

        /* Focus State (Active Box) */
        .otp-box:focus {
            border-color: var(--wmsu-red);
            outline: none;
            box-shadow: 0 5px 15px rgba(164, 4, 4, 0.15);
            transform: translateY(-2px);
        }

        /* Hide Number Spinners */
        .otp-box::-webkit-outer-spin-button,
        .otp-box::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>
<body>

    <div class="register-card" style="max-width: 500px;">
        <div class="register-header">
            <h1>Check Your Email</h1>
            <p>We sent a 6-digit code to <br><strong><?= htmlspecialchars($_SESSION['registration_data']['Email']) ?></strong></p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error" style="text-align:center;">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <form action="" method="post" id="otpForm">
            <p class="verify-text">Enter the 6-digit verification code:</p>
            
            <div class="otp-container">
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
                <input type="number" class="otp-box" maxlength="1" pattern="\d*" inputmode="numeric" required>
            </div>

            <input type="hidden" name="code" id="final_code">

            <button type="submit" class="btn-register">Verify & Create Account</button>
            
            <div class="footer-links" style="margin-top: 15px;">
                <a href="registerAccount.php" class="back-home">← Back to Registration</a>
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

                if (input.value.length === 1) {
                   
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                }
            });

    
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && input.value === '') {
                    if (index > 0) {
                        inputs[index - 1].focus();
                    }
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
            inputs.forEach(input => {
                code += input.value;
            });
            finalCode.value = code;
        });
    </script>

</body>
</html>