<?php
session_start();

$response = ['success' => false, 'message' => 'Invalid request'];

// Expect client_id along with pool_id and duration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pool_id'], $_POST['duration'], $_POST['client_id'])) { 
    $pool_id = filter_input(INPUT_POST, 'pool_id', FILTER_SANITIZE_NUMBER_INT);
    $duration = filter_input(INPUT_POST, 'duration', FILTER_SANITIZE_NUMBER_INT);
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_SANITIZE_NUMBER_INT); // Get client_id

    // Validate input (including client_id)
    if ($client_id && $pool_id && $duration > 0 && isset($_SESSION['client_pool_selections'][$client_id][$pool_id])) {
        
        // Update duration in the client-specific session
        $_SESSION['client_pool_selections'][$client_id][$pool_id]['duration'] = $duration;

        // Recalculate total price for this item using the client-specific session
        $price = isset($_SESSION['client_pool_selections'][$client_id][$pool_id]['price']) ? (float)$_SESSION['client_pool_selections'][$client_id][$pool_id]['price'] : 0;
        $new_total_price = $price * $duration;

        // Prepare success response
        $response = [
            'success' => true, 
            'message' => 'Duration updated for client ' . $client_id, 
            'pool_id' => $pool_id,
            'new_duration' => $duration,
            'new_total_formatted' => '$' . number_format($new_total_price, 2)
        ];

    } else {
        // Update error messages to reflect potential issues
        if (!$client_id) $response['message'] = 'Client ID missing or invalid.';
        elseif (!$pool_id) $response['message'] = 'Invalid Pool ID.';
        elseif ($duration <= 0) $response['message'] = 'Duration must be positive.';
        // Check client-specific session existence
        elseif (!isset($_SESSION['client_pool_selections'][$client_id])) $response['message'] = 'No selections found for this client.';
        elseif (!isset($_SESSION['client_pool_selections'][$client_id][$pool_id])) $response['message'] = 'Pool not found in this client\'s selection.';
        else $response['message'] = 'Invalid data.';
    }
} else {
    // Update error if required POST data is missing
    $missing_fields = [];
    if (!isset($_POST['pool_id'])) $missing_fields[] = 'pool_id';
    if (!isset($_POST['duration'])) $missing_fields[] = 'duration';
    if (!isset($_POST['client_id'])) $missing_fields[] = 'client_id';
    $response['message'] = 'Missing required data: ' . implode(', ', $missing_fields);
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 