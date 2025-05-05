<?php 
include("db.php");

// Update expired pool bookings
if (isset($db)) {
    $update_expired_pools = "UPDATE pool p 
                            JOIN order_pools op ON p.id = op.pool_id 
                            SET p.status_pool = 'available' 
                            WHERE p.status_pool = 'ordered' 
                            AND op.end_time < NOW()";
    mysqli_query($db, $update_expired_pools);
}

$pool_zapros = "SELECT *, count(id) as count_pool_id FROM `pool`";
$pool_result = mysqli_query($db, $pool_zapros);
while ($row = mysqli_fetch_array($pool_result)) {
    $pool_id = $row['id'];
    $pool_count_pool_id = $row['count_pool_id'];
    $pool_num = $row['pool_num'];
    $pool_hourly_rate = $row['hourly_rate'];
    $pool_status = $row['status_pool'];
    
}
$pool_zapros_1= "SELECT * FROM `pool`";
$pool_result_1 = mysqli_query($db, $pool_zapros_1);


$array_pool = [];
while($row = mysqli_fetch_assoc($pool_result_1)){
  $array_pool[] = $row; 
}


$order_zapros = "SELECT * FROM `order_pools` WHERE status = 'ordered' OR status = 'active'";
$order_result = mysqli_query($db, $order_zapros);

$array_booking = [];
while ($row = mysqli_fetch_assoc($order_result)) {
    $array_booking[] = $row;
}

$order_count_zapros = "SELECT count(DISTINCT pool_id) as count_ordered FROM `order_pools` 
                       WHERE (status = 'ordered' OR status = 'active') 
                       AND NOW() < end_time";
$order_count_result = mysqli_query($db, $order_count_zapros);
$order_count_row = mysqli_fetch_assoc($order_count_result);
$order_count_pool_id = $order_count_row ? $order_count_row['count_ordered'] : 0;

$client_zapros = "SELECT *, count(id) as count_client FROM `client` WHERE status='1'";
$client_result = mysqli_query($db, $client_zapros);
while ($row = mysqli_fetch_array($client_result)) {
    $array_client[] = $row;
    $client_count = $row['count_client'];
}
$aviable_pools = $pool_count_pool_id - $order_count_pool_id;

// --- Fetch Total Revenue --- 
$total_revenue = 0;
$revenue_query = "SELECT SUM(total_bill) as total_revenue FROM billing_records WHERE status = 1"; // Assuming status 1 = paid
$revenue_result = mysqli_query($db, $revenue_query);
if($revenue_result && $row = mysqli_fetch_assoc($revenue_result)) {
    $total_revenue = $row['total_revenue'] ?? 0;
}

// --- Fetch VIP/Regular Client Counts --- 
$vip_client_count = 0;
$regular_client_count = 0;
$client_status_query = "SELECT status_client, COUNT(id) as count FROM client WHERE status = 1 GROUP BY status_client";
$client_status_result = mysqli_query($db, $client_status_query);
if ($client_status_result) {
    while ($row = mysqli_fetch_assoc($client_status_result)) {
        if ($row['status_client'] === 'VIP') {
            $vip_client_count = $row['count'];
        } elseif ($row['status_client'] === 'Regular') {
            $regular_client_count = $row['count'];
        }
    }
}

// --- Fetch Currently Active Pool Sessions --- 
$active_sessions_count = 0;
$active_sessions_query = "SELECT COUNT(id) as active_count FROM order_pools 
                          WHERE status = 'ordered' AND Payment_status = 'paid' AND NOW() BETWEEN start_time AND end_time";
$active_sessions_result = mysqli_query($db, $active_sessions_query);
if ($active_sessions_result && $row = mysqli_fetch_assoc($active_sessions_result)) {
    $active_sessions_count = $row['active_count'] ?? 0;
}

// --- Fetch Weekly Revenue ---
$weekly_revenue = 0;
$weekly_revenue_query = "SELECT SUM(total_bill) as weekly_revenue 
                         FROM billing_records 
                         WHERE status = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$weekly_revenue_result = mysqli_query($db, $weekly_revenue_query);
if ($weekly_revenue_result && $row = mysqli_fetch_assoc($weekly_revenue_result)) {
    $weekly_revenue = $row['weekly_revenue'] ?? 0;
}

