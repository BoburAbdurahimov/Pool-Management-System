<?php
include('db.php'); // Include the database connection file

// Check if the client ID is provided in the URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $client_id = mysqli_real_escape_string($db, $_GET['id']);

    // Prepare the SQL UPDATE statement
    $update_query = "UPDATE client SET status = 0 WHERE id = '$client_id'";

    // Execute the query
    if (mysqli_query($db, $update_query)) {
        // Redirect back to the clients list page on success
        // Optionally, you could add a success message parameter to the URL
        header("Location: clients.php?update=success");
        exit();
    } else {
        // Handle database error
        // Redirect back with an error message or display an error
        header("Location: clients.php?update=error&msg=" . urlencode(mysqli_error($db)));
        exit();
    }
} else {
    // Handle invalid or missing ID
    header("Location: clients.php?update=error&msg=" . urlencode("Invalid client ID."));
    exit();
}

?> 