<?php
session_start(); 

if (isset($_SESSION['user_id'])) {
    header("Location: landingpage/userMain.php");
    exit();
}

require_once "classes/lostitems.php"; 
require_once "classes/founditems.php"; 

$lostItemsObj = new LostItems(); 
$foundItemsObj = new FoundItems(); 


$recent_lost_items = $lostItemsObj->viewActiveLostReports("", "");  
if ($recent_lost_items === null) $recent_lost_items = []; 
$recent_lost_items = array_slice($recent_lost_items, 0, 5); 

$recent_found_items = $foundItemsObj->viewActiveFoundReports("", ""); 
if ($recent_found_items === null) $recent_found_items = []; 
$recent_found_items = array_slice($recent_found_items, 0, 5); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMSU Lost & Found System</title>
    <link rel="stylesheet" href="styles/landingpage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <nav class="wmsu-navbar">
        <a href="index.php" class="brand-container">
            <img src="images/wmsu_logo.jpg" alt="WMSU Logo" class="brand-logo">
            <span class="brand-text">Lost & Found</span>
        </a>

        <div class="nav-buttons">
            <a href="account/loginAccount.php" class="btn-nav btn-login">Login</a>
            <a href="account/registerAccount.php" class="btn-nav btn-register">Register</a>
        </div>
    </nav>
    
    <header class="hero-section">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1>Welcome to</h1>
            <h2>Lost & Found</h2>
            <h3>Reporting System</h3>
        </div>
    </header>

    <section class="features-container">
        <div class="feature-box">
            <div class="feature-title"><i class="fas fa-search"></i> Centralized Search</div>
            <p class="feature-desc">Stop wasting time checking multiple physical offices. Our dashboard provides a visual, centralized feed.</p>
        </div>
        <div class="feature-box">
            <div class="feature-title"><i class="fas fa-sync-alt"></i> Real-Time Tracking</div>
            <p class="feature-desc">Track the status of your reports in real-time.</p>
        </div>
        <div class="feature-box">
            <div class="feature-title"><i class="fas fa-shield-alt"></i> Secure Verification</div>
            <p class="feature-desc">Security is our top priority with built-in Secret Detail Verification.</p>
        </div>
    </section>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h2>Recently Reported Items</h2>
        </div>
        <div class="grid-layout">
             <div class="lost-col">
                <h3 class="column-header">Lost Items</h3>
                <?php foreach($recent_lost_items as $item): 
                    
                    $photo = !empty($item["PhotoURL"]) ? htmlspecialchars($item["PhotoURL"]) : "images/placeholder.png";
                ?>
                    <div class="item-card item-card-trigger" onclick="openLoginModal()">
                        <div class="card-image">
                            <img src="<?= $photo ?>" alt="Item">
                        </div>
                        <div class="card-details">
                            <span class="card-category"><?= htmlspecialchars($item['Category']) ?></span>
                            <strong><?= htmlspecialchars($item['ItemName']) ?></strong>
                            <div class="card-meta">
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item['ReportLocation']) ?></span>
                                <span><i class="far fa-clock"></i> <?= date('M d', strtotime($item['DateLost'])) ?></span>
                            </div>
                        </div>
                        <div class="card-arrow"><i class="fas fa-chevron-right"></i></div>
                    </div>
                <?php endforeach; ?>
             </div>

             <div class="found-col">
                <h3 class="column-header">Found Items</h3>
                <?php foreach($recent_found_items as $item): 
                     
                     $photo = !empty($item["Photo"]) ? htmlspecialchars($item["Photo"]) : "images/placeholder.png";
                ?>
                    <div class="item-card item-card-trigger" onclick="openLoginModal()">
                        <div class="card-image">
                            <img src="<?= $photo ?>" alt="Item">
                        </div>
                        <div class="card-details">
                            <span class="card-category"><?= htmlspecialchars($item['Category']) ?></span>
                            <strong><?= htmlspecialchars($item['ItemName']) ?></strong>
                            <div class="card-meta">
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item['ReportLocation']) ?></span>
                                <span><i class="far fa-clock"></i> <?= date('M d', strtotime($item['DateFound'])) ?></span>
                            </div>
                        </div>
                        <div class="card-arrow"><i class="fas fa-chevron-right"></i></div>
                    </div>
                <?php endforeach; ?>
             </div>
        </div>
    </div>

    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeLoginModal()">&times;</span>
            <h2><i class="fas fa-lock"></i> Access Required</h2>
            <p>Please log in or create an account to view details.</p>
            <div class="modal-buttons">
                <a href="account/loginAccount.php" class="btn-modal btn-modal-login">Login</a>
                <a href="account/registerAccount.php" class="btn-modal btn-modal-register">Create Account</a>
            </div>
        </div>
    </div>
    <script>
        function openLoginModal() { document.getElementById("loginModal").style.display = "block"; }
        function closeLoginModal() { document.getElementById("loginModal").style.display = "none"; }
        window.onclick = function(event) {
            var loginModal = document.getElementById("loginModal");
            if (event.target == loginModal) loginModal.style.display = "none";
        }
    </script>

    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <img src="images/wmsu_logo.jpg" alt="WMSU Logo" class="brand-logo">
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