<?php 
session_start();

// --- Ensure a client context exists --- 
if (!isset($_SESSION['current_order_client_id']) || $_SESSION['current_order_client_id'] === null) {
    // Redirect back to bar.php if no client is selected in the session
    $_SESSION['error_message'] = "Please select a client from the Clients page before managing bar orders.";
    header('Location: bar.php');
    exit;
}

// Retrieve client info from session
$client_id = $_SESSION['current_order_client_id'];
$client_status = $_SESSION['current_order_client_status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item_id'])) {
    $item_id_to_remove = filter_input(INPUT_POST, 'remove_item_id', FILTER_SANITIZE_NUMBER_INT);
    if ($item_id_to_remove && isset($_SESSION['cart'][$item_id_to_remove])) {
        unset($_SESSION['cart'][$item_id_to_remove]);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_order'])) {
    $_SESSION['cart'] = [];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

include("header.php");
?>

<div class="layout-page">
<div class="container-xxl flex-grow-1 container-p-y">

<h4 class="py-3 mb-4">Current Bar Order</h4>

<div class="card">
  <h5 class="card-header">Ordered Items</h5>
  <div class="table-responsive text-nowrap">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Item Name</th>
          <th>Category</th>
          <th>Quantity</th>
          <th>Unit Price</th>
          <th>Subtotal</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody class="table-border-bottom-0">
        <?php 
        $grand_total = 0;
        $row_number = 1;
        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])):
            foreach ($_SESSION['cart'] as $item_id => $item):
                $name = htmlspecialchars($item['name'] ?? 'N/A');
                $category = htmlspecialchars($item['category'] ?? 'N/A');
                $quantity = (int)($item['quantity'] ?? 0);
                $price = (float)($item['price'] ?? 0);
                $subtotal = $quantity * $price;
                $grand_total += $subtotal;
        ?>
        <tr>
          <td><?php echo $row_number; ?></td>
          <td><strong><?php echo $name; ?></strong></td>
          <td><?php echo $category; ?></td>
          <td><?php echo $quantity; ?></td>
          <td>$<?php echo number_format($price, 2); ?></td>
          <td>$<?php echo number_format($subtotal, 2); ?></td>
          <td>
             <form action="ordered_bar_items.php" method="post" style="display: inline;">
                <input type="hidden" name="remove_item_id" value="<?php echo $item_id; ?>">
                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
            </form>
          </td>
        </tr>
        <?php 
            $row_number++;
            endforeach;
        else:
        ?>
        <tr>
          <td colspan="7" class="text-center">Your order is currently empty. Go back to add items.</td>
        </tr>
        <?php endif; ?>
      </tbody>
      <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
      <tfoot>
        <tr>
          <td colspan="5" class="text-end"><strong>Grand Total:</strong></td>
          <td><strong>$<?php echo number_format($grand_total, 2); ?></strong></td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>


<div class="mt-4 d-flex justify-content-between">
    <a href="bar.php" class="btn btn-secondary">Back to Menu</a>
    <div> 
      <form action="ordered_bar_items.php" method="post" style="display: inline-block; margin-left: 5px;">
          <button type="submit" name="clear_order" class="btn btn-warning" <?php echo (empty($_SESSION['cart']) ? 'disabled' : ''); ?>>Clear Order</button>
      </form>
      <form action="process_order.php" method="post" style="display: inline-block; margin-left: 5px;">
          <?php // Add hidden fields for client info ?>
          <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_id); ?>">
          <input type="hidden" name="client_status" value="<?php echo htmlspecialchars($client_status); ?>">
          <button type="submit" name="confirm_order" class="btn btn-primary" <?php echo (empty($_SESSION['cart']) ? 'disabled' : ''); ?>>Confirm Order</button>
      </form>
    </div>
</div>


</div> 
</div> 

<?php include("footer.php"); ?>