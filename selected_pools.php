<?php 
session_start();

// --- Get Client ID from URL ---
// Client ID is essential for this page
if (!isset($_GET['client_id']) || !is_numeric($_GET['client_id'])) {
    // Redirect or show error if client_id is missing or invalid
    // header('Location: select_client_page.php'); // Example redirect
    die('Error: Client ID is required to manage pool selections.');
}
$client_id = (int)$_GET['client_id'];
$client_status = null; // Initialize client status
// --- End Get Client ID ---

// --- Initialize Client-Specific Session ---
if (!isset($_SESSION['client_pool_selections'])) {
    $_SESSION['client_pool_selections'] = [];
}
if (!isset($_SESSION['client_pool_selections'][$client_id])) {
    $_SESSION['client_pool_selections'][$client_id] = [];
}
// --- End Initialize Session ---

// --- Clear selections if it's a new registration redirect ---
if (isset($_GET['register']) && $_GET['register'] === 'success') {
    unset($_SESSION['client_pool_selections'][$client_id]);
    // Optional: Redirect to remove the register=success param from URL to prevent clearing on refresh
    // header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?client_id=' . $client_id);
    // exit;
}
// --- End Clear on Register ---

// Handle Remove Pool action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_pool_id'])) {
    $pool_id_to_remove = filter_input(INPUT_POST, 'remove_pool_id', FILTER_SANITIZE_NUMBER_INT);
    if ($pool_id_to_remove && isset($_SESSION['client_pool_selections'][$client_id][$pool_id_to_remove])) {
        unset($_SESSION['client_pool_selections'][$client_id][$pool_id_to_remove]);
    }
    // Redirect back to the same page but include client_id
    header('Location: ' . $_SERVER['PHP_SELF'] . '?client_id=' . $client_id);
    exit;
}

// Handle Clear Selection action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_selection'])) {
    $_SESSION['client_pool_selections'][$client_id] = []; 
    // Redirect back to the same page but include client_id
    header('Location: ' . $_SERVER['PHP_SELF'] . '?client_id=' . $client_id);
    exit;
}

