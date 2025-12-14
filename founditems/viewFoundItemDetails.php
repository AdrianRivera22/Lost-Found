<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/loginAccount.php"); 
    exit("Please log in to view item details.");
}

require_once "../classes/founditems.php"; 
$foundItemsObj = new FoundItems(); 

$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
$item_details = null;
$error_message = "";
$success_message = "";

// Navigation Logic
$back_url = "viewFoundItems.php";
$back_text = "Back to List";

if (isset($_GET['from']) && $_GET['from'] === 'dashboard') {
    $back_url = "../account/myReports.php";
    $back_text = "Back to Dashboard";
} elseif (isset($_GET['from']) && $_GET['from'] === 'landing') {
    $back_url = "../landingpage/index.php";
    $back_text = "Back to Home";
}

// --- POST HANDLING ---

// 1. Claimant: Confirm Receipt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_receipt']) && $item_id) {
    if ($foundItemsObj->markItemAsReturned($item_id, $_SESSION['user_id'])) {
        $success_message = "Item marked as RECEIVED. Case closed!";
        $item_details = $foundItemsObj->fetchFoundItemDetails($item_id); // Refresh
    } else {
        $error_message = "Failed to confirm receipt.";
    }
}

// 2. Claimant: Cancel Claim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_claim']) && $item_id) {
    if ($foundItemsObj->claimantCancelClaim($item_id, $_SESSION['user_id'])) {
        $success_message = "Claim cancelled. Item is now available for others.";
        $item_details = $foundItemsObj->fetchFoundItemDetails($item_id);
    } else {
        $error_message = "Failed to cancel claim.";
    }
}

// 3. Finder: Confirm Return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_finder_returned']) && $item_id) {
    if ($foundItemsObj->finderConfirmReturn($item_id, $_SESSION['user_id'])) {
        $success_message = "Great job! You have successfully returned the item.";
        $item_details = $foundItemsObj->fetchFoundItemDetails($item_id);
    } else {
        $error_message = "Failed to mark item as returned.";
    }
}

// 4. Finder: Cancel Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_finder_cancel']) && $item_id) {
    $reason = $_POST['cancel_reason'] ?? 'Other';
    if ($foundItemsObj->finderCancelTransaction($item_id, $_SESSION['user_id'], $reason)) {
        $success_message = "Transaction cancelled. The item is back to 'Reported' status.";
        $item_details = $foundItemsObj->fetchFoundItemDetails($item_id);
    } else {
        $error_message = "Failed to cancel transaction.";
    }
}

// Fetch Item Details
if (!$item_id) {
    $error_message = "Invalid Item ID provided.";
} elseif ($item_details === null) {
    $item_details = $foundItemsObj->fetchFoundItemDetails($item_id);

    if ($item_details === null) {
        $error_message = "Error fetching item details from the database.";
    } elseif ($item_details === false) {
        $error_message = "No available found item found with the specified ID, or it has already been claimed/returned.";
    }
}

