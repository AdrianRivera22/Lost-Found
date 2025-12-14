<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../landingpage/index.php"); 
    exit("Access Denied.");
}

require_once "../classes/Claim.php"; 
require_once "../classes/LostItems.php"; 
require_once "../classes/FoundItems.php"; 
require_once "../classes/Database.php"; 

$claimObj = new Claim();
$lostItemsObj = new LostItems();
$foundItemsObj = new FoundItems();
$dbObj = new Database(); 

$message = ""; 

// --- POST HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $claim_id = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);

    if (($action === 'verify' || $action === 'reject') && $claim_id && $item_id) {
        $new_claim_status = ($action === 'verify') ? 'Verified' : 'Rejected';
        $new_item_status = ($action === 'verify') ? 'Claimed' : null;

        if ($claimObj->updateClaimStatus($claim_id, $new_claim_status, $item_id, $new_item_status)) {
            $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Claim #{$claim_id} updated to {$new_claim_status}.</div>";
        } else {
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Failed to update claim status.</div>";
        }
    }
    // HANDLE ACCEPT REQUEST
    elseif ($action === 'mark_accepted' && $item_id) {
        if ($lostItemsObj->updateItemStatus($item_id, 'Accepted')) { 
             $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Request Accepted. Item #{$item_id} marked as 'Accepted'. Waiting for owner to confirm receipt.</div>";
        } else {
             $message = "<div class='alert alert-error'>Failed to update status.</div>";
        }
    }
    elseif ($action === 'delete_item' && $item_id) {
        if ($dbObj->deleteItem($item_id)) {
             $message = "<div class='alert alert-success'><i class='fas fa-trash'></i> Item #{$item_id} deleted.</div>";
        } else {
             $message = "<div class='alert alert-error'>Failed to delete item.</div>";
        }
    }
    
    if ($message) $_SESSION['flash_message'] = $message;
    $current_view = $_GET['view'] ?? 'pending_claims'; 
    header("Location: dashboard.php?view=" . urlencode($current_view));
    exit();
}

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// --- DATA FETCHING ---
$view = $_GET['view'] ?? 'pending_claims'; 
$data = null; 
$page_title = "Admin Dashboard"; 

$search_filter = $_GET['search'] ?? ''; 
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

$count_pending_claims = method_exists($claimObj, 'countPendingClaims') ? $claimObj->countPendingClaims() : 0;
$count_pending_returns = method_exists($lostItemsObj, 'countPendingReturns') ? $lostItemsObj->countPendingReturns() : 0;

$kpi_lost_reported = $lostItemsObj->countLostItemsByStatus('Reported');
$kpi_found_reported = $foundItemsObj->countFoundItemsByStatus('Reported');
$kpi_total_returned = $dbObj->countItemsByStatus('Returned');
$kpi_common_category_data = $lostItemsObj->getMostCommonLostCategory();
$kpi_common_category = ($kpi_common_category_data && $kpi_common_category_data['ItemCount'] > 0) ? $kpi_common_category_data['Category'] : 'N/A';

