<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: loginAccount.php");
    exit();
}

require_once "../classes/LostItems.php";
require_once "../classes/FoundItems.php";
require_once "../classes/Student.php";
require_once "../classes/Notification.php"; 

$lostItemObj = new LostItems();
$foundItemObj = new FoundItems();
$studentObj = new Student();
$notifyObj = new Notification();

$user_id = $_SESSION['user_id'];
$student_data = $studentObj->getStudentById($user_id);

// --- FORM HANDLING ---

// Mark Notifications Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notifications_read'])) {
    $unread_notifs = $notifyObj->getUnreadNotifications($user_id);
    if ($unread_notifs) {
        $notif_ids = array_column($unread_notifs, 'NotificationID');
        $notifyObj->markNotificationsAsRead($user_id, $notif_ids);
    }
    header("Location: myReports.php");
    exit();
}

// --- DATA FETCHING ---
$my_lost_items = $lostItemObj->viewMyLostReports($user_id);
$my_found_items = $foundItemObj->viewMyFoundReports($user_id);
$notifications = $notifyObj->getUnreadNotifications($user_id);

// 1. Items I will be GETTING (Incoming)
$pending_returns = $lostItemObj->getPendingReturnsForUser($user_id);

// 2. Items I will be RETURNING (Outgoing)
$outgoing_returns = $lostItemObj->getOutgoingReturnsForUser($user_id);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - WMSU Lost & Found</title>
    <link rel="stylesheet" href="../styles/myReports.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../styles/landingpage.css?v=<?php echo time(); ?>"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Styles for the new sections */
        .section-split-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .pickup-section {
            background: #fff;
            border-left: 5px solid #28a745; /* Green for incoming */
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .pickup-section.outgoing {
            border-left-color: #17a2b8; /* Blue for outgoing */
            background-color: #f0f8ff;
        }

        .pickup-header {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pickup-section.outgoing .pickup-header {
            color: #17a2b8;
        }

        .pickup-card {
            display: flex;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            align-items: center;
            gap: 15px;
        }

        .pickup-item-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
        }

        .pickup-details {
            flex: 1;
        }

        .pickup-details h4 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .contact-box {
            flex: 1;
            padding-left: 15px;
            border-left: 1px solid #eee;
            font-size: 0.9em;
        }

        .btn-view-manage {
            display: inline-block;
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.2s;
            margin-top: 5px;
        }
        .btn-view-manage:hover { background-color: #0056b3; }

        .hidden-overlay { color: #6c757d; font-style: italic; display: flex; align-items: center; gap: 5px; }
        
        .no-items-msg {
            color: #666;
            font-style: italic;
            padding: 15px;
            background: rgba(0,0,0,0.02);
            border-radius: 5px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .pickup-card { flex-direction: column; align-items: flex-start; }
            .contact-box { border-left: none; padding-left: 0; margin-top: 10px; width: 100%; border-top: 1px solid #eee; padding-top: 10px; }
        }
    </style>
</head>
<body>

    <nav class="wmsu-navbar">
        <a href="../landingpage/index.php" class="brand-container">
            <img src="../images/wmsu_logo.jpg" alt="WMSU Logo" class="brand-logo">
            <span class="brand-text">Lost & Found</span>
        </a>
        <div class="nav-buttons">
            <a href="profile.php" class="btn-nav-profile">
                <i class="fas fa-user"></i> My Profile
            </a>
            <a href="../landingpage/index.php" class="btn-nav-back">
                <i class="fas fa-home"></i> Home
            </a>
            <button onclick="openLogoutModal()" class="btn-nav btn-register" style="background-color: #dc3545; color: white; border: none; cursor: pointer;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </nav>

    <div class="main-container">
        
        <div class="dashboard-welcome">
            <h2>Welcome, <?= htmlspecialchars($student_data['First_Name']) ?>!</h2>
            <p>Manage your reported items and track their status here.</p>
            <div style="margin-top: 20px; display:flex; gap:15px; justify-content:center;">
                <a href="../lostitems/reportLost.php" class="btn-add-new"><i class="fas fa-plus"></i> Report Lost Item</a>
                <a href="../founditems/reportFound.php" class="btn-add-new found"><i class="fas fa-plus"></i> Report Found Item</a>
            </div>
        </div>

        <div class="notification-section">
            <div class="notif-header">
                <h3><i class="fas fa-bell"></i> Notifications</h3>
                <?php if (!empty($notifications)): ?>
                    <form method="POST" style="margin:0;">
                        <button type="submit" name="mark_notifications_read" class="btn-mark-read">Mark all as read</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($notifications)): ?>
                <ul class="notification-list">
                    <?php foreach ($notifications as $notif): ?>
                        <li class="notification-item">
                            <i class="fas fa-info-circle notif-icon"></i>
                            <div class="notif-content">
                                <span class="notif-message"><?= htmlspecialchars($notif['Message']) ?></span>
                                <span class="notif-time"><?= date('M d, Y h:i A', strtotime($notif['Timestamp'])) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty-notif">No new notifications.</p>
            <?php endif; ?>
        </div>

        <div class="section-split-container">
            
            <div class="pickup-section">
                <div class="pickup-header">
                    <i class="fas fa-arrow-down"></i> Items To Receive (Incoming)
                </div>
                
                <?php if (empty($pending_returns)): ?>
                    <div class="no-items-msg">
                        <i class="fas fa-box-open" style="margin-right:5px;"></i> There are no incoming items to receive yet.
                    </div>
                <?php else: ?>
                    <p style="margin-bottom: 15px; color:#555;">These items have been found/claimed and are being processed.</p>
                    <?php foreach ($pending_returns as $item): ?>
                        <?php 
                            $photo = !empty($item["PhotoURL"]) ? "../" . htmlspecialchars($item["PhotoURL"]) : "../images/placeholder.png"; 
                            $is_approved = in_array($item['ItemStatus'], ['Accepted', 'Verified']);
                            
                            $detail_link = ($item['ReportType'] === 'Lost') 
                                ? "../lostitems/viewItemDetails.php?item_id=" . $item['ItemID'] . "&from=dashboard"
                                : "../founditems/viewFoundItemDetails.php?item_id=" . $item['ItemID'] . "&from=dashboard";
                        ?>
                        <div class="pickup-card">
                            <img src="<?= $photo ?>" alt="Item" class="pickup-item-img">
                            <div class="pickup-details">
                                <h4><?= htmlspecialchars($item['ItemName']) ?></h4>
                                <p><strong>Category:</strong> <?= htmlspecialchars($item['Category']) ?></p>
                                <p><strong>Status:</strong> <span class="badge-<?= strtolower($item['ItemStatus']) ?>"><?= htmlspecialchars($item['ItemStatus']) ?></span></p>
                            </div>
                            <div class="contact-box">
                                <strong>Contact:</strong> 
                                <?php if ($is_approved): ?>
                                    <?= htmlspecialchars($item['ContactFirst'] . ' ' . $item['ContactLast']) ?>
                                <?php else: ?>
                                    <span class="hidden-overlay"><i class="fas fa-lock"></i> Hidden</span>
                                <?php endif; ?>
                                <br>
                                <a href="<?= $detail_link ?>" class="btn-view-manage">
                                    <i class="fas fa-eye"></i> View Details to Manage
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="pickup-section outgoing">
                <div class="pickup-header">
                    <i class="fas fa-arrow-up"></i> Items To Return (Outgoing)
                </div>
                
                <?php if (empty($outgoing_returns)): ?>
                    <div class="no-items-msg">
                        <i class="fas fa-check-circle" style="margin-right:5px;"></i> There are no items pending return at the moment.
                    </div>
                <?php else: ?>
                    <p style="margin-bottom: 15px; color:#555;">These are items you are holding or reported finding. Please return them to the owner.</p>
                    <?php foreach ($outgoing_returns as $item): ?>
                        <?php 
                            $photo = !empty($item["PhotoURL"]) ? "../" . htmlspecialchars($item["PhotoURL"]) : "../images/placeholder.png"; 
                            $is_approved = in_array($item['ItemStatus'], ['Accepted', 'Verified']);
                            
                            $detail_link = ($item['ReportType'] === 'Lost') 
                                ? "../lostitems/viewItemDetails.php?item_id=" . $item['ItemID'] . "&from=dashboard"
                                : "../founditems/viewFoundItemDetails.php?item_id=" . $item['ItemID'] . "&from=dashboard";
                        ?>
                        <div class="pickup-card">
                            <img src="<?= $photo ?>" alt="Item" class="pickup-item-img">
                            <div class="pickup-details">
                                <h4><?= htmlspecialchars($item['ItemName']) ?></h4>
                                <p><strong>Category:</strong> <?= htmlspecialchars($item['Category']) ?></p>
                                <p><strong>Status:</strong> <span class="badge-<?= strtolower($item['ItemStatus']) ?>"><?= htmlspecialchars($item['ItemStatus']) ?></span></p>
                            </div>
                            <div class="contact-box">
                                <strong>Owner:</strong> 
                                <?php if ($is_approved): ?>
                                    <?= htmlspecialchars($item['ContactFirst'] . ' ' . $item['ContactLast']) ?>
                                <?php else: ?>
                                    <span class="hidden-overlay"><i class="fas fa-lock"></i> Hidden</span>
                                <?php endif; ?>
                                <br>
                                <a href="<?= $detail_link ?>" class="btn-view-manage">
                                    <i class="fas fa-eye"></i> View Details to Manage
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

        <h3 class="section-header"><i class="fas fa-search-location"></i> My Lost Reports</h3>
        
        <?php if (!empty($my_lost_items)): ?>
            <div class="items-grid">
                <?php foreach ($my_lost_items as $item): ?>
                    <?php 
                        $detail_url = "../lostitems/viewItemDetails.php?item_id=" . $item["ItemID"] . "&from=dashboard";
                        $edit_url = "../lostitems/editLostReport.php?item_id=" . $item["ItemID"];
                        $photo = !empty($item["PhotoURL"]) ? "../" . htmlspecialchars($item["PhotoURL"]) : "../images/placeholder.png";
                    ?>
                    <div class="item-card" onclick="window.location.href='<?= $detail_url ?>'">
                        <div class="card-header">
                            <img src="<?= $photo ?>" alt="Item Image" class="item-img">
                            <div class="item-main-info">
                                <div class="item-category"><?= htmlspecialchars($item['Category']) ?></div>
                                <div class="item-title"><?= htmlspecialchars($item['ItemName']) ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <i class="fas fa-map-marker-alt"></i> 
                                <span><?= htmlspecialchars($item['ReportLocation']) ?></span>
                            </div>
                            <div class="info-row">
                                <i class="far fa-calendar-alt"></i> 
                                <span>Lost: <?= date('M d, Y', strtotime($item['DateLost'])) ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <span class="status-badge badge-<?= strtolower(str_replace(' ', '-', $item['ItemStatus'])) ?>">
                                <?= htmlspecialchars($item['ItemStatus']) ?>
                            </span>
                            
                            <?php if ($item['ItemStatus'] === 'Reported'): ?>
                                <a href="<?= $edit_url ?>" class="btn-action-sm btn-edit" onclick="event.stopPropagation();">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            <?php else: ?>
                                <i class="fas fa-chevron-right" style="color: #ccc;"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>You haven't reported any lost items yet.</p>
            </div>
        <?php endif; ?>


        <h3 class="section-header" style="margin-top: 60px;"><i class="fas fa-hand-holding-heart"></i> My Found Reports</h3>
        
        <?php if (!empty($my_found_items)): ?>
            <div class="items-grid">
                <?php foreach ($my_found_items as $item): ?>
                    <?php 
                        $detail_url = "../founditems/viewFoundItemDetails.php?item_id=" . $item["ItemID"] . "&from=dashboard";
                        $edit_url = "../founditems/editFoundReport.php?item_id=" . $item["ItemID"];
                        $photo = !empty($item["Photo"]) ? "../" . htmlspecialchars($item["Photo"]) : "../images/placeholder.png";
                    ?>
                    <div class="item-card type-found" onclick="window.location.href='<?= $detail_url ?>'">
                        <div class="card-header">
                            <img src="<?= $photo ?>" alt="Item Image" class="item-img">
                            <div class="item-main-info">
                                <div class="item-category"><?= htmlspecialchars($item['Category']) ?></div>
                                <div class="item-title"><?= htmlspecialchars($item['ItemName']) ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <i class="fas fa-map-marker-alt"></i> 
                                <span><?= htmlspecialchars($item['ReportLocation']) ?></span>
                            </div>
                            <div class="info-row">
                                <i class="far fa-calendar-check"></i> 
                                <span>Found: <?= date('M d, Y', strtotime($item['DateFound'])) ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <span class="status-badge badge-<?= strtolower(str_replace(' ', '-', $item['ItemStatus'])) ?>">
                                <?= htmlspecialchars($item['ItemStatus']) ?>
                            </span>
                            
                            <?php if ($item['ItemStatus'] === 'Reported'): ?>
                                <a href="<?= $edit_url ?>" class="btn-action-sm btn-edit" onclick="event.stopPropagation();" style="color:#2c3e50;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            <?php else: ?>
                                <i class="fas fa-chevron-right" style="color: #ccc;"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>You haven't reported any found items yet.</p>
            </div>
        <?php endif; ?>

    </div>

    <div id="logoutModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeLogoutModal()">&times;</span>
            <h2><i class="fas fa-sign-out-alt"></i> Confirm Logout</h2>
            <p>Are you sure you want to log out?</p>
            <div class="modal-buttons">
                <button class="btn-modal btn-cancel" onclick="closeLogoutModal()">Cancel</button>
                <a href="logout.php" class="btn-modal btn-confirm">Yes, Logout</a>
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
        function openLogoutModal() { document.getElementById("logoutModal").style.display = "block"; }
        function closeLogoutModal() { document.getElementById("logoutModal").style.display = "none"; }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById("logoutModal");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>

</body>
</html>