// --- Fetch Monthly Revenue ---
$monthly_revenue = 0;
$monthly_revenue_query = "SELECT SUM(total_bill) as monthly_revenue 
                          FROM billing_records 
                          WHERE status = 1 AND YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())";
$monthly_revenue_result = mysqli_query($db, $monthly_revenue_query);
if ($monthly_revenue_result && $row = mysqli_fetch_assoc($monthly_revenue_result)) {
    $monthly_revenue = $row['monthly_revenue'] ?? 0;
}

// --- Fetch Monthly Revenue Data for Chart ---
$monthly_revenue_chart_data = ['labels' => [], 'series' => []];
$revenue_growth_percentage = 0;
$last_month_revenue = 0;
$previous_month_revenue = 0;

// Get revenue for the last 6 months
$monthly_rev_query = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') AS month_year, 
                        SUM(total_bill) AS monthly_total
                      FROM billing_records 
                      WHERE status = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                      GROUP BY month_year
                      ORDER BY month_year ASC";

$monthly_rev_result = mysqli_query($db, $monthly_rev_query);
$monthly_totals = [];
if ($monthly_rev_result) {
    while ($row = mysqli_fetch_assoc($monthly_rev_result)) {
        // Format month for label (e.g., 'Jan', 'Feb')
        $dateObj = DateTime::createFromFormat('!Y-m', $row['month_year']);
        $monthLabel = $dateObj->format('M'); 
        $monthly_revenue_chart_data['labels'][] = $monthLabel;
        $monthly_revenue_chart_data['series'][] = round($row['monthly_total'], 2);
        $monthly_totals[$row['month_year']] = round($row['monthly_total'], 2);
    }
}

// Fill any missing months in the last 6 with 0
$current_date = new DateTime();
for ($i = 5; $i >= 0; $i--) {
    $month_key = (new DateTime())->modify("-$i month")->format('Y-m');
    $month_label = (new DateTime())->modify("-$i month")->format('M');
    if (!in_array($month_label, $monthly_revenue_chart_data['labels'])) {
        // Insert missing month data in correct position
        array_splice($monthly_revenue_chart_data['labels'], 5 - $i, 0, [$month_label]);
        array_splice($monthly_revenue_chart_data['series'], 5 - $i, 0, [0]);
    }
    // Ensure we have the totals for the last two relevant months for growth calc
    if (!isset($monthly_totals[$month_key])) {
         $monthly_totals[$month_key] = 0;
    }
}

// Calculate growth percentage (last completed month vs previous one)
$last_completed_month_key = (new DateTime())->modify("-1 month")->format('Y-m');
$prev_to_last_month_key = (new DateTime())->modify("-2 month")->format('Y-m');

$last_month_revenue = $monthly_totals[$last_completed_month_key] ?? 0;
$previous_month_revenue = $monthly_totals[$prev_to_last_month_key] ?? 0;

if ($previous_month_revenue > 0) {
    $revenue_growth_percentage = round((($last_month_revenue - $previous_month_revenue) / $previous_month_revenue) * 100);
} elseif ($last_month_revenue > 0) {
    $revenue_growth_percentage = 100; // Growth is infinite/undefined if previous was 0, show 100%
}

// --- Fetch Bar Order Statistics ---
$bar_order_revenue = 0;
$bar_order_count = 0;
$top_bar_items = [];

// Fetch total revenue and count from paid bar orders
$bar_stats_query = "SELECT SUM(oi.quantity * oi.price_at_order) as total_revenue, SUM(oi.quantity) as total_items 
                    FROM order_items oi
                    JOIN billing_records br ON br.bar_items_order_id = oi.id 
                    WHERE br.status = 1"; // Assuming status 1 = paid
$bar_stats_result = mysqli_query($db, $bar_stats_query);
if ($bar_stats_result && $row = mysqli_fetch_assoc($bar_stats_result)) {
    $bar_order_revenue = $row['total_revenue'] ?? 0;
    $bar_order_count = $row['total_items'] ?? 0;
}

