<?php
session_start();
// Include DB connection FIRST, as processing logic needs it.
include_once("db.php"); 

// --- Handle Payment Processing --- 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    if (isset($db)) {
        $client_id_to_process = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);

        if ($client_id_to_process) {
            $client_id_safe = mysqli_real_escape_string($db, $client_id_to_process);
            $affected_pool_ids = [];
            $charges_just_paid = []; // Array to hold details for receipt
            $total_paid = 0;
            $client_name_for_receipt = 'Client ' . $client_id_to_process; // Default

            // Fetch client name first
            $client_name_query = "SELECT full_name FROM client WHERE id = {$client_id_safe}";
            $client_name_result = mysqli_query($db, $client_name_query);
            if($client_name_result && $client_data = mysqli_fetch_assoc($client_name_result)) {
                $client_name_for_receipt = $client_data['full_name'];
            }

            mysqli_begin_transaction($db);

            try {
                error_log("[Billing POST] Starting payment process for client: {$client_id_to_process}"); // DEBUG
                // Generate Receipt Number ONCE for all items in this payment
                $receipt_number = 'R-' . date('Ymd') . '-' . $client_id_to_process . '-' . substr(time(), -4);
                $billing_status_paid = 1; // Assuming 1 means 'paid' 
                
                // --- FIX: Fetch current client status directly in POST handler ---
                $current_client_status = null;
                $status_query_post = "SELECT status_client FROM client WHERE id = ?";
                $stmt_status_post = mysqli_prepare($db, $status_query_post);
                if ($stmt_status_post) {
                    mysqli_stmt_bind_param($stmt_status_post, "i", $client_id_to_process);
                    if (mysqli_stmt_execute($stmt_status_post)) {
                        $result_status_post = mysqli_stmt_get_result($stmt_status_post);
                        if ($row_status_post = mysqli_fetch_assoc($result_status_post)) {
                            $current_client_status = $row_status_post['status_client'];
                        }
                    }
                     mysqli_stmt_close($stmt_status_post);
                }
                if ($current_client_status === null) {
                     throw new Exception("Could not determine client status for processing payment.");
                }
                // --- END FIX ---
                
                // $current_client_status = $selected_client['status_client'] ?? null; // REMOVED: Relied on GET variable

                // Prepare insert statements for billing_records OUTSIDE the loops
                $insert_pool_billing_query = "INSERT INTO billing_records 
                                                (client_id, pool_order_id, status, created_at, updated_at, receipt_id, total_bill, bar_items_order_id, booking_id)
                                             VALUES (?, ?, ?, NOW(), NOW(), ?, ?, NULL, NULL)";
                $stmt_pool_billing = mysqli_prepare($db, $insert_pool_billing_query);
                if (!$stmt_pool_billing) throw new Exception("Failed to prepare pool billing insert: " . mysqli_error($db));

                $insert_item_billing_query = "INSERT INTO billing_records 
                                                (client_id, bar_items_order_id, status, created_at, updated_at, receipt_id, total_bill, pool_order_id, booking_id)
                                             VALUES (?, ?, ?, NOW(), NOW(), ?, ?, NULL, NULL)";
                $stmt_item_billing = mysqli_prepare($db, $insert_item_billing_query);
                 if (!$stmt_item_billing) throw new Exception("Failed to prepare item billing insert: " . mysqli_error($db));

                // Prepare insert statement for penalty billing records
                $insert_penalty_billing_query = "INSERT INTO billing_records 
                                                   (client_id, penalty_id, status, created_at, updated_at, receipt_id, total_bill, bar_items_order_id, pool_order_id, booking_id)
                                                VALUES (?, ?, ?, NOW(), NOW(), ?, ?, NULL, NULL, NULL)"; // Added penalty_id placeholder
                $stmt_penalty_billing = mysqli_prepare($db, $insert_penalty_billing_query);
                if (!$stmt_penalty_billing) throw new Exception("Failed to prepare penalty billing insert: " . mysqli_error($db));

                // --- Fetch details of items being paid BEFORE updating --- 
                error_log("[Billing POST] Before fetching pool charges for client: {$client_id_to_process}"); // DEBUG
                
                // 1a. Fetch Pool Charges Details (and IDs for billing_records)
                $pool_query = "SELECT op.id, op.pool_id, op.created_at, op.start_time, op.end_time, op.status, p.hourly_rate, 
                                     IF(c.status_client = 'VIP' AND op.status = 'ordered', TIMESTAMPDIFF(SECOND, op.start_time, NOW()), NULL) as elapsed_seconds_now
                               FROM order_pools op
                               JOIN pool p ON op.pool_id = p.id
                               JOIN client c ON op.client_id = c.id
                               WHERE op.client_id = {$client_id_safe} 
                               AND op.Payment_status = 'unpaid'"; // Fetch all unpaid, regardless of status
                $pool_result = mysqli_query($db, $pool_query);
                if ($pool_result) {
                     while ($row = mysqli_fetch_assoc($pool_result)) {
                         $pool_order_id = $row['id']; // Get the specific order_pools.id
                         $minutes = 60; // Default
                         $final_description = "(1 hour - est.)"; // Default description detail

                         // --- Calculate final amount and handle updates based on status ---
                         if ($current_client_status === 'VIP') {
                             $start_timestamp = strtotime($row['start_time']);
                             $order_status = $row['status'];
                             
                             // Calculate elapsed seconds based on order status
                             if ($order_status === 'ordered') {
                                 $elapsed_seconds = $row['elapsed_seconds_now'] ?? null; 
                             } elseif ($order_status === 'completed' && !empty($row['end_time'])) {
                                 $end_timestamp = strtotime($row['end_time']);
                                 $elapsed_seconds = ($start_timestamp !== false && $end_timestamp !== false) ? ($end_timestamp - $start_timestamp) : null;
                             } else {
                                 $elapsed_seconds = null; // Cannot calculate
                             }

                             if ($elapsed_seconds !== null && $elapsed_seconds >= 0) { // Proceed if seconds calculation was valid
                                 // Calculate rate per minute
                                 $hourly_rate = (float)$row['hourly_rate'];
                                 $minute_rate = ($hourly_rate > 0) ? $hourly_rate / 60.0 : 0;
                                 
                                 // Calculate total minutes elapsed (round to nearest minute for billing)
                                 $total_minutes = round($elapsed_seconds / 60.0); 
                                 
                                 // Calculate final amount
                                 $amount = ($total_minutes >= 0) ? $total_minutes * $minute_rate : 0;
                                 
                                 // --- DEBUG LOG (POST) ---
                                 error_log("[Billing POST VIP Calc] Client: {$client_id_to_process}, OrderPool: {$pool_order_id}, Status: {$order_status}, Start: {$row['start_time']}, End: {$row['end_time']}, ElapsedSec: {$elapsed_seconds}, HourlyRate: {$hourly_rate}, MinuteRate: {$minute_rate}, TotalMins: {$total_minutes}, Amount: {$amount}");
                                 // --- END DEBUG LOG ---
                                 
                                 // --- Update order_pools based on original status (VIP) ---
                                 $vip_update_executed_successfully = false; // Flag for success
                                 $update_pool_table_status = false; // Flag to update pool table status

                                 // Adjust update based on original status
                                 if ($order_status === 'ordered') {
                                     $update_sql_vip = "UPDATE order_pools SET status = 'completed', Payment_status = 'paid', end_time = NOW() WHERE id = ?";
                                     error_log("[Billing POST VIP] Preparing SQL for 'ordered' status: " . $update_sql_vip);
                                     error_log("[Billing POST VIP] DB Connection Status before prepare: " . ($db ? 'Valid Object' : 'Invalid/Null') . " - Ping: " . (isset($db) && mysqli_ping($db) ? 'OK' : 'Failed/Unavailable'));
                                     $update_pool_table_status = true; // Mark pool as available now

                                     $stmt = mysqli_prepare($db, $update_sql_vip);
                                     if ($stmt) {
                                         mysqli_stmt_bind_param($stmt, "i", $pool_order_id);
                                         if (mysqli_stmt_execute($stmt)) {
                                             $vip_update_executed_successfully = true;
                                         } else {
                                             mysqli_stmt_close($stmt);
                                             throw new Exception("Failed to execute prepared VIP order_pools update (ordered) for ID {$pool_order_id}: " . mysqli_stmt_error($stmt));
                                         }
                                         mysqli_stmt_close($stmt);
                                     } else {
                                          throw new Exception("Failed to prepare VIP order_pools update statement (ordered) for ID {$pool_order_id}: " . mysqli_error($db));
                                     }

                                 } else { // Already completed, just mark as paid
                                     // $update_sql_vip = "UPDATE order_pools SET Payment_status = 'paid' WHERE id = ?"; // Original prepare SQL
                                     // --- TEMPORARY DEBUG: Try direct query instead of prepare ---
                                     $direct_update_sql = "UPDATE order_pools SET Payment_status = 'paid' WHERE id = {$pool_order_id}"; // Use ID directly
                                     error_log("[Billing POST VIP Debug] Attempting direct query for 'completed' status: " . $direct_update_sql);
                                     $direct_update_result = mysqli_query($db, $direct_update_sql);
                                     if ($direct_update_result) {
                                         error_log("[Billing POST VIP Debug] Direct query SUCCEEDED for ID {$pool_order_id}.");
                                         $vip_update_executed_successfully = true; // Mark as successful
                                     } else {
                                         error_log("[Billing POST VIP Debug] Direct query FAILED for ID {$pool_order_id}. Error: " . mysqli_error($db));
                                         // Do NOT set success flag. Error will be caught later.
                                     }
                                     // --- END TEMPORARY DEBUG ---
                                 }
                                 
                                 // --- Check if update was successful (either prepare/execute or direct query) ---
                                 if (!$vip_update_executed_successfully) {
                                      // If the direct query for 'completed' status failed, throw exception here
                                       throw new Exception("Failed to update VIP order_pools record (completed status) for ID {$pool_order_id}: " . mysqli_error($db)); // Use generic error or specific from direct query log
                                 }

                                 // --- Update pool table status if needed ---
                                 // Update pool table to available only if the order was previously 'ordered'
                                 if ($update_pool_table_status) {
                                     $update_sql_vip = "UPDATE order_pools SET status = 'completed', Payment_status = 'paid', end_time = NOW() WHERE id = ?";
                                     $stmt = mysqli_prepare($db, $update_sql_vip);
                                     if ($stmt) {
                                         mysqli_stmt_bind_param($stmt, "i", $pool_order_id);
                                         if (mysqli_stmt_execute($stmt)) {
                                             error_log("[Billing POST] Pool table updated successfully for ID {$pool_order_id}.");
                                         } else {
                                             mysqli_stmt_close($stmt);
                                             throw new Exception("Failed to execute prepared order_pools update for ID {$pool_order_id}: " . mysqli_stmt_error($stmt));
                                         }
                                         mysqli_stmt_close($stmt);
                                     } else {
                                         throw new Exception("Failed to prepare order_pools update statement for ID {$pool_order_id}: " . mysqli_error($db));
                                     }
                                 }
                             } else { // Calculation failed (invalid times)
                                 // Handle invalid start time - bill 0?
                                 $amount = 0; // Ensure amount is 0 if time is invalid
                                 $final_description = 'Pool ' . htmlspecialchars($row['pool_id']) . ' (Usage time error - Status: ' . $order_status . ' - not billed)';
                                 error_log("VIP Payment Error: Invalid time data for order_pool ID {$pool_order_id}. Status: {$order_status}, Start: {$row['start_time']}, End: {$row['end_time']}. Billing 0.");
                                 // Mark as paid/completed anyway?
                                 // Maybe add a separate update here to close it without charging
                             }
                         } else { // Regular client
                            // Use pre-calculated/estimated hours if available, else default
                            // $hours = calculate_regular_hours(...); // Or just keep default 1hr
                            $hours = 1; // Keep simple 1 hour estimate for now
                            
                            // --- Calculate amount for regular, ensuring it's not NULL (POST Handler) ---
                            $hourly_rate_regular = isset($row['hourly_rate']) ? (float)$row['hourly_rate'] : null;
                            if ($hourly_rate_regular !== null && is_numeric($hourly_rate_regular)) {
                                $amount = $hours * $hourly_rate_regular;
                            } else {
                                $amount = 0; // Default to 0 if rate is missing/invalid
                                // Log the error, but proceed with billing 0 for this item
                                error_log("[POST Handler] Billing Error (Regular Client ID: {$client_id_to_process}, Pool ID: {$row['pool_id']}, OrderPool ID: {$pool_order_id}): Hourly rate is missing or invalid ('{$row['hourly_rate']}'). Billing 0.");
                            }
                            // --- End amount calculation (POST Handler) ---
                            
                            // Update order_pools: Set paid, keep status ordered (event handles completion)
                            $update_reg_pool_stmt = mysqli_prepare($db, "UPDATE order_pools SET Payment_status = 'paid' WHERE id = ? AND Payment_status = 'unpaid'");
                             if ($update_reg_pool_stmt) {
                                 mysqli_stmt_bind_param($update_reg_pool_stmt, "i", $pool_order_id);
                                 if (!mysqli_stmt_execute($update_reg_pool_stmt)) {
                                      throw new Exception("Failed to update Regular order_pools record ID {$pool_order_id}: " . mysqli_stmt_error($update_reg_pool_stmt));
                                 }
                                 mysqli_stmt_close($update_reg_pool_stmt);
                                 // Scheduled event will set pool to available later
                             } else {
                                throw new Exception("Failed to prepare Regular order_pools update statement for ID {$pool_order_id}: " . mysqli_error($db));
                             }
                            $final_description = 'Pool ' . htmlspecialchars($row['pool_id']) . ' (' . $minutes . ' hour ordering)';
                            error_log("[Billing POST] Processed Regular Pool Order ID: {$pool_order_id}"); // DEBUG
                         }
                         // --- End calculation and individual update ---

                         $charges_just_paid[] = [
                             'date' => date('Y-m-d', strtotime($row['created_at'])),
                             'type' => 'Pool',
                             'description' => $final_description, // Use calculated description
                             'amount' => $amount
                         ];
                         $total_paid += $amount;
                         $affected_pool_ids[] = $row['pool_id']; 

                         // Insert individual billing record for this pool order
                         mysqli_stmt_bind_param($stmt_pool_billing, "iiisd", $client_id_to_process, $pool_order_id, $billing_status_paid, $receipt_number, $amount);
                         if (!mysqli_stmt_execute($stmt_pool_billing)) {
                            throw new Exception("Failed to insert billing record for pool order ID {$pool_order_id}: " . mysqli_stmt_error($stmt_pool_billing));
                         }
                     }
                 } else {
                     throw new Exception("Failed to query pool charges details: " . mysqli_error($db));
                 }
                 mysqli_stmt_close($stmt_pool_billing); // Close pool statement after loop

                 // 2a. Fetch Item Charges Details (and IDs for billing_records)
                 $item_receipt_query = "SELECT oi.id, oi.quantity, oi.created_at, oi.price_at_order, bi.name 
                                      FROM order_items oi 
                                      JOIN bar_items bi ON oi.bar_item_id = bi.id 
                                      WHERE oi.client_id = {$client_id_safe} AND oi.Payment_status = 'unpaid'";
                 $item_receipt_result = mysqli_query($db, $item_receipt_query);
                 if ($item_receipt_result) {
                     while ($row = mysqli_fetch_assoc($item_receipt_result)) {
                          $item_order_id = $row['id']; // Get the specific order_items.id
                          $quantity = (int)$row['quantity']; 
                          if ($quantity <= 0) $quantity = 1;
                          $amount = $quantity * (float)$row['price_at_order']; // Use price from order_items
                          $item_type = 'Bar/Shop'; 
                          $charges_just_paid[] = [
                               'date' => date('Y-m-d', strtotime($row['created_at'])),
                               'type' => $item_type,
                               'description' => $quantity . ' x ' . htmlspecialchars($row['name']),
                               'amount' => $amount
                          ];
                          $total_paid += $amount;

                          // Insert individual billing record for this item order
                          mysqli_stmt_bind_param($stmt_item_billing, "iiisd", $client_id_to_process, $item_order_id, $billing_status_paid, $receipt_number, $amount);
                          if (!mysqli_stmt_execute($stmt_item_billing)) {
                             throw new Exception("Failed to insert billing record for item order ID {$item_order_id}: " . mysqli_stmt_error($stmt_item_billing));
                          }
                     }
                 } else {
                     throw new Exception("Failed to query item charges details for receipt: " . mysqli_error($db));
                 }
                 mysqli_stmt_close($stmt_item_billing); // Close item statement after loop
                 
                 // 3a. Fetch Penalty Charges Details (for receipt and billing_records)
                 $penalty_fetch_query = "SELECT id, description, price, created_at FROM penalty WHERE client_id = {$client_id_safe} AND status = 'unpaid'";
                 $penalty_fetch_result = mysqli_query($db, $penalty_fetch_query);
                 if ($penalty_fetch_result) {
                     while ($row = mysqli_fetch_assoc($penalty_fetch_result)) {
                          $penalty_id = $row['id']; // Get the specific penalty.id
                          $amount = (float)$row['price'];
                          $charges_just_paid[] = [
                              'date' => date('Y-m-d', strtotime($row['created_at'])),
                              'type' => 'Penalty',
                              'description' => htmlspecialchars($row['description']),
                              'amount' => $amount
                          ];
                          $total_paid += $amount;

                          // Insert individual billing record for this penalty
                          // Types: i (client_id), i (penalty_id), i (status), s (receipt), d (amount)
                          mysqli_stmt_bind_param($stmt_penalty_billing, "iiisd", $client_id_to_process, $penalty_id, $billing_status_paid, $receipt_number, $amount);
                          if (!mysqli_stmt_execute($stmt_penalty_billing)) {
                             throw new Exception("Failed to insert billing record for penalty ID {$penalty_id}: " . mysqli_stmt_error($stmt_penalty_billing));
                          }
                     }
                 } else {
                      throw new Exception("Failed to query penalty charges details: " . mysqli_error($db));
                 }
                 mysqli_stmt_close($stmt_penalty_billing); // Close penalty statement after loop
                 
                // --- Now perform the updates --- 
                error_log("[Billing POST] Before updating item/penalty statuses for client: {$client_id_to_process}"); // DEBUG
                
                // 2b. Update order_items status to paid
                $update_items_query = "UPDATE order_items SET Payment_status = 'paid' WHERE client_id = {$client_id_safe} AND Payment_status = 'unpaid'";
                if (!mysqli_query($db, $update_items_query)) {
                    throw new Exception("Failed to update order_items: " . mysqli_error($db));
                }

                // 3b. Update penalty status to paid
                $update_penalties_query = "UPDATE penalty SET status = 'paid' WHERE client_id = {$client_id_safe} AND status = 'unpaid'"; // Adjust 'paid' if status value differs
                if (!mysqli_query($db, $update_penalties_query)) {
                    throw new Exception("Failed to update penalty statuses: " . mysqli_error($db));
                }

                // Store receipt data in session - total_paid is accumulated correctly
                $_SESSION['last_receipt_data'] = [
                    'client_id' => $client_id_to_process,
                    'client_name' => $client_name_for_receipt,
                    'receipt_number' => $receipt_number,
                    'date' => date('Y-m-d'),
                    'total_amount' => $total_paid, 
                    'items' => $charges_just_paid
                ];

                error_log("[Billing POST] Before committing transaction for client: {$client_id_to_process}"); // DEBUG
                mysqli_commit($db);

                // Clear any existing error message
                unset($_SESSION['error_message']);
                
                error_log("[Billing POST] Payment success, redirecting client {$client_id_to_process} to payment_complete.php"); // DEBUG
                // Redirect to payment complete page
                header('Location: payment_complete.php');
                exit();

            } catch (Exception $e) {
                mysqli_rollback($db);
                $_SESSION['error_message'] = "Payment processing failed: " . $e->getMessage();
                error_log("Payment Processing Error for client {$client_id_to_process}: " . $e->getMessage());
                header("Location: billing.php?client_id={$client_id_to_process}&error=1");
                exit();
            }

        } else {
             $_SESSION['error_message'] = "Invalid Client ID for payment processing.";
             header("Location: billing.php?client_id={$client_id_to_process}&error=1");
             exit();
        }
    } else {
        $_SESSION['error_message'] = "Database connection error. Cannot process payment.";
        header("Location: billing.php?client_id={$client_id_to_process}&error=1");
        exit();
    }
    exit();
}
// --- End Handle Payment Processing ---

