<?php
session_start();
require_once "../classes/founditems.php";
$foundItemsObj = new FoundItems();

$search_query = "";
$category_filter = "";

if($_SERVER["REQUEST_METHOD"] == "GET") {
    $search_query = isset($_GET["search"]) ? trim(htmlspecialchars($_GET["search"])) : "";
    $category_filter = isset($_GET["category_filter"]) ? trim(htmlspecialchars($_GET["category_filter"])) : "";
}

$found_reports = $foundItemsObj->viewActiveFoundReports($search_query, $category_filter);
$categories = ['Electronics', 'ID/Documents', 'Keys', 'Bags/Clothing', 'Books/Stationery', 'Other'];

$message = "";
$message_type = "info";

if (isset($_SESSION['report_success'])) {
    $message = $_SESSION['report_success'];
    $message_type = "success";
    unset($_SESSION['report_success']);
} elseif ($found_reports === null) {
    $message = "❌ Error retrieving found item reports.";
} elseif (empty($found_reports)) {
    $message = "🔍 No items match your search criteria.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Found Items - WMSU</title>
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

    <div class="main-container found-theme">
        
        <div class="page-header found-header">
            <h1>All Found Items</h1>
            <p>These items have been reported found. Is one of them yours?</p>
        </div>

        <?php if ($message && empty($found_reports) && $message_type !== 'success'): ?>
             <div style="text-align: center; padding: 15px; margin-bottom: 20px; border-radius: 8px; 
                background-color: #fff3cd; color: #856404;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php elseif($message && $message_type === 'success'): ?>
             <div style="text-align: center; padding: 15px; margin-bottom: 20px; border-radius: 8px; 
                background-color: #d1e7dd; color: #0f5132;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="search-container">
            <form action="" method="get" class="search-form">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search by item name or description..." 
                       value="<?= htmlspecialchars($search_query) ?>">
                
                <select name="category_filter" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= $category_filter == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
            </form>
            
            <a href="../founditems/reportFound.php" class="btn-report">
                <i class="fas fa-plus-circle"></i> Report Found Item
            </a>
        </div>

        <?php if ($found_reports !== null && !empty($found_reports)): ?>
            <div class="items-grid">
                <?php foreach($found_reports as $report): ?>
                    <?php 
                        $claim_message = "Are you sure you want to file a claim for the " . htmlspecialchars(addslashes($report["ItemName"])) . "?";
                        $detail_url = "claimItem.php?item_id=" . $report["ItemID"];
                        $photo = !empty($report["Photo"]) ? "../" . htmlspecialchars($report["Photo"]) : "../images/placeholder.png";
                    ?>
                    
                    <div class="item-card type-found" onclick="window.location.href='<?= $detail_url ?>'">
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
                                <i class="far fa-calendar-check"></i> 
                                <span>Found on: <?= date('M d, Y', strtotime($report['DateFound'])) ?></span>
                            </div>
                            <div class="info-row" style="margin-top:10px; font-style:italic;">
                                "<?= htmlspecialchars(substr($report['Description'], 0, 50)) . (strlen($report['Description']) > 50 ? '...' : '') ?>"
                            </div>
                        </div>

                        <div class="card-footer">
                            <span class="status-badge badge-reported">Available</span>

                            <a href="<?= $detail_url ?>" 
                               class="btn-action btn-claim"
                               onclick="event.stopPropagation();">
                               <i class="fas fa-hand-holding-heart"></i> Claim Item
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif(empty($message)): ?>
             <p style="text-align:center; padding: 40px; color:#777;">No items found.</p>
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