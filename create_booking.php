<?php
session_start(); // Start session early for messages
include_once("db.php"); // Include DB connection early

$error_message = null; // Initialize error message variable

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_booking'])) {
    if (isset($db)) {
        // Sanitize and validate input
        $client_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
        $booking_date = filter_input(INPUT_POST, 'booking_date', FILTER_SANITIZE_STRING); // Basic sanitize, more validation below
        $booking_time = filter_input(INPUT_POST, 'booking_time', FILTER_SANITIZE_STRING);
        $num_guests = filter_input(INPUT_POST, 'num_guests', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $pool_id = filter_input(INPUT_POST, 'pool_id', FILTER_VALIDATE_INT);
        $status = 'pending'; // Default status

        // --- Assume a fixed booking duration (e.g., 2 hours) for overlap check ---
        // TODO: Consider adding a duration field to the booking table for more accuracy.
        $booking_duration = '02:00:00'; 
        // ----------------------------------------------------------------------

        // Combine date and time for validation and potential storage
        $booking_datetime_str = $booking_date . ' ' . $booking_time;
        $booking_timestamp = strtotime($booking_datetime_str);

        // Basic Validation
        if (!$client_id) {
            $error_message .= "Invalid client selected.<br>";
        } 
        if ($booking_timestamp === false || $booking_timestamp < time()) { // Ensure date/time is valid and in the future
             $error_message .= "Invalid or past booking date/time specified.<br>";
        }
        if ($num_guests === false || $num_guests <= 0) {
            $error_message .= "Number of guests must be a positive integer.<br>";
        }
        if (!$pool_id) { // Check if a valid pool was selected
             $error_message .= "Invalid pool selected.<br>";
        }
        // TODO: Add check to ensure the selected pool is still available at the requested time?
        
        if (empty($error_message)) {
            // --- Check for Overlapping Bookings ---
            $overlap_check_sql = "SELECT COUNT(*) as conflict_count FROM booking 
                                  WHERE pool_id = ? 
                                  AND booking_date = ?
                                  AND status NOT IN ('cancelled', 'completed')
                                  AND (
                                      -- New booking starts during an existing booking
                                      ? >= booking_time AND ? < ADDTIME(booking_time, ?) 
                                      OR
                                      -- New booking ends during an existing booking
                                      ADDTIME(?, ?) > booking_time AND ADDTIME(?, ?) <= ADDTIME(booking_time, ?)
                                      OR
                                      -- New booking completely envelops an existing booking
                                      ? < booking_time AND ADDTIME(?, ?) > ADDTIME(booking_time, ?)
                                  )";
            $stmt_check = mysqli_prepare($db, $overlap_check_sql);
            if ($stmt_check) {
                // --- Debugging parameter types and values ---
                error_log("[Overlap Check Debug] Types String Length: " . strlen("issssssssssssss"));
                error_log("[Overlap Check Debug] pool_id Type: " . gettype($pool_id) . ", Value: " . $pool_id);
                error_log("[Overlap Check Debug] booking_date Type: " . gettype($booking_date) . ", Value: " . $booking_date);
                error_log("[Overlap Check Debug] booking_time Type: " . gettype($booking_time) . ", Value: " . $booking_time);
                error_log("[Overlap Check Debug] booking_duration Type: " . gettype($booking_duration) . ", Value: " . $booking_duration);

                // Create distinct variables for binding to avoid potential issues with repeated vars
                $p1_pool_id = (int) $pool_id;
                $p2_booking_date = (string) $booking_date;
                $p3_booking_time1 = (string) $booking_time;
                $p4_booking_time2 = (string) $booking_time;
                $p5_booking_duration1 = (string) $booking_duration;
                $p6_booking_time3 = (string) $booking_time;
                $p7_booking_duration2 = (string) $booking_duration;
                $p8_booking_time4 = (string) $booking_time;
                $p9_booking_duration3 = (string) $booking_duration;
                $p10_booking_duration4 = (string) $booking_duration;
                $p11_booking_time5 = (string) $booking_time;
                $p12_booking_time6 = (string) $booking_time;
                $p13_booking_duration5 = (string) $booking_duration;
                $p14_booking_duration6 = (string) $booking_duration;
                // --- End Debugging ---

                mysqli_stmt_bind_param($stmt_check, "isssssssssssss", // 14 types
                    $p1_pool_id,
                    $p2_booking_date,
                    $p3_booking_time1,
                    $p4_booking_time2,
                    $p5_booking_duration1,
                    $p6_booking_time3,
                    $p7_booking_duration2,
                    $p8_booking_time4,
                    $p9_booking_duration3,
                    $p10_booking_duration4,
                    $p11_booking_time5,
                    $p12_booking_time6,
                    $p13_booking_duration5,
                    $p14_booking_duration6
                );

                mysqli_stmt_execute($stmt_check);
                $result_check = mysqli_stmt_get_result($stmt_check);
                $overlap_data = mysqli_fetch_assoc($result_check);
                mysqli_stmt_close($stmt_check);

                if ($overlap_data && $overlap_data['conflict_count'] > 0) {
                    $error_message = "Booking conflict: The selected pool is already booked for the specified time slot (or overlaps with it). Please choose a different time or pool.";
                }
            } else {
                 $error_message = "Database error checking for booking conflicts: " . mysqli_error($db);
            }
            // --- End Overlap Check ---
        }

        // Proceed only if NO validation errors AND NO overlap conflict
        if (empty($error_message)) { 
            // Prepare INSERT statement - Removed updated_at
            $insert_sql = "INSERT INTO booking (client_id, booking_date, booking_time, num_guests, pool_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = mysqli_prepare($db, $insert_sql);

            if ($stmt) {
                // Adjusted binding - removed one string for updated_at
                mysqli_stmt_bind_param($stmt, "issiis", $client_id, $booking_date, $booking_time, $num_guests, $pool_id, $status);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Booking added successfully!'];
                    // Redirect MUST happen before any output
                    header('Location: bookings.php');
                    exit;
                } else {
                    $error_message = "Error executing booking insert: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Database error preparing booking insert: " . mysqli_error($db);
            }
        }
    } else {
        $error_message = "Database connection error during form submission.";
    }
    // Log errors if any occurred during POST
    if (!empty($error_message)) {
        error_log("Create Booking Error: " . strip_tags($error_message)); // Use strip_tags to remove HTML for cleaner log
    }
}
// --- End Handle Form Submission ---

