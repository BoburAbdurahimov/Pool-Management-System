<?php
session_start();
include_once("db.php"); 
include("header.php");

// Basic Authentication/Permission Check (Highly Recommended for Admin Actions)
// TODO: Implement a real check - is the logged-in user an admin?
/*
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('<div class="container-xxl"><div class="alert alert-danger">Access Denied.</div></div>');
}
*/

$message = '';
$updated_pools = 0;
$updated_orders = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_orders'])) {
    if (isset($db)) {
        mysqli_begin_transaction($db);
        try {
            // 1. Find pools associated with stale orders
            $stale_orders_query = "SELECT id, pool_id FROM order_pools 
                                   WHERE (status = 'ordered' OR status = 'active') AND NOW() > end_time";
            $stale_result = mysqli_query($db, $stale_orders_query);
            
            $stale_order_ids = [];
            $stale_pool_ids = [];
            if ($stale_result) {
                while ($row = mysqli_fetch_assoc($stale_result)) {
                    $stale_order_ids[] = $row['id'];
                    $stale_pool_ids[] = $row['pool_id'];
                }
            } else {
                 throw new Exception("Failed to query stale orders: " . mysqli_error($db));
            }

            // 2. Update stale order_pools records
            if (!empty($stale_order_ids)) {
                $order_ids_string = implode(",", array_map('intval', $stale_order_ids));
                // Change status to 'completed' or perhaps 'expired'?
                $update_orders_query = "UPDATE order_pools SET status = 'completed', Payment_status = 'cancelled_expired' 
                                          WHERE id IN ({$order_ids_string})"; 
                if (!mysqli_query($db, $update_orders_query)) {
                    throw new Exception("Failed to update stale order statuses: " . mysqli_error($db));
                }
                $updated_orders = mysqli_affected_rows($db);
            }

            // 3. Update corresponding pool statuses
            if (!empty($stale_pool_ids)) {
                $pool_ids_string = implode(",", array_map('intval', array_unique($stale_pool_ids))); // Use unique pool IDs
                $update_pools_query = "UPDATE pool SET status_pool = 'available' WHERE id IN ({$pool_ids_string})";
                 if (!mysqli_query($db, $update_pools_query)) {
                    throw new Exception("Failed to update pool statuses: " . mysqli_error($db));
                }
                 $updated_pools = mysqli_affected_rows($db);
            }

            // Commit transaction
            mysqli_commit($db);
            if ($updated_orders > 0 || $updated_pools > 0) {
                $message = "<div class='alert alert-success'>Cleanup complete. Updated {$updated_orders} order record(s) and {$updated_pools} pool status(es).</div>";
            } else {
                 $message = "<div class='alert alert-info'>No stale orders found to clear.</div>";
            }

        } catch (Exception $e) {
            mysqli_rollback($db);
            $message = "<div class='alert alert-danger'>Error during cleanup: " . $e->getMessage() . "</div>";
            error_log("Order Cleanup Error: " . $e->getMessage());
        }
    } else {
         $message = "<div class='alert alert-danger'>Database connection error.</div>";
    }
}

?>

<div class="layout-page">
    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="py-3 mb-4"><span class="text-muted fw-light">Admin /</span> Clear Stale Pool Orders</h4>

        <?php echo $message; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Clear Completed/Expired Pool Orders</h5>
            </div>
            <div class="card-body">
                <p>This tool finds pool orders that are still marked as 'ordered' or 'active' but whose scheduled end time has passed.</p>
                <p>It will update the status of these orders in the <code>order_pools</code> table to 'completed' and set the corresponding pool status in the <code>pool</code> table back to 'available'.</p>
                <p class="text-danger"><i class="bx bx-error-circle me-1"></i>Use with caution. This primarily cleans up orders that were not processed correctly via billing.</p>
                
                <form action="admin_clear_orders.php" method="POST" onsubmit="return confirm('Are you sure you want to clear stale orders? This cannot be undone easily.');">
                    <input type="hidden" name="clear_orders" value="1">
                    <button type="submit" class="btn btn-warning">
                        <i class="bx bx-eraser me-1"></i> Clear Stale Orders Now
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include("footer.php"); ?> 