<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/loginAccount.php"); 
    exit();
}

require_once "../classes/LostItems.php"; 
$lostItemObj = new LostItems(); 

$lost_item = [
    "ItemName" => "", 
    "Description" => "", 
    "Category" => "", 
    "DateLost" => date('Y-m-d'),
    "LastKnownLocation" => "", 
    "SecretDetailType" => "", 
    "SecretDetailValue" => ""
];

$errors = [
    "ItemName" => "", 
    "Description" => "", 
    "Category" => "", 
    "DateLost" => "",
    "LastKnownLocation" => "", 
    "SecretDetailType" => "", 
    "SecretDetailValue" => "", 
    "Photo" => "", 
    "general" => "" 
];

$categories = ['Electronics', 'ID/Documents', 'Keys', 'Bags/Clothing', 'Books/Stationery', 'Other'];
$proof_types = ['Color', 'Text', 'Accessory', 'Code', 'Other']; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lost_item["ItemName"] = trim(htmlspecialchars($_POST["ItemName"] ?? ''));
    $lost_item["Description"] = trim(htmlspecialchars($_POST["Description"] ?? ''));
    $lost_item["Category"] = trim(htmlspecialchars($_POST["Category"] ?? ''));
    $lost_item["DateLost"] = trim(htmlspecialchars($_POST["DateLost"] ?? ''));
    $lost_item["LastKnownLocation"] = trim(htmlspecialchars($_POST["LastKnownLocation"] ?? ''));
    $lost_item["SecretDetailType"] = trim(htmlspecialchars($_POST["SecretDetailType"] ?? ''));
    $lost_item["SecretDetailValue"] = trim(htmlspecialchars($_POST["SecretDetailValue"] ?? ''));
    
    $photo_file = $_FILES['Photo'] ?? null;

    if (empty($lost_item["ItemName"])) { $errors["ItemName"] = "Item Name is required."; }
    if (empty($lost_item["Description"])) { $errors["Description"] = "Detailed Description is required."; }
    if (empty($lost_item["Category"])) { $errors["Category"] = "Category is required."; }
    if (empty($lost_item["DateLost"])) { $errors["DateLost"] = "Date Lost is required."; } 
    elseif (strtotime($lost_item["DateLost"]) > time()) { $errors["DateLost"] = "Date Lost cannot be in the future."; }
    if (empty($lost_item["LastKnownLocation"])) { $errors["LastKnownLocation"] = "Last Known Location is required."; }
    if (empty($lost_item["SecretDetailType"])) { $errors["SecretDetailType"] = "Proof Type is required."; }
    if (empty($lost_item["SecretDetailValue"])) { $errors["SecretDetailValue"] = "Verification Detail is required."; }

    // Optional Photo Validation
    if ($photo_file && $photo_file['error'] == UPLOAD_ERR_OK) {
        if ($photo_file['size'] > 5 * 1024 * 1024) { 
             $errors["Photo"] = "File is too large (Max 5MB).";
        }
    } elseif ($photo_file && $photo_file['error'] != UPLOAD_ERR_NO_FILE && $photo_file['error'] != UPLOAD_ERR_OK) {
         $errors["Photo"] = "There was an error uploading the file (Code: {$photo_file['error']}).";
    }

    if (empty(array_filter($errors))) {
        try {
            $lostItemObj->UserID = $_SESSION['user_id'];
            $lostItemObj->ItemName = $lost_item["ItemName"];
            $lostItemObj->Description = $lost_item["Description"];
            $lostItemObj->Category = $lost_item["Category"];
            $lostItemObj->DateLost = $lost_item["DateLost"];
            $lostItemObj->LastKnownLocation = $lost_item["LastKnownLocation"];
            $lostItemObj->SecretDetailType = $lost_item["SecretDetailType"];
            $lostItemObj->SecretDetailValue = $lost_item["SecretDetailValue"];
            $lostItemObj->PhotoFile = $photo_file; 

            if ($lostItemObj->addLostReport()) {
                // UPDATED: Removed ID from the success message
                $_SESSION['report_success'] = "✅ Lost item report submitted successfully!";
                header("Location: viewLostItems.php"); 
                exit(); 
            } else {
                $errors["general"] = "❌ Failed to submit the lost item report due to a database error.";
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
    <title>Report Lost Item - WMSU</title>
    <link rel="stylesheet" href="../styles/reportLost.css?v=<?php echo time(); ?>">
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
                <h1>Report Lost Item</h1>
                <p>Help us help you find your belongings.</p>
            </div>

            <?php if (!empty($errors["general"])): ?>
                <div class="alert-general"><?= htmlspecialchars($errors["general"]) ?></div>
            <?php endif; ?>

            <form action="" method="post" enctype="multipart/form-data">
                
                <fieldset>
                    <legend><i class="fas fa-info-circle"></i> Item Details</legend>

                    <div class="form-group">
                        <label for="ItemName">Item Name <span class="required">*</span></label>
                        <input type="text" name="ItemName" id="ItemName" 
                               placeholder="e.g. Black Acer Laptop"
                               value="<?= htmlspecialchars($lost_item["ItemName"]) ?>" required>
                        <?php if ($errors["ItemName"]): ?><span class="error"><?= htmlspecialchars($errors["ItemName"])?></span><?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="Category">Category <span class="required">*</span></label>
                                <select name="Category" id="Category" required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat ?>" <?= ($lost_item["Category"] == $cat)? "selected": ""?>><?= $cat ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($errors["Category"]): ?><span class="error"><?= htmlspecialchars($errors["Category"])?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="DateLost">Date Lost <span class="required">*</span></label>
                                <input type="date" name="DateLost" id="DateLost" 
                                       value="<?= htmlspecialchars($lost_item["DateLost"])?>" 
                                       max="<?= date('Y-m-d'); ?>" required>
                                <?php if ($errors["DateLost"]): ?><span class="error"><?= htmlspecialchars($errors["DateLost"])?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="LastKnownLocation">Last Known Location <span class="required">*</span></label>
                        <input type="text" name="LastKnownLocation" id="LastKnownLocation" 
                               placeholder="e.g. 3rd Floor Library, Room 101"
                               value="<?= htmlspecialchars($lost_item["LastKnownLocation"])?>" required>
                        <?php if ($errors["LastKnownLocation"]): ?><span class="error"><?= htmlspecialchars($errors["LastKnownLocation"])?></span><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="Description">Detailed Description <span class="required">*</span></label>
                        <textarea name="Description" id="Description" rows="4" 
                                  placeholder="Describe the item in detail (color, brand, scratches, contents...)"
                                  required><?= htmlspecialchars($lost_item["Description"])?></textarea>
                        <?php if ($errors["Description"]): ?><span class="error"><?= htmlspecialchars($errors["Description"])?></span><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="Photo">Upload Photo (Optional)</label>
                        <input type="file" name="Photo" id="Photo" accept="image/png, image/jpeg, image/gif, image/webp">
                        <small style="color:#666;">Max size: 5MB. If no photo is uploaded, a default image will be used.</small>
                        <?php if ($errors["Photo"]): ?><span class="error"><?= htmlspecialchars($errors["Photo"])?></span><?php endif; ?>
                    </div>
                </fieldset>

                <div class="info-box">
                    <i class="fas fa-shield-alt"></i> <strong>Verification Detail:</strong> Please provide a "Secret Detail" that only you would know.
                </div>

                <fieldset>
                    <legend><i class="fas fa-user-secret"></i> Proof of Ownership</legend>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="SecretDetailType">Proof Type <span class="required">*</span></label>
                                <select name="SecretDetailType" id="SecretDetailType" required>
                                    <option value="">-- Select Type --</option>
                                    <?php foreach ($proof_types as $type): ?>
                                    <option value="<?= $type ?>" <?= ($lost_item["SecretDetailType"] == $type)? "selected": ""?>><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($errors["SecretDetailType"]): ?><span class="error"><?= htmlspecialchars($errors["SecretDetailType"])?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="SecretDetailValue">Secret Value <span class="required">*</span></label>
                                <input type="text" name="SecretDetailValue" id="SecretDetailValue" 
                                       placeholder="e.g. 'Blue Lockscreen'"
                                       value="<?= htmlspecialchars($lost_item["SecretDetailValue"])?>" required>
                                <?php if ($errors["SecretDetailValue"]): ?><span class="error"><?= htmlspecialchars($errors["SecretDetailValue"])?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </fieldset>
                
                <button type="submit" class="btn-submit">Submit Report</button>
            </form>
        </div>
    </div>

</body>
</html>