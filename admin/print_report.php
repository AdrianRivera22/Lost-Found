<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    exit("Access Denied.");
}

require_once "../classes/LostItems.php"; 
require_once "../classes/FoundItems.php"; 
require_once "../classes/Database.php"; 

$lostItemsObj = new LostItems();
$foundItemsObj = new FoundItems();
$dbObj = new Database(); 

$filters = [
    'report_type' => $_GET['report_type'] ?? 'lost_items',
    'status'      => $_GET['status'] ?? '',
    'category'    => $_GET['category'] ?? '',
    'date_start'  => $_GET['date_start'] ?? date('Y-m-01'),
    'date_end'    => $_GET['date_end'] ?? date('Y-m-d')
];

$data = null;
$page_title = "Reports";

switch ($filters['report_type']) {
    case 'returned_items':
        $page_title = "Returned Items Report";
        $data = $dbObj->getReturnedItemsReport($filters['date_start'], $filters['date_end']);
        break;
    case 'found_items':
        $page_title = "Found Items Report";
        $data = $foundItemsObj->viewAllFoundReports($_GET['search'] ?? '', $filters['category'], $filters['status']);
        // Manual Date Filter for Found Items
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
    <title>Print Report - <?= htmlspecialchars($page_title) ?></title>
    <style>

        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 12pt;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 { margin: 0; font-size: 18pt; text-transform: uppercase; }
        .header p { margin: 5px 0 0; color: #555; font-size: 10pt; }
        
        .meta-info {
            margin-bottom: 20px;
            font-size: 10pt;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            vertical-align: top;
            font-size: 10pt;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            border: 1px dashed #ccc;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 9pt;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        @media print {
            @page { margin: 0.5in; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h1>WMSU Lost & Found - <?= htmlspecialchars($page_title) ?></h1>
        <p>Generated on <?= date('F j, Y g:i A') ?></p>
    </div>

    <div class="meta-info">
        <strong>Report Period:</strong> 
        <?= date('M d, Y', strtotime($filters['date_start'])) ?> to <?= date('M d, Y', strtotime($filters['date_end'])) ?>
        <br>
        <strong>Records Found:</strong> <?= $total_records ?>
        <?php if($filters['status']): ?>
            <br><strong>Status Filter:</strong> <?= htmlspecialchars($filters['status']) ?>
        <?php endif; ?>
    </div>

    <?php if (empty($data)): ?>
        <div class="no-data">No records found for this selection.</div>
    <?php else: ?>
        <table>
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
                        <td><?= htmlspecialchars($row['ItemName']) ?></td>
                        <td><?= htmlspecialchars($row['Category']) ?></td>
                        
                        <?php if ($filters['report_type'] != 'returned_items'): ?>
                            <td><?= htmlspecialchars($row['ItemStatus']) ?></td>
                            <td><?= htmlspecialchars(date('M d, Y', strtotime($filters['report_type'] == 'lost_items' ? $row['DateLost'] : $row['DateFound']))) ?></td>
                            <td><?= htmlspecialchars($row['ReportLocation']) ?></td>
                        <?php else: ?>
                            <td><?= htmlspecialchars(date('M d, Y', strtotime($row['DateReported']))) ?></td>
                        <?php endif; ?>

                        <td><?= htmlspecialchars($row['ReporterFirstName'] . ' ' . $row['ReporterLastName']) ?></td>
                        
                        <?php if ($filters['report_type'] == 'returned_items'): ?>
                            <td><?= htmlspecialchars(($row['FinderFirstName'] ?? '') . ' ' . ($row['FinderLastName'] ?? '')) ?: 'N/A' ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="footer">
        Confidential Report - For Administrative Use Only
    </div>

</body>
</html>