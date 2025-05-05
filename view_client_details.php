<?php
include('header.php'); 
include_once('db.php'); // Include DB connection

$client_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$client_info = null;
$pool_orders = [];
$bar_orders = [];
$penalties = [];
$error_message = null;

if (!$client_id) {
    $error_message = "Invalid client ID provided.";
} elseif (!isset($db)) {
    $error_message = "Database connection error.";
} else {
    $client_id_safe = mysqli_real_escape_string($db, $client_id);

    // 1. Fetch Client Info
    $client_query = "SELECT full_name, phone_number, status_client, created_at FROM client WHERE id = {$client_id_safe}";
    $client_result = mysqli_query($db, $client_query);
    if ($client_result && mysqli_num_rows($client_result) > 0) {
        $client_info = mysqli_fetch_assoc($client_result);
    } else {
        $error_message = "Client not found.";
    }

    // Proceed only if client was found
    if ($client_info) {
        // 2. Fetch Recent Pool Orders (e.g., last 10)
        $pool_query = "SELECT op.id, op.pool_id, op.created_at, op.start_time, op.end_time, op.Payment_status, op.status as order_status, p.hourly_rate, p.pool_num
                       FROM order_pools op
                       JOIN pool p ON op.pool_id = p.id
                       WHERE op.client_id = {$client_id_safe}
                       ORDER BY op.created_at DESC
                       LIMIT 10"; // Limit results
        $pool_result = mysqli_query($db, $pool_query);
        if ($pool_result) {
            while ($row = mysqli_fetch_assoc($pool_result)) {
                $pool_orders[] = $row;
            }
        } else {
            error_log("Error fetching pool orders for client {$client_id}: " . mysqli_error($db));
            // Optionally add to user-facing error message
        }

        // 3. Fetch Recent Bar Orders (e.g., last 10)
        // Assuming order_items has price_at_order column
        $bar_query = "SELECT oi.id, oi.quantity, oi.price_at_order, oi.created_at, oi.Payment_status, bi.name as item_name
                      FROM order_items oi
                      JOIN bar_items bi ON oi.bar_item_id = bi.id
                      WHERE oi.client_id = {$client_id_safe}
                      ORDER BY oi.created_at DESC
                      LIMIT 10"; // Limit results
        $bar_result = mysqli_query($db, $bar_query);
        if ($bar_result) {
            while ($row = mysqli_fetch_assoc($bar_result)) {
                $bar_orders[] = $row;
            }
        } else {
            error_log("Error fetching bar orders for client {$client_id}: " . mysqli_error($db));
        }

        // 4. Fetch Recent Penalties (e.g., last 10)
        $penalty_query = "SELECT id, description, price, status, created_at 
                          FROM penalty 
                          WHERE client_id = {$client_id_safe}
                          ORDER BY created_at DESC
                          LIMIT 10"; // Limit results
        $penalty_result = mysqli_query($db, $penalty_query);
        if ($penalty_result) {
             while ($row = mysqli_fetch_assoc($penalty_result)) {
                $penalties[] = $row;
            }
        } else {
             error_log("Error fetching penalties for client {$client_id}: " . mysqli_error($db));
        }
    }
}

?>

<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">

    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Clients /</span> Client Details</h4>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php elseif ($client_info): ?>
        <!-- Client Information Card -->
        <div class="card mb-4">
            <h5 class="card-header">Client Information</h5>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Name:</strong> <?php echo htmlspecialchars($client_info['full_name']); ?><br>
                        <strong>Phone:</strong> <?php echo htmlspecialchars($client_info['phone_number']); ?><br>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Status:</strong> 
                        <span class="badge <?php echo ($client_info['status_client'] == 'VIP') ? 'bg-label-success' : 'bg-label-info'; ?> me-1">
                            <?php echo htmlspecialchars($client_info['status_client']); ?> Client
                        </span><br>
                        <strong>Registered:</strong> <?php echo date('M d, Y H:i', strtotime($client_info['created_at'])); ?>
                    </div>
                </div>
                 <a href="clients.php" class="btn btn-secondary"><i class="bx bx-arrow-back me-1"></i> Back to Clients List</a>
                 <a href="edit_client.php?id=<?php echo $client_id; ?>" class="btn btn-primary"><i class="bx bx-edit-alt me-1"></i> Edit Client</a>
            </div>
        </div>

        <!-- Recent Pool Orders Card -->
        <div class="card mb-4">
            <h5 class="card-header">Recent Pool Orders (Last 10)</h5>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Pool #</th>
                            <th>Order Time</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Rate</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pool_orders)): ?>
                            <tr><td colspan="7" class="text-center text-muted">No recent pool orders found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pool_orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['pool_num'] ?? $order['pool_id']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                    <td><?php echo $order['start_time'] ? date('M d, Y H:i', strtotime($order['start_time'])) : 'N/A'; ?></td>
                                    <td><?php echo $order['end_time'] ? date('M d, Y H:i', strtotime($order['end_time'])) : 'N/A'; ?></td>
                                    <td>$<?php echo number_format($order['hourly_rate'], 2); ?>/hr</td>

                                    <td><span class="badge bg-label-<?php echo ($order['Payment_status'] == 'paid') ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($order['Payment_status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Bar Orders Card -->
        <div class="card mb-4">
            <h5 class="card-header">Recent Bar Orders (Last 10)</h5>
            <div class="table-responsive text-nowrap">
                 <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Price Ea.</th>
                            <th>Order Time</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bar_orders)): ?>
                             <tr><td colspan="5" class="text-center text-muted">No recent bar orders found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bar_orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['item_name']); ?></td>
                                    <td><?php echo (int)$order['quantity']; ?></td>
                                    <td>$<?php echo number_format((float)$order['price_at_order'], 2); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                     <td><span class="badge bg-label-<?php echo ($order['Payment_status'] == 'paid') ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($order['Payment_status']); ?></span></td>
                                </tr>
                             <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Penalties Card -->
        <div class="card mb-4">
            <h5 class="card-header">Recent Penalties (Last 10)</h5>
             <div class="table-responsive text-nowrap">
                 <table class="table table-hover">
                     <thead>
                         <tr>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Date</th>
                             <th>Status</th>
                         </tr>
                     </thead>
                     <tbody>
                        <?php if (empty($penalties)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No recent penalties found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($penalties as $penalty): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($penalty['description']); ?></td>
                                    <td>$<?php echo number_format((float)$penalty['price'], 2); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($penalty['created_at'])); ?></td>
                                     <td><span class="badge bg-label-<?php echo ($penalty['status'] == 'paid') ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($penalty['status']); ?></span></td>
                                </tr>
                             <?php endforeach; ?>
                        <?php endif; ?>
                     </tbody>
                 </table>
             </div>
        </div>

    <?php endif; // End if client_info ?>

</div>
</div>

<?php include('footer.php'); ?> 