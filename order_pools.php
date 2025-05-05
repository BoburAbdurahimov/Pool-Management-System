<?php 
include("header.php"); 

// --- Get Client ID from URL ---
$client_id = null; // Initialize
if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
    $client_id = (int)$_GET['client_id'];
    $client_name = 'Client ' . $client_id; // Placeholder
    // TODO: Query DB for actual client name if needed
    // include_once("db.php");
    // if(isset($db)) { ... query client table ... }
} else {
    echo "<div class='alert alert-danger container-xxl'>Error: Client ID is missing or invalid. Cannot select pools.</div>";
    // Optionally include footer and exit
    // include('footer.php');
    // exit();
}
// --- End Get Client ID ---

// Variable to store the ID of the pool the client is changing FROM
$pool_id_changing_from = null; 

// --- Handle Finishing Previous Pool Order if Changing Pool ---
$change_message = '';
if (isset($_GET['change_order_pool_id']) && is_numeric($_GET['change_order_pool_id']) && $client_id !== null && isset($db)) {
    $previous_order_pool_id = (int)$_GET['change_order_pool_id'];

    // --- Get Pool ID being changed from ---
    $pool_id_check_sql = "SELECT pool_id FROM order_pools WHERE id = ? AND client_id = ?";
    $stmt_pool_check = mysqli_prepare($db, $pool_id_check_sql);
    if ($stmt_pool_check) {
        mysqli_stmt_bind_param($stmt_pool_check, "ii", $previous_order_pool_id, $client_id);
        if (mysqli_stmt_execute($stmt_pool_check)) {
            $result_pool_check = mysqli_stmt_get_result($stmt_pool_check);
            if ($row_pool_check = mysqli_fetch_assoc($result_pool_check)) {
                $pool_id_changing_from = $row_pool_check['pool_id']; // Store the ID
                 error_log("[Change Pool] Client {$client_id} is changing from Pool ID: {$pool_id_changing_from}"); // Log for confirmation
            } else {
                error_log("[Change Pool] Could not find pool_id for order ID {$previous_order_pool_id}, client {$client_id}.");
            }
        } else {
            error_log("[Change Pool] Error executing pool ID check query: " . mysqli_stmt_error($stmt_pool_check));
        }
        mysqli_stmt_close($stmt_pool_check);
    } else {
        error_log("[Change Pool] Error preparing pool ID check query: " . mysqli_error($db));
    }
    // --- End Get Pool ID ---
    
    // --- DEBUGGING START --- (Keep this part if you still need it)
    error_log("[Change Pool Debug] Attempting to finish order. Client ID: {$client_id}, Previous Order Pool ID: {$previous_order_pool_id}");

    // Prepare the update query to mark the previous order as completed
    $update_stmt = mysqli_prepare($db, "UPDATE order_pools SET status = 'completed', end_time = NOW() WHERE id = ? AND client_id = ? AND status != 'completed'");
    if ($update_stmt) {
        mysqli_stmt_bind_param($update_stmt, "ii", $previous_order_pool_id, $client_id);
        if (mysqli_stmt_execute($update_stmt)) {
            if (mysqli_stmt_affected_rows($update_stmt) > 0) {
                $change_message = "<div class='alert alert-info container-xxl'>Previous pool order (ID: {$previous_order_pool_id}) marked as completed. You can now select a new pool.</div>";
                // Optional: Clear the session selection for this client if you store it, so they don't see the old one selected.
                // unset($_SESSION['client_pool_selections'][$client_id]); 
            } else {
                // Order might already be completed or doesn't belong to this client
                $change_message = "<div class='alert alert-warning container-xxl'>Could not update previous pool order (ID: {$previous_order_pool_id}). It might already be completed or invalid for this client.</div>";
            }
        } else {
            error_log("Error executing update for finishing previous pool order: " . mysqli_stmt_error($update_stmt));
            $change_message = "<div class='alert alert-danger container-xxl'>Error processing the change request. Please try again.</div>";
        }
        mysqli_stmt_close($update_stmt);
    } else {
        error_log("Error preparing update statement for finishing previous pool order: " . mysqli_error($db));
        $change_message = "<div class='alert alert-danger container-xxl'>Database error preparing change request. Please contact support.</div>";
    }
}
// --- End Handle Finishing Previous Pool Order ---

// --- Fetch Currently ordered Pools (for availability check) ---
$ordered_pool_ids = [];
if ($client_id !== null && isset($db)) { // Check DB connection exists (likely from header.php)
    
    // Fetch IDs of pools that have an active order or a scheduled order whose end_time has NOT yet passed.
    // TODO: Adjust the status checks ('ordered', 'active') if your active statuses are different.
    $ordered_query = "SELECT DISTINCT pool_id 
                     FROM order_pools 
                     WHERE (status = 'ordered' OR status = 'active') 
                     AND NOW() < end_time"; // Only count as ordered if current time is BEFORE end_time
    
    $ordered_result = mysqli_query($db, $ordered_query);
    if ($ordered_result) {
        while ($row = mysqli_fetch_assoc($ordered_result)) {
            $ordered_pool_ids[] = $row['pool_id'];
        }
    } else {
        error_log("Error fetching ordered pools: " . mysqli_error($db));
        // Decide how to handle this - maybe show all pools? or show an error?
    }
}
// --- End Fetch ordered Pools ---