// Now include the header, as no redirect happened if we reached here
include("header.php"); 

// --- Get Client Info and Check Permissions ---
$selected_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$selected_client = null;
$client_status = null; // Use this to store the actual status from DB
$charges = [];
$vip_clients_list = []; // Only used if accessing directly for VIPs
$can_view_page = false; // Flag to control page display
$error_message = null; // Store potential error messages

if ($selected_client_id) {
    // --- Scenario 1: Client ID provided in URL --- 
    
    // Fetch Client Details (including status) from DB
    if (isset($db)) {
        $client_id_safe = mysqli_real_escape_string($db, $selected_client_id);
        // TODO: Ensure your client table has 'id', 'full_name', and 'status_client' columns
        $client_query = "SELECT id, full_name, status_client FROM client WHERE id = {$client_id_safe}";
        $client_result = mysqli_query($db, $client_query);

        if ($client_result && mysqli_num_rows($client_result) > 0) {
            $selected_client = mysqli_fetch_assoc($client_result);
            $client_status = $selected_client['status_client']; // Get the actual status

            // Permission Check: VIPs always allowed, Regulars only if client_id was provided
            if ($client_status === 'VIP' || $client_status === 'Regular') { 
                 $can_view_page = true; // Allow view for this specific client
                 
                 // --- Fetch Actual Charges from Database ---
                 $charges = []; // Initialize empty charges array
                 $client_id_safe = mysqli_real_escape_string($db, $selected_client_id); // Already have safe ID

                 // 1. Fetch Pool Charges
                 // ERROR: Database does not have an 'op.hours' column as previously assumed.
                 // Calculation below defaults to 1 hour. Consider adding 'hours' to 'order_pools' 
                 // or calculating duration from start/end times for accurate billing.
                 // TODO: Verify column names `p.hourly_rate`, `op.created_at`, `op.Payment_status`

                 // --- Modify query based on client status ---
                 if ($client_status === 'VIP') {
                      // For VIPs, fetch start_time AND calculate elapsed seconds in SQL
                      $pool_query = "SELECT op.id, op.pool_id, op.created_at, op.start_time, op.end_time, op.status, p.hourly_rate, 
                                     IF(op.status = 'ordered', TIMESTAMPDIFF(SECOND, op.start_time, NOW()), TIMESTAMPDIFF(SECOND, op.start_time, op.end_time)) as elapsed_seconds 
                                     FROM order_pools op
                                     JOIN pool p ON op.pool_id = p.id
                                     WHERE op.client_id = {$client_id_safe} 
                                     AND op.Payment_status = 'unpaid'"; // Fetch all unpaid
                 } else {
                      // For Regular, assume fixed duration (e.g., 1 hour or calculate if possible)
                      $pool_query = "SELECT op.id, op.pool_id, op.created_at, op.status, op.end_time, p.hourly_rate 
                                     FROM order_pools op
                                     JOIN pool p ON op.pool_id = p.id
                                     WHERE op.client_id = {$client_id_safe} 
                                     AND op.Payment_status = 'unpaid'"; // Fetch all unpaid, include status/end for potential display logic
                 }
                 // --- End query modification ---
                 
                 $pool_result = mysqli_query($db, $pool_query);
                 if ($pool_result) {
                     while ($row = mysqli_fetch_assoc($pool_result)) {
                         $minutes = 60; // Default
                         $description_detail = "(1 hour - est.)"; // Default description detail
                         
                         if ($client_status === 'VIP' && isset($row['start_time'])) {
                             $start_timestamp = strtotime($row['start_time']); // Keep for reference if needed, but don't use for calculation
                             $elapsed_seconds = $row['elapsed_seconds'] ?? null; 

                             if ($elapsed_seconds !== null && $elapsed_seconds >= 0) {
                                 // Calculate rate per minute
                                 $hourly_rate = (float)$row['hourly_rate'];
                                 $minute_rate = $hourly_rate / 60.0;
                                 
                                 // Calculate elapsed minutes (use floor for "so far" estimate)
                                 $elapsed_minutes_total = floor($elapsed_seconds / 60.0); 
                                 
                                 // Calculate current estimated amount
                                 $amount = $elapsed_minutes_total * $minute_rate;
                                 
                                 // --- DEBUG LOG (GET) ---
                                 error_log("[Billing GET VIP Calc] Client: {$selected_client_id}, OrderPool: {$row['id']}, Start: {$row['start_time']}, StartTS: {$start_timestamp}, ElapsedSec: {$elapsed_seconds}, HourlyRate: {$hourly_rate}, MinuteRate: {$minute_rate}, ElapsedMins: {$elapsed_minutes_total}, Amount: {$amount}");
                                 // --- END DEBUG LOG ---
                                 
                                 // Format elapsed time for description (e.g., 2h 15m ago)
                                 $elapsed_hours_part = floor($elapsed_minutes_total / 60);
                                 $elapsed_minutes_part = $elapsed_minutes_total % 60;
                                 $description_detail = "(Usage: {$elapsed_hours_part}h {$elapsed_minutes_part}m so far)"; 
                             } else {
                                 // Handle case where start_time is invalid for display
                                 $amount = 0; // Set amount to 0
                                 $description_detail = "(Usage time error)";
                                 error_log("VIP Billing Display Error: Invalid start_time '{$row['start_time']}' for order_pool ID {$row['id']}");
                             }
                         } else if ($client_status !== 'VIP'){
                            // For now, stick to the default 1 hour estimate
                            $hours = 1; // Re-define $hours = 1 for Regular client calculation
                            
                            // --- Calculate amount for regular, ensuring it's not NULL ---
                            $hourly_rate_regular = isset($row['hourly_rate']) ? (float)$row['hourly_rate'] : null;
                            if ($hourly_rate_regular !== null && is_numeric($hourly_rate_regular)) {
                                $amount = $hours * $hourly_rate_regular;
                            } else {
                                $amount = 0; // Default to 0 if rate is missing/invalid
                                error_log("Billing Error (Regular Client ID: {$selected_client_id}, Pool ID: {$row['pool_id']}, OrderPool ID: {$row['id']}): Hourly rate is missing or invalid. Billing 0.");
                            }
                            // --- End amount calculation ---
                         }

                         $charges[] = [
                             'date' => date('Y-m-d', strtotime($row['created_at'])), // Format date
                             'type' => 'Pool',
                             'description' => 'Pool ' . htmlspecialchars($row['pool_id']) . ' ' . $description_detail, // Updated description
                             'amount' => $amount
                         ];
                     }
                 } else {
                      error_log("Error fetching pool charges for client {$selected_client_id}: " . mysqli_error($db));
                      // Add user-facing error if needed
                 }

                 // 2. Fetch Bar/Shop Item Charges
                 // Fetch items from order_items table, using the price stored there.
                 // Assuming you added the 'price_at_order' column to order_items.
                 $item_query = "SELECT oi.quantity, oi.created_at, oi.price_at_order, bi.name, bi.category
                                FROM order_items oi 
                                JOIN bar_items bi ON oi.bar_item_id = bi.id
                                WHERE oi.client_id = {$client_id_safe}
                                AND oi.Payment_status = 'unpaid'";
                 $item_result = mysqli_query($db, $item_query);
                 if ($item_result) {
                     while ($row = mysqli_fetch_assoc($item_result)) {
                          $quantity = (int)$row['quantity'];
                          if ($quantity <= 0) $quantity = 1;
                          // Use the price stored AT THE TIME OF ORDER
                          $amount = $quantity * (float)$row['price_at_order']; 
                          // Determine item type based on category from bar_items or just use a generic term
                          $item_type = 'Bar/Shop'; // Generic term
                          // Uncomment below if you want specific types based on category
                          // $category = strtolower(htmlspecialchars($row['category']));
                          // if (in_array($category, ['food', 'drink', 'item'])) {
                          //    $item_type = ucfirst($category);
                          // } else {
                          //    $item_type = 'Bar/Shop'; // Default
                          // }
                          
                          $charges[] = [
                               'date' => date('Y-m-d', strtotime($row['created_at'])),
                               'type' => $item_type, 
                               'description' => $quantity . ' x ' . htmlspecialchars($row['name']),
                               'amount' => $amount
                          ];
                     }
                 } else {
                     error_log("Error fetching item charges for client {$selected_client_id}: " . mysqli_error($db));
                 }

                 // 3. Fetch Penalty Charges
                 // Assumes penalty table has client_id and status columns
                 $penalty_query = "SELECT id, description, price, created_at, status 
                                  FROM penalty 
                                  WHERE client_id = {$client_id_safe} AND status = 'unpaid'"; // Adjust 'unpaid' if status value differs
                 $penalty_result = mysqli_query($db, $penalty_query);
                 if ($penalty_result) {
                     while ($row = mysqli_fetch_assoc($penalty_result)) {
                          $charges[] = [
                              'date' => date('Y-m-d', strtotime($row['created_at'])), 
                              'type' => 'Penalty',
                              'description' => htmlspecialchars($row['description']),
                              'amount' => (float)$row['price']
                          ];
                     }
                 } else {
                     error_log("Error fetching penalties for client {$selected_client_id}: " . mysqli_error($db));
                 }

                 // Optional: Sort charges by date if needed
                 // usort($charges, function($a, $b) {
                 //    return strtotime($a['date']) <=> strtotime($b['date']);
                 // });

                 // --- End Fetch Actual Charges ---

            } else {
                $error_message = "Client status ('{$client_status}') is not eligible for billing here.";
                $selected_client = null; // Clear client data if not eligible
            }
        } else {
             $error_message = "Client with ID {$selected_client_id} not found.";
        }
    } else {
        $error_message = "Database connection error. Cannot fetch client details.";
    }

} else {
    // --- Scenario 2: No Client ID in URL (Direct Access - Only VIPs) ---
    $can_view_page = true; // Allow viewing the page structure, but only show the VIP list
    
    // Fetch List of VIP Clients Only
    if (isset($db)) {
        // Define the query to get eligible clients
        // Condition: VIP clients with an unpaid pool order 
        $eligible_client_query = "
            SELECT c.id, c.full_name 
            FROM client c
            WHERE c.status_client = 'VIP' 
            AND c.id IN (
                SELECT client_id 
                FROM order_pools 
                WHERE Payment_status = 'unpaid' 
            )
            ORDER BY c.full_name ASC
        ";

        $vip_result = mysqli_query($db, $eligible_client_query);
        if ($vip_result) {
            while ($row = mysqli_fetch_assoc($vip_result)) {
                $vip_clients_list[] = $row;
            }
        } else {
             error_log("Error fetching VIP client list: " . mysqli_error($db));
             $error_message = "Could not retrieve VIP client list.";
             // $can_view_page = false; // Optionally disable page view entirely on DB error
        }
    } else {
         $error_message = "Database connection error. Cannot fetch VIP client list.";
         // $can_view_page = false; // Optionally disable page view entirely on DB error
    }
    // Note: $selected_client remains null here, so billing details won't show
}

