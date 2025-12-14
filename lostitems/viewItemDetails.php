<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/loginAccount.php"); 
    exit();
}

require_once "../classes/lostitems.php";
$lostItemsObj = new LostItems();

$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
$item_details = null;
$error_message = "";
$success_message = "";

// Navigation Logic
$back_url = "viewLostItems.php";
$back_text = "Back to List";

if (isset($_GET['from'])) {
    if ($_GET['from'] === 'dashboard') {
        $back_url = "../account/myReports.php";
        $back_text = "Back to Dashboard";
    } elseif ($_GET['from'] === 'landing') {
        $back_url = "../landingpage/index.php";
        $back_text = "Back to Home";
    }
}

// --- POST HANDLING ---

// 1. "I Found This" Submission (Original)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_found_this']) && $item_id) {
    $finder_id = $_SESSION['user_id'];
    if (!isset($_FILES['finder_proof']) || $_FILES['finder_proof']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Please upload a photo of the item you found for verification.";
    } else {
        try {
            if ($lostItemsObj->markAsFoundByFinder($item_id, $finder_id, $_FILES['finder_proof'])) {
                $success_message = "Success! The owner has been notified and the item is now Pending Return.";
                $item_details = $lostItemsObj->fetchLostItemDetails($item_id); 
            } else {
                $error_message = "Failed to update item status.";
            }
        } catch (Exception $e) { $error_message = "Error: " . $e->getMessage(); }
    }
}

// 2. Finder "Item Returned" Confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_finder_returned']) && $item_id) {
    if ($lostItemsObj->finderConfirmReturn($item_id, $_SESSION['user_id'])) {
        $success_message = "Great job! You have successfully returned the item.";
        $item_details = $lostItemsObj->fetchLostItemDetails($item_id);
    } else {
        $error_message = "Failed to mark item as returned. Please try again.";
    }
}

// 3. Finder "Cancel Return"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_finder_cancel']) && $item_id) {
    $reason = $_POST['cancel_reason'] ?? 'Other';
    if ($lostItemsObj->finderCancelReturn($item_id, $_SESSION['user_id'], $reason)) {
        $success_message = "Return request cancelled. The item is back to 'Reported' status.";
        $item_details = $lostItemsObj->fetchLostItemDetails($item_id);
    } else {
        $error_message = "Failed to cancel request.";
    }
}

// 4. OWNER: Confirm Item Received
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_receipt']) && $item_id) {
    if ($lostItemsObj->markItemAsReturned($item_id, $_SESSION['user_id'])) {
        $success_message = "Item marked as RECEIVED. Case closed!";
        $item_details = $lostItemsObj->fetchLostItemDetails($item_id);
    } else {
        $error_message = "Failed to confirm receipt.";
    }
}

// 5. OWNER: Cancel Return Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_return']) && $item_id) {
    if ($lostItemsObj->ownerCancelReturn($item_id, $_SESSION['user_id'])) {
        $success_message = "Return request cancelled. Item is now available for others.";
        $item_details = $lostItemsObj->fetchLostItemDetails($item_id);
    } else {
        $error_message = "Failed to cancel return.";
    }
}

// Fetch Item Details
if (!$item_id) {
    $error_message = "Invalid Item ID provided.";
} elseif ($item_details === null) {
    $item_details = $lostItemsObj->fetchLostItemDetails($item_id);
    if (!$item_details) $error_message = "Item not found.";
}