// Fetch top 3 selling bar items
$top_items_query = "SELECT bi.name, SUM(oi.quantity) as total_quantity
                    FROM order_items oi
                    JOIN bar_items bi ON oi.bar_item_id = bi.id
                    JOIN billing_records br ON br.bar_items_order_id = oi.id 
                    WHERE br.status = 1 -- Consider only paid items for popularity
                    GROUP BY bi.name
                    ORDER BY total_quantity DESC
                    LIMIT 3";
$top_items_result = mysqli_query($db, $top_items_query);
if ($top_items_result) {
    while ($row = mysqli_fetch_assoc($top_items_result)) {
        $top_bar_items[] = $row;
    }
}

// --- Fetch Recent Billing Records ---
$recent_billing_records = [];
$billing_limit = 5; // Number of records to fetch
$recent_billing_query = "SELECT 
                            br.id, 
                            br.created_at, 
                            br.total_bill, 
                            c.full_name, 
                            br.pool_order_id, 
                            br.bar_items_order_id, 
                            br.penalty_id
                        FROM billing_records br
                        JOIN client c ON br.client_id = c.id
                        WHERE br.status = 1 -- Assuming status 1 = paid
                        ORDER BY br.created_at DESC
                        LIMIT {$billing_limit}";
$recent_billing_result = mysqli_query($db, $recent_billing_query);
if ($recent_billing_result) {
    while ($row = mysqli_fetch_assoc($recent_billing_result)) {
        // Determine bill type
        $bill_type = [];
        if (!empty($row['pool_order_id'])) $bill_type[] = 'Pool';
        if (!empty($row['bar_items_order_id'])) $bill_type[] = 'Bar';
        if (!empty($row['penalty_id'])) $bill_type[] = 'Penalty';
        $row['bill_type_description'] = implode(', ', $bill_type);
        if (empty($row['bill_type_description'])) $row['bill_type_description'] = 'Misc'; // Fallback

        $recent_billing_records[] = $row;
    }
}

// --- Fetch Pool Orders Ending Soon (e.g., within 15 minutes) ---
$ending_soon_orders = [];
$ending_soon_minutes = 15; // Define the threshold
$ending_soon_query = "SELECT op.id as order_id, op.pool_id, op.end_time, c.id as client_id, c.full_name 
                      FROM order_pools op
                      JOIN client c ON op.client_id = c.id
                      WHERE op.status = 'ordered' AND op.Payment_status = 'paid' AND c.status_client = 'Regular'
                      AND op.end_time BETWEEN NOW() AND NOW() + INTERVAL {$ending_soon_minutes} MINUTE
                      ORDER BY op.end_time ASC";
$ending_soon_result = mysqli_query($db, $ending_soon_query);
if ($ending_soon_result) {
    while ($row = mysqli_fetch_assoc($ending_soon_result)) {
        $ending_soon_orders[] = $row;
    }
}

$bar_zapros = "SELECT * FROM `bar_items` WHERE status='1'";
$bar_result = mysqli_query($db, $bar_zapros);
while ($row = mysqli_fetch_array($bar_result)) {
   $bar_array[] = $row;
} 
$bar_zapros_1 = "SELECT * FROM `bar_items` WHERE status='1'";
$bar_result_1 = mysqli_query($db, $bar_zapros_1);

$array_bar = [];
while($row = mysqli_fetch_assoc($bar_result_1)){
  $array_bar[] = $row; 
}

$order_item_zapros = "SELECT * FROM `order_items` WHERE status='1'";
$order_item_result = mysqli_query($db, $order_item_zapros);

$array_order_item = [];
while($row = mysqli_fetch_assoc($order_item_result)){
  $array_order_item[] = $row; 
}
$order_pools_zapros = "SELECT * FROM `order_pools` WHERE status='1'";
$order_pools_result = mysqli_query($db, $order_pools_zapros);

$array_order_pools = [];
while($row = mysqli_fetch_assoc($order_pools_result)){
  $array_order_pools[] = $row; 
}
/* --- Commented out: Client data will be fetched directly in clients.php ---
$client_zapros_1 = "SELECT * FROM `client` WHERE status='1'";
$client_result_1 = mysqli_query($db, $client_zapros_1);

  $array_client = [];
while($row = mysqli_fetch_assoc($client_result_1)){
  $array_client[] = $row; 
}
--- End Commented out --- */

