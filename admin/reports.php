<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../landingpage/index.php"); 
    exit("Access Denied.");
}

require_once "../classes/LostItems.php"; 
require_once "../classes/FoundItems.php"; 
require_once "../classes/Database.php"; 

$lostItemsObj = new LostItems();
$foundItemsObj = new FoundItems();
$dbObj = new Database(); 

// --- Initialize Filters ---
$filters = [
    'report_type' => $_GET['report_type'] ?? 'lost_items',
    'status'      => $_GET['status'] ?? '',
    'category'    => $_GET['category'] ?? '',
    'date_start'  => $_GET['date_start'] ?? date('Y-m-01'), // Default to start of current month
    'date_end'    => $_GET['date_end'] ?? date('Y-m-d')      // Default to today
];

$data = null;
$page_title = "Reports";
$report_summary = "";

// --- Fetch Data Based on Report Type ---
switch ($filters['report_type']) {
    case 'returned_items':
        $page_title = "Returned Items Report";
        $data = $dbObj->getReturnedItemsReport($filters['date_start'], $filters['date_end']);
        break;
    case 'found_items':
        $page_title = "Found Items Report";
        $data = $foundItemsObj->viewAllFoundReports($filters['search'] ?? '', $filters['category'], $filters['status']);
        // Date filtering for Found Items (if not built into the class method, we filter manually or adjust query)
        // For this example, we assume the class method handles it or we filter the array:
        if (!empty($data) && ($filters['date_start'] || $filters['date_end'])) {
            $data = array_filter($data, function($item) use ($filters) {
                $d = date('Y-m-d', strtotime($item['DateFound']));
                return (!$filters['date_start'] || $d >= $filters['date_start']) && 
                       (!$filters['date_end'] || $d <= $filters['date_end']);
            });
        }
        break;
    case 'lost_items':
    default:
        $page_title = "Lost Items Report";
        $data = $lostItemsObj->getLostItemReport($filters);
        break;
}

