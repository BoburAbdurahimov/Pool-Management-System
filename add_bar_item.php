<?php
session_start();
include_once("db.php"); // Include DB connection

$message = ''; // For success/error messages
$upload_dir = '../uploads/bar_items/'; // Relative path to your desired upload directory
$allowed_types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];

// --- Handle Form Submission --- 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    if (isset($db)) {
        // --- Basic Input Sanitization/Validation ---
        $name = isset($_POST['name']) ? trim(mysqli_real_escape_string($db, $_POST['name'])) : '';
        $category = isset($_POST['category']) && in_array($_POST['category'], ['food', 'drink', 'item']) ? $_POST['category'] : '';
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $description = isset($_POST['description']) ? trim(mysqli_real_escape_string($db, $_POST['description'])) : '';
        $status = 1; // Default status to active (assuming 1 means active)
        $image_url_to_save = null; // Default to NULL if no image uploaded or error

        // --- Validation Checks ---
        $errors = [];
        if (empty($name)) $errors[] = 'Item name is required.';
        if (empty($category)) $errors[] = 'Valid category (food, drink, item) is required.';
        if ($price === false || $price < 0) $errors[] = 'Valid non-negative price is required.';

        // --- Image Upload Handling ---
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == UPLOAD_ERR_OK) {
            $file_name = $_FILES['item_image']['name'];
            $file_tmp_name = $_FILES['item_image']['tmp_name'];
            $file_size = $_FILES['item_image']['size'];
            $file_type = $_FILES['item_image']['type'];
            $file_ext_parts = explode('.', $file_name);
            $file_ext = strtolower(end($file_ext_parts));

            // Check file type
            if (!array_key_exists($file_ext, $allowed_types) || !in_array($file_type, $allowed_types)) {
                $errors[] = 'Invalid image file type. Allowed types: JPG, PNG, GIF.';
            }

            // Check file size (e.g., max 2MB)
            if ($file_size > 2 * 1024 * 1024) { 
                $errors[] = 'Image file size exceeds the 2MB limit.';
            }

            // If no validation errors so far regarding the image itself
            if (empty($errors)) {
                // Ensure upload directory exists (create if not - requires permissions)
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) { 
                        $errors[] = 'Failed to create upload directory. Please check permissions.';
                        error_log("Failed to create directory: {$upload_dir}");
                    } 
                }
                
                // Check writability again after trying to create
                 if (!is_writable($upload_dir)) {
                     $errors[] = 'Upload directory is not writable. Please check permissions.';
                      error_log("Upload directory not writable: {$upload_dir}");
                 }

                // Proceed if directory is okay
                if (empty($errors)) {
                    $new_filename = uniqid('item_', true) . '.' . $file_ext;
                    $target_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file_tmp_name, $target_path)) {
                        // Store the relative path for the database
                        $image_url_to_save = 'uploads/bar_items/' . $new_filename; 
                    } else {
                        $errors[] = 'Failed to move uploaded image file.';
                        error_log("Failed to move uploaded file to: {$target_path}");
                    }
                }
            }
        } elseif (isset($_FILES['item_image']) && $_FILES['item_image']['error'] != UPLOAD_ERR_NO_FILE) {
            // Handle other upload errors
            $errors[] = 'Error uploading image file. Code: ' . $_FILES['item_image']['error'];
        }
        // --- End Image Upload Handling ---

        // --- Database Insertion (if no validation errors) ---
        if (empty($errors)) {
            // Based on schema: name, status, created_at, updated_at, price, category, description, img_url
            $insert_query = "INSERT INTO bar_items (name, status, created_at, updated_at, price, category, description, img_url) 
                             VALUES (?, ?, NOW(), NOW(), ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($db, $insert_query);
            if ($stmt) {
                // Bind params: s (name), i (status), d (price), s (category), s (description), s (img_url)
                mysqli_stmt_bind_param($stmt, 'sidsss', $name, $status, $price, $category, $description, $image_url_to_save);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = '<div class="alert alert-success">New bar item \'' . htmlspecialchars($name) . '\' added successfully!</div>';
                    // Optionally clear form fields or redirect
                    // header('Location: bar.php'); exit();
                } else {
                     $message = '<div class="alert alert-danger">Error adding item: ' . mysqli_stmt_error($stmt) . '</div>';
                }
                 mysqli_stmt_close($stmt);
            } else {
                 $message = '<div class="alert alert-danger">Database error preparing statement: ' . mysqli_error($db) . '</div>';
            }
        } else {
            // Display validation errors
            $message = '<div class="alert alert-warning">Please fix the following errors: <ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
        }
        // --- End Database Insertion ---

    } else {
        $message = '<div class="alert alert-danger">Database connection error. Cannot save item.</div>';
    }
}
// --- End Handle Form Submission ---

// Include header AFTER processing potentially redirecting logic (though we aren't redirecting here)
include("header.php"); 

?>

<div class="layout-page">
    <div class="container-xxl flex-grow-1 container-p-y">

        <h4 class="py-3 mb-4"><span class="text-muted fw-light">Bar /</span> Add New Item</h4>

        <?php echo $message; // Display success or error messages ?>

        <div class="row">
            <div class="col-xl">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">New Bar Item Details</h5>
                        <small class="text-muted float-end">Enter item information</small>
                    </div>
                    <div class="card-body">
                        <!-- IMPORTANT: Add enctype for file uploads -->
                        <form action="add_bar_item.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="add_item" value="1"> 

                            <div class="mb-3">
                                <label class="form-label" for="item-name">Item Name</label>
                                <input type="text" class="form-control" id="item-name" name="name" placeholder="e.g., Cheeseburger, Coca-Cola, Pool Towel" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="item-category">Category</label>
                                <select class="form-select" id="item-category" name="category" required>
                                    <option value="" disabled selected>-- Select Category --</option>
                                    <option value="food">Food</option>
                                    <option value="drink">Drink</option>
                                    <option value="item">Item</option>
                                </select>
                            </div>

                             <div class="mb-3">
                                <label class="form-label" for="item-price">Price ($)</label>
                                <input type="number" class="form-control" id="item-price" name="price" placeholder="5.99" step="0.01" min="0" required />
                            </div>

                             <div class="mb-3">
                                <label class="form-label" for="item-description">Description</label>
                                <textarea class="form-control" id="item-description" name="description" rows="3" placeholder="Brief description of the item (optional)"></textarea>
                            </div>

                             <div class="mb-3">
                                <label class="form-label" for="item-image">Item Image (Optional)</label>
                                <input class="form-control" type="file" id="item-image" name="item_image" accept="image/jpeg, image/png, image/gif"> 
                                <div class="form-text">Allowed types: JPG, PNG, GIF. Max size: 2MB.</div>
                            </div>
                           
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-plus-circle me-1"></i> Add Item
                            </button>
                            <a href="bar.php" class="btn btn-secondary">Cancel</a> 
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- / Content -->

    <?php include("footer.php"); ?>
</div> 