// --- Fetch Pending Booking Count ---
$pending_booking_count = 0;
if (isset($db)) {
    $pending_count_query = "SELECT COUNT(id) as pending_count FROM booking WHERE status = 'pending'";
    $pending_count_result = mysqli_query($db, $pending_count_query);
    if ($pending_count_result && $row = mysqli_fetch_assoc($pending_count_result)) {
        $pending_booking_count = $row['pending_count'] ?? 0;
    }
} 
// --- End Fetch Pending Booking Count ---

// --- Fetch Upcoming Bookings --- 
$upcoming_bookings = [];
if (isset($db)) {
    $upcoming_limit = 5; // Max bookings to show
    $upcoming_query = "SELECT 
                            b.id, 
                            b.booking_date, 
                            b.booking_time, 
                            b.pool_id, 
                            b.status,
                            c.full_name as client_name
                        FROM booking b
                        JOIN client c ON b.client_id = c.id
                        WHERE b.status IN ('pending', 'confirmed') 
                          AND CONCAT(b.booking_date, ' ', b.booking_time) >= NOW()
                        ORDER BY b.booking_date ASC, b.booking_time ASC
                        LIMIT {$upcoming_limit}";
    $upcoming_result = mysqli_query($db, $upcoming_query);
    if ($upcoming_result) {
        while ($row = mysqli_fetch_assoc($upcoming_result)) {
            $upcoming_bookings[] = $row;
        }
    } else {
        error_log("Error fetching upcoming bookings: " . mysqli_error($db));
    }
}
// --- End Fetch Upcoming Bookings ---