// --- Handle Confirm Order (Save to DB) Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_order'])) {
    // Double-check client_id from POST matches the one in session context
    $posted_client_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
    
    if ($posted_client_id && $posted_client_id === $client_id && !empty($_SESSION['client_pool_selections'][$client_id])) {
        include_once('db.php'); // Ensure DB connection is included
        
        if (isset($db)) {
            $ordering_success = true; // Assume success initially
            $current_time = date('Y-m-d H:i:s');
            $client_id_safe = mysqli_real_escape_string($db, $client_id); // Use safe client_id

            // --- Fetch Client Status FIRST ---
            $client_status = null; // Reset before fetching
            $status_query = "SELECT status_client FROM client WHERE id = {$client_id_safe}";
            $status_result = mysqli_query($db, $status_query);
            if ($status_result && mysqli_num_rows($status_result) > 0) {
                $client_data = mysqli_fetch_assoc($status_result);
                $client_status = $client_data['status_client'];
            } else {
                // Handle error: Client not found or status couldn't be fetched
                $_SESSION['error_message'] = "Could not determine client status for ordering.";
                header('Location: ' . $_SERVER['PHP_SELF'] . '?client_id=' . $client_id);
                exit();
            }
            // --- End Fetch Client Status ---

            // Prepare INSERT statement OUTSIDE the loop for efficiency
            $insert_sql = "INSERT INTO order_pools (
                                client_id, pool_id, created_at, updated_at, status, 
                                Payment_status, start_time, end_time
                            ) VALUES (
                                ?, ?, NOW(), NOW(), 'ordered', 
                                'unpaid', NOW(), ? 
                            )";
            $stmt = mysqli_prepare($db, $insert_sql);
            if (!$stmt) {
                // Handle prepare error
                $_SESSION['error_message'] = "Database error preparing statement: " . mysqli_error($db);
                error_log("Prepare statement failed: " . mysqli_error($db));
                header('Location: ' . $_SERVER['PHP_SELF'] . '?client_id=' . $client_id);
                exit();
            }

            foreach ($_SESSION['client_pool_selections'][$client_id] as $pool_id => $pool_details) {
                $duration = isset($pool_details['duration']) ? (int)$pool_details['duration'] : 1;
                if ($duration < 1) $duration = 1;
                
                // Calculate end_time_for_sql: either NULL or NOW() + INTERVAL
                $end_time_for_sql = null; // Default for VIP or calculation failure
                if ($client_status !== 'VIP') {
                    // Use a separate query to get the calculated end time from MySQL
                    $calc_end_time_query = "SELECT NOW() + INTERVAL ? HOUR as calculated_end_time";
                    $stmt_calc = mysqli_prepare($db, $calc_end_time_query);
                    if ($stmt_calc) {
                        mysqli_stmt_bind_param($stmt_calc, "i", $duration);
                        if (mysqli_stmt_execute($stmt_calc)) {
                            $result_calc = mysqli_stmt_get_result($stmt_calc);
                            if ($row_calc = mysqli_fetch_assoc($result_calc)) {
                                $end_time_for_sql = $row_calc['calculated_end_time'];
                            }
                        }
                        mysqli_stmt_close($stmt_calc);
                    }
                     // Log if calculation failed, but proceed with NULL end_time
                    if ($end_time_for_sql === null) {
                       error_log("Failed to calculate end_time using SQL for client {$client_id}, pool {$pool_id}, duration {$duration}");
                    } 
                } // End if not VIP

                // Bind parameters for the main INSERT statement
                // Types: i (client_id), i (pool_id), s (end_time_for_sql or NULL)
                 mysqli_stmt_bind_param($stmt, "iis", $client_id, $pool_id, $end_time_for_sql);

                // Execute the prepared statement
                if (!mysqli_stmt_execute($stmt)) {
                    error_log("Error inserting pool order for client {$client_id}, pool {$pool_id}: " . mysqli_stmt_error($stmt));
                    $ordering_success = false;
                     $_SESSION['error_message'] = "Error saving order for Pool ID {$pool_id}. " . mysqli_stmt_error($stmt); 
                    break; // Stop processing on error
                } else {
                    // --- Create Scheduled Event for Regular Clients --- 
                    if ($client_status === 'Regular' && $end_time_for_sql !== null) {
                        $new_order_pool_id = mysqli_insert_id($db); // Get the ID of the order just inserted
                        if ($new_order_pool_id > 0) {
                             // Create a unique event name
                            $event_name = "complete_order_{$new_order_pool_id}_". time(); // Append timestamp for uniqueness
                            // Ensure pool_id is safe
                            $pool_id_safe_event = mysqli_real_escape_string($db, $pool_id);
                            
                            // Make sure end time is properly quoted for SQL
                            $end_time_sql_quoted = mysqli_real_escape_string($db, $end_time_for_sql); 
                            
                            // The SQL for the event body
                            $event_sql = "
                                CREATE EVENT IF NOT EXISTS `{$event_name}`
                                ON SCHEDULE AT '{$end_time_sql_quoted}'
                                DO
                                BEGIN
                                    UPDATE order_pools 
                                    SET status = 'completed' 
                                    WHERE id = {$new_order_pool_id} AND status = 'ordered';
                                    
                                    UPDATE pool 
                                    SET status_pool = 'available' 
                                    WHERE id = {$pool_id_safe_event} AND status_pool = 'ordered';
                                END;
                            ";
                            
                            // Execute the CREATE EVENT statement
                            if (!mysqli_query($db, $event_sql)) {
                                // Log the error but don't stop the whole process
                                error_log("Warning: Could not create completion event '{$event_name}' for order ID {$new_order_pool_id}. MySQL Error: " . mysqli_error($db));
                            } else {
                                error_log("Successfully created completion event '{$event_name}' for order ID {$new_order_pool_id} scheduled at {$end_time_for_sql}.");
                            }
                        } else {
                             error_log("Warning: Could not get insert ID after inserting order for pool {$pool_id}, client {$client_id}. Cannot create completion event.");
                        }
                    }
                    // --- End Create Scheduled Event --- 
                }
            }
            mysqli_stmt_close($stmt); // Close statement AFTER the loop

            // Check if loop was stopped due to an error before proceeding
            if (!$ordering_success) {
                 header('Location: ' . $_SERVER['PHP_SELF'] . '?client_id=' . $client_id);
                 exit();
            }
            // --- End Check ---

            // --- Update Pool Status if ordering was successful ---
            if ($ordering_success) { 
                // Now, update the status in the main pool table for all ordered pools in this transaction
                foreach ($_SESSION['client_pool_selections'][$client_id] as $pool_id => $pool_details) {
                    $pool_id_safe = mysqli_real_escape_string($db, $pool_id);
                    // THIS IS THE KEY QUERY:
                    $update_pool_query = "UPDATE pool SET status_pool = 'ordered' WHERE id = {$pool_id_safe}"; 

                    // -- TEMPORARY DEBUGGING START --
                    echo "<hr>Attempting to update pool ID: " . $pool_id_safe . "<br>";
                    echo "Query: " . $update_pool_query . "<br>";
                    // -- TEMPORARY DEBUGGING END --

                    $update_result = mysqli_query($db, $update_pool_query); // Store the result

                    // -- TEMPORARY DEBUGGING START --
                    if ($update_result) {
                        echo "Pool ID " . $pool_id_safe . " UPDATE query SUCCESS reported by MySQL.<br>";
                    } else {
                        echo "Pool ID " . $pool_id_safe . " UPDATE query FAILED reported by MySQL.<br>";
                        echo "MySQL Error: " . mysqli_error($db) . "<br>"; // Display MySQL error directly
                        // Error logging if the update fails
                        error_log("Error updating pool status to ordered for pool {$pool_id}: " . mysqli_error($db));
                    }
                    echo "<hr>";
                    // -- TEMPORARY DEBUGGING END --
                }
                // --- End Update Pool Status ---

                // -- TEMPORARY DEBUGGING START --
                echo "Finished updating pool statuses. Preparing to redirect...<br>";
                // die(); // You can uncomment this die() temporarily if you want to stop before the redirect happens
                // -- TEMPORARY DEBUGGING END --

                // Clear the selections for this client from the session after successful ordering
                unset($_SESSION['client_pool_selections'][$client_id]);

                // Client status is already fetched ($client_status variable holds it)
                // No need to fetch client status again here

                // --- Conditional Redirect based on Client Status ---
                if ($client_status === 'Regular') {
                    // Redirect Regular clients to Billing
                    $redirect_url = 'billing.php?client_id=' . $client_id;
                    // Optionally add status if billing.php uses it, though it will fetch it anyway
                    // if ($client_status) { $redirect_url .= '&client_status=' . urlencode($client_status); }
                } else {
                    // Redirect VIP clients (and any others) to Dashboard (or another preferred page)
                    $redirect_url = 'index.php?ordering_success=1'; // Example: Redirect VIPs to dashboard
                }
                // --- End Conditional Redirect ---
                
                header('Location: ' . $redirect_url);
                exit();
            } else {
                // Handle ordering failure (e.g., show an error message)
                // Set a session flash message maybe?
                $_SESSION['error_message'] = "There was an error saving the order. Please try again.";
                // Redirect back to the selection page to show the error
                header('Location: ' . $_SERVER['PHP_SELF'] . '?client_id=' . $client_id);
                exit();
            }
        } else {
            die("Database connection error during order confirmation.");
        }
    } else {
        // Handle cases like mismatched client ID or empty selection
        $_SESSION['error_message'] = "Order could not be confirmed. Invalid data or no pools selected.";
        header('Location: ' . $_SERVER['PHP_SELF'] . '?client_id=' . $client_id);
        exit();
    }
}
// --- End Handle Confirm Order ---

