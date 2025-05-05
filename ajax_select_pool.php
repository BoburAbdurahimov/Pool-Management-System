<?php
session_start();
include_once("db.php"); // Include DB connection

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pool_id'], $_POST['pool_name'], $_POST['pool_rate'], $_POST['client_id'])) {

    $pool_id = filter_input(INPUT_POST, 'pool_id', FILTER_SANITIZE_NUMBER_INT);
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_SANITIZE_NUMBER_INT); 
    $pool_name = filter_input(INPUT_POST, 'pool_name', FILTER_SANITIZE_STRING);
    $pool_rate = filter_input(INPUT_POST, 'pool_rate', FILTER_VALIDATE_FLOAT); 

    // Basic validation
    if ($client_id && $pool_id && $pool_name && $pool_rate !== false) {
        
        // --- Conflict Check --- 
        if (isset($db)) {
            $pool_id_safe = mysqli_real_escape_string($db, $pool_id);
            $default_duration_hours = 1; // Check conflict for a 1-hour order starting now
            $now_time = date('Y-m-d H:i:s');
            
            // Calculate potential end time for conflict check
            try {
                $start_dt = new DateTime($now_time);
                $start_dt->modify("+" . $default_duration_hours . " hours");
                $potential_end_time = $start_dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $response['message'] = 'Error calculating time for conflict check.';
                 error_log("Time calculation error in ajax_select_pool: " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }

            // TODO: Adjust status checks ('ordered', 'active') if needed
            // Overlap condition: (new_start < existing_end) AND (new_end > existing_start)
            $conflict_query = "SELECT id FROM order_pools 
                               WHERE pool_id = {$pool_id_safe} 
                               AND (status = 'ordered' OR status = 'active')
                               AND ('{$now_time}' < end_time AND '{$potential_end_time}' > start_time)";
            
            $conflict_result = mysqli_query($db, $conflict_query);

            if ($conflict_result && mysqli_num_rows($conflict_result) > 0) {
                // Conflict found!
                $response['message'] = 'Pool ' . htmlspecialchars($pool_name) . ' has a conflicting order for the selected time.';
            } else if ($conflict_result === false) {
                 // Query error
                 $response['message'] = 'Database error checking for conflicts.';
                 error_log("Conflict check query error: " . mysqli_error($db));
            } else {
                // No conflict found, proceed to add to session
                if (!isset($_SESSION['client_pool_selections'])) {
                    $_SESSION['client_pool_selections'] = [];
                }
                if (!isset($_SESSION['client_pool_selections'][$client_id])) {
                    $_SESSION['client_pool_selections'][$client_id] = [];
                }
        
                $_SESSION['client_pool_selections'][$client_id][$pool_id] = [
                    'name' => $pool_name,
                    'price' => $pool_rate,
                    'duration' => $default_duration_hours // Start with default duration
                ];
        
                $response = [
                    'success' => true, 
                    'message' => htmlspecialchars($pool_name) . ' selected for client ' . $client_id
                ];
            }
        } else {
             $response['message'] = 'Database connection error. Cannot check for conflicts.';
        }
        // --- End Conflict Check ---

    } else {
        // Construct a more specific error message
        $errors = [];
        if (!$client_id) $errors[] = 'Invalid Client ID';
        if (!$pool_id) $errors[] = 'Invalid Pool ID';
        if (!$pool_name) $errors[] = 'Missing Pool Name';
        if ($pool_rate === false) $errors[] = 'Invalid Pool Rate';
        $response['message'] = 'Failed to select pool: ' . implode(', ', $errors) . '.';
    }
} else {
    // Identify missing fields
    $missing_fields = [];
    if (!isset($_POST['pool_id'])) $missing_fields[] = 'pool_id';
    if (!isset($_POST['pool_name'])) $missing_fields[] = 'pool_name';
    if (!isset($_POST['pool_rate'])) $missing_fields[] = 'pool_rate';
    if (!isset($_POST['client_id'])) $missing_fields[] = 'client_id';
    if (!empty($missing_fields)) {
         $response['message'] = 'Missing required data: ' . implode(', ', $missing_fields);
    } else {
        $response['message'] = 'Invalid request method.';
    }   
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 