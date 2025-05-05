<?php include("header.php"); ?>

<?php 
// --- Fetch Booking Statistics ---
$pending_bookings_count = 0;
$confirmed_future_bookings_count = 0;
$completed_today_bookings_count = 0;

if (isset($db)) {
    $stats_query = "
        SELECT
            COUNT(CASE WHEN status = 'pending' THEN 1 ELSE NULL END) as pending_count,
            COUNT(CASE WHEN status = 'confirmed' AND booking_date >= CURDATE() THEN 1 ELSE NULL END) as confirmed_future_count,
            COUNT(CASE WHEN status = 'completed' AND DATE(created_at) = CURDATE() THEN 1 ELSE NULL END) as completed_today_count -- Assuming completion is marked by status change, check against created_at date
        FROM booking;
    ";
    
    $stats_result = mysqli_query($db, $stats_query);
    if ($stats_result && $row = mysqli_fetch_assoc($stats_result)) {
        $pending_bookings_count = $row['pending_count'] ?? 0;
        $confirmed_future_bookings_count = $row['confirmed_future_count'] ?? 0;
        $completed_today_bookings_count = $row['completed_today_count'] ?? 0;
    } else {
        error_log("Error fetching booking statistics: " . mysqli_error($db));
        // Keep counts at 0 if query fails
    }
} else {
    error_log("Database connection not available for booking statistics.");
}
// --- End Fetch Booking Statistics ---