// --- VIEW LOGIC ---
switch ($view) {
    case 'pending_returns':
        $page_title = "Pending Returns";
        $data = $lostItemsObj->viewPendingReturnItems(); 
        break;
    case 'all_lost':
        $page_title = "All Lost Items";
        $data = $lostItemsObj->viewAllLostReports($search_filter, $category_filter, $status_filter); 
        break;
    case 'all_found':
        $page_title = "All Found Items";
        $data = $foundItemsObj->viewAllFoundReports($search_filter, $category_filter, $status_filter); 
        break;
    case 'pending_claims':
    default:
        $view = 'pending_claims'; 
        $page_title = "Pending Claims";
        $data = $claimObj->viewPendingClaims(); 
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Admin</title>
    <link rel="stylesheet" href="../styles/landingpage.css">
    <link rel="stylesheet" href="../styles/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* --- MODAL STYLES --- */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.6); 
            backdrop-filter: blur(2px);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; 
            padding: 0;
            border: 1px solid #888;
            width: 50%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: fadeIn 0.3s;
        }
        .modal-header {
            padding: 15px 20px;
            background-color: var(--wmsu-red);
            color: white;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 { margin: 0; font-size: 1.2rem; }
        .close-modal, .close-accept {
            color: white;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-modal:hover, .close-accept:hover { color: #ddd; }
        .modal-body { padding: 20px; }
        .modal-row { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .modal-row:last-child { border-bottom: none; }
        .modal-label { font-weight: bold; color: #555; display: block; font-size: 0.9em; margin-bottom: 4px; }
        .modal-value { color: #333; }
        .secret-box { background: #fff3cd; padding: 10px; border-radius: 5px; border-left: 4px solid #ffc107; margin-top:10px; }
        
        /* Accept Modal Specifics */
        #acceptModal .modal-content {
            max-width: 400px;
            margin-top: 15%;
            text-align: center;
        }
        #acceptModal .modal-body {
            padding: 30px 20px;
        }

        /* Full Size Image Modal */
        .image-modal {
            display: none; 
            position: fixed; 
            z-index: 2000; 
            padding-top: 50px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.9);
        }

        .image-modal-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 900px;
            max-height: 85vh;
            object-fit: contain;
            border-radius: 4px;
            border: 3px solid #fff;
            animation: zoomIn 0.3s;
        }

        .close-image-modal {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }
        .close-image-modal:hover { color: #bbb; text-decoration: none; cursor: pointer; }
        
        .clickable-image:hover { opacity: 0.9; box-shadow: 0 0 10px rgba(40, 167, 69, 0.5); transition: all 0.2s; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes zoomIn { from {transform:scale(0.8); opacity:0} to {transform:scale(1); opacity:1} }
    </style>
</head>
<body>

    <aside class="admin-sidebar">
        <div class="admin-sidebar-header">
            <h2><i class="fas fa-user-shield"></i> Admin Panel</h2>
        </div>
        <nav>
            <ul>
                <li>
                    <a href="dashboard.php?view=pending_claims" class="<?= ($view === 'pending_claims') ? 'active' : '' ?>">
                        <i class="fas fa-gavel"></i> Pending Claims
                        <?php if ($count_pending_claims > 0): ?>
                            <span class="nav-badge"><?= $count_pending_claims ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="dashboard.php?view=pending_returns" class="<?= ($view === 'pending_returns') ? 'active' : '' ?>">
                        <i class="fas fa-undo-alt"></i> Pending Returns
                         <?php if ($count_pending_returns > 0): ?>
                            <span class="nav-badge"><?= $count_pending_returns ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="dashboard.php?view=all_lost" class="<?= ($view === 'all_lost') ? 'active' : '' ?>">
                        <i class="fas fa-search"></i> All Lost Items
                    </a>
                </li>
                <li>
                    <a href="dashboard.php?view=all_found" class="<?= ($view === 'all_found') ? 'active' : '' ?>">
                        <i class="fas fa-hand-holding-heart"></i> All Found Items
                    </a>
                </li>
                <li>
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
            </ul>
        </nav>
        <div class="logout-section">
             <a href="../account/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <main class="admin-main-content">
        <div class="page-header">
            <h1><?= htmlspecialchars($page_title) ?></h1>
            <div style="color: #666; font-size: 0.9rem;">
                <i class="far fa-calendar-alt"></i> <?= date('l, F j, Y') ?>
            </div>
        </div>
        
        <?php if ($view === 'pending_claims' || $view === 'pending_returns'): ?>
        <div class="kpi-container">
            <div class="kpi-card pending-claims">
                <i class="fas fa-file-contract kpi-icon"></i>
                <span class="value"><?= htmlspecialchars($count_pending_claims) ?></span>
                <span class="label">Claims to Review</span>
            </div>
            <div class="kpi-card pending-returns">
                <i class="fas fa-exchange-alt kpi-icon"></i>
                <span class="value"><?= htmlspecialchars($count_pending_returns) ?></span>
                <span class="label">Returns to Process</span>
            </div>
            <div class="kpi-card total-lost">
                <i class="fas fa-box-open kpi-icon"></i>
                <span class="value"><?= htmlspecialchars($kpi_lost_reported) ?></span>
                <span class="label">Active Lost</span>
            </div>
            <div class="kpi-card total-found">
                <i class="fas fa-box kpi-icon"></i>
                <span class="value"><?= htmlspecialchars($kpi_found_reported) ?></span>
                <span class="label">Active Found</span>
            </div>
             <div class="kpi-card common-category">
                <i class="fas fa-tags kpi-icon"></i>
                <span class="value" style="font-size: 1.5rem; margin-top:5px;"><?= htmlspecialchars($kpi_common_category) ?></span>
                <span class="label">Top Category</span>
            </div>
        </div>
        <?php endif; ?>

        <?= $message ?>

        <?php
        switch ($view):
            case 'pending_returns':
                if (empty($data)) {
                    echo "<div class='no-data'><i class='fas fa-check-circle fa-2x' style='margin-bottom:15px; color:#ccc;'></i><br>All caught up! No items pending return.</div>";
                } else {
                    foreach($data as $item) { 
                        $photo_path = !empty($item["PhotoURL"]) ? "../" . htmlspecialchars($item["PhotoURL"]) : "../images/placeholder.png";
                        
                        // Data attributes
                        $itemNameSafe = htmlspecialchars($item['ItemName']);
                        $itemDescSafe = htmlspecialchars($item['Description']);
                        $proofTypeSafe = htmlspecialchars($item['ProofType'] ?? 'Not set');
                        $proofValueSafe = htmlspecialchars($item['ProofValue'] ?? 'N/A');
                        $finderProofPath = !empty($item["FinderProofPhotoURL"]) ? "../" . htmlspecialchars($item["FinderProofPhotoURL"]) : "";
                        
                        echo "<div class='claim-details'>";
                        echo "<div style='display:flex; justify-content:space-between; align-items:flex-start;'>";
                        echo "<div><h3><i class='fas fa-cube'></i> Item #".htmlspecialchars($item['ItemID']).": ".$itemNameSafe."</h3>";
                        echo "<div class='info-group'><span class='status-badge status-pending-return'>Status: ".htmlspecialchars($item['ItemStatus'])."</span></div></div>";
                        echo "<img src='{$photo_path}' class='item-thumb-lg'></div>";
                        
                        echo "<div class='details-grid'>";
                        echo "<div><h4>Owner Details</h4><div class='info-group'><label>Student ID</label><span>".htmlspecialchars($item['OwnerStudentID'])."</span></div><div class='info-group'><label>Name</label><span>".htmlspecialchars($item['OwnerFirstName'] . ' ' . $item['OwnerLastName'])."</span></div></div>";
                        echo "<div><h4>Finder Details</h4><div class='info-group'><label>Student ID</label><span>".htmlspecialchars($item['FinderStudentID'] ?: 'N/A')."</span></div><div class='info-group'><label>Name</label><span>".htmlspecialchars($item['FinderFirstName'] . ' ' . $item['FinderLastName'])."</span></div></div>";
                        echo "</div>";

                        echo "<div class='action-buttons' style='margin-top:25px; border-top:1px solid #eee; padding-top:20px;'>";
                        
                        // View Details Button
                        echo "<button type='button' class='btn btn-outline view-details-btn' 
                                data-id='".$item['ItemID']."' 
                                data-name='".$itemNameSafe."' 
                                data-desc='".$itemDescSafe."'
                                data-proof-type='".$proofTypeSafe."'
                                data-proof-val='".$proofValueSafe."'
                                data-finder-proof='".$finderProofPath."'>
                                <i class='fas fa-eye'></i> View Security & Proof
                              </button>";

                        // Accept Request Button
                        echo "<button type='button' class='btn btn-info' onclick=\"openAcceptModal('".$item['ItemID']."')\">
                                <i class='fas fa-check'></i> Accept Request
                              </button>";

                        // Delete Button
                        echo "<form action='' method='post' onsubmit=\"return confirm('DELETE Item?')\" style='display:inline;'><input type='hidden' name='item_id' value='".$item['ItemID']."'><input type='hidden' name='action' value='delete_item'><button type='submit' class='btn btn-secondary'><i class='fas fa-trash'></i> Delete</button></form>";
                        echo "</div></div>";
                    }
                }
                break; 

            case 'all_lost':
            case 'all_found':
                $is_lost = ($view === 'all_lost');
                $action_val = $view;
                echo '<form action="" method="get" class="filters-bar"><input type="hidden" name="view" value="'.$action_val.'">';
                echo '<label><i class="fas fa-filter"></i> Filters:</label>';
                echo '<input type="search" name="search" class="form-control" value="'.htmlspecialchars($search_filter).'" placeholder="Search Name/Description">';
                echo '<input type="text" name="category" class="form-control" value="'.htmlspecialchars($category_filter).'" placeholder="Category (e.g. Keys)">';
                echo '<select name="status" class="form-control"><option value="">All Statuses</option>';
                $statuses = ['Reported', 'Pending Return', 'Claimed', 'Returned', 'Accepted'];
                foreach($statuses as $s) { echo "<option value='$s' ".($status_filter == $s ? 'selected' : '').">$s</option>"; }
                echo '</select><button type="submit" class="btn btn-primary">Apply</button>';
                echo '<a href="dashboard.php?view='.$action_val.'" class="btn btn-outline">Clear</a></form>';

                if (empty($data)) { 
                    echo "<div class='no-data'><i class='fas fa-search fa-2x' style='margin-bottom:15px; color:#ccc;'></i><br>No items found matching your filters.</div>"; 
                } else {
                    echo '<div class="table-container"><table class="admin-table"><thead><tr><th>ID</th><th>Photo</th><th>Name</th><th>Category</th><th>Date '.($is_lost?'Lost':'Found').'</th><th>Status</th><th>Reported By</th><th>Actions</th></tr></thead><tbody>';
                    foreach($data as $item) { 
                        $id = htmlspecialchars($item['ItemID']);
                        $detail_url = $is_lost ? "../lostitems/viewItemDetails.php?item_id=$id" : "../founditems/claimItem.php?item_id=$id";
                        $photo_key = $is_lost ? "PhotoURL" : "Photo";
                        $photo_path = !empty($item[$photo_key]) ? "../" . htmlspecialchars($item[$photo_key]) : "../images/placeholder.png";
                        $status_slug = strtolower(str_replace(' ', '-', $item["ItemStatus"]));
                        
                        echo "<tr>";
                        echo "<td>#{$id}</td>";
                        echo "<td><img src='{$photo_path}'></td>";
                        echo "<td><strong>".htmlspecialchars($item['ItemName'])."</strong></td>";
                        echo "<td>".htmlspecialchars($item['Category'])."</td>";
                        echo "<td>".date('M d, Y', strtotime($is_lost ? $item['DateLost'] : $item['DateFound']))."</td>";
                        echo "<td><span class='status-badge status-{$status_slug}'>".htmlspecialchars($item['ItemStatus'])."</span></td>";
                        echo "<td>".htmlspecialchars($item['ReporterFirstName'])."</td>";
                        echo "<td><div class='action-buttons'>";
                        echo "<a href='{$detail_url}' target='_blank' class='btn btn-outline' title='View'><i class='fas fa-eye'></i></a>";
                        $show_return = ($is_lost && $item['ItemStatus'] !== 'Returned') || (!$is_lost && in_array($item['ItemStatus'], ['Claimed', 'Verified', 'Pending Return', 'Accepted']));
                        if ($show_return) {
                            echo "<form action='' method='post' onsubmit=\"return confirm('Return Item #{$id}?')\"><input type='hidden' name='item_id' value='{$id}'><input type='hidden' name='action' value='mark_returned'><button type='submit' class='btn btn-info' title='Mark Returned'><i class='fas fa-undo'></i></button></form>";
                        }
                        echo "<form action='' method='post' onsubmit=\"return confirm('Delete Item #{$id}?')\"><input type='hidden' name='item_id' value='{$id}'><input type='hidden' name='action' value='delete_item'><button type='submit' class='btn btn-secondary' title='Delete'><i class='fas fa-trash'></i></button></form>";
                        echo "</div></td></tr>";
                    }
                    echo '</tbody></table></div>';
                }
                break; 

            case 'pending_claims':
            default:
                if (empty($data)) {
                    echo "<div class='no-data'><i class='fas fa-check-double fa-2x' style='margin-bottom:15px; color:#ccc;'></i><br>No pending claims. Good job!</div>";
                } else {
                    foreach($data as $claim) { 
                         echo "<div class='claim-details'>";
                         echo "<div style='display:flex; justify-content:space-between;'>";
                         echo "<div><h3><i class='fas fa-gavel'></i> Claim #".htmlspecialchars($claim['ClaimID'])." <span style='font-weight:400; font-size:0.9em; color:#666;'>for Item #".htmlspecialchars($claim['ItemID'])."</span></h3></div>";
                         echo "<div><span class='status-badge' style='background:#fff3cd; color:#856404;'>".htmlspecialchars($claim['VerificationStatus'])."</span></div>";
                         echo "</div>";
                         $photo_path = !empty($claim["FoundItemPhoto"]) ? "../" . htmlspecialchars($claim["FoundItemPhoto"]) : "../images/placeholder.png";
                         echo "<div class='details-grid'>";
                         echo "<div>";
                         echo "<div class='info-group'><label>Item</label><span>".htmlspecialchars($claim['ItemName'])." (".htmlspecialchars($claim['ItemCategory']).")</span></div>";
                         echo "<div class='info-group'><label>Claimant</label><span>".htmlspecialchars($claim['ClaimantFirstName'] . ' ' . $claim['ClaimantLastName'])." (ID: ".htmlspecialchars($claim['ClaimantStudentID']).")</span></div>";
                         echo "<div class='info-group'><label>Finder</label><span>".htmlspecialchars($claim['ReporterFirstName'] . ' ' . $claim['ReporterLastName'])."</span></div>";
                         echo "</div>";
                         echo "<div style='text-align:right;'><img src='{$photo_path}' class='item-thumb-lg'></div>";
                         echo "</div>";
                         echo "<div class='verification-box'><strong><i class='fas fa-shield-alt'></i> Verification Data:</strong><br><br>";
                         echo "<div style='display:grid; grid-template-columns:1fr 1fr; gap:20px;'>";
                         echo "<div><label style='font-size:0.75em; text-transform:uppercase;'>Security Answer</label><br>".nl2br(htmlspecialchars($claim['SecurityQuestionAnswers']))."</div>";
                         echo "<div><label style='font-size:0.75em; text-transform:uppercase;'>Proof Provided</label><br>".htmlspecialchars($claim['OriginalProofType'] ?? 'None').": ".htmlspecialchars($claim['OriginalProofValue'] ?? 'N/A')."</div>";
                         echo "</div></div>";
                         echo "<div class='action-buttons' style='margin-top:20px; justify-content:flex-end;'>";
                         echo "<form action='' method='post' onsubmit=\"return confirm('Approve Claim?')\"><input type='hidden' name='claim_id' value='".$claim['ClaimID']."'><input type='hidden' name='item_id' value='".$claim['ItemID']."'><input type='hidden' name='action' value='verify'><button type='submit' class='btn btn-success'><i class='fas fa-check'></i> Approve Claim</button></form>";
                         echo "<form action='' method='post' onsubmit=\"return confirm('Reject Claim?')\"><input type='hidden' name='claim_id' value='".$claim['ClaimID']."'><input type='hidden' name='item_id' value='".$claim['ItemID']."'><input type='hidden' name='action' value='reject'><button type='submit' class='btn btn-danger'><i class='fas fa-times'></i> Reject</button></form>";
                         echo "</div></div>";
                    }
                }
                break; 
        endswitch;
        ?>

        <div id="detailsModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modalItemTitle">Item Details</h2>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="modal-row">
                        <span class="modal-label">Description:</span>
                        <span class="modal-value" id="modalDesc"></span>
                    </div>
                    <div class="secret-box">
                        <div style="font-size:0.9em; text-transform:uppercase; color:#856404; font-weight:bold; margin-bottom:10px;">
                            <i class="fas fa-lock"></i> Security / Proof Details (Confidential)
                        </div>
                        <div class="modal-row" style="border:none;">
                            <span class="modal-label">Proof Type (Question):</span>
                            <span class="modal-value" id="modalProofType"></span>
                        </div>
                        <div class="modal-row" style="border:none;">
                            <span class="modal-label">Proof Value (Answer):</span>
                            <span class="modal-value" id="modalProofValue" style="font-weight:bold;"></span>
                        </div>
                    </div>
                    <div id="modalProofImgContainer"></div>
                </div>
            </div>
        </div>

        <div id="acceptModal" class="modal">
            <div class="modal-content">
                <div class="modal-body">
                    <i class="fas fa-check-circle fa-3x" style="color:#28a745; margin-bottom:15px; display:block;"></i>
                    <h3 style="margin-top:0; color:#333;">Confirm Acceptance</h3>
                    <p style="font-size:1.1em; color:#555; margin-bottom:20px;">
                        Accept return request for Item #<span id="acceptItemSpan" style="font-weight:bold;"></span>? 
                        <br><br>
                        <span style="font-size:0.95em; color:#666;">
                            This will mark it as <strong>ACCEPTED</strong> and notify the owner.<br>
                            <em>(The owner will confirm receipt later.)</em>
                        </span>
                    </p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="mark_accepted">
                        <input type="hidden" name="item_id" id="acceptInputId">
                        
                        <div style="display:flex; justify-content:center; gap:10px;">
                            <button type="button" class="btn btn-secondary close-accept" style="float:none; font-size:1rem; padding:8px 20px;">Cancel</button>
                            <button type="submit" class="btn btn-success" style="padding:8px 20px;">Yes, Accept Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="imageModal" class="image-modal">
            <span class="close-image-modal">&times;</span>
            <img class="image-modal-content" id="fullSizeImage">
        </div>

    </main> 

    <script>
        // --- References ---
        const detailsModal = document.getElementById('detailsModal');
        const imgModal = document.getElementById('imageModal');
        const acceptModal = document.getElementById('acceptModal');

        const viewBtns = document.querySelectorAll('.view-details-btn');
        const closeDetailsBtn = document.querySelector('#detailsModal .close-modal');
        const closeImgBtn = document.querySelector('.close-image-modal');
        const closeAcceptBtns = document.querySelectorAll('.close-accept');

        // Fields
        const modalTitle = document.getElementById('modalItemTitle');
        const modalDesc = document.getElementById('modalDesc');
        const modalProofType = document.getElementById('modalProofType');
        const modalProofValue = document.getElementById('modalProofValue');
        const modalProofImgContainer = document.getElementById('modalProofImgContainer');
        const acceptInputId = document.getElementById('acceptInputId');
        const acceptItemSpan = document.getElementById('acceptItemSpan');
        const fullSizeImg = document.getElementById('fullSizeImage');

        // Open Details
        viewBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const name = btn.getAttribute('data-name');
                const desc = btn.getAttribute('data-desc');
                const pType = btn.getAttribute('data-proof-type');
                const pVal = btn.getAttribute('data-proof-val');
                const finderProof = btn.getAttribute('data-finder-proof');

                modalTitle.innerText = `Item #${id}: ${name}`;
                modalDesc.innerText = desc;
                modalProofType.innerText = pType;
                modalProofValue.innerText = pVal;

                if (finderProof) {
                    modalProofImgContainer.innerHTML = `
                        <div style="margin-top:15px; border-top:1px solid #eee; padding-top:10px;">
                            <span class="modal-label" style="color:#28a745;"><i class="fas fa-camera"></i> Finder's Proof Photo (Click to enlarge):</span>
                            <img src="${finderProof}" class="clickable-image" onclick="openFullImage('${finderProof}')" style="max-width:100%; height:auto; border-radius:4px; margin-top:5px; border:1px solid #ccc; cursor:pointer;">
                        </div>`;
                } else {
                    modalProofImgContainer.innerHTML = '<p style="color:#666; font-size:0.9em; margin-top:10px;">No proof photo uploaded by finder.</p>';
                }

                detailsModal.style.display = "block";
            });
        });

        function openAcceptModal(itemId) {
            acceptInputId.value = itemId;
            acceptItemSpan.innerText = itemId;
            acceptModal.style.display = "block";
        }

        function openFullImage(src) {
            fullSizeImg.src = src;
            imgModal.style.display = "block";
        }

        if(closeDetailsBtn) closeDetailsBtn.onclick = () => { detailsModal.style.display = "none"; };
        if(closeImgBtn) closeImgBtn.onclick = () => { imgModal.style.display = "none"; };
        closeAcceptBtns.forEach(btn => { btn.onclick = () => { acceptModal.style.display = "none"; }; });

        window.onclick = function(event) {
            if (event.target == detailsModal) detailsModal.style.display = "none";
            if (event.target == imgModal) imgModal.style.display = "none";
            if (event.target == acceptModal) acceptModal.style.display = "none";
        }
    </script>
</body>
</html>