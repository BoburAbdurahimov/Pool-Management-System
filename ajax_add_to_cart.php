<?php
session_start();

// Default response
$response = ['success' => false, 'message' => 'Invalid request'];

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle Add to Order POST request (only check for POST method)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_SANITIZE_NUMBER_INT);
    $item_name = filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_STRING);
    $item_price = filter_input(INPUT_POST, 'item_price', FILTER_VALIDATE_FLOAT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $item_category = filter_input(INPUT_POST, 'item_category', FILTER_SANITIZE_STRING);

    if ($item_id && $item_name && $item_price !== false && $quantity > 0 && $item_category) {
        if (isset($_SESSION['cart'][$item_id])) {
            $_SESSION['cart'][$item_id]['quantity'] += $quantity;
            $response = ['success' => true, 'message' => 'Quantity updated'];
        } else {
            $_SESSION['cart'][$item_id] = [
                'name' => $item_name,
                'price' => $item_price,
                'quantity' => $quantity,
                'category' => $item_category
            ];
            $response = ['success' => true, 'message' => 'Item added'];
        }
    } else {
        // If item data is invalid, send specific error
        $response = ['success' => false, 'message' => 'Invalid item data provided'];
    }
} 
// The initial $response ('Invalid request') handles non-POST requests

// Send JSON response back to the JavaScript
header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 