<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/loginAccount.php");
    exit();
}

require_once "../classes/FoundItems.php";
require_once "../classes/Claim.php";

$foundItemObj = new FoundItems();
$claimObj = new Claim();

$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
$item_details = null;
$error_message = "";
$success_message = "";
$is_reporter = false; 
$claim_data = [ "SecurityQuestionAnswers" => "" ];
$claim_errors = [ "SecurityQuestionAnswers" => "", "general" => "" ];


$back_url = "viewFoundItems.php";
$back_text = "Back to Found List";

if (isset($_GET['from'])) {
    if ($_GET['from'] === 'dashboard') {
        $back_url = "../account/myReports.php";
        $back_text = "Back to Dashboard";
    } elseif ($_GET['from'] === 'landing') {
     
        $back_url = "../landingpage/userMain.php";
        $back_text = "Back to Home";
    }
}

if (!$item_id) {
    $error_message = "Invalid Item ID provided.";
} else {
    $item_details = $foundItemObj->fetchFoundItemDetails($item_id);

    if ($item_details === null) {
        $error_message = "Error fetching item details from the database.";
    } elseif ($item_details === false) {
        $error_message = "This item is not available for claiming.";
    } else {
        if (isset($item_details['ReporterUserID']) && $item_details['ReporterUserID'] == $_SESSION['user_id']) {
            $is_reporter = true;
            $error_message = "You cannot claim an item that you reported finding.";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $item_details && !$is_reporter) {
    $claim_data["SecurityQuestionAnswers"] = trim(htmlspecialchars($_POST["SecurityQuestionAnswers"] ?? ''));
    if (empty($claim_data["SecurityQuestionAnswers"])) { $claim_errors["SecurityQuestionAnswers"] = "Please provide details to prove ownership."; }

    if (empty(array_filter($claim_errors))) {
        try {
            $claimObj->ClaimantUserID = $_SESSION['user_id'];
            $claimObj->FoundItemID = $item_id;
            $claimObj->SecurityQuestionAnswers = $claim_data["SecurityQuestionAnswers"];
            if ($claimObj->addClaim()) {
                $success_message = "✅ Your claim has been submitted successfully (Claim ID: " . $claimObj->NewClaimID . ").";
                $claim_data["SecurityQuestionAnswers"] = "";
            } else { $claim_errors["general"] = "❌ Failed to submit claim due to a database error."; }
        } catch (Exception $e) { $claim_errors["general"] = "❌ Error: " . $e->getMessage(); }
    } else { $claim_errors["general"] = "Please correct the errors below."; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Found Item - WMSU</title>
    <link rel="stylesheet" href="../styles/claimItem.css?v=<?php echo time(); ?>"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <nav class="wmsu-navbar">
        <a href="../landingpage/userMain.php" class="brand-container">
            <img src="../images/wmsu_logo.jpg" alt="WMSU Logo" class="brand-logo">
            <span class="brand-text">Lost & Found</span>
        </a>
        <div class="nav-buttons">
            <a href="<?= htmlspecialchars($back_url) ?>" class="btn-nav-back">
                <i class="fas fa-arrow-left"></i> <?= htmlspecialchars($back_text) ?>
            </a>
        </div>
    </nav>

    <div class="main-container">
        
        <?php if ($error_message && !$is_reporter): ?>
            <div class="no-notifications" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php elseif ($item_details): ?>

            <div class="detail-card">
                <div class="detail-layout">
                    
                    <div class="detail-image-section">
                        <?php 
                            $photo_url = !empty($item_details['Photo']) ? "../" . htmlspecialchars($item_details['Photo']) : "../images/placeholder.png"; 
                        ?>
                        <img src="<?= $photo_url ?>" alt="Photo of <?= htmlspecialchars($item_details['ItemName']) ?>">
                        
                        <div style="margin-top: 20px; text-align: center;">
                            <span class="status-badge badge-<?= strtolower(str_replace(' ', '-', $item_details['ItemStatus'])) ?>" 
                                  style="font-size: 1rem; padding: 8px 20px;">
                                <?= htmlspecialchars($item_details['ItemStatus']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="detail-info-section">
                        <div class="detail-header">
                            <h1 class="detail-title"><?= htmlspecialchars($item_details['ItemName']) ?></h1>
                        </div>

                        <div class="detail-row"><div class="detail-label">Category</div><div class="detail-value"><?= htmlspecialchars($item_details['Category']) ?></div></div>
                        <div class="detail-row"><div class="detail-label">Date Found</div><div class="detail-value"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars(date('F j, Y', strtotime($item_details['DateFound']))) ?></div></div>
                        <div class="detail-row"><div class="detail-label">Location</div><div class="detail-value"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item_details['ReportLocation']) ?></div></div>
                        <div class="detail-row"><div class="detail-label">Description</div><div class="detail-value"><?= nl2br(htmlspecialchars($item_details['Description'])) ?></div></div>
                        
                        <div class="reporter-section">
                            <div class="reporter-title"><i class="fas fa-user-circle"></i> Found By</div>
                            <div class="detail-row" style="border-bottom:none; margin-bottom:0;">
                                <div class="detail-label">Name</div>
                                <div class="detail-value"><?= htmlspecialchars($item_details['ReporterFirstName'] . ' ' . $item_details['ReporterLastName']) ?></div>
                            </div>
                        </div>

                        <?php if ($success_message): ?>
                            <div class="claim-section">
                                <div class="no-notifications" style="background: #d1e7dd; border: 1px solid #badbcc; color: #0f5132;">
                                    <?= $success_message ?>
                                </div>
                                <p style="text-align:center; margin-top:15px;">
                                    <a href="../account/myReports.php" style="color:var(--found-dark); font-weight:600;">Go to My Dashboard</a>
                                </p>
                            </div>

                        <?php elseif ($is_reporter): ?>
                            <div class="claim-section">
                                <div class="no-notifications" style="background: #fff3cd; color: #856404;">
                                    <i class="fas fa-info-circle"></i> <?= htmlspecialchars($error_message) ?>
                                </div>
                            </div>

                        <?php else: ?>
                            <div class="claim-section">
                                <div class="claim-form-header">
                                    <i class="fas fa-hand-holding-heart"></i> Submit Your Claim
                                </div>

                                <?php if ($claim_errors["general"]): ?>
                                    <div class="error-text" style="text-align:center; margin-bottom:15px;"><?= $claim_errors["general"] ?></div>
                                <?php endif; ?>

                                <form action="" method="post" class="claim-form">
                                    
                                    <div class="info-box">
                                        <i class="fas fa-shield-alt"></i> <strong>Proof Required:</strong> 
                                        The finder specified a "<strong><?= htmlspecialchars($item_details['ProofType'] ?: 'General Description') ?></strong>" verification. 
                                        Please describe specific details (e.g., wallpaper, scratches, contents) that only the owner would know.
                                    </div>

                                    <div class="form-group">
                                        <label for="SecurityQuestionAnswers" style="font-weight:700; color:var(--text-dark); display:block; margin-bottom:8px;">
                                            Verification Details <span style="color:red;">*</span>
                                        </label>
                                        <textarea name="SecurityQuestionAnswers" id="SecurityQuestionAnswers" rows="5" 
                                                  placeholder="e.g. 'The phone has a crack on the top left screen and a picture of a dog on the lock screen.'"
                                                  required><?= htmlspecialchars($claim_data["SecurityQuestionAnswers"])?></textarea>
                                        <?php if ($claim_errors["SecurityQuestionAnswers"]): ?>
                                            <p class="error-text"><?= htmlspecialchars($claim_errors["SecurityQuestionAnswers"])?></p>
                                        <?php endif; ?>
                                        <small class="form-hint">These details will be sent to the administrator/finder for verification.</small>
                                    </div>

                                    <input type="hidden" name="item_id_hidden" value="<?= $item_id ?>">
                                    <button type="submit" class="btn-submit-claim">
                                        Submit Claim <i class="fas fa-paper-plane" style="margin-left:5px;"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <img src="../images/wmsu_logo.jpg" alt="WMSU Logo" class="brand-logo">
                <div>
                    <strong>WMSU Lost & Found</strong><br>
                    <small>Official Reporting System</small>
                </div>
            </div>
            <div class="copyright">
                &copy; <?= date('Y') ?> Western Mindanao State University.
            </div>
        </div>
    </footer>

</body>
</html>