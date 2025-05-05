<?php 
session_start(); // Corrected function name: session_start()

// --- Handle Clearing Client Context ---
if (isset($_GET['clear_client']) && $_GET['clear_client'] == '1') {
    unset($_SESSION['current_order_client_id']);
    unset($_SESSION['current_order_client_status']);
    unset($_SESSION['cart']); // Also clear cart when clearing client
    unset($_SESSION['show_client_selection_message']); // <-- Unset the flag here too
    // Redirect to remove the GET parameter
    header('Location: bar.php');
    exit;
}

// --- Initialize session keys if they don't exist ---
if (!isset($_SESSION['current_order_client_id'])) {
    $_SESSION['current_order_client_id'] = null;
}
if (!isset($_SESSION['current_order_client_status'])) {
    $_SESSION['current_order_client_status'] = null;
}
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// --- Handle Client Selection from clients.php ---
if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
    $new_client_id = (int)$_GET['client_id'];
    // If it's a different client than the one already in the session, start a new order
    if ($new_client_id !== $_SESSION['current_order_client_id']) {
        $_SESSION['current_order_client_id'] = $new_client_id;
        $_SESSION['current_order_client_status'] = isset($_GET['client_status']) ? $_GET['client_status'] : null;
        $_SESSION['cart'] = []; // Clear cart for the new client
        $_SESSION['show_client_selection_message'] = true; // <-- Set the flag here
        
        // Redirect to remove GET parameters after setting session
        $redirect_url = 'bar.php';
        $query_params = [];
        if(isset($_GET['search'])) $query_params['search'] = $_GET['search'];
        // Add other persistent params if needed, but NOT client_id/status
        if (!empty($query_params)) {
            $redirect_url .= '?' . http_build_query($query_params);
        }
         // Redirect only if client_id was in the GET params to avoid loops
        if (isset($_GET['client_id'])) { 
           header('Location: ' . $redirect_url);
           exit;
        }
    }
}

// --- Check if we should show Add to Order buttons ---
$show_add_button = isset($_SESSION['current_order_client_id']) && $_SESSION['current_order_client_id'] !== null;
// --- End check ---

// Handle Add to Order form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_order'])) {
    // Sanitize and validate inputs (basic example)
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_SANITIZE_NUMBER_INT);
    $item_name = filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_STRING);
    $item_price = filter_input(INPUT_POST, 'item_price', FILTER_VALIDATE_FLOAT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    // Ensure required fields are present and valid
    if ($item_id && $item_name && $item_price !== false && $quantity > 0) {
        // Check if item already exists in cart
        if (isset($_SESSION['cart'][$item_id])) {
            // Update quantity
            $_SESSION['cart'][$item_id]['quantity'] += $quantity;
        } else {
            // Add new item
            $_SESSION['cart'][$item_id] = [
                'name' => $item_name,
                'price' => $item_price,
                'quantity' => $quantity
            ];
        }
        
        // Optional: Redirect to prevent form resubmission on refresh
        // header('Location: ' . $_SERVER['PHP_SELF']); // Redirect to the same page
        // exit;
        
        // Or just set a success message (requires display logic below)
        // $_SESSION['message'] = htmlspecialchars($item_name) . ' added to order.';
    } else {
        // Handle invalid input - maybe set an error message
        // $_SESSION['error'] = 'Invalid item data.';
    }
    // It's often good practice to redirect after POST to prevent refresh issues
    header('Location: ' . $_SERVER['REQUEST_URI']); // Redirect to current page/tab
    exit;
}

// --- Get Search Term ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
// --- End Search Term ---

?>
<?php include("header.php"); ?>
<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">

<!-- Add Search Form -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" action="bar.php">
      <div class="input-group">
        <input type="text" class="form-control" placeholder="Search Food, Drinks, Items..." name="search" value="<?php echo htmlspecialchars($search_term); ?>">
        <button class="btn btn-outline-primary" type="submit">Search</button>
        <?php if (!empty($search_term)): ?>
          <a href="bar.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </div>
      <?php // Preserve other relevant GET params if needed (like 'from') ?>
      <?php if (isset($_GET['from'])): ?>
        <input type="hidden" name="from" value="<?php echo htmlspecialchars($_GET['from']); ?>">
      <?php endif; ?>
       <?php // Do NOT add client_id/status here anymore, they are in session ?>
    </form>
  </div>
