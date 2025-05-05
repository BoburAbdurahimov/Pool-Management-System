<?php
session_start(); // Start session if needed for authorization
include("db.php"); // Include database connection

// Basic input validation
if (!isset($_GET['pool_id']) || !is_numeric($_GET['pool_id']) || !isset($_GET['new_status'])) {
    // Redirect or show error if parameters are missing or invalid
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid request. Missing parameters.'];
    header("Location: pool.php");
    exit;
}

$pool_id = (int)$_GET['pool_id'];
$new_status = mysqli_real_escape_string($db, $_GET['new_status']); // Sanitize status

// **Authorization Check (Example - Implement as needed)**
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
//     $_SESSION['message'] = ['type' => 'danger', 'text' => 'You are not authorized to perform this action.'];
//     header("Location: pool.php");
//     exit;
// }

// Validate the new status (allow 'available' or 'maintenance')
$allowed_statuses = ['available', 'maintenance'];
if (!in_array($new_status, $allowed_statuses)) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid pool status provided.'];
    header("Location: pool.php");
    exit;
}

// Check if pool exists (optional but good practice)
$check_sql = "SELECT id FROM pool WHERE id = ?";
$check_stmt = mysqli_prepare($db, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $pool_id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) == 0) {
     $_SESSION['message'] = ['type' => 'danger', 'text' => 'Pool not found.'];
     header("Location: pool.php");
     exit;
}
mysqli_stmt_close($check_stmt);


// Prepare the UPDATE statement
$sql = "UPDATE pool SET status_pool = ? WHERE id = ?";
$stmt = mysqli_prepare($db, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "si", $new_status, $pool_id);

    if (mysqli_stmt_execute($stmt)) {
        // Success message
        $action_text = ($new_status === 'maintenance') ? 'marked for maintenance' : 'made available';
        $_SESSION['message'] = ['type' => 'success', 'text' => "Pool #{$pool_id} successfully {$action_text}."];
    } else {
        // Error message
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error updating pool status: ' . mysqli_error($db)];
    }
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error preparing statement: ' . mysqli_error($db)];
}

mysqli_close($db);

// Redirect back to pool management page
header("Location: pool.php");
exit;
?> 