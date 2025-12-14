<?php
session_start();
require_once "../classes/lostitems.php";
$lostItemsObj = new LostItems();

// Handle Flash Messages
$flash_message = "";
$flash_message_type = "";
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_message_type = $_SESSION['flash_message_type'] ?? 'info';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_message_type']);
}

if (isset($_SESSION['report_success'])) {
    $flash_message = $_SESSION['report_success'];
    $flash_message_type = "success";
    unset($_SESSION['report_success']);
}

// Filters
$search_query = isset($_GET["search"]) ? trim(htmlspecialchars($_GET["search"])) : "";
$category_filter = isset($_GET["category_filter"]) ? trim(htmlspecialchars($_GET["category_filter"])) : "";

// Fetch Data
$lost_reports = $lostItemsObj->viewActiveLostReports($search_query, $category_filter);
$categories = ['Electronics', 'ID/Documents', 'Keys', 'Bags/Clothing', 'Books/Stationery', 'Other'];
$current_user_id = $_SESSION['user_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost Items - WMSU</title>
    <link rel="stylesheet" href="../styles/viewLostItems.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <nav class="wmsu-navbar">
        <a href="../landingpage/userMain.php" class="brand-container">
            <img src="../images/wmsu_logo.jpg" alt="WMSU Logo" class="brand-logo">
            <span class="brand-text">Lost & Found</span>
        </a>
        <div class="nav-buttons">
            <a href="../landingpage/userMain.php" class="btn-nav-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </nav>

    <div class="main-container">
        
        <div class="page-header">
            <h1>All Lost Items</h1>
            <p>Browse reports to see if you can help locate missing belongings.</p>
        </div>

        <?php if ($flash_message): ?>
            <div style="text-align: center; padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; 
                background-color: <?= $flash_message_type === 'success' ? '#d1e7dd' : '#f8d7da' ?>;
                color: <?= $flash_message_type === 'success' ? '#0f5132' : '#842029' ?>;">
                <?= htmlspecialchars($flash_message) ?>
            </div>
        <?php endif; ?>

        <div class="search-container">
            <form action="" method="get" class="search-form">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search by name or description..." 
                       value="<?= htmlspecialchars($search_query) ?>">
                
                <select name="category_filter" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= $category_filter == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
            </form>
            
            <a href="../lostitems/reportLost.php" class="btn-report">
                <i class="fas fa-plus-circle"></i> Report Lost Item
            </a>
        </div>

        <?php if ($lost_reports === null): ?>
            <p style="text-align: center; color: #dc3545;">Error fetching data.</p>
        <?php elseif (empty($lost_reports)): ?>
            <div style="text-align: center; padding: 50px; color: #888;">
                <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 15px;"></i>
                <p>No lost items match your criteria.</p>
            </div>
        <?php else: ?>
            <div class="items-grid">
                <?php foreach($lost_reports as $report): ?>
                    <?php 
                        $detail_url = "viewItemDetails.php?item_id=" . $report["ItemID"];
                        $found_url = "foundLostItem.php?item_id=" . $report["ItemID"];
                        
                        // PLACEHOLDER LOGIC
                        $photo = !empty($report["PhotoURL"]) ? "../" . htmlspecialchars($report["PhotoURL"]) : "../images/placeholder.png";
                        
                        $is_own_item = ($current_user_id == $report['ReporterUserID']);
                    ?>
                    
                    <div class="item-card" onclick="window.location.href='<?= $detail_url ?>'">
                        <div class="card-header">
                            <img src="<?= $photo ?>" alt="Item Image" class="item-img">
                            <div class="item-main-info">
                                <div class="item-category"><?= htmlspecialchars($report['Category']) ?></div>
                                <div class="item-title"><?= htmlspecialchars($report['ItemName']) ?></div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="info-row">
                                <i class="fas fa-map-marker-alt"></i> 
                                <span><?= htmlspecialchars($report['ReportLocation']) ?></span>
                            </div>
                            <div class="info-row">
                                <i class="far fa-calendar-alt"></i> 
                                <span>Lost on: <?= date('M d, Y', strtotime($report['DateLost'])) ?></span>
                            </div>
                            <div class="info-row" style="margin-top:10px; font-style:italic;">
                                "<?= htmlspecialchars(substr($report['Description'], 0, 50)) . (strlen($report['Description']) > 50 ? '...' : '') ?>"
                            </div>
                        </div>

                        <div class="card-footer">
                            <span class="status-badge badge-<?= strtolower($report['ItemStatus'] === 'Pending Return' ? 'returned' : strtolower($report['ItemStatus'])) ?>">
                                <?= htmlspecialchars($report['ItemStatus']) ?>
                            </span>

                            <?php if ($report['ItemStatus'] === 'Reported' && !$is_own_item): ?>
                                <a href="<?= htmlspecialchars($found_url) ?>" 
                                   class="btn-action btn-found"
                                   onclick="event.stopPropagation(); return confirm('Are you sure you found this item?');">
                                   <i class="fas fa-hand-holding"></i> I Found This
                                </a>
                            <?php endif; ?>
                            
                            <i class="fas fa-chevron-right" style="color: #ccc;"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
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