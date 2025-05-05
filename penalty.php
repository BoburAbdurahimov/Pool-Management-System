<?php
session_start();
include_once("db.php"); // Include DB connection

$message = ''; // Initialize message variable

// --- Handle Form Submission --- 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_penalty'])) {
    if (isset($db)) {
        // Sanitize and validate inputs
        $client_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
        $description = isset($_POST['description']) ? trim(mysqli_real_escape_string($db, $_POST['description'])) : '';
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT); // Allow decimals for price?
        
        if ($client_id && !empty($description) && $price !== false && $price >= 0) {
            // Prepare data for insertion
            $status = 'unpaid'; // Default status for a new penalty
            $created_at = date('Y-m-d H:i:s');

            // TODO: Verify column names match your 'penalty' table schema
            $insert_query = "INSERT INTO penalty (client_id, description, price, status, created_at) 
                             VALUES (?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($db, $insert_query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'isdss', $client_id, $description, $price, $status, $created_at);
                
                if (mysqli_stmt_execute($stmt)) {
                    $penalty_id = mysqli_insert_id($db); // Get the ID of the penalty just inserted
                    mysqli_stmt_close($stmt); // Close statement before next query

                    // --- Check client status for redirection ---
                    $client_status_query = "SELECT status_client FROM client WHERE id = ? LIMIT 1";
                    $stmt_status = mysqli_prepare($db, $client_status_query);
                    if ($stmt_status) {
                        mysqli_stmt_bind_param($stmt_status, "i", $client_id);
                        mysqli_stmt_execute($stmt_status);
                        $result_status = mysqli_stmt_get_result($stmt_status);
                        if ($row_status = mysqli_fetch_assoc($result_status)) {
                            if (strtolower($row_status['status_client']) === 'regular') {
                                // Redirect Regular client to billing
                                header('Location: billing.php?client_id=' . $client_id . '&penalty_added=' . $penalty_id);
                                exit(); // IMPORTANT: Exit after redirect header
                            } else {
                                // For VIP or others, set success message (will be displayed AFTER header include)
                                $message = '<div class="alert alert-success">Penalty (ID: ' . $penalty_id . ') added successfully for client ID ' . $client_id . '.</div>';
                            }
                        } else {
                             // Client status not found, set message
                             $message = '<div class="alert alert-success">Penalty (ID: ' . $penalty_id . ') added successfully for client ID ' . $client_id . ' (Could not determine status for redirect).</div>';
                             error_log("Could not fetch status for client {$client_id} after adding penalty.");
                        }
                         mysqli_stmt_close($stmt_status);
                    } else {
                         // Error preparing status query, set message
                         $message = '<div class="alert alert-success">Penalty (ID: ' . $penalty_id . ') added successfully for client ID ' . $client_id . ' (DB error checking status).</div>';
                         error_log("Error preparing client status query in penalty.php: " . mysqli_error($db));
                    }
                    // --- End client status check ---
                    
                } else {
                     $message = '<div class="alert alert-danger">Error adding penalty: ' . mysqli_stmt_error($stmt) . '</div>';
                     mysqli_stmt_close($stmt); // Close statement on error too
                }
            } else {
                 $message = '<div class="alert alert-danger">Error preparing statement: ' . mysqli_error($db) . '</div>';
            }

        } else {
            // Validation errors
            $errors = [];
            if (!$client_id) $errors[] = 'Please select a client.';
            if (empty($description)) $errors[] = 'Description cannot be empty.';
            if ($price === false || $price < 0) $errors[] = 'Please enter a valid non-negative price.';
            $message = '<div class="alert alert-warning">Please fix the following errors: <ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Database connection error. Cannot save penalty.</div>';
    }
}
// --- End Handle Form Submission ---

include("header.php"); // Include header AFTER processing potentially redirecting logic

$clients = []; // To store client list for dropdown

