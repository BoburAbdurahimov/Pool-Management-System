<?php include("header.php");

// Fetch active/ordered pool data
$array_ordering = []; // Initialize as empty array
if (isset($db)) {
    $ordering_query = "SELECT op.id, op.client_id, op.pool_id, op.start_time, op.end_time 
                       FROM order_pools op
                       WHERE op.status = 'ordered' OR (op.status = 'active' AND NOW() < op.end_time) -- Adjust status checks if needed
                       ORDER BY op.pool_id ASC"; // Or order as needed
    $ordering_result = mysqli_query($db, $ordering_query);
    if ($ordering_result) {
        while ($row = mysqli_fetch_assoc($ordering_result)) {
            $array_ordering[] = $row;
        }
    } else {
        error_log("Error fetching pool ordering data in pool.php: " . mysqli_error($db));
        // Handle error appropriately, maybe display a message
    }
} else {
     error_log("Database connection not available in pool.php to fetch ordering data.");
}

// --- Display Session Messages ---
if (isset($_SESSION['message'])) {
    $message_type = $_SESSION['message']['type']; // e.g., success, danger
    $message_text = $_SESSION['message']['text'];
    echo "<div class='alert alert-{$message_type} alert-dismissible fade show' role='alert'>
            {$message_text}
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
          </div>";
    unset($_SESSION['message']); // Clear the message after displaying
}
// --- End Display Session Messages ---

// --- Get Client ID from URL ---
$client_id = null; // Initialize
if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
    $client_id = (int)$_GET['client_id'];
    // Optional: You might want to add a check here to ensure the client ID exists in your database
}
// --- End Get Client ID ---