// Role Checks
$is_reporter = ($item_details && $item_details['ReporterUserID'] == $_SESSION['user_id']);
$is_finder   = ($item_details && $item_details['FinderUserID'] == $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost Item Details - WMSU</title>
    <link rel="stylesheet" href="../styles/viewLostItems.css?v=<?php echo time(); ?>"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Specific styles for Finder View & Owner Actions */
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
        .alert-timer {
            background: #fff3cd; color: #856404; padding: 12px; border-radius: 6px; font-size: 0.9rem;
            margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
        }
        .finder-actions, .owner-actions { display: flex; gap: 15px; }
        
        .btn-return-success, .btn-confirm-receipt {
            flex: 2; background-color: #28a745; color: white; border: none; padding: 12px;
            border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-return-success:hover, .btn-confirm-receipt:hover { background-color: #218838; }
        
        .btn-cancel-return {
            flex: 1; background-color: #dc3545; color: white; border: none; padding: 12px;
            border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-cancel-return:hover { background-color: #c82333; }
        
        /* Modal Specifics */
        .modal-content { max-width: 450px; }
        .reason-option {
            display: block; padding: 10px; border: 1px solid #ddd; margin-bottom: 8px; border-radius: 6px; cursor: pointer;
        }
        .reason-option:hover { background-color: #f8f9fa; }
        .reason-radio { margin-right: 10px; }
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
                            $photo_url = !empty($item_details['PhotoURL']) ? "../" . htmlspecialchars($item_details['PhotoURL']) : "../images/placeholder.png"; 
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
                        <div class="detail-row"><div class="detail-label">Date Lost</div><div class="detail-value"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars(date('F j, Y', strtotime($item_details['DateLost']))) ?></div></div>
                        <div class="detail-row"><div class="detail-label">Location</div><div class="detail-value"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item_details['ReportLocation']) ?></div></div>
                        <div class="detail-row"><div class="detail-label">Description</div><div class="detail-value"><?= nl2br(htmlspecialchars($item_details['Description'])) ?></div></div>

                        <div class="reporter-section">
                            <div class="reporter-title"><i class="fas fa-user-circle"></i> Reported By</div>
                            <div class="detail-row" style="border:none; margin:0;">
                                <div class="detail-value" style="font-weight:bold;">
                                    <?= htmlspecialchars($item_details['ReporterFirstName'] . ' ' . $item_details['ReporterLastName']) ?>
                                </div>
                            </div>
                        </div>

                        <?php if($is_reporter && isset($item_details['ProofType'])): ?>
                        <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; font-size: 0.9em; color: #666;">
                            <i class="fas fa-lock"></i> <strong>Proof:</strong> <?= htmlspecialchars($item_details['ProofType']) ?>: <em><?= htmlspecialchars($item_details['ProofValue']) ?></em>
                        </div>
                        <?php endif; ?>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                        
                        <div style="margin-top: 20px;">
                            
                            <?php if ($is_finder && ($item_details['ItemStatus'] == 'Accepted' || $item_details['ItemStatus'] == 'Verified')): ?>
                                <div class="contact-card">
                                    <div class="contact-header">
                                        <div class="contact-avatar"><i class="fas fa-user"></i></div>
                                        <div>
                                            <h3 style="margin:0; color:var(--text-dark);">Owner Contact Details</h3>
                                            <span style="font-size:0.9em; color:#666;">Request Accepted by Admin</span>
                                        </div>
                                    </div>
                                    
                                    <div class="info-grid">
                                        <div class="info-box"><label>Name</label><span><?= htmlspecialchars($item_details['ReporterFirstName'] . ' ' . $item_details['ReporterLastName']) ?></span></div>
                                        <div class="info-box"><label>Email</label><span><?= htmlspecialchars($item_details['ReporterEmail']) ?></span></div>
                                        <div class="info-box"><label>Phone</label><span><?= htmlspecialchars($item_details['ReporterPhone']) ?></span></div>
                                        <div class="info-box"><label>Student ID</label><span><?= htmlspecialchars($item_details['StudentID']) ?></span></div>
                                    </div>

                                    <div class="alert-timer">
                                        <i class="fas fa-clock"></i> 
                                        <strong>Reminder:</strong> You have 7 days to return this item to the owner or the Admin Office.
                                    </div>

                                    <div class="finder-actions">
                                        <button onclick="document.getElementById('returnModal').style.display='block'" class="btn-return-success">
                                            <i class="fas fa-hand-holding-heart"></i> Item Returned
                                        </button>
                                        <button onclick="document.getElementById('cancelModal').style.display='block'" class="btn-cancel-return">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </div>

                            <?php elseif ($is_reporter): ?>
                                <?php if ($item_details['ItemStatus'] == 'Pending Return'): ?>
                                    <div style="background-color: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; text-align: center;">
                                        <i class="fas fa-bell"></i> <strong>Item Found!</strong><br>Waiting for Admin approval.
                                    </div>
                                
                                <?php elseif ($item_details['ItemStatus'] == 'Accepted' || $item_details['ItemStatus'] == 'Verified'): ?>
                                    <div class="contact-card" style="border-color: #28a745;">
                                        <div class="contact-header">
                                            <div class="contact-avatar" style="background-color: #28a745;"><i class="fas fa-search-location"></i></div>
                                            <div>
                                                <h3 style="margin:0; color:var(--text-dark);">Finder Contact Details</h3>
                                                <span style="font-size:0.9em; color:#666;">Please contact the finder to retrieve your item.</span>
                                            </div>
                                        </div>
                                        
                                        <div class="info-grid">
                                            <div class="info-box"><label>Name</label><span><?= htmlspecialchars($item_details['FinderFirstName'] . ' ' . $item_details['FinderLastName']) ?></span></div>
                                            <div class="info-box"><label>Email</label><span><?= htmlspecialchars($item_details['FinderEmail']) ?></span></div>
                                            <div class="info-box"><label>Phone</label><span><?= htmlspecialchars($item_details['FinderPhone']) ?></span></div>
                                        </div>
                                        
                                        <div class="owner-actions">
                                            <button onclick="document.getElementById('ownerReceiptModal').style.display='block'" class="btn-confirm-receipt">
                                                <i class="fas fa-check-circle"></i> I have received this item
                                            </button>
                                            
                                            <button onclick="document.getElementById('ownerCancelModal').style.display='block'" class="btn-cancel-return">
                                                <i class="fas fa-times-circle"></i> Cancel
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <?php if ($item_details['ItemStatus'] == 'Reported'): ?>
                                    <div class="found-trigger-box">
                                        <h3><i class="fas fa-search-location"></i> Found this item?</h3>
                                        <p style="color:#666; margin-bottom:15px;">If you have found this item, please report it to help verify the claim.</p>
                                        <button id="openFoundModalBtn" class="btn-open-modal"><i class="fas fa-camera"></i> I Found This Item</button>
                                    </div>
                                <?php elseif ($item_details['ItemStatus'] == 'Pending Return' || $item_details['ItemStatus'] == 'Accepted'): ?>
                                    <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 8px; text-align: center;">
                                        <i class="fas fa-check-circle"></i> <strong>Reported Found!</strong><br>This item is currently being processed.
                                    </div>
                                <?php else: ?>
                                    <p style="color: #6c757d;">This item is no longer active.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <div id="foundModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-camera"></i> Submit Found Report</h2>
                <span class="close-modal" id="closeFoundModal">&times;</span>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: #666; text-align: center;">Please upload a clear photo of the item you found.</p>
                <form method="POST" enctype="multipart/form-data" id="foundForm">
                    <input type="hidden" name="item_id" value="<?= $item_id ?>">
                    <div class="upload-zone" id="uploadZone" onclick="document.getElementById('finderProof').click()">
                        <input type="file" name="finder_proof" id="finderProof" accept="image/*" hidden required>
                        <div class="upload-content" id="uploadContent">
                            <i class="fas fa-cloud-upload-alt fa-3x"></i>
                            <p style="margin-top: 10px;">Click to Upload or Drag & Drop Photo</p>
                            <span style="font-size: 0.8em; color: #999;">(Max size: 5MB, JPG/PNG)</span>
                        </div>
                        <img id="imagePreview" class="image-preview">
                        <button type="button" id="removeImageBtn" class="remove-preview" title="Remove photo"><i class="fas fa-times"></i></button>
                    </div>
                    <button type="button" id="triggerConfirmBtn" class="btn-submit-modal">Submit Report</button>
                    <input type="hidden" name="btn_found_this" value="1">
                </form>
            </div>
        </div>
    </div>

    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="justify-content:center; background:#fff; border:none;">
                <i class="fas fa-question-circle fa-3x" style="color:#ffc107;"></i>
            </div>
            <div class="modal-body" style="padding-top:0;">
                <h3 style="margin-top:0; color:#333;">Confirm Submission</h3>
                <p style="font-size:1.1em; color:#555;">Are you sure you want to mark this item as <strong>FOUND</strong> by you?</p>
                <div style="margin-top:25px; display:flex; justify-content:center; gap:10px;">
                    <button type="button" class="btn-confirm-no" id="cancelConfirmBtn">Cancel</button>
                    <button type="button" class="btn-confirm-yes" id="yesConfirmBtn">Yes, I'm Sure</button>
                </div>
            </div>
        </div>
    </div>

    <div id="returnModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-check-circle"></i> Confirm Return</h2>
                <span class="close-modal" onclick="document.getElementById('returnModal').style.display='none'">&times;</span>
            </div>
            <div class="modal-body" style="text-align:center;">
                <p style="font-size:1.1rem; margin-bottom:20px;">
                    Have you successfully handed over the item to the owner or the Admin Office?
                </p>
                <form method="POST">
                    <input type="hidden" name="item_id" value="<?= $item_id ?>">
                    <button type="submit" name="btn_finder_returned" class="btn-submit-modal" style="background:#28a745;">
                        Yes, Item Returned
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background:#dc3545;">
                <h2><i class="fas fa-times-circle"></i> Cancel Return</h2>
                <span class="close-modal" onclick="document.getElementById('cancelModal').style.display='none'">&times;</span>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:15px;">Please select a reason for cancelling:</p>
                <form method="POST">
                    <input type="hidden" name="item_id" value="<?= $item_id ?>">
                    <label class="reason-option"><input type="radio" name="cancel_reason" value="Owner Unresponsive" class="reason-radio" required> Owner is unresponsive</label>
                    <label class="reason-option"><input type="radio" name="cancel_reason" value="Unable to Meet" class="reason-radio"> Unable to meet owner</label>
                    <label class="reason-option"><input type="radio" name="cancel_reason" value="Item Mismatch" class="reason-radio"> Item does not match description</label>
                    <label class="reason-option"><input type="radio" name="cancel_reason" value="Emergency" class="reason-radio"> Personal emergency</label>
                    <label class="reason-option"><input type="radio" name="cancel_reason" value="Other" class="reason-radio" checked> Other</label>
                    <button type="submit" name="btn_finder_cancel" class="btn-submit-modal" style="background:#dc3545; margin-top:15px;">Cancel Request</button>
                </form>
            </div>
        </div>
    </div>

    <div id="ownerReceiptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-check-circle"></i> Item Received</h2>
                <span class="close-modal" onclick="document.getElementById('ownerReceiptModal').style.display='none'">&times;</span>
            </div>
            <div class="modal-body" style="text-align:center;">
                <p style="font-size:1.1rem; margin-bottom:20px;">
                    Please confirm that you have successfully received your item from the Finder or the Admin Office.
                </p>
                <form method="POST">
                    <input type="hidden" name="item_id" value="<?= $item_id ?>">
                    <button type="submit" name="confirm_receipt" class="btn-submit-modal" style="background:#28a745;">
                        Yes, I have it
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="ownerCancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background:#dc3545;">
                <h2><i class="fas fa-times-circle"></i> Cancel Request</h2>
                <span class="close-modal" onclick="document.getElementById('ownerCancelModal').style.display='none'">&times;</span>
            </div>
            <div class="modal-body" style="text-align:center;">
                <p style="font-size:1.1rem; margin-bottom:20px;">
                    Are you sure you want to cancel this return request? <br><br>
                    <small>The item will be made available for others to find/claim again.</small>
                </p>
                <form method="POST">
                    <input type="hidden" name="item_id" value="<?= $item_id ?>">
                    <button type="submit" name="cancel_return" class="btn-submit-modal" style="background:#dc3545;">
                        Yes, Cancel Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <div class="footer-content">
            <div class="copyright">&copy; <?= date('Y') ?> Western Mindanao State University.</div>
        </div>
    </footer>

    <script>
        const foundModal = document.getElementById('foundModal');
        const confirmModal = document.getElementById('confirmModal');
        const openBtn = document.getElementById('openFoundModalBtn');
        const closeFoundBtn = document.getElementById('closeFoundModal');
        const triggerConfirmBtn = document.getElementById('triggerConfirmBtn');
        const cancelConfirmBtn = document.getElementById('cancelConfirmBtn');
        const yesConfirmBtn = document.getElementById('yesConfirmBtn');

        if(openBtn) openBtn.onclick = function() { foundModal.style.display = "block"; }
        if(closeFoundBtn) closeFoundBtn.onclick = function() { foundModal.style.display = "none"; }

        if(triggerConfirmBtn) {
            triggerConfirmBtn.onclick = function() {
                const fileInput = document.getElementById('finderProof');
                if (fileInput.files.length === 0) {
                    alert("Please upload a photo of the item first.");
                    return;
                }
                confirmModal.style.display = "block";
            }
        }

        if(cancelConfirmBtn) cancelConfirmBtn.onclick = function() { confirmModal.style.display = "none"; }
        if(yesConfirmBtn) yesConfirmBtn.onclick = function() { document.getElementById('foundForm').submit(); }

        const dropZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('finderProof');
        const previewImg = document.getElementById('imagePreview');
        const uploadContent = document.getElementById('uploadContent');
        const removeBtn = document.getElementById('removeImageBtn');

        if (dropZone) {
            fileInput.addEventListener('change', function() { showPreview(this.files[0]); });
            dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
            dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('dragover'); });
            dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; showPreview(e.dataTransfer.files[0]); } });
            removeBtn.addEventListener('click', (e) => { e.stopPropagation(); fileInput.value = ''; resetPreview(); });
        }

        function showPreview(file) {
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) { previewImg.src = e.target.result; previewImg.style.display = 'block'; uploadContent.style.display = 'none'; removeBtn.style.display = 'flex'; }
                reader.readAsDataURL(file);
            }
        }
        function resetPreview() { previewImg.src = ''; previewImg.style.display = 'none'; uploadContent.style.display = 'block'; removeBtn.style.display = 'none'; }

        // General Modal Closing Logic
        window.onclick = function(event) {
            if (event.target == foundModal) foundModal.style.display = "none";
            if (event.target == confirmModal) confirmModal.style.display = "none";
            if (event.target == document.getElementById('returnModal')) document.getElementById('returnModal').style.display = "none";
            if (event.target == document.getElementById('cancelModal')) document.getElementById('cancelModal').style.display = "none";
            if (event.target == document.getElementById('ownerReceiptModal')) document.getElementById('ownerReceiptModal').style.display = "none";
            if (event.target == document.getElementById('ownerCancelModal')) document.getElementById('ownerCancelModal').style.display = "none";
        }
    </script>

</body>
</html>