// --- Calculations (Only run if a client is selected and eligible) ---
$pool_charges_total = 0;
$bar_shop_charges_total = 0;
$penalty_charges_total = 0;
$subtotal = 0;
$total = 0;

// DEBUGGING: Check the contents of the $charges array
// echo "<pre>Debug Charges Array:\n";
// print_r($charges);
// echo "</pre>";
// END DEBUGGING

if ($selected_client && !empty($charges)) {
    foreach ($charges as $charge) {
        $type = strtolower($charge['type']); // Case-insensitive check
        if ($type == 'pool') {
            $pool_charges_total += $charge['amount'];
        } elseif (in_array($type, ['bar', 'shop', 'food', 'drink', 'item', 'bar/shop'])) { // Added 'bar/shop' here just in case
            $bar_shop_charges_total += $charge['amount'];
        } elseif ($type == 'penalty') {
            $penalty_charges_total += $charge['amount'];
        }
    }
    $subtotal = $pool_charges_total + $bar_shop_charges_total + $penalty_charges_total;
    // Removed Tax Calculation
    $total = $subtotal;
}
// --- End Calculations ---

// --- Filter Logic (Only run if a client is selected and eligible) ---
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // Default to 'all'
$filtered_charges = [];
if ($selected_client && !empty($charges)) {
    if ($filter === 'all') {
        $filtered_charges = $charges;
    } else {
        foreach ($charges as $charge) {
            $charge_type_lower = strtolower($charge['type']);
            $filter_lower = strtolower($filter);
            if ($filter_lower === 'bar') {
                // Check for the actual type assigned during fetch ('Bar/Shop')
                if ($charge_type_lower === 'bar/shop') { 
                     $filtered_charges[] = $charge;
                }
            } elseif (stripos($charge_type_lower, $filter_lower) !== false) {
                 // This handles 'pool' and 'penalty' filters
                 $filtered_charges[] = $charge;
            }
        }
    }
}
// --- End Filter Logic ---