include("header.php"); 

// --- Fetch Client Status (moved down, fetch only if needed for display/links) ---
if (isset($db)) { 
    // Status might already be fetched if a ordering was just saved, check first
    if ($client_status === null) { 
        $client_id_safe = mysqli_real_escape_string($db, $client_id);
        $status_query = "SELECT status_client FROM client WHERE id = {$client_id_safe}";
        $status_result = mysqli_query($db, $status_query);
        if ($status_result && mysqli_num_rows($status_result) > 0) {
            $client_data = mysqli_fetch_assoc($status_result);
            $client_status = $client_data['status_client'];
        } else {
            error_log("Client ID {$client_id} not found when fetching status for display."); 
            // Maybe set status to a default or show indication of error?
            // $client_status = 'Unknown'; 
        }
    }
} else {
    error_log("Database connection (\$db) not available in selected_pools.php for status fetch.");
}
// --- End Fetch Client Status ---
?>

<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">

<?php 
// Display Client ID and Status if available
if ($client_id !== null): 
?>
<div class="float-end text-muted small mb-2">
    Client ID: <?php echo htmlspecialchars($client_id); ?> 
    <?php if ($client_status): ?> | Status: <?php echo htmlspecialchars($client_status); ?><?php endif; ?>
</div>
<?php endif; ?>

<?php
// Display error messages if set (e.g., from failed ordering)
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']); // Clear message after displaying
}
?>



<h4 class="py-3 mb-4">Selected Pools for Client #<?php echo htmlspecialchars($client_id); ?></h4>

