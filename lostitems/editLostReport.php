<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/loginAccount.php"); 
    exit();
}

require_once "../classes/LostItems.php"; 
$lostItemObj = new LostItems(); 

$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);


$item_data = null;
if ($item_id) {
    $item_data = $lostItemObj->fetchLostItemDetails($item_id);
    
    
    if ($item_data && $item_data['ReporterUserID'] != $_SESSION['user_id']) {
        exit("Unauthorized access.");
    }
} else {
    header("Location: ../account/myReports.php");
    exit();
}

$errors = [
    "ItemName" => "", "Description" => "", "Category" => "", 
    "DateLost" => "", "LastKnownLocation" => "", "SecretDetailType" => "", 
    "SecretDetailValue" => "", "Photo" => "", "general" => "" 
];

$categories = ['Electronics', 'ID/Documents', 'Keys', 'Bags/Clothing', 'Books/Stationery', 'Other'];
$proof_types = ['Color', 'Text', 'Accessory', 'Code', 'Other']; 


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    
    $lostItemObj->UserID = $_SESSION['user_id'];
    $lostItemObj->ItemName = trim(htmlspecialchars($_POST["ItemName"]));
    $lostItemObj->Description = trim(htmlspecialchars($_POST["Description"]));
    $lostItemObj->Category = trim(htmlspecialchars($_POST["Category"]));
    $lostItemObj->DateLost = trim(htmlspecialchars($_POST["DateLost"]));
    $lostItemObj->LastKnownLocation = trim(htmlspecialchars($_POST["LastKnownLocation"]));
    $lostItemObj->SecretDetailType = trim(htmlspecialchars($_POST["SecretDetailType"]));
    $lostItemObj->SecretDetailValue = trim(htmlspecialchars($_POST["SecretDetailValue"]));
    $lostItemObj->PhotoFile = $_FILES['Photo'] ?? null;

    
    if (empty($lostItemObj->ItemName)) $errors["ItemName"] = "Item Name is required.";
    if (empty($lostItemObj->Description)) $errors["Description"] = "Description is required.";
    if (strtotime($lostItemObj->DateLost) > time()) $errors["DateLost"] = "Date cannot be in future.";
    
    if ($lostItemObj->PhotoFile && $lostItemObj->PhotoFile['error'] == UPLOAD_ERR_OK) {
        if ($lostItemObj->PhotoFile['size'] > 5 * 1024 * 1024) $errors["Photo"] = "Max size 5MB.";
    }

    if (empty(array_filter($errors))) {
        try {
            if ($lostItemObj->updateLostReport($item_id)) {
                header("Location: ../account/myReports.php");
                exit();
            } else {
                $errors["general"] = "Failed to update report.";
            }
        } catch (Exception $e) {
            $errors["general"] = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lost Report - WMSU</title>
    <link rel="stylesheet" href="../styles/reportLost.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="wmsu-navbar">
        <a href="../landingpage/userMain.php" class="brand-container">
            <img src="../images/wmsu_logo.jpg" alt="WMSU Logo" class="brand-logo">
            <span class="brand-text">Lost & Found</span>
        </a>
        <div class="nav-buttons">
            <a href="../account/myReports.php" class="btn-nav-back">
                <i class="fas fa-arrow-left"></i> Cancel Edit
            </a>
        </div>
    </nav>

    <div class="main-container">
        <div class="form-card">
            <div class="form-header">
                <h1>Edit Lost Item</h1>
                <p>Update details for: <strong><?= htmlspecialchars($item_data['ItemName']) ?></strong></p>
            </div>

            <?php if (!empty($errors["general"])): ?>
                <div class="alert-general"><?= htmlspecialchars($errors["general"]) ?></div>
            <?php endif; ?>

            <form action="" method="post" enctype="multipart/form-data">
                <fieldset>
                    <legend><i class="fas fa-edit"></i> Item Details</legend>
                    
                    <div class="form-group">
                        <label>Item Name</label>
                        <input type="text" name="ItemName" value="<?= htmlspecialchars($_POST['ItemName'] ?? $item_data['ItemName']) ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label>Category</label>
                            <select name="Category" required>
                                <?php foreach($categories as $cat): ?>
                                <option value="<?=$cat?>" <?= ($cat == ($item_data['Category'])) ? 'selected' : '' ?>><?=$cat?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-col">
                            <label>Date Lost</label>
                            <input type="date" name="DateLost" value="<?= htmlspecialchars($item_data['DateLost']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Last Known Location</label>
                        <input type="text" name="LastKnownLocation" value="<?= htmlspecialchars($item_data['ReportLocation']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="Description" rows="4" required><?= htmlspecialchars($item_data['Description']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Update Photo (Optional)</label>
                        <?php if($item_data['PhotoURL']): ?>
                            <div style="margin-bottom:10px;">
                                <img src="../<?= htmlspecialchars($item_data['PhotoURL']) ?>" alt="Current" style="height:60px; border-radius:4px;">
                                <small style="display:block; color:#777;">Current Photo</small>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="Photo" accept="image/*">
                    </div>
                </fieldset>

                <fieldset>
                    <legend><i class="fas fa-user-secret"></i> Verification Detail</legend>
                    <div class="form-row">
                        <div class="form-col">
                            <label>Proof Type</label>
                            <select name="SecretDetailType" required>
                                <?php foreach($proof_types as $type): ?>
                                <option value="<?=$type?>" <?= ($type == $item_data['ProofType']) ? 'selected' : '' ?>><?=$type?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-col">
                            <label>Secret Value</label>
                            <input type="text" name="SecretDetailValue" value="<?= htmlspecialchars($item_data['ProofValue']) ?>" required>
                        </div>
                    </div>
                </fieldset>

                <button type="submit" class="btn-submit">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>