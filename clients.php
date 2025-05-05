<?php include('header.php'); ?>
<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="py-3 mb-0">Clients Management</h4>
  <a href="register_client.php" class="btn btn-primary">Register Client</a>
</div>

<!-- Add Search Form -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" action="clients.php">
      <div class="input-group">
        <input type="text" class="form-control" placeholder="Search by Name or Phone" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
        <button class="btn btn-outline-primary" type="submit">Search</button>
        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
          <a href="clients.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<!-- End Search Form -->

<!-- Client Status Navbar -->
<div class="card mb-4">
  <div class="card-body">
    <ul class="nav nav-tabs" id="clientStatusTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="vip-tab" data-bs-toggle="tab" data-bs-target="#vip" type="button" role="tab" aria-controls="vip" aria-selected="true">VIP Clients</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="regular-tab" data-bs-toggle="tab" data-bs-target="#regular" type="button" role="tab" aria-controls="regular" aria-selected="false">Regular Clients</button>
      </li>
    </ul>
    <div class="tab-content p-3" id="clientStatusTabsContent">
      <div class="tab-pane fade show active" id="vip" role="tabpanel" aria-labelledby="vip-tab">
        <?php
        // Query for VIP clients with unpaid pool orders
        if (isset($db)) {
            $vip_query = "SELECT c.id, c.full_name, c.phone_number, op.id as order_pool_id, op.created_at, op.pool_id, p.hourly_rate 
                          FROM client c 
                          JOIN order_pools op ON c.id = op.client_id 
                          JOIN pool p ON op.pool_id = p.id
                          WHERE c.status_client = 'VIP' 
                          AND op.Payment_status = 'unpaid' 
                          AND op.status = 'ordered'
                          ORDER BY op.created_at DESC";
            
            $vip_result = mysqli_query($db, $vip_query);
            
            if ($vip_result && mysqli_num_rows($vip_result) > 0) {
                echo '<div class="table-responsive"><table class="table table-hover">';
                echo '<thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Pool #</th><th>Ordered On</th><th>Rate</th><th>Actions</th></tr></thead>';
                echo '<tbody>';
                
                $vip_row_num = 1; // Initialize counter
                while ($row = mysqli_fetch_assoc($vip_result)) {
                    echo '<tr>';
                    echo '<td>' . $vip_row_num++ . '</td>'; // Add counter cell
                    echo '<td>' . htmlspecialchars($row['full_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['phone_number']) . '</td>';
                    echo '<td>Pool ' . htmlspecialchars($row['pool_id']) . '</td>';
                    echo '<td>' . date('d-m-Y H:i', strtotime($row['created_at'])) . '</td>';
                    echo '<td>$' . number_format($row['hourly_rate'], 2) . '/hr</td>';
                    echo '<td>';
                    echo '<div class="dropdown">';
                    echo '<button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">';
                    echo '<i class="icon-base bx bx-dots-vertical-rounded"></i>';
                    echo '</button>';
                    echo '<div class="dropdown-menu">';
                    echo '<a class="dropdown-item" href="billing.php?client_id=' . $row['id'] . '"><i class="icon-base bx bx-credit-card me-1"></i> Process Payment</a>';
                    echo '<a class="dropdown-item" href="order_pools.php?client_id=' . $row['id'] . '&client_status=VIP&change_order_pool_id=' . $row['order_pool_id'] . '"><i class="icon-base bx bx-transfer-alt me-1"></i> Change Pool</a>'; 
                    echo '<a class="dropdown-item" href="bar.php?client_id=' . $row['id'] . '&client_status=VIP&from=clients"><i class="icon-base bx bx-food-menu me-1"></i> Bar Order</a>'; 
                    echo '</div>';
                    echo '</div>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table></div>';
            } else {
                echo '<p class="text-muted">No VIP clients with unpaid pool orders found.</p>';
            }
        } else {
            echo '<p class="text-danger">Database connection not available.</p>';
        }
        ?>
      </div>
      <div class="tab-pane fade" id="regular" role="tabpanel" aria-labelledby="regular-tab">
        <?php
        // Query for Regular clients who paid but are between their ordered pool time
        if (isset($db)) {
            $regular_query = "SELECT c.id, c.full_name, c.phone_number, op.created_at, op.pool_id, p.hourly_rate, op.start_time, op.end_time 
                             FROM client c 
                             JOIN order_pools op ON c.id = op.client_id 
                             JOIN pool p ON op.pool_id = p.id
                             WHERE c.status_client = 'Regular' 
                             AND op.Payment_status = 'paid' 
                             AND NOW() <= op.end_time
                             AND op.start_time <= NOW()
                             ORDER BY op.end_time ASC";
            
            $regular_result = mysqli_query($db, $regular_query);
            
            if ($regular_result && mysqli_num_rows($regular_result) > 0) {
                echo '<div class="table-responsive"><table class="table table-hover">';
                echo '<thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Pool #</th><th>Start Time</th><th>End Time</th><th>Rate</th><th>Actions</th></tr></thead>';
                echo '<tbody>';
                
                $regular_row_num = 1; // Initialize counter
                while ($row = mysqli_fetch_assoc($regular_result)) {
                    $start_time = strtotime($row['start_time']);
                    $end_time = strtotime($row['end_time']);
                    
                    echo '<tr>';
                    echo '<td>' . $regular_row_num++ . '</td>'; // Add counter cell
                    echo '<td>' . htmlspecialchars($row['full_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['phone_number']) . '</td>';
                    echo '<td>Pool ' . htmlspecialchars($row['pool_id']) . '</td>';
                    echo '<td>' . date('d-m-Y H:i', $start_time) . '</td>';
                    echo '<td>' . date('d-m-Y H:i', $end_time) . '</td>';
                    echo '<td>$' . number_format($row['hourly_rate'], 2) . '/hr</td>';
                    echo '<td>';
                    echo '<div class="dropdown">';
                    echo '<button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">';
                    echo '<i class="icon-base bx bx-dots-vertical-rounded"></i>';
                    echo '</button>';
                    echo '<div class="dropdown-menu">';
                    echo '<a class="dropdown-item" href="bar.php?client_id=' . $row['id'] . '&client_status=Regular&from=clients"><i class="icon-base bx bx-food-menu me-1"></i> Bar Order</a>'; 
                    echo '</div>';
                    echo '</div>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table></div>';
            } else {
                echo '<p class="text-muted">No Regular clients currently using pools found.</p>';
            }
        } else {
            echo '<p class="text-danger">Database connection not available.</p>';
        }
        ?>
      </div>
    </div>
  </div>
</div>
<!-- End Client Status Navbar -->

<div class="card">
                <h5 class="card-header">Clients List</h5>
                <div class="table-responsive text-nowrap">
                  <?php
                  // --- Database Query Approach for Search, Sort, Pagination ---
                  
                  // --- Parameters ---
                  $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
                  $sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'last_visit'; // Default sort: last_visit
                  $sort_order = isset($_GET['order']) && strtolower($_GET['order']) == 'asc' ? 'asc' : 'desc'; // Default sort order
                  $clients_per_page = 10;
                  $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
                  if ($current_page < 1) $current_page = 1;

                  // --- Validate Sort Column ---
                  // Add 'last_visit' to valid columns
                  $valid_sort_columns = ['full_name', 'phone_number', 'last_visit', 'status_client', 'created_at'];
                  if (!in_array($sort_column, $valid_sort_columns)) {
                      $sort_column = 'last_visit'; // Reset to default if invalid
                  }
                  
                  // --- Build SQL Query ---
                  $base_query = "FROM client c LEFT JOIN order_pools op ON c.id = op.client_id WHERE c.status = '1'"; // Assuming status 1 is active
                  $count_query = "SELECT COUNT(DISTINCT c.id) as total " . $base_query;
                  $select_query = "SELECT c.id, c.full_name, c.phone_number, c.created_at, c.status_client, MAX(op.end_time) as last_visit " . $base_query;
                  
                  $params = [];
                  $types = '';

                  // Add search conditions
                  if (!empty($search_term)) {
                      $search_clause = " AND (c.full_name LIKE ? OR c.phone_number LIKE ?)";
                      $select_query .= $search_clause;
                      $count_query .= $search_clause; 
                      $like_term = '%' . $search_term . '%';
                      $params[] = &$like_term;
                      $params[] = &$like_term;
                      $types .= 'ss';
                  }

                  // Add GROUP BY
                  $select_query .= " GROUP BY c.id, c.full_name, c.phone_number, c.created_at, c.status_client"; 
                  
                  // Add ORDER BY
                  // Special handling for last_visit (which is an aggregate)
                  $order_by_sql = $sort_column;
                  if ($sort_column === 'last_visit') {
                     // Use COALESCE to handle NULLs - treat NULL as very old/very new depending on sort order
                     $order_by_sql = "MAX(op.end_time)"; 
                     // Add NULLS LAST/FIRST depending on order if needed by DB (MySQL handles NULLS LAST on DESC by default)
                     // if ($sort_order === 'asc') $order_by_sql .= " NULLS FIRST"; 
                     // else $order_by_sql .= " NULLS LAST";
                  }
                  $select_query .= " ORDER BY " . $order_by_sql . " " . ($sort_order === 'asc' ? 'ASC' : 'DESC');
                  
                  // --- Get Total Count (for pagination) ---
                  $total_clients = 0;
                  if (isset($db)) {
                      $stmt_count = mysqli_prepare($db, $count_query);
                      if ($stmt_count) {
                          if (!empty($params)) {
                             mysqli_stmt_bind_param($stmt_count, $types, ...$params);
                          }
                          if (mysqli_stmt_execute($stmt_count)) {
                              $result_count = mysqli_stmt_get_result($stmt_count);
                              if ($row_count = mysqli_fetch_assoc($result_count)) {
                                  $total_clients = (int)$row_count['total'];
                              }
                          }
                          mysqli_stmt_close($stmt_count);
                      } else {
                           error_log("Error preparing count query: " . mysqli_error($db));
                      }
                  }
                  
                  $total_pages = ceil($total_clients / $clients_per_page);
                  if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
                  $offset = ($current_page - 1) * $clients_per_page;
                  
                  // --- Add LIMIT for Pagination ---
                  $select_query .= " LIMIT ?, ?";
                  $params[] = &$offset;
                  $params[] = &$clients_per_page;
                  $types .= 'ii';
                  
                  // --- Fetch Clients for Current Page ---
                  $clients_on_page = [];
                  if (isset($db) && $total_clients > 0) {
                       $stmt_select = mysqli_prepare($db, $select_query);
                       if ($stmt_select) {
                           mysqli_stmt_bind_param($stmt_select, $types, ...$params);
                           if (mysqli_stmt_execute($stmt_select)) {
                               $result_select = mysqli_stmt_get_result($stmt_select);
                               while ($row = mysqli_fetch_assoc($result_select)) {
                                   $clients_on_page[] = $row;
                               }
                           }
                            mysqli_stmt_close($stmt_select);
                       } else {
                           error_log("Error preparing select query: " . mysqli_error($db));
                           echo '<tr><td colspan="8" class="text-center text-danger">Error fetching client data.</td></tr>';
                       }
                  } 
                  // --- End Database Query Approach ---
                  ?>
                  <table class="table">
                    <thead>
                      <tr>
                        <th>#</th>
                        <?php
                        // Helper function to generate sort links
                        function generateSortLink($column, $display_text, $current_sort, $current_order, $current_search) {
                            $order = ($current_sort == $column && $current_order == 'asc') ? 'desc' : 'asc';
                            $arrow = '';
                            if ($current_sort == $column) {
                                $arrow = ($current_order == 'asc') ? ' <i class="bx bx-chevron-up"></i>' : ' <i class="bx bx-chevron-down"></i>';
                            }
                            $search_param = $current_search ? '&search=' . urlencode($current_search) : '';
                            return "<a href='?sort={$column}&order={$order}{$search_param}'>{$display_text}{$arrow}</a>";
                        }
                        $current_search_term = isset($_GET['search']) ? $_GET['search'] : '';
                        ?>
                        <th><?php echo generateSortLink('full_name', 'Name', $sort_column, $sort_order, $current_search_term); ?></th>
                        <th><?php echo generateSortLink('phone_number', 'Phone number', $sort_column, $sort_order, $current_search_term); ?></th>
                        <th><?php echo generateSortLink('last_visit', 'Last Visit', $sort_column, $sort_order, $current_search_term); ?></th>
                        <th><?php echo generateSortLink('status_client', 'Client Status', $sort_column, $sort_order, $current_search_term); ?></th>
                        <th>Order Actions</th>
                        <th>Bar Order</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                   
                    <tbody>
                     <?php 
                     $row_number = $offset + 1; // Initialize row number based on offset
                     if (empty($clients_on_page)) {
                         echo '<tr><td colspan="7" class="text-center">No clients found.</td></tr>';
                     } else {
                         foreach ($clients_on_page as $client) { 
                     ?>
                      <tr>
                        <td><?php echo $row_number++; ?></td>
                        <td><?php echo $client['full_name']; ?></td>
                        <td><?php echo $client['phone_number']; ?></td>
                       
                        <td><?php 
                             if (!empty($client['last_visit'])) {
                                 echo date('d-m-Y H:i', strtotime($client['last_visit'])); 
                             } else {
                                 // Show created_at date if no last_visit is available
                                 echo date('d-m-Y H:i', strtotime($client['created_at'])) . ' <em class="text-muted">(Registered)</em>'; 
                             }
                         ?></td>
                        <td><span class="badge <?php echo ($client['status_client'] == 'VIP') ? 'bg-label-success' : 'bg-label-info'; ?> me-1"><?php echo ($client['status_client'] == 'VIP') ? 'VIP Client' : 'Regular Client'; ?></span></td>
                   
                        <td>
                            <a href="order_pools.php?client_id=<?php echo $client['id']; ?>&client_status=<?php echo urlencode($client['status_client']); ?>" class="btn btn-sm btn-primary">New Orders</a>
                        </td>
                        <td>
                           <a href="bar.php?client_id=<?php echo $client['id']; ?>&client_status=<?php echo urlencode($client['status_client']); ?>&from=clients" class="btn btn-sm btn-success">
                                <i class="bx bx-plus me-1"></i> Add 
                           </a>
                        </td>
                        <td>
                          <div class="dropdown">
                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                              <i class="icon-base bx bx-dots-vertical-rounded"></i>
                            </button>
                            <div class="dropdown-menu">
                              <a class="dropdown-item" href="view_client_details.php?id=<?php echo $client['id']; ?>"
                                ><i class="icon-base bx bx-show me-1"></i> View Details</a
                              >
                              <a class="dropdown-item" href="edit_client.php?id=<?php echo $client['id']; ?>"
                                ><i class="icon-base bx bx-edit-alt me-1"></i> Edit</a
                              >
                              <?php if ($client['status_client'] == 'VIP'): ?>
                                <a class="dropdown-item" href="update_client_status.php?id=<?php echo $client['id']; ?>&status=Regular"
                                  ><i class="icon-base bx bx-user me-1"></i> Make Regular</a
                                >
                              <?php else: ?>
                                <a class="dropdown-item" href="update_client_status.php?id=<?php echo $client['id']; ?>&status=VIP"
                                  ><i class="icon-base bx bx-star me-1"></i> Make VIP</a
                                >
                              <?php endif; ?>
                              <a class="dropdown-item" href="update_client_status.php?id=<?php echo $client['id']; ?>" onclick="return confirm('Are you sure you want to delete this client?');"
                                ><i class="icon-base bx bx-trash me-1"></i> Delete</a
                              >
                            </div>
                          </div>
                        </td>
                      </tr>
                       <?php }
                       } // End else
                       ?>
                      </tbody>
                  </table>
                  <?php if ($total_pages > 1): ?>
                  <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-3">
                      <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['sort']) ? '&sort='.urlencode($_GET['sort']).'&order='.urlencode($_GET['order']) : ''; ?>" aria-label="Previous">
                          <span aria-hidden="true">&laquo;</span>
                        </a>
                      </li>
                      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                          <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['sort']) ? '&sort='.urlencode($_GET['sort']).'&order='.urlencode($_GET['order']) : ''; ?>"><?php echo $i; ?></a>
                        </li>
                      <?php endfor; ?>
                      <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['sort']) ? '&sort='.urlencode($_GET['sort']).'&order='.urlencode($_GET['order']) : ''; ?>" aria-label="Next">
                          <span aria-hidden="true">&raquo;</span>
                        </a>
                      </li>
                    </ul>
                  </nav>
                  <?php endif; ?>
                </div>
              </div>



</div>
</div>
<?php include('footer.php'); ?>