</div>
<!-- End Search Form -->

<?php 
// --- Display Client Selection Message (Flash Message) ---
if (isset($_SESSION['show_client_selection_message']) && $_SESSION['show_client_selection_message']): 
?>
    <?php
    // Fetch client name to display - Requires DB connection
    include_once('db.php'); // Include DB connection
    $client_name = 'Client ' . $_SESSION['current_order_client_id']; // Default
    if (isset($db)) {
        $stmt = mysqli_prepare($db, "SELECT full_name FROM client WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['current_order_client_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $client_name = htmlspecialchars($row['full_name']);
        }
        mysqli_stmt_close($stmt);
    }
    ?>
    <div class="alert alert-info" role="alert">
        <i class='bx bx-user-pin me-1'></i> Creating order for: <strong><?php echo $client_name; ?></strong> (<?php echo htmlspecialchars($_SESSION['current_order_client_status'] ?? 'N/A'); ?>)
         | <a href="bar.php?clear_client=1" class="alert-link">Start New Client Order</a>
    </div>
<?php 
    unset($_SESSION['show_client_selection_message']); // <-- Unset the flag after displaying
endif; 
// --- End Display Client Selection Message ---
?>

<h4 class="py-3 mb-4">Bar & Items Management</h4>
<div class="d-flex justify-content-end mb-3">
    <?php 
    // Construct link for View Current Order, including client info if available
    $view_order_link = "ordered_bar_items.php";
    if ($show_add_button) { // Only add client info if a client is selected
        $view_order_link .= "?client_id=" . urlencode($_SESSION['current_order_client_id']) . "&client_status=" . urlencode($_SESSION['current_order_client_status']);
    }
    ?>
    <a href="add_bar_item.php" class="btn btn-info me-2">
        <i class="bx bx-plus me-1"></i> Add New Item
    </a>
    <a href="<?php echo $view_order_link; ?>" class="btn btn-success">View Current Order </a>
