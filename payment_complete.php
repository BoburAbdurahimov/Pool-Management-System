<?php
session_start();
include_once("db.php"); // Might need DB for other things, include anyway
include("header.php"); 

// Check if last receipt details exist in session
if (!isset($_SESSION['last_receipt_data'])) { // Check for the new key
    // Redirect to billing or dashboard if no receipt details are found
    header("Location: billing.php"); 
    exit();
}

// Get receipt details from session
$receipt = $_SESSION['last_receipt_data']; // Use the new key

// IMPORTANT: DO NOT Clear the session variable here - PDF script needs it
// unset($_SESSION['last_receipt_data']); 

// Extract data for easier use
$client_id = $receipt['client_id'];
$client_name = htmlspecialchars($receipt['client_name']);
$receipt_number = htmlspecialchars($receipt['receipt_number']);
$payment_date = htmlspecialchars($receipt['date']);
$total_amount = (float)$receipt['total_amount'];
$items = $receipt['items']; // Array of charge details

// --- Calculate Subtotal for display --- 
// (Tax was removed earlier, so subtotal is the same as total)
$subtotal = $total_amount; 

?>

<div class="layout-page">
    <div class="container-xxl flex-grow-1 container-p-y">

        <h4 class="py-3 mb-4"><span class="text-muted fw-light">Billing /</span> Payment Complete</h4>

        <div class="row">
            <!-- Payment Complete Section -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Payment Complete</h5>
                        <small class="text-muted">Payment has been processed successfully</small>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                             <span class="alert-icon text-success me-2">
                                <i class="bx bx-check-circle bx-sm"></i>
                             </span>
                            <div>
                                <strong>Payment Successful</strong><br>
                                Payment of $<?php echo number_format($total_amount, 2); ?> for <?php echo $client_name; ?> has been processed.
                            </div>
                        </div>

                        <ul class="list-unstyled mt-4">
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="fw-medium">Receipt Number:</span>
                                <span><?php echo $receipt_number; ?></span>
                            </li>
                             <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="fw-medium">Date:</span>
                                <span><?php echo date('d-m-Y', strtotime($payment_date)); ?></span>
                            </li>
                             <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="fw-medium">Client:</span>
                                <span><?php echo $client_name; ?></span>
                            </li>
                             <li class="d-flex justify-content-between py-2">
                                <span class="fw-medium">Total Amount:</span>
                                <span class="fw-bold">$<?php echo number_format($total_amount, 2); ?></span>
                            </li>
                        </ul>

                        <div class="mt-4">
                             <?php // Link to the PDF generation script ?>
                             <a href="generate_receipt_pdf.php?receipt_num=<?php echo urlencode($receipt_number); ?>" class="btn btn-dark w-100 mb-2" target="_blank">
                                <i class="bx bx-printer me-1"></i> Generate Receipt
                             </a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Payment Complete Section -->

            <!-- Receipt Preview Section -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Receipt Preview</h5>
                        <small class="text-muted">Preview of the generated receipt</small>
                    </div>
                    <div class="card-body">
                        <div class="p-3 border rounded">
                            <div class="text-center mb-3">
                                <h5 class="mb-1">Aqua Oasis</h5>
                                <p class="mb-0">Receipt #<?php echo $receipt_number; ?></p>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Client: <?php echo $client_name; ?></span>
                                <span>Date: <?php echo date('d-m-Y', strtotime($payment_date)); ?></span>
                            </div>
                            
                            <table class="table table-sm mb-4">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Date</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($items)): ?>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($item['date'])); ?></td>
                                                <td class="text-end">$<?php echo number_format($item['amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No items found for this receipt.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                     <tr class="border-top">
                                        <td colspan="2" class="text-end fw-medium">Subtotal:</td>
                                        <td class="text-end">$<?php echo number_format($subtotal, 2); ?></td>
                                    </tr>
                                     <?php /* Tax row removed 
                                     <tr>
                                        <td colspan="2" class="text-end">Tax (X%):</td> 
                                        <td class="text-end">$X.XX</td>
                                    </tr>
                                    */ ?>
                                     <tr class="fw-bold border-top">
                                        <td colspan="2" class="text-end">Total:</td>
                                        <td class="text-end">$<?php echo number_format($total_amount, 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
             <!-- End Receipt Preview Section -->
        </div>

        <?php // Optional: Display a floating success message too ?>
        <div class="bs-toast toast fade show bg-success position-fixed bottom-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                 <i class="bx bx-bell me-2"></i>
                <div class="me-auto fw-semibold">Payment Processed</div>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                Payment of $<?php echo number_format($total_amount, 2); ?> for <?php echo $client_name; ?> has been processed successfully.
            </div>
        </div>

    </div>
    <!-- / Content -->

    <?php include("footer.php"); ?>
</div> 