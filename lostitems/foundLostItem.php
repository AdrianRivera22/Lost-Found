<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/loginAccount.php"); 
    exit("Please log in to report finding an item.");
}

require_once "../classes/LostItems.php"; 
$lostItemObj = new LostItems(); 

$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
$finder_user_id = $_SESSION['user_id']; 
$message = "";
$message_type = "error"; 

if (!$item_id) {
    $message = "Invalid Item ID provided.";
} else {
    try {
        if ($lostItemObj->markAsFoundByFinder($item_id, $finder_user_id)) {
            $message = "✅ Success! Item #{$item_id} has been marked as 'Pending Return'. An administrator will be notified to facilitate the return.";
            $message_type = "success";
        } else {
             $message = "❌ Could not mark item as found. It might already be claimed, returned, or the ID is incorrect.";
        }
    } catch (Exception $e) {
         $message = "❌ Error: " . $e.getMessage();
    }
}

$_SESSION['flash_message'] = $message;
$_SESSION['flash_message_type'] = $message_type;

header("Location: viewLostItems.php");
exit(); 
?>