<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/loginAccount.php"); 
    exit();
}

require_once "../classes/FoundItems.php"; 
$foundItemObj = new FoundItems(); 

$found_item = [
    "ItemName" => "", 
    "Description" => "", 
    "Category" => "", 
    "DateFound" => date('Y-m-d'), 
    "LocationFound" => "", 
    "SecretDetailType" => "", 
    "SecretDetailValue" => ""  
];

$errors = [
    "ItemName" => "", 
    "Description" => "", 
    "Category" => "", 
    "DateFound" => "",
    "LocationFound" => "", 
    "SecretDetailType" => "", 
    "SecretDetailValue" => "", 
    "Photo" => "", 
    "general" => "" 
];


$categories = ['Electronics', 'ID/Documents', 'Keys', 'Bags/Clothing', 'Books/Stationery', 'Other'];
$proof_types = ['Color', 'Text', 'Accessory', 'Code', 'Other']; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $found_item["ItemName"] = trim(htmlspecialchars($_POST["ItemName"] ?? ''));
    $found_item["Description"] = trim(htmlspecialchars($_POST["Description"] ?? ''));
    $found_item["Category"] = trim(htmlspecialchars($_POST["Category"] ?? ''));
    $found_item["DateFound"] = trim(htmlspecialchars($_POST["DateFound"] ?? ''));
    $found_item["LocationFound"] = trim(htmlspecialchars($_POST["LocationFound"] ?? ''));
    $found_item["SecretDetailType"] = trim(htmlspecialchars($_POST["SecretDetailType"] ?? ''));
    $found_item["SecretDetailValue"] = trim(htmlspecialchars($_POST["SecretDetailValue"] ?? ''));
    
    $photo_file = $_FILES['Photo'] ?? null;

    if (empty($found_item["ItemName"])) { $errors["ItemName"] = "Item Name is required."; }
    if (empty($found_item["Description"])) { $errors["Description"] = "Detailed Description is required."; }
    if (empty($found_item["Category"])) { $errors["Category"] = "Category is required."; }
    if (empty($found_item["DateFound"])) { $errors["DateFound"] = "Date Found is required."; } 
    elseif (strtotime($found_item["DateFound"]) > time()) { $errors["DateFound"] = "Date Found cannot be in the future."; }
    if (empty($found_item["LocationFound"])) { $errors["LocationFound"] = "Location Found is required."; }

    // Photo is REQUIRED for Found Items
    if (!$photo_file || $photo_file['error'] == UPLOAD_ERR_NO_FILE) {
        $errors["Photo"] = "A photo of the found item is required.";
    } elseif ($photo_file['error'] != UPLOAD_ERR_OK) {
        $errors["Photo"] = "There was an error uploading the file (Code: {$photo_file['error']}).";
    } elseif ($photo_file['size'] > 5 * 1024 * 1024) { 
        $errors["Photo"] = "File is too large (Max 5MB).";
    }

    if (empty(array_filter($errors))) {
        try {
            $foundItemObj->UserID = $_SESSION['user_id'];
            $foundItemObj->ItemName = $found_item["ItemName"];
            $foundItemObj->Description = $found_item["Description"];
            $foundItemObj->Category = $found_item["Category"];
            $foundItemObj->DateFound = $found_item["DateFound"];
            $foundItemObj->LocationFound = $found_item["LocationFound"];          
            $foundItemObj->SecretDetailType = $found_item["SecretDetailType"]; 
            $foundItemObj->SecretDetailValue = $found_item["SecretDetailValue"]; 
            $foundItemObj->PhotoFile = $photo_file; 

            if ($foundItemObj->addFoundReport()) {
                // UPDATED: Removed ID from success message
                $_SESSION['report_success'] = "✅ Found item report submitted successfully!";
                header("Location: viewFoundItems.php");
                exit();
            } else {
                $errors["general"] = "❌ Failed to submit the found item report due to a database error. Check server logs.";
            }
        } catch (Exception $e) {
            $errors["general"] = "❌ Error: " . $e->getMessage();
        }
    } else {
        $errors["general"] = "Please correct the errors highlighted below.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Found Item - WMSU</title>
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
            <a href="../landingpage/index.php" class="btn-nav-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </nav>

    <div class="main-container">
        <div class="form-card">
            
            <div class="form-header">
                <h1>Report Found Item</h1>
                <p>Thank you for being honest! Help return this item to its owner.</p>
            </div>
            
            <?php if (isset($errors["general"]) && $errors["general"]): ?>
                <div class="alert-general"><?= htmlspecialchars($errors["general"]) ?></div>
            <?php endif; ?>

            <form action="" method="post" enctype="multipart/form-data">
                <p style="text-align:center; color:#666; font-size:0.9rem;">Fields marked with <span class="required">*</span> are required.</p>

                <fieldset>
                    <legend><i class="fas fa-box-open"></i> Item Details</legend>

                    <div class="form-group">
                        <label for="ItemName">Item Name <span class="required">*</span></label>
                        <input type="text" name="ItemName" id="ItemName" 
                               value="<?= htmlspecialchars($found_item["ItemName"]) ?>" 
                               placeholder="e.g. Silver Water Bottle" required>
                        <p class="error"><?= htmlspecialchars($errors["ItemName"])?></p>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="Category">Category <span class="required">*</span></label>
                                <select name="Category" id="Category" required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat ?>" <?= ($found_item["Category"] == $cat)? "selected": ""?>><?= $cat ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="error"><?= htmlspecialchars($errors["Category"])?></p>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="DateFound">Date Found <span class="required">*</span></label>
                                <input type="date" name="DateFound" id="DateFound" 
                                       value="<?= htmlspecialchars($found_item["DateFound"])?>" 
                                       required max="<?= date('Y-m-d'); ?>">
                                <p class="error"><?= htmlspecialchars($errors["DateFound"])?></p>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="LocationFound">Location Found <span class="required">*</span></label>
                        <input type="text" name="LocationFound" id="LocationFound" 
                               value="<?= htmlspecialchars($found_item["LocationFound"])?>" 
                               placeholder="Be specific, e.g., 'Left bench near the Science Building entrance'" required>
                        <p class="error"><?= htmlspecialchars($errors["LocationFound"])?></p>
                    </div>

                    <div class="form-group">
                        <label for="Description">Detailed Description <span class="required">*</span></label>
                        <textarea name="Description" id="Description" rows="4" 
                                  placeholder="Describe the item condition, color, and size." required><?= htmlspecialchars($found_item["Description"])?></textarea>
                        <p class="error"><?= htmlspecialchars($errors["Description"])?></p>
                    </div>

                    <div class="form-group">
                        <label for="Photo">Upload Photo <span class="required">*</span></label>
                        <input type="file" name="Photo" id="Photo" accept="image/png, image/jpeg, image/gif, image/webp" required>
                        <small style="color:#666;">Max size: 5MB</small>
                        <p class="error"><?= htmlspecialchars($errors["Photo"])?></p>
                    </div>
                </fieldset>

                <div class="info-box">
                    <i class="fas fa-lightbulb"></i> <strong>Secret Detail (Optional):</strong> If the item has a hidden feature or unique mark (like a name engraved on the bottom), mention it here. The owner will need to provide this detail to verify ownership.
                </div>

                <fieldset>
                    <legend><i class="fas fa-user-secret"></i> Verification Detail</legend>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="SecretDetailType">Detail Type</label>
                                <select name="SecretDetailType" id="SecretDetailType">
                                    <option value="">-- Select Type --</option>
                                     <?php foreach ($proof_types as $type): ?>
                                       <option value="<?= $type ?>" <?= ($found_item["SecretDetailType"] == $type)? "selected": ""?>><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="error"><?= htmlspecialchars($errors["SecretDetailType"])?></p>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="SecretDetailValue">Secret Value</label>
                                <input type="text" name="SecretDetailValue" id="SecretDetailValue" 
                                       placeholder="e.g. 'Sticker of a cat on the back'"
                                       value="<?= htmlspecialchars($found_item["SecretDetailValue"])?>">
                                <p class="error"><?= htmlspecialchars($errors["SecretDetailValue"])?></p>
                            </div>
                        </div>
                    </div>
                </fieldset>
                
                <button type="submit" class="btn-submit">Submit Found Item Report</button>
            </form>
        </div>
    </div>
</body>
</html>