?>
<!-- Content -->
<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4">Pool Management</h4>
<div class="content-wrapper">
    <!-- Tabs navigation wrapper -->
    <div class="d-flex mb-4">
      <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item me-2">
          <button type="button" class="nav-link active rounded p-2" role="tab" data-bs-toggle="tab" data-bs-target="#allPools" aria-controls="allPools" aria-selected="true">
            <i class="tf-icons bx bx-swim me-1"></i> All Pools
          </button>
        </li>
        <li class="nav-item me-2">
          <button type="button" class="nav-link rounded p-2" role="tab" data-bs-toggle="tab" data-bs-target="#availablePools" aria-controls="availablePools" aria-selected="false">
            <i class="tf-icons bx bx-check-circle me-1"></i> Available Pools
          </button>
        </li>
        <li class="nav-item me-2">
          <button type="button" class="nav-link rounded p-2" role="tab" data-bs-toggle="tab" data-bs-target="#occupiedPools" aria-controls="occupiedPools" aria-selected="false">
            <i class="tf-icons bx bx-user me-1"></i> Ordered Pools
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link rounded p-2" role="tab" data-bs-toggle="tab" data-bs-target="#maintainedPools" aria-controls="maintainedPools" aria-selected="false">
            <i class="tf-icons bx bx-wrench me-1"></i> Maintenance Pools
          </button>
        </li>
      </ul>
    </div>

    <!-- Tab panes -->
    <div class="tab-content">
      <div class="tab-pane fade show active" id="allPools" role="tabpanel">
        
            <div class="row">
            <?php 
            if (isset($array_pool) && is_array($array_pool)) {
                foreach ($array_pool as $pool) { 
                    $pool_id = htmlspecialchars($pool['id'] ?? '');
                    $pool_rate = htmlspecialchars($pool['hourly_rate'] ?? 'N/A');
                    $pool_status = strtolower($pool['status_pool'] ?? 'unknown');
                    $card_border_class = '';
                    $status_badge = '';

                    if ($pool_status == 'maintenance') {
                        $card_border_class = 'border-warning';
                        $status_badge = '<span class="badge bg-label-warning ms-2">Maintenance</span>';
                    } elseif ($pool_status == 'ordered') {
                        $card_border_class = 'border-danger';
                        $status_badge = '<span class="badge bg-label-danger ms-2">Ordered</span>';
                    } else {
                         $status_badge = '<span class="badge bg-label-success ms-2">Available</span>';
                    }
            ?>
                <div class="col-md-4 mb-4">
                  <div class="card h-100 <?php echo $card_border_class; ?>">
                    <div class="card-header d-flex justify-content-between align-items-center pb-0">
                        <h5 class="card-title mb-0">Pool Number <?php echo $pool_id; ?> <?php echo $status_badge; ?></h5>
                         <!-- Dropdown -->
                         <?php if ($pool_status == 'available' || $pool_status == 'maintenance'): ?>
                         <div class="dropdown">
                            <button class="btn p-0" type="button" id="poolActionDropdown<?php echo $pool_id; ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bx bx-dots-vertical-rounded"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="poolActionDropdown<?php echo $pool_id; ?>">
                                <?php if ($pool_status == 'maintenance'): ?>
                                    <a class="dropdown-item" href="update_pool_status.php?pool_id=<?php echo $pool_id; ?>&new_status=available">
                                        <i class="bx bx-check-circle me-1"></i> Make Available
                                    </a>
                                <?php elseif ($pool_status == 'available'): ?>
                                    <a class="dropdown-item" href="update_pool_status.php?pool_id=<?php echo $pool_id; ?>&new_status=maintenance">
                                        <i class="bx bx-wrench me-1"></i> Mark for Maintenance
                                    </a>
                                <?php endif; ?>
                                <!-- Add other actions like 'View Details' if needed -->
                            </div>
                         </div>
                         <?php endif; // End check for available or maintenance ?>
                         <!-- /Dropdown -->
                    </div>
                    <div class="card-body pt-2"> 
                      <img
                        class="img-fluid d-flex mx-auto my-4 rounded" style="width: 100%; max-width: 300px; height: 200px; object-fit: cover; <?php echo ($pool_status == 'maintenance' ? 'filter: grayscale(80%);' : ''); ?>"
                        src="assets/img/elements/pool.jpg"
                        alt="Pool image" />
                      <h6 class="card-text fw-bold" >Pool price is $<?php echo $pool_rate; ?> / hour</h6>
                      <?php if ($pool_status == 'maintenance'): ?>
                         <p class="card-text text-warning"><i class="bx bx-wrench me-1"></i> Pool is under maintenance.</p>
                      <?php elseif ($pool_status == 'ordered'): ?>
                        <?php
                            // Find ordering info for this pool
                            $ordering_info = null;
                            $client_name = 'Unknown Client';
                            foreach ($array_ordering as $ordering) {
                                if ($ordering['pool_id'] == $pool_id) {
                                    $ordering_info = $ordering;
                                    // Find client name
                                    foreach($array_client as $client) {
                                        if ($client['id'] == $ordering['client_id']) {
                                            $client_name = htmlspecialchars($client['full_name']);
                                            break;
                                        }
                                    }
                                    break;
                                }
                            }
                        ?>
                         <p class="card-text text-danger">Ordered by: <strong><?php echo $client_name; ?></strong></p>
                         
                      <?php else: // Available ?>
                        <!-- Add Order Button or other actions for available pools if needed -->
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
            <?php 
                }
            } else {
                 echo "<div class='col-12'><p class='text-center text-muted mt-4'>No pools found.</p></div>";
            }
            ?>
            </div>
          
            <!-- All pools content -->
     
      </div>
      <div class="tab-pane fade" id="availablePools" role="tabpanel">
        <div c
        <div class="row">
            <?php
            $found_available = false;
            if (isset($array_pool) && is_array($array_pool)) {
                foreach ($array_pool as $pool) {
                    if (isset($pool['status_pool']) && strtolower($pool['status_pool']) == 'available') {
                        $found_available = true;
                        $pool_id = htmlspecialchars($pool['id'] ?? '');
                        $pool_rate = htmlspecialchars($pool['hourly_rate'] ?? 'N/A');
                        $pool_num_display = htmlspecialchars($pool['id'] ?? 'Unknown'); 
            ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                 <div class="card-header d-flex justify-content-between align-items-center pb-0">
                                    <h5 class="card-title mb-0">Pool Number <?php echo $pool_num_display; ?></h5>
                                    <!-- Dropdown -->
                                    <div class="dropdown">
                                        <button class="btn p-0" type="button" id="poolActionDropdownAvail<?php echo $pool_id; ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="bx bx-dots-vertical-rounded"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="poolActionDropdownAvail<?php echo $pool_id; ?>">
                                            <a class="dropdown-item" href="update_pool_status.php?pool_id=<?php echo $pool_id; ?>&new_status=maintenance">
                                                <i class="bx bx-wrench me-1"></i> Mark for Maintenance
                                            </a>
                                        </div>
                                    </div>
                                    <!-- /Dropdown -->
                                </div>
                                <div class="card-body pt-2">
                                    <img class="img-fluid d-flex mx-auto my-4 rounded" style="width: 100%; max-width: 300px; height: 200px; object-fit: cover;" src="assets/img/elements/pool.jpg" alt="Pool image" />
                                    <h6 class="card-text fw-bold">Price: $<?php echo $pool_rate; ?> / hour</h6>
                                    
                                    <div class="select-controls-container mt-3" id="select-controls-<?php echo $pool_id; ?>">
                                         <?php 
                                         if ($pool_id && isset($_SESSION['selected_pools'][$pool_id])):
                                         ?>
                                             <button type="button" class="btn btn-secondary w-100" disabled>Selected</button>
                                         <?php 
                                         elseif (isset($_GET['from']) && $_GET['from'] === 'selected'): 
                                         ?>
                                             <form action="ajax_select_pool.php" method="post" class="select-pool-form">
                                                 <input type="hidden" name="pool_id" value="<?php echo $pool_id; ?>">
                                                 <input type="hidden" name="pool_name" value="Pool <?php echo $pool_id; ?>">
                                                 <input type="hidden" name="pool_rate" value="<?php echo $pool_rate; ?>">
                                                 <button type="submit" name="select_pool" class="btn btn-primary w-100">Add to Order</button>
                                             </form>
                                         <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
            <?php
                    } 
                } 
            }
            if (!$found_available) {
                echo "<div class='col-12'><p class='text-center text-muted mt-4'>No pools are currently available.</p></div>";
            }
            ?>
        </div>
      </div>
      <div class="tab-pane fade" id="occupiedPools" role="tabpanel">
        <div class="card border">
          <div class="card-body">
            <div class="row">
              <?php
              $poolsById = array_column($array_pool, null, 'id');
              $clientsById = [];
              if (isset($array_client)) {
                 $clientsById = array_column($array_client, null, 'id');
              }
              $occupiedPoolsFound = false; 

              if (isset($array_ordering)) {
                  foreach ($array_ordering as $ordering) {
                      $poolId = $ordering['pool_id'];
                      $clientId = $ordering['client_id'];

                      // Check pool exists and is actually 'ordered' (redundant if $array_ordering only contains ordered, but safe)
                      if (isset($poolsById[$poolId]) && strtolower($poolsById[$poolId]['status_pool']) === 'ordered') {
                          $pool = $poolsById[$poolId];
                          $client = $clientsById[$clientId] ?? null; // Handle case where client might be missing
                          $occupiedPoolsFound = true;
                          $client_name = $client ? htmlspecialchars($client['full_name']) : 'Unknown Client';
                          $client_phone = $client ? htmlspecialchars($client['phone_number']) : 'N/A';
                  ?>
                          <div class="col-md-4 mb-4">
                            <div class="card h-100 border-danger">
                              <div class="card-header d-flex justify-content-between align-items-center pb-0">
                                <h5 class="card-title mb-0">Pool Number <?php echo htmlspecialchars($pool['id']); ?></h5>
                                <!-- Dropdown -->
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="poolActionDropdownOccupied<?php echo $pool['id']; ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                     <div class="dropdown-menu dropdown-menu-end" aria-labelledby="poolActionDropdownOccupied<?php echo $pool['id']; ?>">
                                        <!-- Cannot mark occupied pool for maintenance directly, maybe view order? -->
                                        <a class="dropdown-item disabled" href="#">Mark for Maintenance (N/A)</a>
                                    </div>
                                </div>
                                <!-- /Dropdown -->
                              </div>
                              <div class="card-body pt-2">
                                 <img
                                    class="img-fluid d-flex mx-auto my-4 rounded" style="width: 100%; max-width: 300px; height: 200px; object-fit: cover;" 
                                    src="assets/img/elements/pool.jpg"
                                    alt="Pool image" />
                                <h6 class="card-subtitle mb-2 text-muted">Rate: <?php echo htmlspecialchars($pool['hourly_rate']); ?>$ / hour</h6>
                                <hr>
                                <p class="card-text"><strong>Client:</strong> <?php echo $client_name; ?></p>
                                <p class="card-text"><strong>Phone:</strong> <?php echo $client_phone; ?></p>
                                <p class="card-text small text-muted"><strong>Start:</strong> <?php echo htmlspecialchars($ordering['start_time']); ?></p>
                                <p class="card-text small text-muted"><strong>End:</strong> <?php echo htmlspecialchars($ordering['end_time']); ?></p>
                              </div>
                            </div>
                          </div>
                  <?php
                      }
                  }
              }

              if (!$occupiedPoolsFound) {
                  echo '<div class="col-12"><p class="text-center text-muted mt-4">No pools are currently occupied.</p></div>';
              }
              ?>
            </div>
          </div>
        </div>
      </div>
      <div class="tab-pane fade" id="maintainedPools" role="tabpanel">
        <div class="row">
            <?php
            $found_maintenance = false;
            if (isset($array_pool) && is_array($array_pool)) {
                foreach ($array_pool as $pool) {
                    if (isset($pool['status_pool']) && strtolower($pool['status_pool']) == 'maintenance') {
                        $found_maintenance = true;
                        $pool_id = htmlspecialchars($pool['id'] ?? '');
                        $pool_rate = htmlspecialchars($pool['hourly_rate'] ?? 'N/A');
                        $pool_num_display = htmlspecialchars($pool['id'] ?? 'Unknown');
            ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border-warning">
                                 <div class="card-header d-flex justify-content-between align-items-center pb-0">
                                    <h5 class="card-title mb-0">Pool Number <?php echo $pool_num_display; ?></h5>
                                    <!-- Dropdown -->
                                    <div class="dropdown">
                                        <button class="btn p-0" type="button" id="poolActionDropdownMaint<?php echo $pool_id; ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="bx bx-dots-vertical-rounded"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="poolActionDropdownMaint<?php echo $pool_id; ?>">
                                            <a class="dropdown-item" href="update_pool_status.php?pool_id=<?php echo $pool_id; ?>&new_status=available">
                                                 <i class="bx bx-check-circle me-1"></i> Make Available
                                             </a>
                                         </div>
                                     </div>
                                     <!-- /Dropdown -->
                                 </div>
                                <div class="card-body pt-2">
                                    <img class="img-fluid d-flex mx-auto my-4 rounded" style="width: 100%; max-width: 300px; height: 200px; object-fit: cover; filter: grayscale(80%);" src="assets/img/elements/pool.jpg" alt="Pool image (under maintenance)" />
                                    <h6 class="card-text fw-bold">Price: $<?php echo $pool_rate; ?> / hour</h6>
                                    <p class="card-text text-warning"><i class="bx bx-wrench me-1"></i>Currently under maintenance.</p>
                                </div>
                            </div>
                        </div>
            <?php
                    } 
                } 
            }

            if (!$found_maintenance) {
                echo "<div class='col-12'><p class='text-center text-muted mt-4'>No pools are currently under maintenance.</p></div>";
            }
            ?>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
  <!-- / Content -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Use event delegation on the body to catch form submissions
    document.body.addEventListener('submit', function(event) {
        // Check if the submitted form has the class 'select-pool-form'
        if (event.target.matches('.select-pool-form')) {
            event.preventDefault(); // Prevent the default form submission

            const form = event.target;
            const formData = new FormData(form);
            const poolId = formData.get('pool_id'); 
            const controlsContainer = document.getElementById('select-controls-' + poolId);
            const submitButton = form.querySelector('button[type="submit"]');

            // Optional: Disable button during request
            if(submitButton) submitButton.disabled = true;
            if(submitButton) submitButton.textContent = 'Adding...';

            fetch(form.action, { // form.action should be "ajax_select_pool.php"
                method: 'POST',
                body: formData 
            })
            .then(response => {
                // Check if the response is ok (status 200-299)
                if (!response.ok) {
                    // If not ok, try to parse as JSON for an error message, or throw a generic error
                    return response.json().then(errData => {
                        throw new Error(errData.message || 'Network response was not ok.');
                    }).catch(() => {
                        throw new Error('Network response was not ok and no error message available.');
                    });
                }
                return response.json(); // Parse the JSON response
            })
            .then(data => {
                if (data.success) {
                    // Update UI: replace form with 'Selected' button
                    if (controlsContainer) {
                        controlsContainer.innerHTML = '<button type="button" class="btn btn-secondary w-100" disabled>Selected</button>';
                    }
                    // Update the cart display (assuming you have a function or element for this)
                    // Example: updateCartDisplay(data.cart); 
                    // Might need another AJAX call to get updated cart HTML if complex
                    if (typeof updateCartSidebar === 'function') {
                         updateCartSidebar(); // Call function to refresh cart sidebar
                    }
                } else {
                    // Handle failure: Show error message, re-enable button
                    alert('Error: ' + data.message);
                    if(submitButton) submitButton.disabled = false;
                    if(submitButton) submitButton.textContent = 'Add to Order';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('An error occurred while selecting the pool: ' + error.message);
                // Re-enable button on error
                 if(submitButton) submitButton.disabled = false;
                 if(submitButton) submitButton.textContent = 'Add to Order';
            });
        }
    });

    // Optional: Function to update cart sidebar via AJAX (if needed)
    function updateCartSidebar() {
        fetch('ajax_get_cart.php') // Endpoint that returns cart HTML or data
            .then(response => response.text()) // Or .json() if returning data
            .then(html => {
                const cartElement = document.getElementById('cart-sidebar-content'); // Adjust ID if needed
                if (cartElement) {
                    cartElement.innerHTML = html;
                }
            })
            .catch(error => console.error('Error updating cart sidebar:', error));
    }

});
</script>

<?php include_once("footer.php"); ?> 