<div class="card">
  <h5 class="card-header">Currently Selected</h5>
  <div class="table-responsive text-nowrap">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Pool Name</th>
          <th>Price/Hour</th> 
          <?php if ($client_status !== 'VIP'): // Hide for VIP ?>
          <th>Duration (Hours)</th>
          <th>Total Price</th>
          <?php else: ?>
          <th>Usage Time</th>
          <?php endif; ?>
          <th>Action</th>
        </tr>
      </thead>
      <tbody class="table-border-bottom-0">
        <?php 
        $row_number = 1;
        $grand_total = 0;
        // Use the client-specific session key
        if (isset($_SESSION['client_pool_selections'][$client_id]) && !empty($_SESSION['client_pool_selections'][$client_id])):
            foreach ($_SESSION['client_pool_selections'][$client_id] as $pool_id => $pool):
                $name = htmlspecialchars($pool['name'] ?? 'N/A');
                $price = isset($pool['price']) ? (float)$pool['price'] : 0;
        ?>
        <tr>
          <td><?php echo $row_number; ?></td>
          <td><strong><?php echo $name; ?></strong></td>
          <td>$<?php echo number_format($price, 2); ?></td> 
          <?php 
          if ($client_status !== 'VIP'): 
              $duration = isset($pool['duration']) ? (int)$pool['duration'] : 1;
              if ($duration < 1) $duration = 1;
              $total_price = $price * $duration;
              $grand_total += $total_price;
          ?>
          <td>
            <input 
                type="number" 
                class="form-control form-control-sm duration-input" 
                style="width: 80px;" 
                value="<?php echo $duration; ?>" 
                min="1" 
                step="1"
                data-pool-id="<?php echo $pool_id; ?>"
                data-price-per-hour="<?php echo $price; ?>">
          </td>
          <td class="total-price-cell" data-pool-id="<?php echo $pool_id; ?>">$<?php echo number_format($total_price, 2); ?></td>
          <?php else: // Display for VIP ?>
           <td><span class="text-muted fst-italic">Calculated on checkout</span></td>
          <?php endif; ?>
          <td>
             <form action="selected_pools.php?client_id=<?php echo $client_id; ?>" method="post" style="display: inline;"> <?php // Pass client_id in action ?>
                <input type="hidden" name="remove_pool_id" value="<?php echo $pool_id; ?>">
                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
            </form>
          </td>
        </tr>
        <?php 
            $row_number++;
            endforeach;
        else:
        ?>
        <tr>
          <td colspan="6" class="text-center">No pools currently selected for this client.</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="mt-4 d-flex justify-content-between">
    <a href="order_pools.php?from=selected&client_id=<?php echo $client_id; ?>" class="btn btn-secondary">Back to Available Pools</a> <?php // Add client_id back ?>
    <div> <!-- Wrap buttons on the right -->
        <form action="selected_pools.php?client_id=<?php echo $client_id; ?>" method="post" style="display: inline;"> <?php // Pass client_id in action ?>
            <button type="submit" name="clear_selection" class="btn btn-warning me-2" <?php echo (empty($_SESSION['client_pool_selections'][$client_id]) ? 'disabled' : ''); ?>>Clear Selection</button>
        </form>
        <?php 
        // Enable the "Confirm & Save Order" button for all clients
        $confirm_disabled = empty($_SESSION['client_pool_selections'][$client_id]) ? 'disabled' : ''; 
        ?>
        <form action="selected_pools.php?client_id=<?php echo $client_id; ?>" method="post" style="display: inline-block;"> 
             <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_id); ?>">
             <button type="submit" name="save_order" class="btn btn-primary" <?php echo $confirm_disabled; ?>>
                Confirm & Save Order
            </button> 
        </form>
      
    </div>
</div>

</div> 
</div> 

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.querySelector('.table tbody');
    const currentClientId = <?php echo json_encode($client_id); ?>; // Get client_id for JS

    if (tableBody) {
        tableBody.addEventListener('change', function(event) {
            if (event.target.matches('.duration-input')) {
                const input = event.target;
                const poolId = input.dataset.poolId;
                const duration = parseInt(input.value, 10);
                const pricePerHour = parseFloat(input.dataset.pricePerHour);

                if (isNaN(duration) || duration < 1) {
                    console.warn('Invalid duration entered');
                    input.value = 1; 
                    input.dispatchEvent(new Event('change')); 
                    return; 
                }
                
                const totalPriceCell = tableBody.querySelector(`.total-price-cell[data-pool-id="${poolId}"]`);

                const formData = new FormData();
                formData.append('pool_id', poolId);
                formData.append('duration', duration);
                formData.append('client_id', currentClientId); // <-- Add client_id here

                // Send AJAX request to update session
                fetch('ajax_update_pool_duration.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Duration updated successfully for pool:', poolId);
                        if (totalPriceCell) {
                            totalPriceCell.textContent = data.new_total_formatted;
                        }
                        // updateGrandTotal(); // Optional
                    } else {
                        console.error('Error updating duration:', data.message);
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                    alert('An error occurred while updating duration.');
                });
            }
        });
    }

    // Optional: Grand Total update function remains the same
});
</script>

<?php include("footer.php"); ?> 