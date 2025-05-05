<?php
session_start();
include_once("db.php"); // Ensure DB connection is included

// Redirect if accessed directly or without necessary data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirm_order'])) {
    header('Location: bar.php'); // Redirect to bar menu if accessed improperly
    exit;
}

// --- Data Validation ---
$client_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
$client_status = filter_input(INPUT_POST, 'client_status', FILTER_SANITIZE_STRING);
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

if (!$client_id || empty($cart)) {
    $_SESSION['error_message'] = "Missing client information or empty cart. Cannot process order.";
    // Redirect back to the order page, attempting to preserve client context if possible
    $redirect_url = 'ordered_bar_items.php';
    if ($client_id && $client_status) {
         $redirect_url .= '?client_id=' . urlencode($client_id) . '&client_status=' . urlencode($client_status);
    }
    header('Location: ' . $redirect_url);
    exit;
}

if (!isset($db)) {
     $_SESSION['error_message'] = "Database connection error. Cannot process order.";
     header('Location: ordered_bar_items.php?client_id=' . urlencode($client_id) . '&client_status=' . urlencode($client_status));
     exit;
}

// --- Database Operations ---
mysqli_begin_transaction($db);

try {
    // Prepare statement for inserting order items
    // Assuming order_items table columns: id, client_id, bar_item_id, quantity, item_price, created_at, Payment_status
    $insert_query = "INSERT INTO order_items (client_id, bar_item_id, quantity, price_at_order, created_at, Payment_status) VALUES (?, ?, ?, ?, NOW(), 'unpaid')";
    $stmt = mysqli_prepare($db, $insert_query);

    if (!$stmt) {
        throw new Exception("Database prepare statement failed: " . mysqli_error($db));
    }

    foreach ($cart as $item_id => $item) {
        // Validate item data from cart
        $bar_item_id = filter_var($item_id, FILTER_VALIDATE_INT);
        $quantity = filter_var($item['quantity'], FILTER_VALIDATE_INT);
        $item_price = filter_var($item['price'], FILTER_VALIDATE_FLOAT); // Renamed variable for clarity

        if (!$bar_item_id || !$quantity || $item_price === false || $quantity <= 0 || $item_price < 0) {
            throw new Exception("Invalid item data found in cart for item ID: " . htmlspecialchars($item_id));
        }

        mysqli_stmt_bind_param($stmt, "iiid", $client_id, $bar_item_id, $quantity, $item_price);
        
        if (!mysqli_stmt_execute($stmt)) {
             throw new Exception("Failed to insert order item ID " . htmlspecialchars($bar_item_id) . ": " . mysqli_stmt_error($stmt));
        }
    }

    mysqli_stmt_close($stmt);

    // Commit the transaction
    mysqli_commit($db);

    // Clear the cart and client context from session
    $_SESSION['cart'] = [];
    unset($_SESSION['current_order_client_id']);
    unset($_SESSION['current_order_client_status']);

    // --- Redirection based on client status ---
    $_SESSION['success_message'] = "Bar order placed successfully!"; // Optional success message

    // Restore conditional redirection
    if (strtolower($client_status) === 'regular') {
        // Redirect Regular clients to billing to potentially pay immediately
        header('Location: billing.php?client_id=' . urlencode($client_id) . '&order_success=1');
        exit;
    } else {
        // Redirect VIP (and any others) to the main dashboard/client list
        // index.php is often the dashboard in these templates
        header('Location: index.php?order_success=1'); 
        exit;
    }

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($db);
    
    // Log the detailed error
    error_log("Order Processing Error for client {$client_id}: " . $e->getMessage());
    
    // Set user-friendly error message
    $_SESSION['error_message'] = "An error occurred while processing your order. Please try again. Details: " . $e->getMessage(); // Provide detail in message for debugging if needed

    // Redirect back to the order page with client context
    header('Location: ordered_bar_items.php?client_id=' . urlencode($client_id) . '&client_status=' . urlencode($client_status));
    exit;
}

?> 