// --- Now include header (starts HTML output) ---
include('header.php');

// --- Fetch Clients and Available Pools for Dropdowns (Only needed if not redirected) ---
$clients = [];
$available_pools = [];
$fetch_error_message = null; // Use separate variable for fetch errors

if (isset($db)) {
    // Fetch Clients
    $client_query = "SELECT id, full_name FROM client ORDER BY full_name ASC";
    $client_result = mysqli_query($db, $client_query);
    if ($client_result) {
        while ($row = mysqli_fetch_assoc($client_result)) {
            $clients[] = $row;
        }
    } else {
        $fetch_error_message .= "Error fetching clients: " . mysqli_error($db) . "<br>";
    }

    // Fetch Available Pools (adjust status check if needed)
    $pool_query = "SELECT id FROM pool WHERE status_pool = 'available' ORDER BY id ASC";
    $pool_result = mysqli_query($db, $pool_query);
    if ($pool_result) {
        while ($row = mysqli_fetch_assoc($pool_result)) {
            $available_pools[] = $row;
        }
    } else {
        $fetch_error_message .= "Error fetching available pools: " . mysqli_error($db) . "<br>";
    }
} else {
    // This case should ideally not happen if db check passed above, but good practice
    $fetch_error_message = "Database connection became unavailable.";
}
if (!empty($fetch_error_message)) {
    error_log("Create Booking Fetch Error: " . strip_tags($fetch_error_message));
}

?>

<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">

    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Bookings /</span> Add New Booking</h4>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <h5 class="card-header">Booking Details</h5>
                <div class="card-body">
                    <?php 
                    // Display errors from POST submission (if redirect didn't happen)
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error_message)):
                    ?>
                        <div class="alert alert-danger" role="alert">
                            <strong>Error(s) creating booking:</strong><br>
                            <?php echo $error_message; ?>
                        </div>
                    <?php 
                    // Display errors from fetching data for the form
                    elseif (!empty($fetch_error_message)):
                     ?>
                         <div class="alert alert-warning" role="alert">
                            <strong>Warning:</strong> Could not load all data for the form.<br>
                            <?php echo $fetch_error_message; ?>
                        </div>
                    <?php endif; ?>

                    <form action="create_booking.php" method="POST">
                        <div class="row">
                            <div class="mb-3 col-md-6">
                                <label for="client_id" class="form-label">Client</label>
                                <select id="client_id" name="client_id" class="form-select" required>
                                    <option value="" disabled selected>Select Client</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>" <?php echo (isset($_POST['client_id']) && $_POST['client_id'] == $client['id']) ? 'selected' : ''; ?> >
                                            <?php echo htmlspecialchars($client['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="num_guests" class="form-label">Number of Guests</label>
                                <input class="form-control" type="number" id="num_guests" name="num_guests" min="1" value="<?php echo isset($_POST['num_guests']) ? htmlspecialchars($_POST['num_guests']) : '1'; ?>" required />
                            </div>
                             <div class="mb-3 col-md-6">
                                <label for="booking_date" class="form-label">Booking Date</label>
                                <input class="form-control" type="date" id="booking_date" name="booking_date" value="<?php echo isset($_POST['booking_date']) ? htmlspecialchars($_POST['booking_date']) : date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required />
                            </div>
                             <div class="mb-3 col-md-6">
                                <label for="booking_time" class="form-label">Booking Time</label>
                                <input class="form-control" type="time" id="booking_time" name="booking_time" value="<?php echo isset($_POST['booking_time']) ? htmlspecialchars($_POST['booking_time']) : '10:00'; ?>" required />
                            </div>
                             <div class="mb-3 col-md-6">
                                <label for="pool_id" class="form-label">Assign Pool</label>
                                <select id="pool_id" name="pool_id" class="form-select" required>
                                    <option value="" disabled selected>Select Available Pool</option>
                                     <?php if (empty($available_pools)): ?>
                                        <option value="" disabled>No pools currently available</option>
                                    <?php else: ?>
                                        <?php foreach ($available_pools as $pool): ?>
                                            <option value="<?php echo $pool['id']; ?>" <?php echo (isset($_POST['pool_id']) && $_POST['pool_id'] == $pool['id']) ? 'selected' : ''; ?> >
                                                Pool <?php echo htmlspecialchars($pool['id']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                     <?php endif; ?>
                                </select>
                            </div>
                            <!-- Status is typically set programmatically, not by user on creation -->
                        </div>
                        <div class="mt-2">
                            <button type="submit" name="add_booking" class="btn btn-primary me-2">Save Booking</button>
                            <a href="bookings.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<?php include('footer.php'); ?> 