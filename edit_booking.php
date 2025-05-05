<?php
include('header.php');

// --- Get Booking ID ---
$booking_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$booking = null;
$error_message = null;
$fetch_error = false;

if (!$booking_id) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid Booking ID for editing.'];
    header('Location: bookings.php');
    exit;
}

// --- Fetch existing booking data, clients, and available pools ---
$clients = [];
$available_pools = [];

if (isset($db)) {
    $booking_id_safe = mysqli_real_escape_string($db, $booking_id);
    
    // Fetch Booking
    $query = "SELECT * FROM booking WHERE id = {$booking_id_safe}";
    $result = mysqli_query($db, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $booking = mysqli_fetch_assoc($result);
    } else {
        $error_message = "Booking not found or error fetching booking: " . mysqli_error($db);
        $fetch_error = true;
    }

    // Fetch Clients
    $client_query = "SELECT id, full_name FROM client ORDER BY full_name ASC";
    $client_result = mysqli_query($db, $client_query);
    if ($client_result) {
        while ($row = mysqli_fetch_assoc($client_result)) {
            $clients[] = $row;
        }
    } else {
        $error_message .= "Error fetching clients: " . mysqli_error($db) . "<br>";
        $fetch_error = true;
    }

    // Fetch Available Pools (Include the currently assigned pool even if not 'available')
    $current_pool_id = $booking['pool_id'] ?? null;
    $current_pool_id_safe = $current_pool_id ? mysqli_real_escape_string($db, $current_pool_id) : 'NULL';
    
    $pool_query = "SELECT id FROM pool WHERE status_pool = 'available' OR id = {$current_pool_id_safe} ORDER BY id ASC";
    $pool_result = mysqli_query($db, $pool_query);
    if ($pool_result) {
        while ($row = mysqli_fetch_assoc($pool_result)) {
            $available_pools[] = $row;
        }
    } else {
        $error_message .= "Error fetching available pools: " . mysqli_error($db) . "<br>";
        $fetch_error = true;
    }

} else {
    $error_message = "Database connection not available.";
    $fetch_error = true;
}

