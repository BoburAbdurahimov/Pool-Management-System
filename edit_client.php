<?php
include('db.php'); // Include DB connection

$message = ''; // Variable to store success or error messages
$client_data = null; // Variable to hold client data
$client_id = null;

// 1. Check if ID is provided in the URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $client_id = mysqli_real_escape_string($db, $_GET['id']);

    // 2. Fetch existing client data if not a POST request
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        $select_query = "SELECT * FROM client WHERE id = '$client_id'";
        $result = mysqli_query($db, $select_query);
        if ($result && mysqli_num_rows($result) > 0) {
            $client_data = mysqli_fetch_assoc($result);
        } else {
            $message = '<div class="alert alert-danger">Client not found.</div>';
            // Optional: Redirect if client not found
            // header("Location: clients.php?error=notfound");
            // exit();
        }
    }
} else if ($_SERVER["REQUEST_METHOD"] != "POST") {
    // If ID is missing and it's not a POST request, redirect or show error
    header("Location: clients.php?error=noid");
    exit();
}

// 3. Handle form submission (POST request)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Make sure client ID is available (either from GET or hidden form field)
    if (isset($_POST['clientId']) && is_numeric($_POST['clientId'])) {
        $client_id = mysqli_real_escape_string($db, $_POST['clientId']);

        // Sanitize input data
        $full_name = mysqli_real_escape_string($db, $_POST['fullName']);
        $phone_number = mysqli_real_escape_string($db, $_POST['phoneNumber']);
        $status_client = mysqli_real_escape_string($db, $_POST['clientStatus']);
        $updated_at = date('Y-m-d H:i:s'); // Current timestamp for update

        // Simple validation
        if (!empty($full_name) && !empty($phone_number) && !empty($status_client)) {
            // Prepare the UPDATE statement
            $update_query = "UPDATE client SET 
                                full_name = '$full_name', 
                                phone_number = '$phone_number', 
                                status_client = '$status_client', 
                                updated_at = '$updated_at' 
                            WHERE id = '$client_id'";

            // Execute the query
            if (mysqli_query($db, $update_query)) {
                // Redirect to clients list on success
                header("Location: clients.php?update=success");
                exit();
            } else {
                // Database error
                $message = '<div class="alert alert-danger" role="alert">Error updating client: ' . mysqli_error($db) . '</div>';
                // Re-fetch data to display in form after error
                $select_query = "SELECT * FROM client WHERE id = '$client_id'";
                $result = mysqli_query($db, $select_query);
                if ($result && mysqli_num_rows($result) > 0) {
                    $client_data = mysqli_fetch_assoc($result);
                } else {
                    // Handle case where client data cannot be re-fetched
                    $message .= '<div class="alert alert-warning">Could not retrieve client data after error.</div>';
                }
            }
        } else {
            // Validation error
            $message = '<div class="alert alert-warning" role="alert">Please fill in all required fields.</div>';
             // Re-fetch data to display in form after error
             $select_query = "SELECT * FROM client WHERE id = '$client_id'";
             $result = mysqli_query($db, $select_query);
             if ($result && mysqli_num_rows($result) > 0) {
                 $client_data = mysqli_fetch_assoc($result);
             } else {
                 $message .= '<div class="alert alert-warning">Could not retrieve client data after error.</div>';
             }
        }
    } else {
        // Handle missing client ID in POST
        $message = '<div class="alert alert-danger">Client ID missing during update process.</div>';
    }
}


include('header.php'); // Include header
?>

<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">
  <h4 class="py-3 mb-4">Edit Client</h4>

  <?php echo $message; // Display messages here ?>

  <?php if ($client_data): // Only show form if client data was fetched ?>
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Client Details</h5>
    </div>
    <div class="card-body">
      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?id=<?php echo $client_id; ?>" method="POST"> 
        <input type="hidden" name="clientId" value="<?php echo htmlspecialchars($client_data['id']); ?>">

        <div class="mb-3">
          <label class="form-label" for="fullName">Full Name</label>
          <input type="text" class="form-control" id="fullName" name="fullName" placeholder="John Doe" value="<?php echo htmlspecialchars($client_data['full_name']); ?>" required />
        </div>
        <div class="mb-3">
          <label class="form-label" for="phoneNumber">Phone Number</label>
          <input type="text" class="form-control" id="phoneNumber" name="phoneNumber" placeholder="+1 234 567 8900" value="<?php echo htmlspecialchars($client_data['phone_number']); ?>" required />
        </div>
        <div class="mb-3">
          <label class="form-label" for="clientStatus">Client Status</label>
          <select class="form-select" id="clientStatus" name="clientStatus" required>
            <option value="Regular" <?php echo ($client_data['status_client'] == 'Regular') ? 'selected' : ''; ?>>Regular</option>
            <option value="VIP" <?php echo ($client_data['status_client'] == 'VIP') ? 'selected' : ''; ?>>VIP</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Update Client</button>
        <a href="clients.php" class="btn btn-secondary">Cancel</a>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div>
</div>

<?php include('footer.php'); // Include footer ?> 