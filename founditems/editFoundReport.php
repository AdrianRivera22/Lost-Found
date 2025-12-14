<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/loginAccount.php"); 
    exit();
}

require_once "../classes/FoundItems.php"; 
$foundItemObj = new FoundItems(); 

$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);

// 1. Fetch Existing Data
$item_data = null;
if ($item_id) {
    $item_data = $foundItemObj->fetchFoundItemDetails($item_id);
    
    // Authorization Check
    if ($item_data && $item_data['ReporterUserID'] != $_SESSION['user_id']) {
        exit("Unauthorized access.");
    }
} else {
    header("Location: ../account/myReports.php");
    exit();
}

$errors = [
    "ItemName" => "", "Description" => "", "Category" => "", 
    "DateFound" => "", "LocationFound" => "", "SecretDetailType" => "", 
    "SecretDetailValue" => "", "Photo" => "", "general" => "" 
];

$categories = ['Electronics', 'ID/Documents', 'Keys', 'Bags/Clothing', 'Books/Stationery', 'Other'];
$proof_types = ['Color', 'Text', 'Accessory', 'Code', 'Other']; 

// 2. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Collect Inputs
    $foundItemObj->UserID = $_SESSION['user_id'];
    $foundItemObj->ItemName = trim(htmlspecialchars($_POST["ItemName"]));
    $foundItemObj->Description = trim(htmlspecialchars($_POST["Description"]));
    $foundItemObj->Category = trim(htmlspecialchars($_POST["Category"]));
    $foundItemObj->DateFound = trim(htmlspecialchars($_POST["DateFound"]));
    $foundItemObj->LocationFound = trim(htmlspecialchars($_POST["LocationFound"]));
    $foundItemObj->SecretDetailType = trim(htmlspecialchars($_POST["SecretDetailType"]));
    $foundItemObj->SecretDetailValue = trim(htmlspecialchars($_POST["SecretDetailValue"]));
    $foundItemObj->PhotoFile = $_FILES['Photo'] ?? null;

    // Validate
    if (empty($foundItemObj->ItemName)) $errors["ItemName"] = "Item Name is required.";
    if (empty($foundItemObj->Description)) $errors["Description"] = "Description is required.";
    if (strtotime($foundItemObj->DateFound) > time()) $errors["DateFound"] = "Date cannot be in future.";
    
    // Photo Validation (Only if new file uploaded)
    if ($foundItemObj->PhotoFile && $foundItemObj->PhotoFile['error'] == UPLOAD_ERR_OK) {
        if ($foundItemObj->PhotoFile['size'] > 5 * 1024 * 1024) $errors["Photo"] = "Max size 5MB.";
    }

    if (empty(array_filter($errors))) {
        try {
            if ($foundItemObj->updateFoundReport($item_id)) {
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
    <title>Edit Found Report - WMSU</title>
    <link rel="stylesheet" href="../styles/reportFound.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="wmsu-navbar">
        <a href="../landingpage/index.php" class="brand-container">
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
                <h1>Edit Found Item</h1>
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
                            <label>Date Found</label>
                            <input type="date" name="DateFound" value="<?= htmlspecialchars($item_data['DateFound']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Location Found</label>
                        <input type="text" name="LocationFound" value="<?= htmlspecialchars($item_data['ReportLocation']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="Description" rows="4" required><?= htmlspecialchars($item_data['Description']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Update Photo (Optional)</label>
                        <?php if(isset($item_data['Photo']) && $item_data['Photo']): ?>
                            <div style="margin-bottom:10px;">
                                <img src="../<?= htmlspecialchars($item_data['Photo']) ?>" alt="Current" style="height:60px; border-radius:4px;">
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
                            <label>Detail Type</label>
                            <select name="SecretDetailType">
                                <option value="">None</option>
                                <?php foreach($proof_types as $type): ?>
                                <option value="<?=$type?>" <?= ($type == ($item_data['ProofType'] ?? '')) ? 'selected' : '' ?>><?=$type?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-col">
                            <label>Secret Value</label>
                            <input type="text" name="SecretDetailValue" value="<?= htmlspecialchars($item_data['ProofValue'] ?? '') ?>">
                        </div>
                    </div>
                </fieldset>

                <button type="submit" class="btn-submit">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>