// --- Handle Form Submission (UPDATE) ---
if (!$fetch_error && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking'])) {
    if (isset($db)) {
        // Sanitize and validate input
        $client_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
        $booking_date = filter_input(INPUT_POST, 'booking_date', FILTER_SANITIZE_STRING);
        $booking_time = filter_input(INPUT_POST, 'booking_time', FILTER_SANITIZE_STRING);
        $num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $pool_id = filter_input(INPUT_POST, 'pool_id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING); // Allow status update
        $valid_statuses = ['pending', 'confirmed', 'cancelled', 'completed']; // Define allowed statuses
        
        // Combine date and time for validation
        $booking_datetime_str = $booking_date . ' ' . $booking_time;
        $booking_timestamp = strtotime($booking_datetime_str);

        // Basic Validation
        $update_error_message = ''; // Use a separate variable for update errors
        if (!$client_id) {
            $update_error_message .= "Invalid client selected.<br>";
        }
        if ($booking_timestamp === false) { // Allow past time for editing maybe?
             $update_error_message .= "Invalid booking date/time format specified.<br>";
        }
        if ($num_guests === false || $num_guests <= 0) {
            $update_error_message .= "Number of guests must be a positive integer.<br>";
        }
        if (!$pool_id) {
             $update_error_message .= "Invalid pool selected.<br>";
        }
        if (!in_array($status, $valid_statuses)) {
             $update_error_message .= "Invalid status selected.<br>";
        }
        // TODO: Add check to ensure the selected pool is available if changed?
        
        if (empty($update_error_message)) {
            // --- Assume a fixed booking duration (e.g., 2 hours) for overlap check ---
            // TODO: Consider adding a duration field to the booking table for more accuracy.
            $booking_duration = '02:00:00'; 
            // ----------------------------------------------------------------------

            // --- Check for Overlapping Bookings (excluding the current one) ---
            $overlap_check_sql = "SELECT COUNT(*) as conflict_count FROM booking 
                                  WHERE pool_id = ? 
                                  AND id != ?  -- Exclude the booking being edited
                                  AND booking_date = ?
                                  AND status NOT IN ('cancelled', 'completed')
                                  AND (
                                      -- New time starts during an existing booking
                                      ? >= booking_time AND ? < ADDTIME(booking_time, ?) 
                                      OR
                                      -- New time ends during an existing booking
                                      ADDTIME(?, ?) > booking_time AND ADDTIME(?, ?) <= ADDTIME(booking_time, ?)
                                      OR
                                      -- New time completely envelops an existing booking
                                      ? < booking_time AND ADDTIME(?, ?) > ADDTIME(booking_time, ?)
                                  )";
            $stmt_check = mysqli_prepare($db, $overlap_check_sql);
            if ($stmt_check) {
                 mysqli_stmt_bind_param($stmt_check, "iisssssssssss", 
                    $pool_id, 
                    $booking_id, // Add the booking ID to exclude
                    $booking_date, 
                    $booking_time, $booking_time, $booking_duration, // Condition 1
                    $booking_time, $booking_duration, $booking_time, $booking_duration, $booking_duration, // Condition 2
                    $booking_time, $booking_time, $booking_duration, $booking_duration // Condition 3
                );

                mysqli_stmt_execute($stmt_check);
                $result_check = mysqli_stmt_get_result($stmt_check);
                $overlap_data = mysqli_fetch_assoc($result_check);
                mysqli_stmt_close($stmt_check);

                if ($overlap_data && $overlap_data['conflict_count'] > 0) {
                    $update_error_message = "Booking conflict: The selected pool is already booked for the specified time slot (or overlaps with it). Please choose a different time or pool.";
                }
            } else {
                 $update_error_message = "Database error checking for booking conflicts: " . mysqli_error($db);
            }
            // --- End Overlap Check ---
        }
        
        // Proceed with update only if NO validation errors AND NO overlap conflict
        if (empty($update_error_message)) { 
            // Prepare UPDATE statement - Removed updated_at
            $update_sql = "UPDATE booking SET 
                               client_id = ?, 
                               booking_date = ?, 
                               booking_time = ?, 
                               num_guests = ?, 
                               pool_id = ?, 
                               status = ? 
                           WHERE id = ?";
            $stmt = mysqli_prepare($db, $update_sql);

            if ($stmt) {
                // Adjusted binding - removed NOW() for updated_at
                mysqli_stmt_bind_param($stmt, "issisii", $client_id, $booking_date, $booking_time, $num_guests, $pool_id, $status, $booking_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Booking updated successfully!'];
                    header('Location: view_booking.php?id=' . $booking_id); // Redirect to view page
                    exit;
                } else {
                    $update_error_message = "Error executing booking update: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $update_error_message = "Database error preparing booking update: " . mysqli_error($db);
            }
        } 
        // Assign update errors to the main error message variable for display
        $error_message = $update_error_message; 
    } else {
        $error_message = "Database connection error during form submission.";
    }
     // Log errors if any occurred during POST
    if (!empty($error_message)) {
        error_log("Edit Booking Error (ID: {$booking_id}): " . strip_tags($error_message));
    }
}
// --- End Handle Form Submission ---

?>

<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">

    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Bookings /</span> Edit Booking</h4>

    <?php 
    // Display Fetch errors
    if ($fetch_error && !empty($error_message)):
    ?>
        <div class="alert alert-danger">Error loading booking data: <?php echo $error_message; ?> <a href="bookings.php">Go back to list.</a></div>
    <?php 
    // Display Update errors
    elseif (!$fetch_error && !empty($error_message)):
    ?>
         <div class="alert alert-danger">Error updating booking: <?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (!$fetch_error && $booking): // Only show form if fetch was successful ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <h5 class="card-header">Edit Details for Booking #<?php echo htmlspecialchars($booking['id']); ?></h5>
                <div class="card-body">
                    <form action="edit_booking.php?id=<?php echo $booking_id; ?>" method="POST">
                        <div class="row">
                            <div class="mb-3 col-md-6">
                                <label for="client_id" class="form-label">Client</label>
                                <select id="client_id" name="client_id" class="form-select" required>
                                    <option value="">Select Client</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>" <?php echo ($client['id'] == $booking['client_id']) ? 'selected' : ''; ?> >
                                            <?php echo htmlspecialchars($client['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="num_guests" class="form-label">Number of Guests</label>
                                <input class="form-control" type="number" id="num_guests" name="num_guests" min="1" value="<?php echo htmlspecialchars($booking['num_guests']); ?>" required />
                            </div>
                             <div class="mb-3 col-md-6">
                                <label for="booking_date" class="form-label">Booking Date</label>
                                <input class="form-control" type="date" id="booking_date" name="booking_date" value="<?php echo htmlspecialchars($booking['booking_date']); ?>" required />
                            </div>
                             <div class="mb-3 col-md-6">
                                <label for="booking_time" class="form-label">Booking Time</label>
                                <input class="form-control" type="time" id="booking_time" name="booking_time" value="<?php echo htmlspecialchars($booking['booking_time']); ?>" required />
                            </div>
                             <div class="mb-3 col-md-6">
                                <label for="pool_id" class="form-label">Assign Pool</label>
                                <select id="pool_id" name="pool_id" class="form-select" required>
                                    <option value="">Select Available Pool</option>
                                    <?php if (empty($available_pools)): ?>
                                        <option value="" disabled>No pools currently available</option>
                                    <?php else: ?>
                                        <?php foreach ($available_pools as $pool): ?>
                                            <option value="<?php echo $pool['id']; ?>" <?php echo ($pool['id'] == $booking['pool_id']) ? 'selected' : ''; ?> >
                                                Pool <?php echo htmlspecialchars($pool['id']); ?> <?php echo ($pool['id'] == $current_pool_id && $booking['status_pool'] !== 'available' ) ? '(Current)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                             <div class="mb-3 col-md-6">
                                <label for="status" class="form-label">Booking Status</label>
                                <select id="status" name="status" class="form-select" required>
                                    <?php 
                                    $valid_statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
                                    foreach ($valid_statuses as $stat): ?>
                                        <option value="<?php echo $stat; ?>" <?php echo ($stat == $booking['status']) ? 'selected' : ''; ?> >
                                            <?php echo ucfirst($stat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="submit" name="update_booking" class="btn btn-primary me-2">Update Booking</button>
                            <a href="view_booking.php?id=<?php echo $booking_id; ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; // End form display check ?>

</div>
</div>

<?php include('footer.php'); ?> 