$total_records = $data ? count($data) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Admin</title>
    <link rel="stylesheet" href="../styles/landingpage.css">
    <link rel="stylesheet" href="../styles/admin.css">
    <link rel="stylesheet" href="../styles/print.css" media="print">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Local specific styles for reports page */
        .summary-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid var(--wmsu-red);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .summary-info h3 { margin: 0 0 5px 0; color: var(--text-dark); font-size: 1.5rem; }
        .summary-info p { margin: 0; color: #666; font-size: 0.9rem; }
        .summary-icon { font-size: 2.5rem; color: #ddd; }
        
        /* Ensure form lays out horizontally */
        .report-form {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
            width: 100%;
        }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-actions { display: flex; gap: 10px; }
        
        @media (max-width: 768px) {
            .report-form { flex-direction: column; align-items: stretch; }
            .filter-actions { margin-top: 10px; }
        }
    </style>
</head>
<body>

    <aside class="admin-sidebar">
        <div class="admin-sidebar-header">
            <h2><i class="fas fa-user-shield"></i> Admin Panel</h2>
        </div>
        <nav>
            <ul>
                <li><a href="dashboard.php?view=pending_claims"><i class="fas fa-gavel"></i> Pending Claims</a></li>
                <li><a href="dashboard.php?view=pending_returns"><i class="fas fa-undo-alt"></i> Pending Returns</a></li>
                <li><a href="dashboard.php?view=all_lost"><i class="fas fa-search"></i> All Lost Items</a></li>
                <li><a href="dashboard.php?view=all_found"><i class="fas fa-hand-holding-heart"></i> All Found Items</a></li>
                <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
            </ul>
        </nav>
         <div class="logout-section">
             <a href="../account/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
         </div>
    </aside>

    <main class="admin-main-content">
        <div class="page-header">
            <h1>Report Generation</h1>
            <div style="color: #666; font-size: 0.9rem;">
                <i class="fas fa-calendar-alt"></i> <?= date('F j, Y') ?>
            </div>
        </div>
        
        <div class="filters-bar">
            <form action="reports.php" method="get" class="report-form">
                <div class="filter-group">
                    <label for="report_type">Report Type</label>
                    <select name="report_type" id="report_type" class="form-control" onchange="this.form.submit()">
                        <option value="lost_items" <?= ($filters['report_type'] == 'lost_items') ? 'selected' : '' ?>>Lost Items</option>
                        <option value="found_items" <?= ($filters['report_type'] == 'found_items') ? 'selected' : '' ?>>Found Items</option>
                        <option value="returned_items" <?= ($filters['report_type'] == 'returned_items') ? 'selected' : '' ?>>Returned Items</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date_start">From Date</label>
                    <input type="date" name="date_start" id="date_start" class="form-control" value="<?= htmlspecialchars($filters['date_start']) ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date_end">To Date</label>
                    <input type="date" name="date_end" id="date_end" class="form-control" value="<?= htmlspecialchars($filters['date_end']) ?>">
                </div>

                <?php if ($filters['report_type'] != 'returned_items'): ?>
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="Reported" <?= ($filters['status'] == 'Reported') ? 'selected' : '' ?>>Reported</option>
                            <option value="Claimed" <?= ($filters['status'] == 'Claimed') ? 'selected' : '' ?>>Claimed</option>
                            <option value="Returned" <?= ($filters['status'] == 'Returned') ? 'selected' : '' ?>>Returned</option>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                    <?php if (!empty($data)): ?>
                        <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (!empty($data)): ?>
        <div class="summary-card">
            <div class="summary-info">
                <h3><?= htmlspecialchars($total_records) ?> Records Found</h3>
                <p>
                    Showing <strong><?= str_replace('_', ' ', ucfirst($filters['report_type'])) ?></strong> 
                    from <?= date('M d', strtotime($filters['date_start'])) ?> to <?= date('M d', strtotime($filters['date_end'])) ?>.
                </p>
            </div>
            <div class="summary-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
        </div>
        <?php endif; ?>

        <div class="print-only-title">
            <h2>WMSU Lost & Found - <?= htmlspecialchars($page_title) ?></h2>
            <p>Generated on: <?= date('Y-m-d H:i:s') ?></p>
        </div>

        <div class="report-results">
            <?php if (empty($data)): ?>
                <div class="no-data">
                    <i class="fas fa-folder-open fa-3x" style="margin-bottom:15px; color:#e0e0e0;"></i>
                    <br>No records found matching your selection.
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <?php if ($filters['report_type'] == 'lost_items'): ?>
                                <tr>
                                    <th>ID</th>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Date Lost</th>
                                    <th>Location</th>
                                    <th>Reported By</th>
                                </tr>
                            <?php elseif ($filters['report_type'] == 'found_items'): ?>
                                <tr>
                                    <th>ID</th>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Date Found</th>
                                    <th>Location</th>
                                    <th>Finder</th>
                                </tr>
                            <?php elseif ($filters['report_type'] == 'returned_items'): ?>
                                <tr>
                                    <th>ID</th>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Date Returned</th>
                                    <th>Reporter</th>
                                    <th>Finder/Claimant</th>
                                </tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                            <?php foreach($data as $row): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($row['ItemID']) ?></td>
                                    <td><strong><?= htmlspecialchars($row['ItemName']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['Category']) ?></td>
                                    
                                    <?php if ($filters['report_type'] != 'returned_items'): ?>
                                        <td>
                                            <?php 
                                                $statusClass = 'status-' . str_replace(' ', '-', strtolower($row['ItemStatus'])); 
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>">
                                                <?= htmlspecialchars($row['ItemStatus']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars(date('M d, Y', strtotime($filters['report_type'] == 'lost_items' ? $row['DateLost'] : $row['DateFound']))) ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['ReportLocation']) ?></td>
                                    <?php else: ?>
                                        <td><?= htmlspecialchars(date('M d, Y', strtotime($row['DateReported']))) ?></td>
                                    <?php endif; ?>

                                    <td><?= htmlspecialchars($row['ReporterFirstName'] . ' ' . $row['ReporterLastName']) ?></td>
                                    
                                    <?php if ($filters['report_type'] == 'returned_items'): ?>
                                        <td><?= htmlspecialchars(($row['FinderFirstName'] ?? '') . ' ' . ($row['FinderLastName'] ?? '')) ?: '<span style="color:#ccc;">N/A</span>' ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>