?>

   
        <div class="layout-page">
    
           <h2 class="container-xxl flex-grow-1 container-p-y fw-bold">Dashboard</h2>

          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Content -->
            <div class="container-xxl flex-grow-1 container-p-y">
              <div class="row">
           
                <!-- Top Row Statistics -->
                <div class="col-lg-3 col-md-6 col-6 mb-4">
                  <div class="card">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <img src="assets/img/icons/unicons/swimming-pool.png" alt="Active Sessions" class="rounded" />
                        </div>
                         <?php // Optional dropdown if needed later ?>
                      </div>
                      <span class="d-block mb-1">Active Pool Sessions</span>
                      <h3 class="card-title mb-2"><?php echo $active_sessions_count; ?></h3>
                      <small class="text-info fw-medium">Currently Occupied</small>
                    </div>
                  </div>
                </div>

                 <div class="col-lg-3 col-md-6 col-6 mb-4">
                  <div class="card">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                           <img src="assets/img/icons/unicons/available.png" alt="Available Pools" class="rounded" />
                        </div>
                      </div>
                      <span class="d-block mb-1">Available Pools</span>
                      <h3 class="card-title mb-2"><?php echo $aviable_pools; ?></h3>
                       <small class="text-success fw-medium">Ready for Ordering</small>
                    </div>
                  </div>
                </div>

                 <div class="col-lg-3 col-md-6 col-6 mb-4">
                  <div class="card">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                           <img src="assets/img/icons/unicons/wallet-info.png" alt="Total Revenue" class="rounded" />
                        </div>
                      </div>
                      <span class="d-block mb-1">Total Billing</span>
                      <h3 class="card-title mb-2">$<?php echo number_format($total_revenue, 2); ?></h3>
                       <small class="text-primary fw-medium">All Time Paid</small>
                    </div>
                  </div>
                </div>

                 <div class="col-lg-3 col-md-6 col-6 mb-4">
                  <div class="card">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                           <img src="assets/img/icons/unicons/client.png" alt="Total Clients" class="rounded" />
                        </div>
                      </div>
                      <span class="d-block mb-1">Total Active Clients</span>
                      <h3 class="card-title mb-2"><?php echo $client_count; ?></h3>
                      <small class="text-secondary fw-medium">Registered & Active</small>
                    </div>
                  </div>
                </div>
              </div>
             
               <div class="row mb-4">
                  <!-- Pending Bookings -->
                <div class="col-lg-4 col-md-4 col-6">
                  <div class="card">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <img src="assets/img/icons/unicons/clock.png" alt="Pending Bookings" class="rounded" />
                        </div>
                      </div>
                      <span class="d-block mb-1">Pending Bookings</span>
                      <h3 class="card-title mb-2"><?php echo $pending_bookings_count; ?></h3>
                      <small class="text-warning fw-medium"><i class="bx bx-time-five"></i> Awaiting Confirmation</small>
                    </div>
                  </div>
                </div>
                 <!-- Confirmed Bookings (Future/Today) -->
                <div class="col-lg-4 col-md-4 col-6">
                  <div class="card">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <img src="assets/img/icons/unicons/right.png" alt="Confirmed Bookings" class="rounded" />
                        </div>
                      </div>
                      <span class="d-block mb-1">Confirmed Bookings</span>
                      <h3 class="card-title mb-2"><?php echo $confirmed_future_bookings_count; ?></h3>
                      <small class="text-success fw-medium"><i class="bx bx-calendar-event"></i> Today & Upcoming</small>
                    </div>
                  </div>
                </div>
                 <!-- Completed Bookings Today -->
                 <div class="col-lg-4 col-md-4 col-12">
                  <div class="card">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <img src="assets/img/icons/unicons/checked.png" alt="Completed Today" class="rounded" />
                        </div>
                      </div>
                      <span class="d-block mb-1">Bookings Completed Today</span>
                      <h3 class="card-title mb-2"><?php echo $completed_today_bookings_count; ?></h3>
                       <small class="text-secondary fw-medium"><i class="bx bx-calendar-check"></i> Marked as Completed</small>
                    </div>
                  </div>
                </div>
              </div>
               
                <div class="row">
                    <!-- VIP Clients -->
                    <div class="col-md-6 mb-4">
                      <div class="card">
                        <div class="card-body">
                          <div class="d-flex justify-content-between flex-sm-row flex-column gap-3">
                            <div class="d-flex flex-sm-column flex-row align-items-start justify-content-between">
                              <div class="card-title">
                                <h5 class="text-nowrap mb-2">VIP Clients</h5>
                                <span class="badge bg-label-success rounded-pill">Status: VIP</span>
                              </div>
                              <div class="mt-sm-auto">
                                 <h3 class="mb-0"><?php echo $vip_client_count; ?></h3>
                              </div>
                            </div>
                             <div id="vipClientChart"></div> <!-- Placeholder for potential chart -->
                          </div>
                        </div>
                      </div>
                    </div>
                     <!-- Regular Clients -->
                    <div class="col-md-6 mb-4">
                      <div class="card">
                        <div class="card-body">
                          <div class="d-flex justify-content-between flex-sm-row flex-column gap-3">
                            <div class="d-flex flex-sm-column flex-row align-items-start justify-content-between">
                              <div class="card-title">
                                <h5 class="text-nowrap mb-2">Regular Clients</h5>
                                <span class="badge bg-label-info rounded-pill">Status: Regular</span>
                              </div>
                              <div class="mt-sm-auto">
                                 <h3 class="mb-0"><?php echo $regular_client_count; ?></h3>
                              </div>
                            </div>
                             <div id="regularClientChart"></div> <!-- Placeholder for potential chart -->
                          </div>
                        </div>
                      </div>
                    </div>
                </div>
                <div class="row">
                <!-- Bar Order Statistics -->
                <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-6"> 
                  <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">
                      <div class="card-title mb-0">
                        <h5 class="mb-1 me-2">Bar Order Statistics</h5>
                        <p class="card-subtitle">$<?php echo number_format($bar_order_revenue, 2); ?> Total Sales</p> 
                      </div>
                      <div class="dropdown">
                        <button
                          class="btn text-body-secondary p-0"
                          type="button"
                          id="barOrderStatistics"
                          data-bs-toggle="dropdown"
                          aria-haspopup="true"
                          aria-expanded="false">
                          <i class="icon-base bx bx-dots-vertical-rounded icon-lg"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="barOrderStatistics">
                          <a class="dropdown-item" href="javascript:location.reload();">Refresh</a>
                          </div>
                      </div>
                    </div>
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-center mb-6">
                        <div class="d-flex flex-column align-items-center gap-1">
                          <h3 class="mb-1"><?php echo number_format($bar_order_count); ?></h3>
                          <small>Total Items Sold</small>
                        </div>
                        <div id="orderStatisticsChart"></div> 
                      </div>
                      <ul class="p-0 m-0">
                        <?php if (!empty($top_bar_items)): ?>
                          <?php 
                            $icons = ['bx-drink', 'bx-food-menu', 'bx-purchase-tag']; // Example icons 
                            $colors = ['bg-label-primary', 'bg-label-success', 'bg-label-info'];
                            $i = 0;
                          ?>
                          <?php foreach ($top_bar_items as $item): ?>
                            <li class="d-flex align-items-center mb-5">
                              <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded <?php echo $colors[$i % count($colors)]; ?>">
                                  <i class="icon-base bx <?php echo $icons[$i % count($icons)]; ?>"></i>
                                </span>
                              </div>
                              <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                  <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                  <small>Top Seller</small>
                                </div>
                                <div class="user-progress">
                                  <h6 class="mb-0"><?php echo number_format($item['total_quantity']); ?></h6>
                                </div>
                              </div>
                            </li>
                            <?php $i++; ?>
                          <?php endforeach; ?>
                           <?php // Fill remaining slots if less than 3 top items 
                             while ($i < 3): ?>
                              <li class="d-flex align-items-center mb-5 text-muted">
                                  <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded bg-label-secondary"><i class="icon-base bx bx-question-mark"></i></span>
                                  </div>
                                  <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                      <div class="me-2">
                                          <h6 class="mb-0">--</h6>
                                          <small>Not Available</small>
                                      </div>
                                      <div class="user-progress">
                                          <h6 class="mb-0">--</h6>
                                      </div>
                                  </div>
                              </li>
                           <?php $i++; endwhile; ?>
                        <?php else: ?>
                           <li class="d-flex align-items-center">
                                <p class="text-muted">No bar order data available yet.</p>
                           </li>
                        <?php endif; ?>
                      </ul>
                    </div>
                  </div>
                </div>
                <!--/ Bar Order Statistics -->



                <!-- Transactions -->
                <div class="col-md-6 col-lg-4 order-2 mb-6">
                  <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                      <h5 class="card-title m-0 me-2">Recent Billing Activity</h5>
                      <div class="dropdown">
                        <button
                          class="btn text-body-secondary p-0"
                          type="button"
                          id="billingActivityDropdown"
                          data-bs-toggle="dropdown"
                          aria-haspopup="true"
                          aria-expanded="false">
                          <i class="icon-base bx bx-dots-vertical-rounded icon-lg"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="billingActivityDropdown">
                          <a class="dropdown-item" href="javascript:location.reload();">Refresh</a>
                        </div>
                      </div>
                    </div>
                    <div class="card-body pt-4">
                      <ul class="p-0 m-0">
                         <?php if (!empty($recent_billing_records)): ?>
                            <?php foreach ($recent_billing_records as $record): ?>
                                <?php 
                                    // Determine icon based on bill type
                                    $icon_class = 'bx-receipt'; // Default
                                    $icon_color = 'bg-label-secondary';
                                    if (strpos($record['bill_type_description'], 'Pool') !== false) {
                                        $icon_class = 'bx-swim';
                                        $icon_color = 'bg-label-primary';
                                    } elseif (strpos($record['bill_type_description'], 'Bar') !== false) {
                                        $icon_class = 'bx-food-menu';
                                        $icon_color = 'bg-label-info';
                                    } elseif (strpos($record['bill_type_description'], 'Penalty') !== false) {
                                        $icon_class = 'bx-error-alt';
                                        $icon_color = 'bg-label-danger';
                                    }
                                    // Format date
                                    $record_date = new DateTime($record['created_at']);
                                ?>
                                <li class="d-flex align-items-center mb-4"> 
                                  <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded <?php echo $icon_color; ?>"><i class="icon-base bx <?php echo $icon_class; ?>"></i></span>
                                  </div>
                                  <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="me-2">
                                      <small class="d-block"><?php echo htmlspecialchars($record['full_name']); ?></small>
                                      <h6 class="fw-normal mb-0 text-body-secondary"><?php echo htmlspecialchars($record['bill_type_description']); ?></h6>
                                    </div>
                                    <div class="user-progress d-flex align-items-center gap-2">
                                      <h6 class="fw-normal mb-0">$<?php echo number_format($record['total_bill'], 2); ?></h6>
                                      <small class="text-muted ms-1" style="font-size: 0.75rem;"><?php echo $record_date->format('d-m-Y'); ?></small> 
                                    </div>
                                  </div>
                                </li>
                            <?php endforeach; ?>
                         <?php else: ?>
                            <li class="d-flex align-items-center">
                                <p class="text-muted">No recent billing activity found.</p>
                            </li>
                         <?php endif; ?>
                      </ul>
                    </div>
                  </div>
                </div>
                <!--/ Transactions -->

                <!-- Upcoming Bookings -->
                <div class="col-md-6 col-lg-4 order-3 mb-6"> <!-- Adjusted order class -->
                  <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                      <h5 class="card-title m-0 me-2">Upcoming Bookings</h5>
                      <div class="dropdown">
                        <button
                          class="btn text-body-secondary p-0"
                          type="button"
                          id="upcomingBookingsDropdown"
                          data-bs-toggle="dropdown"
                          aria-haspopup="true"
                          aria-expanded="false">
                          <i class="icon-base bx bx-dots-vertical-rounded icon-lg"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="upcomingBookingsDropdown">
                          <a class="dropdown-item" href="javascript:location.reload();">Refresh</a> 
                          <a class="dropdown-item" href="bookings.php">View All Bookings</a>
                        </div>
                      </div>
                    </div>
                    <div class="card-body pt-4">
                      <ul class="p-0 m-0">
                         <?php if (!empty($upcoming_bookings)): ?>
                            <?php 
                            $now = new DateTime(); // Current time for comparison
                            foreach ($upcoming_bookings as $booking):
                                $booking_datetime_str = $booking['booking_date'] . ' ' . $booking['booking_time'];
                                try {
                                    $booking_dt = new DateTime($booking_datetime_str);
                                    $interval = $now->diff($booking_dt);
                                    
                                    // Format time difference or date/time
                                    $time_display = '';
                                    if ($booking_dt->format('Y-m-d') == $now->format('Y-m-d')) { // Today
                                        if ($interval->h > 0) {
                                            $time_display = 'in ' . $interval->format('%h hr %i min');
                                        } elseif ($interval->i > 0) {
                                             $time_display = 'in ' . $interval->format('%i min');
                                        } else {
                                            $time_display = 'Now';
                                        }
                                        $time_display .= ' (' . $booking_dt->format('g:i A') . ')';
                                    } elseif ($booking_dt->format('Y-m-d') == $now->modify('+1 day')->format('Y-m-d')) { // Tomorrow
                                        $time_display = 'Tomorrow at ' . $booking_dt->format('g:i A');
                                        $now->modify('-1 day'); // Reset $now
                                    } else { // Further in the future
                                        $time_display = $booking_dt->format('M j, g:i A');
                                        $time_display = $booking_dt->format('d-m-Y, g:i A'); // Changed format
                                    }
                                } catch (Exception $e) {
                                    $booking_dt = null;
                                    $time_display = 'Invalid Date'; 
                                    error_log("Error parsing date for upcoming booking ID {$booking['id']}: {$booking_datetime_str}");
                                }

                                // Determine icon/color based on status
                                $icon_class = ($booking['status'] == 'confirmed') ? 'bx-calendar-check' : 'bx-calendar-exclamation';
                                $icon_color = ($booking['status'] == 'confirmed') ? 'bg-label-success' : 'bg-label-warning';
                            ?>
                                <li class="d-flex align-items-center mb-4"> 
                                  <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded <?php echo $icon_color; ?>"><i class="icon-base bx <?php echo $icon_class; ?>"></i></span>
                                  </div>
                                  <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="me-2">
                                      <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="text-body d-block"> Pool <?php echo htmlspecialchars($booking['pool_id']); ?></a>
                                      <small class="d-block text-muted"><?php echo htmlspecialchars($booking['client_name']); ?></small>
                                    </div>
                                    <div class="user-progress d-flex align-items-center gap-1">
                                      <small class="text-muted" style="font-size: 0.8rem;"><?php echo $time_display; ?></small> 
                                    </div>
                                  </div>
                                </li>
                            <?php endforeach; ?>
                         <?php else: ?>
                            <li class="d-flex align-items-center">
                                <p class="text-muted">No upcoming bookings found.</p>
                            </li>
                         <?php endif; ?>
                      </ul>
                    </div>
                  </div>
                </div>
                <!--/ Upcoming Bookings -->
              </div>
           
               
                <!-- Weekly Revenue -->
                 
                <div class="row">
                    <div class="col-md-6 mb-4">
                      <div class="card">
                        <div class="card-body">
                           <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                     <div class="avatar flex-shrink-0 me-3">
                                        <span class="avatar-initial rounded bg-label-primary"><i class='bx bx-calendar-dollar bx-sm'></i></span>
                                    </div>
                                    <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                        <div class="me-2">
                                            <h6 class="mb-0">Weekly Revenue</h6>
                                            <small class="text-muted">Last 7 Days</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="user-progress">
                                    <h5 class="mb-0">$<?php echo number_format($weekly_revenue, 2); ?></h5>
                                </div>
                           </div>
                        </div>
                      </div>
                    </div>
                    <!-- Monthly Revenue Placeholder -->
                     <div class="col-md-6 mb-4">
                      <div class="card">
                        <div class="card-body">
                           <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                     <div class="avatar flex-shrink-0 me-3">
                                        <span class="avatar-initial rounded bg-label-info"><i class='bx bx-calendar-check bx-sm'></i></span>
                                    </div>
                                    <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                        <div class="me-2">
                                            <h6 class="mb-0">Monthly Revenue</h6>
                                            <small class="text-muted">Placeholder</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="user-progress">
                                    <h5 class="mb-0">$<?php echo number_format($monthly_revenue, 2); ?></h5>
                                </div>
                           </div>
                        </div>
                      </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-12 mb-4">
                      <div class="card h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                           <h5 class="card-title m-0 me-2">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                          <ul class="list-group list-group-flush">
                               <a href="register_client.php" class="list-group-item list-group-item-action d-flex align-items-center mb-2">
                                <i class="icon-base bx bx-user-plus me-2"></i>
                                Create New Client
                              </a>
                             <a href="clients.php" class="list-group-item list-group-item-action d-flex align-items-center mb-2">
                                <i class="icon-base bx bx-list-ul me-2"></i>
                                View/Manage Clients
                              </a>
                              <a href="bookings.php" class="list-group-item list-group-item-action d-flex align-items-center mb-2">
                                <i class="icon-base bx bx-swim me-2"></i>
                                View/Manage Bookings
                              </a>
                              <a href="add_bar_item.php" class="list-group-item list-group-item-action d-flex align-items-center mb-2">
                                <i class="icon-base bx bx-food-menu me-2"></i>
                                Add Bar/Menu Item
                              </a>
                              <a href="penalty.php" class="list-group-item list-group-item-action d-flex align-items-center mb-2">
                                <i class="icon-base bx bx-error-alt me-2"></i>
                                Add Penalty Charge
                              </a>
                            </ul>
                        </div>
                      </div>
                    </div>
                

             
                </div> 
              </div>
              </div>
            </div>
            <!-- / Content -->

