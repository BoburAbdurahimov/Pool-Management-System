<?php
include('db.php'); // Include DB connection

// --- Debugging: Check DB Connection ---
if (!$db) {
    die("<div class='alert alert-danger'>Database Connection Failed: " . mysqli_connect_error() . "</div>");
}
// --- End Debugging ---

$message = ''; // Variable to store success or error messages

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Debugging: Show POST data ---
    // echo '<pre>POST Data: '; print_r($_POST); echo '</pre>'; 
    // --- End Debugging ---

    // Sanitize input data
    $full_name = isset($_POST['fullName']) ? mysqli_real_escape_string($db, $_POST['fullName']) : '';
    $phone_number = isset($_POST['phoneNumber']) ? mysqli_real_escape_string($db, $_POST['phoneNumber']) : '';
    $status_client = isset($_POST['clientStatus']) ? mysqli_real_escape_string($db, $_POST['clientStatus']) : '';

    // Simple validation (check if fields are not empty)
    if (!empty($full_name) && !empty($phone_number) && !empty($status_client)) { // Add status_client validation
        // Default values
        $status = 1; // Active
        // $status_client is now taken from the form
        $created_at = date('Y-m-d H:i:s'); // Current timestamp

        // Prepare the INSERT statement
        $insert_query = "INSERT INTO client (full_name, phone_number, status, status_client, created_at) VALUES ('$full_name', '$phone_number', '$status', '$status_client', '$created_at')";

        // --- Debugging: Show Query ---
        // echo "<p>Executing Query: " . htmlspecialchars($insert_query) . "</p>";
        // --- End Debugging ---

        // Execute the query
        if (mysqli_query($db, $insert_query)) {
            // Get the ID of the newly inserted client
            $new_client_id = mysqli_insert_id($db);

            // Redirect to the dedicated order pools page with the new client ID
            header("Location: order_pools.php?client_id=" . $new_client_id . "&register=success");
            exit();
        } else {
            // Database error
            // --- Debugging: Show detailed error --- 
            $message = '<div class="alert alert-danger" role="alert">Error registering client. Query failed: ' . mysqli_error($db) . '</div>';
            error_log("MySQL Error: " . mysqli_error($db) . " | Query: " . $insert_query); // Log error to server logs
            // --- End Debugging ---
        }
    } else {
        // Validation error
        $message = '<div class="alert alert-warning" role="alert">Please fill in all required fields. Make sure Full Name, Phone Number, and Client Status are provided.</div>';
        // --- Debugging: Show which fields might be empty ---
        // $message .= "<br>Debug: FullName='{$full_name}', PhoneNumber='{$phone_number}', Status='{$status_client}'";
        // --- End Debugging ---
    }
}

include('header.php'); // Include header
?>

<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">
  <h4 class="py-3 mb-4">Register New Client</h4>

  <?php echo $message; // Display messages here ?>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Client Details</h5>
    </div>
    <div class="card-body">
      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <div class="mb-3">
          <label class="form-label" for="fullName">Full Name</label>
          <input type="text" class="form-control" id="fullName" name="fullName" placeholder="John Doe" required />
        </div>
        <div class="mb-3">
          <label class="form-label" for="phoneNumber">Phone Number</label>
          <input type="text" class="form-control" id="phoneNumber" name="phoneNumber" placeholder="+998 (XX) XXX-XX-XX" required />
        </div>
        <div class="mb-3">
          <label class="form-label" for="clientStatus">Client Status</label>
          <select class="form-select" id="clientStatus" name="clientStatus" required>
            <option value="Regular" selected>Regular</option>
            <option value="VIP">VIP</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Register Client</button>
        <a href="clients.php" class="btn btn-secondary">Cancel</a>
      </form>
    </div>
  </div>

</div>
</div>

<?php include('footer.php'); // Include footer ?> 