</div>
<div class="content-wrapper">
    <!-- Tabs navigation wrapper -->
    <div class="d-flex mb-4">
      <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item me-2">
          <button type="button" class="nav-link active rounded p-2" role="tab" data-bs-toggle="tab" data-bs-target="#food" aria-controls="food" aria-selected="true">
            <i class="tf-icons bx bx-food-menu me-1"></i> Food
          </button>
        </li>
        <li class="nav-item me-2">
          <button type="button" class="nav-link rounded p-2" role="tab" data-bs-toggle="tab" data-bs-target="#drinks" aria-controls="drinks" aria-selected="false">
            <i class="tf-icons bx bx-drink me-1"></i> Drinks
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link rounded p-2" role="tab" data-bs-toggle="tab" data-bs-target="#items" aria-controls="items" aria-selected="false">
            <i class="tf-icons bx bx-package me-1"></i> Items
          </button>
        </li>
      </ul>
    </div>

    <!-- Tab panes -->
    <div class="tab-content">
      <div class="tab-pane fade show active" id="food" role="tabpanel">
        
          <div class="row">
            <?php 
            $found_food = false; // Flag to check if any food item is displayed
            foreach ($array_bar as $row) { 
              // Filter by category AND search term
              $matches_search = empty($search_term) || 
                                stripos($row['name'], $search_term) !== false || 
                                stripos($row['description'], $search_term) !== false;
              
              if($row['category'] == 'food' && $matches_search) {
                $found_food = true; // Mark as found
               ?>
                <div class="col-md-4 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <h5 class="card-title">Bar <?php echo $row['name']; ?></h5>
                      <img
                        class="img-fluid d-flex mx-auto my-6 rounded" style="width: 400px; height: 300px; object-fit: cover;"
                        src="assets/img/elements/4.png"
                        alt="Card image cap" />
                      <h4 class="card-text fw-bold" >Bar price is <?php echo $row['price']; ?>$</h4>
                      <p class="card-text"><?php echo $row['description']; ?></p>
                      <div class="order-controls-container mt-2" id="order-controls-<?php echo htmlspecialchars($row['id'] ?? ''); ?>">
                          <?php 
                          $item_id_check = htmlspecialchars($row['id'] ?? '');
                          if ($item_id_check && isset($_SESSION['cart'][$item_id_check])):
                          ?>
                              <button type="button" class="btn btn-secondary btn-sm" disabled>Added to Order</button>
                          <?php else: ?>
                              <?php if ($show_add_button): ?>
                                  <form action="ajax_add_to_cart.php" method="post" class="add-to-cart-form">
                                      <input type="hidden" name="item_id" value="<?php echo $item_id_check; ?>">
                                      <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($row['name'] ?? ''); ?>">
                                      <input type="hidden" name="item_price" value="<?php echo htmlspecialchars($row['price'] ?? ''); ?>">
                                      <input type="hidden" name="item_category" value="food">
                                      <div class="d-flex align-items-center">
                                          <input type="number" name="quantity" value="1" min="1" class="form-control form-control-sm me-2" style="width: 70px;">
                                          <button type="submit" name="add_to_order" class="btn btn-primary btn-sm">Add to Order</button>
                                      </div>
                                  </form>
                              <?php endif; ?>
                          <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
            <?php  
              }
            }
            // Display message if no food items found after filtering
            if (!$found_food) {
                echo "<div class='col-12'><p class='text-center text-muted mt-4'>No food items found" . (!empty($search_term) ? " matching '" . htmlspecialchars($search_term) . "'." : ".") . "</p></div>";
            }
            ?>
            </div>
            <!-- Food content -->
     
      </div>
      <div class="tab-pane fade" id="drinks" role="tabpanel">
      <div class="row">
            <?php 
            $found_drinks = false; // Flag
            foreach ($array_bar as $row) { 
              // Filter by category AND search term
              $matches_search = empty($search_term) || 
                                stripos($row['name'], $search_term) !== false || 
                                stripos($row['description'], $search_term) !== false;

              if($row['category'] == 'drink' && $matches_search) {
                $found_drinks = true; // Mark as found
               ?>
                <div class="col-md-4 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <h5 class="card-title">Bar <?php 
                        
                          echo $row['name'];
                        
                      ?></h5>
                      <img
                        class="img-fluid d-flex mx-auto my-6 rounded" style="width: 400px; height: 300px; object-fit: cover;"
                        src="assets/img/elements/5.png"
                        alt="Card image cap" />
                      <h4 class="card-text fw-bold" >Bar price is <?php echo $row['price']; ?>$</h4>
                      <p class="card-text"><?php echo $row['description']; ?></p>
                      <div class="order-controls-container mt-2" id="order-controls-<?php echo htmlspecialchars($row['id'] ?? ''); ?>">
                          <?php 
                          $item_id_check = htmlspecialchars($row['id'] ?? '');
                          if ($item_id_check && isset($_SESSION['cart'][$item_id_check])):
                          ?>
                              <button type="button" class="btn btn-secondary btn-sm" disabled>Added to Order</button>
                          <?php else: ?>
                              <?php if ($show_add_button): ?>
                                  <form action="ajax_add_to_cart.php" method="post" class="add-to-cart-form">
                                      <input type="hidden" name="item_id" value="<?php echo $item_id_check; ?>">
                                      <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($row['name'] ?? ''); ?>">
                                      <input type="hidden" name="item_price" value="<?php echo htmlspecialchars($row['price'] ?? ''); ?>">
                                      <input type="hidden" name="item_category" value="drink">
                                      <div class="d-flex align-items-center">
                                          <input type="number" name="quantity" value="1" min="1" class="form-control form-control-sm me-2" style="width: 70px;">
                                          <button type="submit" name="add_to_order" class="btn btn-primary btn-sm">Add to Order</button>
                                      </div>
                                  </form>
                              <?php endif; ?>
                          <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
            <?php  
              }
            }
             // Display message if no drinks found after filtering
            if (!$found_drinks) {
                echo "<div class='col-12'><p class='text-center text-muted mt-4'>No drinks found" . (!empty($search_term) ? " matching '" . htmlspecialchars($search_term) . "'." : ".") . "</p></div>";
            }
            ?>
            </div>
      </div>
      <div class="tab-pane fade" id="items" role="tabpanel">
      <div class="row">
            <?php 
            $found_items = false; // Flag
            foreach ($array_bar as $row) { 
              // Filter by category AND search term
              $matches_search = empty($search_term) || 
                                stripos($row['name'], $search_term) !== false || 
                                stripos($row['description'], $search_term) !== false;

              if($row['category'] == 'item' && $matches_search) {
                $found_items = true; // Mark as found
               ?>
                <div class="col-md-4 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <h5 class="card-title">Bar <?php echo $row['name']; ?></h5>
                      <img
                        class="img-fluid d-flex mx-auto my-6 rounded" style="width: 400px; height: 300px; object-fit: cover;"
                        src="assets/img/elements/18.png"
                        alt="Card image cap" />
                      <h4 class="card-text fw-bold" >Bar price is <?php echo $row['price']; ?>$</h4>
                      <p class="card-text"><?php echo $row['description']; ?></p>
                      <div class="order-controls-container mt-2" id="order-controls-<?php echo htmlspecialchars($row['id'] ?? ''); ?>">
                          <?php 
                          $item_id_check = htmlspecialchars($row['id'] ?? '');
                          if ($item_id_check && isset($_SESSION['cart'][$item_id_check])):
                          ?>
                              <button type="button" class="btn btn-secondary btn-sm mt-2" disabled>Added to Order</button>
                          <?php else: ?>
                              <?php if ($show_add_button): ?>
                                  <form action="ajax_add_to_cart.php" method="post" class="add-to-cart-form">
                                      <input type="hidden" name="item_id" value="<?php echo $item_id_check; ?>">
                                      <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($row['name'] ?? ''); ?>">
                                      <input type="hidden" name="item_price" value="<?php echo htmlspecialchars($row['price'] ?? ''); ?>">
                                      <input type="hidden" name="item_category" value="item">
                                      <div class="d-flex align-items-center">
                                          <input type="number" name="quantity" value="1" min="1" class="form-control form-control-sm me-2" style="width: 70px;">
                                          <button type="submit" name="add_to_order" class="btn btn-primary btn-sm">Add to Order</button>
                                      </div>
                                  </form>
                              <?php endif; ?>
                          <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
            <?php  
              }
            }
            // Display message if no items found after filtering
            if (!$found_items) {
                echo "<div class='col-12'><p class='text-center text-muted mt-4'>No other items found" . (!empty($search_term) ? " matching '" . htmlspecialchars($search_term) . "'." : ".") . "</p></div>";
            }
            ?>
            </div>
      </div>
    </div>