<script>
  // Pass PHP data for Bar Order Stats chart to JavaScript
  const barOrderStatsData = <?php echo json_encode($top_bar_items ?? []); ?>;
</script>

<?php include 'footer.php'; ?>

<script>
// Re-initialize Bar Order Statistics chart with dynamic data
document.addEventListener('DOMContentLoaded', function () {
  // Get theme colors (assuming config is globally available after theme scripts)
  let cardColor = config.colors.cardColor;
  let headingColor = config.colors.headingColor;
  let legendColor = config.colors.bodyColor;
  let fontFamily = config.fontFamily;
  
  // Prepare data from PHP
  const chartLabels = barOrderStatsData.map(item => item.name || 'Unknown'); // Use 'name' based on previous fix
  const chartSeries = barOrderStatsData.map(item => parseInt(item.total_quantity || 0));
  const totalItems = chartSeries.reduce((acc, val) => acc + val, 0);

  const chartOrderStatisticsEl = document.querySelector('#orderStatisticsChart');
  if (chartOrderStatisticsEl && typeof ApexCharts !== 'undefined' && barOrderStatsData.length > 0) {
    const orderChartConfig = {
      chart: {
        height: 165,
        width: 136,
        type: 'donut',
        offsetX: 15,
        fontFamily: fontFamily
      },
      labels: chartLabels,
      series: chartSeries,
      colors: [config.colors.primary, config.colors.success, config.colors.info, config.colors.secondary, config.colors.warning].slice(0, chartLabels.length),
      stroke: {
        width: 5,
        colors: [cardColor]
      },
      dataLabels: {
        enabled: false,
        formatter: function (val, opt) {
          return parseInt(val) + '%'; // Percentage calculation might need adjustment
        }
      },
      legend: {
        show: false
      },
      grid: {
        padding: {
          top: 0,
          bottom: 0,
          right: 15
        }
      },
      states: {
        hover: { filter: { type: 'none' } },
        active: { filter: { type: 'none' } }
      },
      plotOptions: {
        pie: {
          donut: {
            size: '75%',
            labels: {
              show: true,
              value: {
                fontSize: '1.125rem',
                fontFamily: fontFamily,
                fontWeight: 500,
                color: headingColor,
                offsetY: -17,
                formatter: function (val) {
                   // Calculate percentage based on totalItems
                   return totalItems > 0 ? Math.round((parseInt(val) / totalItems) * 100) + '%' : '0%';
                }
              },
              name: {
                offsetY: 17,
                fontFamily: fontFamily
              },
              total: {
                show: true,
                fontSize: '13px',
                color: legendColor,
                label: 'Top Items', // Changed label
                formatter: function (w) {
                  // Display total count
                  return totalItems;
                }
              }
            }
          }
        }
      }
    };

    // Clear any existing chart (if the original script ran)
    chartOrderStatisticsEl.innerHTML = ''; 
    
    // Render the new chart
    const statisticsChart = new ApexCharts(chartOrderStatisticsEl, orderChartConfig);
    statisticsChart.render();

  } else if (chartOrderStatisticsEl) {
    // Display a message if no data
    chartOrderStatisticsEl.innerHTML = '<p class="text-center text-muted mt-4">No bar order data for chart.</p>';
  }
});
</script>
    