// Role Checks
$is_reporter = ($item_details && $item_details['ReporterUserID'] == $_SESSION['user_id']);
$is_claimant = ($item_details && isset($item_details['ClaimantUserID']) && $item_details['ClaimantUserID'] == $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Found Item Details</title>
    <link rel="stylesheet" href="../styles/viewLostItems.css?v=<?php echo time(); ?>"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Shared Styles for Contact Cards */
        .contact-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .contact-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .contact-avatar {
            width: 50px; height: 50px;
            background: var(--wmsu-red);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; font-weight: bold;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .info-box label { display: block; font-size: 0.85rem; color: #666; margin-bottom: 5px; text-transform: uppercase; font-weight: 600; }
        .info-box span { font-size: 1.1rem; color: #333; font-weight: 500; }
        
        .action-buttons { display: flex; gap: 15px; }
        .btn-confirm-receipt, .btn-return-success {
            flex: 2; background-color: #28a745; color: white; border: none; padding: 12px;
            border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-confirm-receipt:hover, .btn-return-success:hover { background-color: #218838; }
        .btn-cancel {
            flex: 1; background-color: #dc3545; color: white; border: none; padding: 12px;
            border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-cancel:hover { background-color: #c82333; }

        /* Modal Specifics */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 450px; border-radius: 8px; position: relative; }
        .close-modal { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-modal:hover { color: black; }
        .modal-header h2 { margin-top: 0; color: #333; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .btn-submit-modal { width: 100%; padding: 12px; color: white; border: none; border-radius: 6px; font-size: 1rem; font-weight: bold; cursor: pointer; margin-top: 15px; }
        .reason-option { display: block; padding: 10px; border: 1px solid #ddd; margin-bottom: 8px; border-radius: 6px; cursor: pointer; }
        .reason-option:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>

    <nav class="wmsu-navbar">
        <a href="../landingpage/index.php" class="brand-container">
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
        
        <?php if ($success_message): ?>
            <div class="no-notifications" style="background: #d4edda; border-radius:8px; color: #155724; padding: 20px; margin-bottom: 20px; text-align: center;">
                <i class="fas fa-check-circle fa-2x"></i><br><br>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="no-notifications" style="background: #fff3cd; border-radius:8px; color: #856404; padding: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
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
                        <div class="detail-row"><div class="detail-label">Location Found</div><div class="detail-value"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item_details['ReportLocation']) ?></div></div>
                        <div class="detail-row"><div class="detail-label">Description</div><div class="detail-value"><?= nl2br(htmlspecialchars($item_details['Description'])) ?></div></div>
                        
                        <div class="reporter-section">
                            <div class="reporter-title"><i class="fas fa-user-circle"></i> Reported By (Finder)</div>
                            <div class="detail-row" style="border-bottom: none; margin-bottom: 5px;">
                                <div class="detail-value" style="font-weight:bold;"><?= htmlspecialchars($item_details['ReporterFirstName'] . ' ' . $item_details['ReporterLastName']) ?></div>
                            </div>
                        </div>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                        
                        <div style="margin-top: 20px;">
                            
                            <?php if ($is_reporter): ?>
                                
                                <?php if ($item_details['ItemStatus'] == 'Accepted' || $item_details['ItemStatus'] == 'Verified'): ?>
                                    <div class="contact-card">
                                        <div class="contact-header">
                                            <div class="contact-avatar"><i class="fas fa-user"></i></div>
                                            <div>
                                                <h3 style="margin:0; color:var(--text-dark);">Claimant (Owner) Details</h3>
                                                <span style="font-size:0.9em; color:#666;">Claim Accepted by Admin</span>
                                            </div>
                                        </div>
                                        
                                        <div class="info-grid">
                                            <div class="info-box"><label>Name</label><span><?= htmlspecialchars($item_details['ClaimantFirstName'] . ' ' . $item_details['ClaimantLastName']) ?></span></div>
                                            <div class="info-box"><label>Email</label><span><?= htmlspecialchars($item_details['ClaimantEmail']) ?></span></div>
                                            <div class="info-box"><label>Phone</label><span><?= htmlspecialchars($item_details['ClaimantPhone']) ?></span></div>
                                            <div class="info-box"><label>Student ID</label><span><?= htmlspecialchars($item_details['ClaimantStudentID']) ?></span></div>
                                        </div>

                                        <div class="action-buttons">
                                            <button onclick="document.getElementById('finderReturnModal').style.display='block'" class="btn-return-success">
                                                <i class="fas fa-hand-holding-heart"></i> Item Returned
                                            </button>
                                            <button onclick="document.getElementById('finderCancelModal').style.display='block'" class="btn-cancel">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </div>
                                    </div>
                                <?php elseif ($item_details['ItemStatus'] == 'Returned'): ?>
                                    <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 8px;">
                                        <i class="fas fa-check-circle"></i> This item has been returned.
                                    </div>
                                <?php else: ?>
                                    <p style="color: #666;">Thank you for reporting! Waiting for the owner to claim this item.</p>
                                <?php endif; ?>

                            <?php elseif ($is_claimant): ?>
                                
                                <?php if ($item_details['ItemStatus'] == 'Accepted' || $item_details['ItemStatus'] == 'Verified'): ?>
                                    <div class="contact-card" style="border-color: #28a745;">
                                        <div class="contact-header">
                                            <div class="contact-avatar" style="background-color: #28a745;"><i class="fas fa-search-location"></i></div>
                                            <div>
                                                <h3 style="margin:0; color:var(--text-dark);">Finder Contact Details</h3>
                                                <span style="font-size:0.9em; color:#666;">Please contact the finder to retrieve your item.</span>
                                            </div>
                                        </div>
                                        
                                        <div class="info-grid">
                                            <div class="info-box"><label>Name</label><span><?= htmlspecialchars($item_details['ReporterFirstName'] . ' ' . $item_details['ReporterLastName']) ?></span></div>
                                            <div class="info-box"><label>Email</label><span><?= htmlspecialchars($item_details['ReporterEmail']) ?></span></div>
                                            <div class="info-box"><label>Phone</label><span><?= htmlspecialchars($item_details['ReporterPhone']) ?></span></div>
                                        </div>
                                        
                                        <div class="action-buttons">
                                            <button onclick="document.getElementById('claimantReceiptModal').style.display='block'" class="btn-confirm-receipt">
                                                <i class="fas fa-check-circle"></i> I have received this item
                                            </button>
                                            <button onclick="document.getElementById('claimantCancelModal').style.display='block'" class="btn-cancel">
                                                <i class="fas fa-times-circle"></i> Cancel
                                            </button>
                                        </div>
                                    </div>
                                <?php elseif ($item_details['ItemStatus'] == 'Returned'): ?>
                                    <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 8px;">
                                        <i class="fas fa-check-circle"></i> You have received this item.
                                    </div>
                                <?php else: ?>
                                    <div style="background-color: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px;">
                                        <i class="fas fa-clock"></i> Your claim is pending Admin verification.
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <h3>Claim This Item</h3>
                                <?php if ($item_details['ItemStatus'] == 'Reported'): ?>
                                    <p style="margin-bottom: 15px;">If you believe this is your lost item, you can submit a claim providing details only the owner would know.</p>
                                    <a href="claimItem.php?item_id=<?= $item_id ?>" class="btn-action btn-claim" style="padding: 10px 25px; font-size: 1rem; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">Claim This Item</a>
                                <?php else: ?>
                                    <p style="color: #6c757d;">This item is currently being processed or has been returned.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <div id="claimantReceiptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-check-circle"></i> Confirm Receipt</h2>
                <span class="close-modal" onclick="document.getElementById('claimantReceiptModal').style.display='none'">&times;</span>
            </div>
            <div class="modal-body" style="text-align: center;">
                <p>Have you successfully met with the Finder and received your item?</p>
                <form method="POST">
                    <input type="hidden" name="item_id" value="<?= $item_id ?>">
                    <button type="submit" name="confirm_receipt" class="btn-submit-modal" style="background-color: #28a745;">Yes, I Received It</button>
                </form>
            </div>
        </div>
    </div>

    <div id="claimantCancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-times-circle"></i> Cancel Claim</h2>
                <span class="close-modal" onclick="document.getElementById('claimantCancelModal').style.display='none'">&times;</span>
            </div>
            <div class="modal-body" style="text-align: center;">
                <p>Are you sure you want to cancel your claim? This item will be made available for others.</p>
                <form method="POST">
                    <input type="hidden" name="item_id" value="<?= $item_id ?>">
                    <button type="submit" name="cancel_claim" class="btn-submit-modal" style="background-color: #dc3545;">Yes, Cancel Claim</button>
                </form>
            </div>
        </div>
    </div>

    <div id="finderReturnModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-hand-holding-heart"></i> Item Returned</h2>
                <span class="close-modal" onclick="document.getElementById('finderReturnModal').style.display='none'">&times;</span>
            </div>
            <div class="modal-body" style="text-align: center;">
                <p>Confirm that you have handed the item over to the Claimant or Admin?</p>
                <form method="POST">
                    <input type="hidden" name="item_id" value="<?= $item_id ?>">
                    <button type="submit" name="btn_finder_returned" class="btn-submit-modal" style="background-color: #28a745;">Yes, Returned</button>
                </form>
            </div>
        </div>
    </div>

    <div id="finderCancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-ban"></i> Cancel Transaction</h2>
                <span class="close-modal" onclick="document.getElementById('finderCancelModal').style.display='none'">&times;</span>
            </div>
            <div class="modal-body">
                <p>Reason for cancellation:</p>
                <form method="POST">
                    <input type="hidden" name="item_id" value="<?= $item_id ?>">
                    <label class="reason-option"><input type="radio" name="cancel_reason" value="Claimant No-Show" required> Claimant did not show up</label>
                    <label class="reason-option"><input type="radio" name="cancel_reason" value="Proof Mismatch"> Proof details do not match</label>
                    <label class="reason-option"><input type="radio" name="cancel_reason" value="Other" checked> Other</label>
                    <button type="submit" name="btn_finder_cancel" class="btn-submit-modal" style="background-color: #dc3545;">Cancel Transaction</button>
                </form>
            </div>
        </div>
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

    <script>
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }
    </script>

</body>
</html>