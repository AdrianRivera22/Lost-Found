<?php

    require_once "../classes/student.php";
    $studentObj = new Student();
    $search = "";
    $college_filter ="";
    if($_SERVER["REQUEST_METHOD"] == "GET") {
    $search = isset($_GET["search"])? trim(htmlspecialchars($_GET["search"])) : "";
    $college_filter = isset($_GET["college_filter"])? trim(htmlspecialchars($_GET["college_filter"])) : "";
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Accounts</title>
</head>
<body>

<h1>List of Accounts</h1>  
<form action="" method="get">
    <label for="search">Search Last Name: </label>
    <input type="search" name="search" id="search" value="<?= $search ?>">
    <input type="submit" value="Search"> <br>
    <label for="college_filter">Filter By College:</label>
    <select name="college_filter" id="college_filter">
        <option value="">--Select College--</option>
        <option value="College of Computer Science" <?= ($college_filter == "College of Computer Science") ? "selected" : "" ?>>College of Computer Science</option>
        <option value="College of Liberal Arts" <?= ($college_filter == "College of Liberal Arts") ? "selected" : "" ?>>College of Liberal Arts</option>
        <option value="College of Nursing" <?= ($college_filter == "College of Nursing") ? "selected" : "" ?>>College of Nursing</option>
    </select>
     <input type="submit" value="Filter">
    
</form>

<a href="addstudent.php">Add Student</a>
<table border="1">
    <tr>
        <td>No.</td>
        <td>Student ID</td>
        <td>Last Name</td>
        <td>First Name</td>
        <td>Middle Name</td>
        <td>Contact Number</td>
        <td>Email</td>
        <td>College</td>
    </tr>

    <?php
    $no=1;
    foreach($studentObj->viewAccount($search, $college_filter) as $student){
        $message ="Are you sure you want to delete Student " . $student["First_Name"] . "?";
    ?>
    <tr>
         <td><?=$no++?></td>
        <td><?= $student["StudentID"]?></td>
        <td><?= $student["Last_Name"]?></td>
        <td><?= $student["First_Name"]?></td>
        <td><?= $student["Middle_Name"]?></td>
        <td><?= $student["PhoneNo"]?></td>
        <td><?= $student["Email"]?></td>
        <td><?= $student["College"]?></td>
    </tr>
   <?php 
    } 
    ?>
  
</table>
    
</body>
</html>