?>
<!-- Content -->
<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4">Select Pool for <?php echo $client_name ?? 'Client'; ?></h4>

    <?php 
    // Display the message about finishing the previous order, if any
    if (!empty($change_message)) {
        echo $change_message;
    }
    ?>

    <div class="content-wrapper">
        <div class="row">
            <?php
            if ($client_id !== null) {
                $found_available = false;
                // Assuming $array_pool is still fetched somehow (e.g., from header.php or another include)
                if (isset($array_pool) && is_array($array_pool)) {
                    foreach ($array_pool as $pool) {
                        $pool_id = $pool['id'] ?? null;
                        $pool_status = $pool['status_pool'] ?? 'ordered'; // Get status_pool from the array
                        
                        // --- Availability Check --- 
                        // Condition 1: Pool itself must be marked as 'available' in the pool table
                        $is_initially_available = ($pool_status === 'available');
                        
                        // Condition 2: Pool must not have an active or scheduled order right now (check against fetched ordered IDs)
                        $is_not_currently_ordered = $pool_id !== null && !in_array($pool_id, $ordered_pool_ids);
                        
                        // Final check: Must be both initially available in pool table AND not currently ordered in order_pools
                        $is_available = $is_initially_available && $is_not_currently_ordered;
                        
                        // --- ADDED: Exclude the pool being changed FROM --- 
                        if ($pool_id_changing_from !== null && $pool_id == $pool_id_changing_from) {
                            $is_available = false; // Mark as not available for selection now
                             error_log("[Pool Display] Excluding Pool ID {$pool_id} because client is changing from it."); // Log exclusion
                        }
                        // --- END ADDED --- 
                        
                        if ($is_available) {
                            $found_available = true;
                            $pool_rate = htmlspecialchars($pool['hourly_rate'] ?? 'N/A');
                            $pool_num_display = htmlspecialchars($pool['id'] ?? 'Unknown'); 
                ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title">Pool Number <?php echo $pool_num_display; ?> (Available)</h5>
                                        <img class="img-fluid d-flex mx-auto my-4 rounded" style="width: 100%; max-width: 300px; height: 200px; object-fit: cover;" src="assets/img/elements/pool.jpg" alt="Pool image" />
                                        <h6 class="card-text fw-bold mt-auto">Price: $<?php echo $pool_rate; ?> / hour</h6>
                                        
                                        <div class="select-controls-container mt-3" id="select-controls-<?php echo $pool_id; ?>">
                                             <?php 
                                             // Check if this pool is already selected in the *client-specific* session
                                             if ($pool_id && isset($_SESSION['client_pool_selections'][$client_id][$pool_id])): 
                                             ?>
                                                 <button type="button" class="btn btn-secondary w-100" disabled>Selected by You</button>
                                             <?php else: ?>
                                                 <form action="ajax_select_pool.php" method="post" class="select-pool-form">
                                                     <input type="hidden" name="pool_id" value="<?php echo $pool_id; ?>">
                                                     <input type="hidden" name="pool_name" value="Pool <?php echo $pool_id; ?>"> 
                                                     <input type="hidden" name="pool_rate" value="<?php echo $pool_rate; ?>">
                                                     <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                                                     <button type="submit" name="select_pool" class="btn btn-primary w-100">Select Pool for <?php echo $client_name ?? 'Client'; ?></button>
                                                 </form>
                                             <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                <?php
                        } // end if $is_available
                    } // end foreach
                } // end if isset $array_pool

                if (!$found_available) {
                    echo "<div class='col-12'><p class='text-center text-muted mt-4'>No pools are currently available.</p></div>";
                }
            } else {
                 echo "<div class='col-12'><p class='text-center text-danger mt-4'>Cannot display pools without a valid client ID.</p></div>";
            }
            ?>
        </div>
    </div>
</div>
</div>
<!-- / Content -->

<!-- JavaScript remains the same, it correctly sends to ajax_select_pool.php -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.body.addEventListener('submit', function(event) {
        if (event.target.matches('.select-pool-form')) {
            event.preventDefault(); 

            const form = event.target;
            const formData = new FormData(form);
            const poolId = formData.get('pool_id'); 
            const controlsContainer = document.getElementById('select-controls-' + poolId); // Might not need this if just redirecting
            const submitButton = form.querySelector('button[type="submit"]');

            if(submitButton) submitButton.disabled = true;
            if(submitButton) submitButton.textContent = 'Selecting...';

            fetch(form.action, { // Should be "ajax_select_pool.php"
                method: 'POST',
                body: formData 
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errData => {
                        throw new Error(errData.message || `HTTP error! Status: ${response.status}`);
                    }).catch(() => {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    });
                }
                return response.json(); 
            }) 
            .then(data => {
                if (data.success) {
                    console.log('Success:', data.message);
                    // Decide where to redirect after successful pool selection for the client
                    // Maybe to a confirmation page or the main dashboard?
                    // Let's redirect to selected_pools.php for now, as in pool.php
                    window.location.href = 'selected_pools.php?client_id=' + formData.get('client_id') ; // Pass client_id along
                } else {
                    if(submitButton) submitButton.disabled = false;
                    if(submitButton) submitButton.textContent = 'Select Pool for <?php echo $client_name ?? 'Client'; ?>'; // Reset text
                    alert('Error: ' + (data.message || 'Could not select pool.'));
                    console.error('Error reported by server:', data.message);
                }
            })
            .catch(error => {
                if(submitButton) submitButton.disabled = false;
                 if(submitButton) submitButton.textContent = 'Select Pool for <?php echo $client_name ?? 'Client'; ?>'; // Reset text
                console.error('AJAX Form Submission Error:', error);
                alert('An error occurred: ' + error.message);
            });
        }
    });
});
</script>

<?php include_once("footer.php"); ?> 