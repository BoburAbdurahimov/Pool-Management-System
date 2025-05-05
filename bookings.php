<?php 
include('header.php'); 

// --- Fetch Bookings ---
$bookings = [];
$error_message = null;

if (isset($db)) {
    // Base query - adjust joins and selected fields based on your actual booking table schema
    $booking_query = "
        SELECT
            b.id,
            b.client_id,
            c.full_name as client_name,
            b.booking_date,
            b.booking_time,
            b.pool_id,       -- Include if you have pool_id in booking
            b.num_guests,    -- Include if you have num_guests
            b.status,
            b.created_at
        FROM booking b
        JOIN client c ON b.client_id = c.id
        -- Optional: LEFT JOIN pool p ON b.pool_id = p.id -- Uncomment and adjust if you need pool info
        ORDER BY b.booking_date DESC, b.booking_time DESC"; // Example ordering

    $booking_result = mysqli_query($db, $booking_query);

    if ($booking_result) {
        while ($row = mysqli_fetch_assoc($booking_result)) {
            $bookings[] = $row;
        }
    } else {
        $error_message = "Error fetching bookings: " . mysqli_error($db);
        error_log($error_message); // Log the detailed error
    }
} else {
    $error_message = "Database connection not available.";
    error_log($error_message);
}
// --- End Fetch Bookings ---

// --- Auto-update status for past bookings ---
if (isset($db) && !empty($bookings)) {
    $current_timestamp = time();
    $booking_duration = '02:00:00'; // Assuming 2-hour duration, consistent with create/edit
    $update_stmt = null; // Prepare statement outside the loop

    foreach ($bookings as $key => $booking) {
        // Only check bookings that are currently pending or confirmed
        $current_status = strtolower($booking['status'] ?? 'pending');
        if ($current_status === 'pending' || $current_status === 'confirmed') {
            
            // Calculate booking end time
            $booking_start_str = $booking['booking_date'] . ' ' . $booking['booking_time'];
            $booking_start_timestamp = strtotime($booking_start_str);
            
            if ($booking_start_timestamp !== false) {
                // Calculate end timestamp by adding duration
                // Need to be careful with adding time intervals in PHP
                $booking_end_timestamp = strtotime("+" . str_replace(':', ' hours ', substr($booking_duration, 0, 2)) . " minutes", $booking_start_timestamp);
                // A more robust way using DateTime objects:
                 try {
                     $start_dt = new DateTime($booking_start_str);
                     list($h, $m, $s) = explode(':', $booking_duration);
                     $start_dt->add(new DateInterval(sprintf('PT%dH%dM%dS', $h, $m, $s)));
                     $booking_end_timestamp = $start_dt->getTimestamp();
                 } catch (Exception $e) {
                     error_log("Error calculating end time for booking ID {$booking['id']}: " . $e->getMessage());
                     continue; // Skip this booking if date calculation fails
                 }

                // Check if the booking end time has passed
                if ($current_timestamp > $booking_end_timestamp) {
                    $new_status = 'completed'; // Set to completed
                    
                    // Prepare update statement once
                    if ($update_stmt === null) {
                        $update_sql = "UPDATE booking SET status = ?, updated_at = NOW() WHERE id = ?";
                        $update_stmt = mysqli_prepare($db, $update_sql);
                    }

                    // Execute update if statement prepared successfully
                    if ($update_stmt) {
                        mysqli_stmt_bind_param($update_stmt, "si", $new_status, $booking['id']);
                        if(mysqli_stmt_execute($update_stmt)) {
                            // Update the status in the array for immediate display
                            $bookings[$key]['status'] = $new_status; 
                            $_SESSION['message'] = ['type' => 'info', 'text' => 'Automatically marked past booking #'.$booking['id'].' as completed.']; // Optional feedback
                        } else {
                            error_log("Error auto-updating booking status for ID {$booking['id']}: " . mysqli_stmt_error($update_stmt));
                        }
                    } else {
                         error_log("Error preparing auto-update statement: " . mysqli_error($db));
                         // Break loop if prepare fails to avoid repeated errors?
                         break; 
                    }
                }
            }
        }
    }
    // Close the prepared statement after the loop
    if ($update_stmt !== null) {
        mysqli_stmt_close($update_stmt);
    }
}
// --- End Auto-update status ---

?>

<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="py-3 mb-0"><span class="text-muted fw-light">Management /</span> Bookings</h4>
      <a href="create_booking.php" class="btn btn-primary">Add New Booking</a>
    </div>

    <?php 
    // Display error message if query failed
    if ($error_message && empty($bookings)) {
        echo "<div class='alert alert-danger'>{$error_message} Please check the booking table structure and database connection.</div>";
    }
    ?>

    <div class="card">
        <h5 class="card-header">Bookings List</h5>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client Name</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Guests</th> <!-- Adjust header if needed -->
                        <th>Pool</th>   <!-- Adjust header if needed -->
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    <?php if (!empty($bookings)): ?>
                        <?php $row_num = 1; foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo $row_num++; ?></td>
                                <td><?php echo htmlspecialchars($booking['client_name']); ?></td>
                                <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($booking['booking_date'] ?? ''))); ?></td>
                                <td><?php echo htmlspecialchars(date('H:i', strtotime($booking['booking_time'] ?? ''))); ?></td>
                                <td><?php echo htmlspecialchars($booking['num_guests'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($booking['pool_id'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $status = strtolower($booking['status'] ?? 'pending');
                                    $badge_class = 'warning'; // Default
                                    if ($status == 'confirmed') $badge_class = 'success';
                                    elseif ($status == 'cancelled') $badge_class = 'danger';
                                    elseif ($status == 'completed') $badge_class = 'secondary';
                                    ?>
                                    <span class="badge bg-label-<?php echo $badge_class; ?>"><?php echo ucfirst(htmlspecialchars($status)); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($booking['created_at'] ?? ''))); ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                            <i class="bx bx-dots-vertical-rounded"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <!-- Add links to actual view/edit/cancel pages later -->
                                            <a class="dropdown-item" href="view_booking.php?id=<?php echo $booking['id']; ?>"><i class="bx bx-show me-1"></i> View</a>
                                            <a class="dropdown-item" href="edit_booking.php?id=<?php echo $booking['id']; ?>"><i class="bx bx-edit-alt me-1"></i> Edit</a>
                                            <?php if ($status != 'cancelled' && $status != 'completed'): ?>
                                            <a class="dropdown-item" href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=cancelled" onclick="return confirm('Are you sure you want to cancel this booking?');"><i class="bx bx-trash me-1"></i> Cancel</a>
                                            <?php endif; ?>
                                             <?php if ($status == 'pending'): // Example: Allow confirmation only if pending ?>
                                            <a class="dropdown-item" href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=confirmed"><i class="bx bx-check me-1"></i> Confirm</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center">
                            <?php echo $error_message ? 'Could not load bookings.' : 'No bookings found.'; ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Optional: Add Pagination if needed -->
        
    </div>

</div>
</div>

<?php include('footer.php'); ?> 