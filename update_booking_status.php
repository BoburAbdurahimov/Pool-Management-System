<?php
session_start(); // Needed for session messages
include_once("db.php"); // Adjust path as needed

// --- Get Parameters ---
$booking_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$new_status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);

// --- Validation ---
$allowed_statuses = ['confirmed', 'cancelled', 'completed']; // Define which statuses can be set via this script
$error_message = null;

if (!$booking_id) {
    $error_message = "Invalid Booking ID provided.";
} elseif (!$new_status || !in_array($new_status, $allowed_statuses)) {
    $error_message = "Invalid or disallowed status provided.";
} elseif (!isset($db)) {
     $error_message = "Database connection not available.";
}

if ($error_message) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => $error_message];
    header('Location: bookings.php'); // Redirect back to list on error
    exit;
}

// --- Check Current Status (Optional but recommended) ---
// You might want to add checks here, e.g., cannot confirm a cancelled booking.
/*
$check_sql = "SELECT status FROM booking WHERE id = ?";
$stmt_check = mysqli_prepare($db, $check_sql);
mysqli_stmt_bind_param($stmt_check, "i", $booking_id);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
if ($row_check = mysqli_fetch_assoc($result_check)) {
    $current_status = $row_check['status'];
    if ($current_status == 'cancelled' && $new_status == 'confirmed') {
        // Example check
        $_SESSION['message'] = ['type' => 'warning', 'text' => 'Cannot confirm an already cancelled booking.'];
        header('Location: bookings.php');
        exit;
    }
} else {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Booking not found.'];
    header('Location: bookings.php');
    exit;
}
mysqli_stmt_close($stmt_check);
*/

// --- Update Status --- 
$booking_id_safe = mysqli_real_escape_string($db, $booking_id);
$new_status_safe = mysqli_real_escape_string($db, $new_status);

$update_sql = "UPDATE booking SET status = ?, updated_at = NOW() WHERE id = ?";
$stmt = mysqli_prepare($db, $update_sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "si", $new_status_safe, $booking_id_safe);
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $_SESSION['message'] = ['type' => 'success', 'text' => "Booking status updated to '{$new_status}'."];
            
            // TODO: Add logic if status change affects other things
            // e.g., if cancelling, mark the pool as available if this was the booking holding it?
            // if ($new_status == 'cancelled') { ... check pool status and update if needed ... }
            
        } else {
             $_SESSION['message'] = ['type' => 'warning', 'text' => 'Booking status was not changed (it might already be set or booking not found).'];
        }
    } else {
        $error_message = "Error updating booking status: " . mysqli_stmt_error($stmt);
        error_log($error_message);
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error updating status.'];
    }
    mysqli_stmt_close($stmt);
} else {
    $error_message = "Database error preparing status update: " . mysqli_error($db);
    error_log($error_message);
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error preparing update.'];
}

// --- Redirect Back ---
header('Location: bookings.php'); // Redirect back to the list page
exit;

?> 