// --- Fetch Clients for Dropdown (Filtered) ---
if (isset($db)) {
    // Define the query to get eligible clients
    // Condition 1: Regular clients currently active in a pool
    // Condition 2: VIP clients with any unpaid item/pool (NOTE: Cannot check penalties due to missing client_id in penalty table)
    $eligible_client_query = "
        SELECT c.id, c.full_name 
        FROM client c
        WHERE 
        -- Condition 1: Active Regular Clients
        (
            c.status_client = 'Regular' AND c.id IN (
                SELECT op.client_id 
                FROM order_pools op 
                WHERE op.status = 'ordered' -- Assumes 'ordered' means active until paid/closed
                AND NOW() BETWEEN op.start_time AND op.end_time -- Check if currently within ordered time
            )
        )
        OR
        -- Condition 2: VIP Clients with Unpaid Pool/Item Bills
        (
            c.status_client = 'VIP' AND c.id IN (
                SELECT client_id FROM order_pools WHERE Payment_status = 'unpaid'
                UNION DISTINCT
                SELECT client_id FROM order_items WHERE Payment_status = 'unpaid'
                -- Cannot check penalty table: UNION DISTINCT SELECT client_id FROM penalty WHERE status = 'unpaid'
            )
        )
        ORDER BY c.full_name ASC
    ";

    // --- Debugging: Show the query ---
    // echo "<pre>Client Query:\n" . htmlspecialchars($eligible_client_query) . "</pre>";
    // --- End Debugging ---

    $client_result = mysqli_query($db, $eligible_client_query);

    // --- Debugging: Check Query Success ---
    if ($client_result === false) {
        $message = '<div class="alert alert-danger">SQL Error fetching eligible client list: ' . mysqli_error($db) . '</div>';
        error_log("SQL Error in penalty.php client query: " . mysqli_error($db));
    // --- End Debugging ---
    } else {
        while ($row = mysqli_fetch_assoc($client_result)) {
            $clients[] = $row;
        }
        // --- Debugging: Check if any clients were found ---
        if (empty($clients)) {
             if (empty($message)) { // Avoid overwriting SQL error message
                 $message = '<div class="alert alert-info">No clients currently meet the criteria for adding penalties (Active Regular or VIP with unpaid bills).</div>';
             }
        }
        // --- End Debugging ---
    }
} else {
    $message = '<div class="alert alert-danger">Database connection error. Cannot load clients.</div>';
    // Optionally disable the form
}
// --- End Fetch Clients ---

?>

<div class="layout-page">
    <div class="container-xxl flex-grow-1 container-p-y">

        <h4 class="py-3 mb-4"><span class="text-muted fw-light">Billing /</span> Add Penalty Charge</h4>

        <?php echo $message; // Display success or error messages ?>

        <div class="row">
            <div class="col-xl">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Penalty Details</h5>
                        <small class="text-muted float-end">Select client and enter penalty info</small>
                    </div>
                    <div class="card-body">
                        <form action="penalty.php" method="POST">
                            <input type="hidden" name="add_penalty" value="1"> <?php // Hidden field to trigger processing ?>

                            <div class="mb-3">
                                <label class="form-label" for="selectClient">Select Client</label>
                                <select class="form-select" id="selectClient" name="client_id" required <?php echo empty($clients) ? 'disabled' : ''; ?>>
                                    <option value="" disabled selected>-- Choose a Client --</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo htmlspecialchars($client['id']); ?>">
                                            <?php echo htmlspecialchars($client['full_name']); ?> (ID: <?php echo htmlspecialchars($client['id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($clients) && !$message): // Show only if no clients and no other error ?>
                                    <div class="form-text text-danger">Could not load client list.</div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="penalty-description">Description</label>
                                <textarea class="form-control" id="penalty-description" name="description" rows="3" placeholder="e.g., Late checkout fee, Damaged towel" required></textarea>
                            </div>

                             <div class="mb-3">
                                <label class="form-label" for="penalty-price">Price ($)</label>
                                <input type="number" class="form-control" id="penalty-price" name="price" placeholder="15.00" step="0.01" min="0" required />
                            </div>
                           
                            <button type="submit" class="btn btn-danger" <?php echo empty($clients) ? 'disabled' : ''; ?>>
                                <i class="bx bx-error-circle me-1"></i> Add Penalty
                            </button>
                            <a href="billing.php" class="btn btn-secondary">Cancel</a> <?php // Link back to generic billing or dashboard ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- / Content -->

    <?php include("footer.php"); ?>
</div> 