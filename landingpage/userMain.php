<?php
session_start(); 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once "../classes/lostitems.php"; 
require_once "../classes/founditems.php"; 
require_once "../classes/student.php"; 

$lostItemsObj = new LostItems(); 
$foundItemsObj = new FoundItems(); 
$studentObj = new Student(); 


$recent_lost_items = $lostItemsObj->viewActiveLostReports("", "");  
if ($recent_lost_items === null) $recent_lost_items = []; 
$recent_lost_items = array_slice($recent_lost_items, 0, 5); 

$recent_found_items = $foundItemsObj->viewActiveFoundReports("", ""); 
if ($recent_found_items === null) $recent_found_items = []; 
$recent_found_items = array_slice($recent_found_items, 0, 5); 

$student_data = $studentObj->getStudentById($_SESSION['user_id']); 
$student_name = "";
if ($student_data) {
    $student_name = htmlspecialchars($student_data['First_Name'] . ' ' . $student_data['Last_Name']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WMSU Lost & Found</title>
    <link rel="stylesheet" href="../styles/landingpage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <nav class="wmsu-navbar">
        <a href="../landingpage/userMain.php" class="brand-container">
            <img src="../images/wmsu_logo.jpg" alt="WMSU Logo" class="brand-logo">
            <span class="brand-text">Lost & Found</span>
        </a>

        <div class="nav-buttons">
            <a href="../account/myReports.php" class="btn-nav btn-login">My Dashboard</a>
            <button onclick="openLogoutModal()" class="btn-nav" style="background-color: #dc3545; color: white; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </nav>
    
    <div class="dashboard-container" style="margin-top: 40px;">
        <div class="dashboard-header">
            <h2>WELCOME, <?= $student_name ?></h2>
        </div>

        <div style="margin-bottom: 30px; display: flex; gap: 15px; justify-content: center;">
            <a href="../lostitems/reportLost.php" class="btn-nav" style="background: var(--wmsu-red); color: white;">+ Report Lost Item</a>
            <a href="../founditems/reportFound.php" class="btn-nav" style="background: #2c3e50; color: white;">+ Report Found Item</a>
            <a href="../lostitems/viewLostItems.php" class="btn-nav" style="border: 1px solid #ccc; color: #333;">Browse All Lost</a>
            <a href="../founditems/viewFoundItems.php" class="btn-nav" style="border: 1px solid #ccc; color: #333;">Browse All Found</a>
        </div>

        <div class="grid-layout">
            <div class="lost-col">
                <h3 class="column-header">Recently Lost</h3>
                <?php if (!empty($recent_lost_items)): ?>
                    <?php foreach($recent_lost_items as $item): ?>
                        <?php 
                            $detail_url = "../lostitems/viewItemDetails.php?item_id=" . $item["ItemID"] . "&from=landing"; 
                            $photo_path = !empty($item["PhotoURL"]) ? "../" . htmlspecialchars($item["PhotoURL"]) : "../images/placeholder.png"; 
                        ?>
                        <a href="<?= htmlspecialchars($detail_url) ?>" style="text-decoration: none;">
                            <div class="item-card">
                                <div class="card-image">
                                    <img src="<?= $photo_path ?>" alt="<?= htmlspecialchars($item["ItemName"]) ?>">
                                </div>
                                <div class="card-details">
                                    <span class="card-category"><?= htmlspecialchars($item['Category']) ?></span>
                                    <strong><?= htmlspecialchars($item["ItemName"]) ?></strong>
                                    <div class="card-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item['ReportLocation']) ?></span>
                                    </div>
                                </div>
                                <div class="card-arrow"><i class="fas fa-chevron-right"></i></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; color:#888;">No recent lost items.</p>
                <?php endif; ?>
            </div>

            <div class="found-col">
                <h3 class="column-header">Recently Found</h3>
                 <?php if (!empty($recent_found_items)): ?>
                    <?php foreach($recent_found_items as $item): ?>
                         <?php 
                            $claim_url = "../founditems/claimItem.php?item_id=" . $item["ItemID"] . "&from=landing"; 
                            $photo_path = !empty($item["Photo"]) ? "../" . htmlspecialchars($item["Photo"]) : "../images/placeholder.png"; 
                         ?>
                        <a href="<?= htmlspecialchars($claim_url) ?>" style="text-decoration: none;">
                            <div class="item-card">
                                <div class="card-image">
                                    <img src="<?= $photo_path ?>" alt="<?= htmlspecialchars($item["ItemName"]) ?>">
                                </div>
                                <div class="card-details">
                                    <span class="card-category"><?= htmlspecialchars($item['Category']) ?></span>
                                    <strong><?= htmlspecialchars($item["ItemName"]) ?></strong>
                                    <div class="card-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item['ReportLocation']) ?></span>
                                    </div>
                                </div>
                                <div class="card-arrow"><i class="fas fa-chevron-right"></i></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; color:#888;">No recent found items.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="logoutModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeLogoutModal()">&times;</span>
            <h2><i class="fas fa-sign-out-alt"></i> Confirm Logout</h2>
            <p>Are you sure you want to log out?</p>
            <div class="modal-buttons">
                <button class="btn-modal btn-cancel" onclick="closeLogoutModal()">Cancel</button>
                <a href="../account/logout.php" class="btn-modal btn-confirm">Yes, Logout</a>
            </div>
        </div>
    </div>
    <script>
        function openLogoutModal() { document.getElementById("logoutModal").style.display = "block"; }
        function closeLogoutModal() { document.getElementById("logoutModal").style.display = "none"; }
        window.onclick = function(event) {
            var logoutModal = document.getElementById("logoutModal");
            if (event.target == logoutModal) logoutModal.style.display = "none";
        }
    </script>

    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <img src="../images/wmsu_logo.jpg" alt="WMSU Logo" class="brand-logo">
                <div>
                    <strong>WMSU Lost & Found</strong><br>
                    <small>Official Reporting System</small>
                </div>
            </div>
            <div class="copyright">&copy; <?= date('Y') ?> Western Mindanao State University. All Rights Reserved.</div>
        </div>
    </footer>
</body>
</html>