?>
<!doctype html>
<html
  lang="en"
  class="layout-menu-fixed layout-compact"
  data-assets-path="assets/"
  data-template="vertical-menu-template-free">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>POOL AQUA CRM DASHBOARD</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <!-- build:css assets/vendor/css/theme.css  -->

    <link rel="stylesheet" href="assets/vendor/css/core.css" />
    <link rel="stylesheet" href="assets/css/demo.css" />

    <!-- Vendors CSS -->

    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- endbuild -->

    <link rel="stylesheet" href="assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="assets/vendor/js/helpers.js"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->

    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->

    <script src="assets/js/config.js"></script>
  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <!-- Menu -->

        <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
          <div class="app-brand demo">
            <a href="index.php" class="app-brand-link">
              <span class="app-brand-logo demo">
                <span class="text-primary">
                  <svg
                    width="25"
                    viewBox="0 0 25 42"
                    version="1.1"
                    xmlns="http://www.w3.org/2000/svg"
                    xmlns:xlink="http://www.w3.org/1999/xlink">
                    <defs>
                      <path
                        d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z"
                        id="path-1"></path>
                      <path
                        d="M5.47320593,6.00457225 C4.05321814,8.216144 4.36334763,10.0722806 6.40359441,11.5729822 C8.61520715,12.571656 10.0999176,13.2171421 10.8577257,13.5094407 L15.5088241,14.433041 L18.6192054,7.984237 C15.5364148,3.11535317 13.9273018,0.573395879 13.7918663,0.358365126 C13.5790555,0.511491653 10.8061687,2.3935607 5.47320593,6.00457225 Z"
                        id="path-3"></path>
                      <path
                        d="M7.50063644,21.2294429 L12.3234468,23.3159332 C14.1688022,24.7579751 14.397098,26.4880487 13.008334,28.506154 C11.6195701,30.5242593 10.3099883,31.790241 9.07958868,32.3040991 C5.78142938,33.4346997 4.13234973,34 4.13234973,34 C4.13234973,34 2.75489982,33.0538207 2.37032616e-14,31.1614621 C-0.55822714,27.8186216 -0.55822714,26.0572515 -4.05231404e-15,25.8773518 C0.83734071,25.6075023 2.77988457,22.8248993 3.3049379,22.52991 C3.65497346,22.3332504 5.05353963,21.8997614 7.50063644,21.2294429 Z"
                        id="path-4"></path>
                      <path
                        d="M20.6,7.13333333 L25.6,13.8 C26.2627417,14.6836556 26.0836556,15.9372583 25.2,16.6 C24.8538077,16.8596443 24.4327404,17 24,17 L14,17 C12.8954305,17 12,16.1045695 12,15 C12,14.5672596 12.1403557,14.1461923 12.4,13.8 L17.4,7.13333333 C18.0627417,6.24967773 19.3163444,6.07059163 20.2,6.73333333 C20.3516113,6.84704183 20.4862915,6.981722 20.6,7.13333333 Z"
                        id="path-5"></path>
                    </defs>
                    <g id="g-app-brand" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                      <g id="Brand-Logo" transform="translate(-27.000000, -15.000000)">
                        <g id="Icon" transform="translate(27.000000, 15.000000)">
                          <g id="Mask" transform="translate(0.000000, 8.000000)">
                            <mask id="mask-2" fill="white">
                              <use xlink:href="#path-1"></use>
                            </mask>
                            <use fill="currentColor" xlink:href="#path-1"></use>
                            <g id="Path-3" mask="url(#mask-2)">
                              <use fill="currentColor" xlink:href="#path-3"></use>
                              <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-3"></use>
                            </g>
                            <g id="Path-4" mask="url(#mask-2)">
                              <use fill="currentColor" xlink:href="#path-4"></use>
                              <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-4"></use>
                            </g>
                          </g>
                          <g
                            id="Triangle"
                            transform="translate(19.000000, 11.000000) rotate(-300.000000) translate(-19.000000, -11.000000) ">
                            <use fill="currentColor" xlink:href="#path-5"></use>
                            <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-5"></use>
                          </g>
                        </g>
                      </g>
                    </g>
                  </svg>
                </span>
              </span>
              <span class="app-brand-text demo menu-text fw-bold ms-2">AQUA</span>
            </a>

            <a href="index.php" class="layout-menu-toggle menu-link text-large ms-auto">
              <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i>
            </a>
          </div>

          <div class="menu-divider mt-0"></div>

          <div class="menu-inner-shadow"></div>

          <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>

          <ul class="menu-inner py-1">
            <!-- Dashboards -->
            <li class="menu-item <?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>">
              <a href="index.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div class="text-truncate" data-i18n="Analytics">Dashboard</div>
              </a>
            </li>
            <li class="menu-item <?php echo ($currentPage == 'pool.php') ? 'active' : ''; ?>">
              <a href="pool.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div class="text-truncate" data-i18n="Analytics">Pools</div>
              </a>
            </li>
            <li class="menu-item <?php echo ($currentPage == 'bar.php') ? 'active' : ''; ?>">
              <a href="bar.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div class="text-truncate" data-i18n="Analytics">Bar&Items</div>
              </a>
            </li>
            <li class="menu-item <?php echo ($currentPage == 'clients.php') ? 'active' : ''; ?>">
              <a href="clients.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div class="text-truncate" data-i18n="Analytics">Clients</div>
              </a>
            </li>
            <li class="menu-item <?php echo ($currentPage == 'billing.php') ? 'active' : ''; ?>">
              <a href="billing.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div class="text-truncate" data-i18n="Analytics">Billing</div>
              </a>
            </li>
            <li class="menu-item <?php echo ($currentPage == 'penalty.php') ? 'active' : ''; ?>">
              <a href="penalty.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div class="text-truncate" data-i18n="Analytics">Penalty</div>
              </a>
            </li>
            <li class="menu-item <?php echo in_array($currentPage, ['bookings.php', 'create_booking.php', 'edit_booking.php', 'view_booking.php']) ? 'active' : ''; ?>">
              <a href="bookings.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div class="text-truncate" data-i18n="Analytics">Bookings</div>
                 <?php if ($pending_booking_count > 0): ?>
                  <span class="badge bg-warning rounded-pill ms-auto"><?php echo $pending_booking_count; ?></span>
                <?php endif; ?>
              </a>
            </li>
            
            <!-- <li class="menu-item <?php echo ($currentPage == 'admin_clear_orders.php') ? 'active' : ''; ?>">
              <a href="admin_clear_orders.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div class="text-truncate" data-i18n="Analytics">Clear Orders</div>
              </a>
            </li> -->
       
            <!-- Misc -->
           
        </aside>
        <!-- / Menu -->