</div>



</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Use event delegation for dynamically added forms if needed
    document.body.addEventListener('submit', function(event) {
        // Check if the submitted target is one of our forms
        if (event.target.matches('.add-to-cart-form')) {
            event.preventDefault(); // Stop traditional form submission

            const form = event.target;
            const formData = new FormData(form);
            const itemId = formData.get('item_id'); // Get item ID for targeting the container
            const controlsContainer = document.getElementById('order-controls-' + itemId);

            fetch(form.action, { // Use the form's action attribute
                method: 'POST',
                body: formData 
            })
            .then(response => response.json()) // Expect a JSON response from PHP
            .then(data => {
                if (data.success) {
                    // Update the UI: Replace form with 'Added' button
                    if (controlsContainer) {
                        controlsContainer.innerHTML = '<button type="button" class="btn btn-secondary btn-sm" disabled>Added to Order</button>';
                    }
                    // Optional: Show a success message (e.g., using a toast notification library)
                    console.log('Success:', data.message); 
                } else {
                    // Handle errors (e.g., show an alert)
                    alert('Error: ' + (data.message || 'Could not add item.'));
                    console.error('Error:', data.message);
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                alert('An error occurred while adding the item.');
            });
        }
    });
});
</script>

<?php include("footer.php"); ?>
