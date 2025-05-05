<?php
include('header.php');

// --- Get Booking ID and Fetch Data ---
$booking_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$booking = null;
$error_message = null;

if (!$booking_id) {
    $error_message = "Invalid Booking ID specified.";
} else {
    if (isset($db)) {
        $booking_id_safe = mysqli_real_escape_string($db, $booking_id);
        
        $query = "SELECT b.*, c.full_name as client_name, c.phone_number as client_phone 
                  FROM booking b 
                  JOIN client c ON b.client_id = c.id 
                  WHERE b.id = {$booking_id_safe}";
        
        $result = mysqli_query($db, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $booking = mysqli_fetch_assoc($result);
        } else {
            if (!$result) { // Query error
                 $error_message = "Error fetching booking details: " . mysqli_error($db);
                 error_log($error_message);
            } else { // No booking found
                 $error_message = "Booking with ID {$booking_id} not found.";
            }
        }
    } else {
        $error_message = "Database connection not available.";
    }
}
// --- End Fetch Data ---

?>

<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">

    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Bookings /</span> View Details</h4>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php elseif ($booking): 
        $status = strtolower($booking['status'] ?? 'pending');
        $badge_class = 'warning'; // Default
        if ($status == 'confirmed') $badge_class = 'success';
        elseif ($status == 'cancelled') $badge_class = 'danger';
        elseif ($status == 'completed') $badge_class = 'secondary';
    ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Booking #<?php echo htmlspecialchars($booking['id']); ?></h5>
                <span class="badge bg-label-<?php echo $badge_class; ?>"><?php echo ucfirst(htmlspecialchars($status)); ?></span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="mb-1">Client Details</h6>
                        <p class="mb-0"><strong>Name:</strong> <?php echo htmlspecialchars($booking['client_name']); ?></p>
                        <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($booking['client_phone'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-1">Booking Time</h6>
                        <p class="mb-0"><strong>Date:</strong> <?php echo htmlspecialchars(date('d-m-Y', strtotime($booking['booking_date'] ?? ''))); ?></p>
                        <p class="mb-0"><strong>Time:</strong> <?php echo htmlspecialchars(date('g:i A', strtotime($booking['booking_time'] ?? ''))); ?></p>
                    </div>
                </div>
                <div class="row">
                     <div class="col-md-6">
                        <h6 class="mb-1">Booking Info</h6>
                        <p class="mb-0"><strong>Guests:</strong> <?php echo htmlspecialchars($booking['num_guests'] ?? 'N/A'); ?></p>
                        <p class="mb-0"><strong>Pool Assigned:</strong> Pool <?php echo htmlspecialchars($booking['pool_id'] ?? 'N/A'); ?></p> 
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-1">Record Info</h6>
                        <p class="mb-0"><strong>Created:</strong> <?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($booking['created_at'] ?? ''))); ?></p>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="bookings.php" class="btn btn-secondary me-2">Back to List</a>
                <a href="edit_booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary me-2">Edit Booking</a>
                <?php if ($status != 'cancelled' && $status != 'completed'): ?>
                    <a href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=cancelled" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this booking?');">Cancel Booking</a>
                <?php endif; ?>
                 <?php if ($status == 'pending'): ?>
                    <a href="update_booking_status.php?id=<?php echo $booking['id']; ?>&status=confirmed" class="btn btn-success ms-2">Confirm Booking</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>
</div>

<?php include('footer.php'); ?> 