?>

<div class="layout-page">
    <div class="container-xxl flex-grow-1 container-p-y">

        <h4 class="py-3 mb-4"><span class="text-muted fw-light">Aqua Oasis /</span> Billing & Payments</h4>
        
        <?php 
        // Display any top-level errors
        if ($error_message) {
             echo "<div class='alert alert-danger'>{$error_message}</div>";
        }
        ?>

        <?php // --- Display Logic based on Permissions --- ?>
        <?php if ($can_view_page): ?>

            <?php if (!$selected_client_id): ?>
                <?php // --- Show VIP Client Selection Dropdown (Direct Access) --- ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Select VIP Client</h5>
                        <small class="text-muted">Choose a VIP client to view their bill.</small>
                    </div>
                    <div class="card-body">
                         <form method="GET" action="billing.php">
                             <div class="mb-3">
                                <label for="selectVipClient" class="form-label">Select VIP Client</label>
                                <select class="form-select" id="selectVipClient" name="client_id" onchange="this.form.submit()" required <?php echo empty($vip_clients_list) ? 'disabled' : ''; ?>>
                                    <option value="" disabled selected>-- Choose a Client --</option>
                                    <?php foreach ($vip_clients_list as $vip_client): ?>
                                        <option value="<?php echo htmlspecialchars($vip_client['id']); ?>">
                                            <?php echo htmlspecialchars($vip_client['full_name']); // Use full_name from query ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($vip_clients_list) && !$error_message): // Show only if no clients and no other error ?>
                                    <div class="form-text text-danger">Could not load VIP client list.</div>
                                <?php endif; ?>
                            </div>
                             <?php // Optionally add a submit button if JS is disabled, though onchange covers most cases ?>
                             <!-- <button type="submit" class="btn btn-primary">View Bill</button> -->
                         </form>
                    </div>
                </div>
                <?php // --- End VIP Client Selection Dropdown --- ?>
            
            <?php elseif ($selected_client): ?>
                <?php // --- Show Billing Details for Selected Client (VIP or Regular via specific link) --- ?>
                <p>Processing payment for <?php echo htmlspecialchars($client_status); ?> client: <strong><?php echo htmlspecialchars($selected_client['full_name']); ?></strong></p>
        
                <div class="row mb-4 g-4">
                    <!-- Client Details Display -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Client Details</h5>
                                <small class="text-muted">Client selected for billing</small>
                            </div>
                            <div class="card-body">
                                <div class="p-3 border rounded bg-light">
                                    <h5><?php echo htmlspecialchars($selected_client['full_name']); ?></h5>
                                    <span class="badge bg-label-primary mb-2"><?php echo htmlspecialchars($client_status); ?> Client</span>
                                    <?php $actual_charge_count = count($charges); ?>
                                    <p class="mb-0">Total unpaid charges: <?php echo $actual_charge_count; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
        
                    <!-- Payment Summary -->
                    <div class="col-md-6">
                        <div class="card">
                             <div class="card-header">
                                <h5 class="card-title mb-0">Payment Summary</h5>
                                <small class="text-muted">Review and process payment</small>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($charges)): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Pool Charges:</span>
                                        <span>$<?php echo number_format($pool_charges_total, 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Bar & Shop Charges:</span>
                                        <span>$<?php echo number_format($bar_shop_charges_total, 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3 border-bottom pb-3">
                                        <span>Penalty Charges:</span>
                                        <span>$<?php echo number_format($penalty_charges_total, 2); ?></span>
                                    </div>
                                     <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span>$<?php echo number_format($subtotal, 2); ?></span>
                                    </div>
                                    <?php /* Removed Tax Display Block
                                    <div class="d-flex justify-content-between mb-3 border-bottom pb-3">
                                        <span>Tax (<?php echo ($tax_rate * 100); ?>%):</span>
                                        <span>$<?php echo number_format($tax_amount, 2); ?></span>
                                    </div> 
                                    */ ?>
                                    <div class="d-flex justify-content-between fw-bold mb-4">
                                        <span>Total:</span>
                                        <span>$<?php echo number_format($total, 2); ?></span>
                                    </div>
                                    <?php // Wrap button in a form ?>
                                    <form action="billing.php?client_id=<?php echo $selected_client_id; ?>" method="POST" class="process-payment-form">
                                        <input type="hidden" name="process_payment" value="1">
                                        <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($selected_client_id); ?>">
                                        <button type="submit" class="btn btn-primary w-100" <?php echo empty($charges) ? 'disabled' : ''; ?>>
                                            <i class="bx bx-credit-card me-1"></i> Process Payment
                                        </button>
                                    </form>
                                <?php else: ?>
                                     <p class="text-muted">No outstanding charges found for this client.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
        
                <!-- Charge Details -->
                <div class="card">
                     <div class="card-header">
                        <h5 class="card-title mb-0">Charge Details</h5>
                         <small class="text-muted">Detailed breakdown of all charges</small>
                     </div>
                    <div class="card-body">
                            <!-- Tabs -->
                            <ul class="nav nav-pills mb-3" role="tablist">
                                <?php
                                // Construct base URL preserving client_id and status
                                $base_url = "billing.php?client_id=" . urlencode($selected_client_id);
                                // Use the status fetched from DB
                                if ($client_status) { 
                                    $base_url .= "&client_status=" . urlencode($client_status); 
                                }
    
                                $all_active = ($filter === 'all') ? 'active' : '';
                                $pool_active = ($filter === 'pool') ? 'active' : '';
                                $bar_shop_active = ($filter === 'bar') ? 'active' : ''; 
                                $penalties_active = ($filter === 'penalty') ? 'active' : '';
                                ?>
                                <li class="nav-item">
                                     <a class="nav-link <?php echo $all_active; ?>" href="<?php echo $base_url; ?>&filter=all" role="tab">All Charges</a>
                                </li>
                                <li class="nav-item">
                                     <a class="nav-link <?php echo $pool_active; ?>" href="<?php echo $base_url; ?>&filter=pool" role="tab"><i class="bx bx-water me-1"></i> Pool</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $bar_shop_active; ?>" href="<?php echo $base_url; ?>&filter=bar" role="tab"><i class="bx bx-food-menu me-1"></i> Bar & Shop</a>
                                </li>
                                 <li class="nav-item">
                                     <a class="nav-link <?php echo $penalties_active; ?>" href="<?php echo $base_url; ?>&filter=penalty" role="tab"><i class="bx bx-error-circle me-1"></i> Penalties</a>
                                </li>
                            </ul>
        
                            <!-- Tab Content -->
                            <div class="tab-content p-0">
                                <div class="tab-pane fade show active" role="tabpanel">
                                    <div class="table-responsive text-nowrap">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Description</th>
                                                    <th class="text-end">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-border-bottom-0">
                                                <?php if (!empty($filtered_charges)): ?>
                                                    <?php foreach ($filtered_charges as $charge): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($charge['date']); ?></td>
                                                            <td>
                                                                <?php
                                                                    $type = htmlspecialchars($charge['type']);
                                                                    $badge_class = 'bg-label-secondary'; // Default
                                                                    if ($type == 'Pool') $badge_class = 'bg-label-info';
                                                                    elseif ($type == 'Bar' || $type == 'Shop' || $type == 'Food' || $type == 'Drink' || $type == 'Item' || $type == 'Bar/Shop') $badge_class = 'bg-label-warning';
                                                                    elseif ($type == 'Penalty') $badge_class = 'bg-label-danger';
                                                                ?>
                                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $type; ?></span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($charge['description']); ?></td>
                                                            <td class="text-end">$<?php echo number_format($charge['amount'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                 <?php else: ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">
                                                            <?php if (empty($charges)): ?>
                                                                No charges found for this client.
                                                            <?php else: ?>
                                                                No charges found for the selected filter '<?php echo htmlspecialchars($filter); ?>'.
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                    </div>
                </div>
                <!-- End Charge Details -->
            <?php endif; // End check for selected_client ?>
        <?php endif; // End check for can_view_page ?>

    </div>
    <!-- / Content -->

    <?php include("footer.php"); ?>
</div> 