<?php
session_start();

// 1. Define path to FPDF library
// Adjust the path if you placed fpdf.php elsewhere (e.g., 'lib/fpdf.php')
define('FPDF_FONTPATH', __DIR__ . '/font/'); // Path to font folder
require('fpdf.php'); 

// 2. Security Check: Validate Session and GET parameter
$requested_receipt_num = isset($_GET['receipt_num']) ? $_GET['receipt_num'] : null;

if (!$requested_receipt_num 
    || !isset($_SESSION['last_receipt_data']) 
    || $_SESSION['last_receipt_data']['receipt_number'] !== $requested_receipt_num)
{
    die('Error: Invalid receipt request or session data missing.');
}

// 3. Retrieve data from session
$receipt = $_SESSION['last_receipt_data'];

// IMPORTANT: Clear session data AFTER retrieving it for PDF generation
unset($_SESSION['last_receipt_data']);

// 4. Extract data
$client_name = $receipt['client_name']; // Already HTML-safe from billing.php ideally, but decode just in case?
$receipt_number = $receipt['receipt_number'];
$payment_date = $receipt['date'];
$total_amount = (float)$receipt['total_amount'];
$items = $receipt['items'];
$subtotal = $total_amount; // Tax was removed earlier

// 5. Create PDF using FPDF
class PDF extends FPDF
{
    // Simple Header
    function Header()
    {
        // Logo or Title
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(30, 10, 'Aqua Oasis Receipt', 0, 0, 'C');
        $this->Ln(20);
    }

    // Simple Footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Receipt Table
    function ReceiptTable($header, $data)
    {
        // Column widths
        $w = array(100, 30, 40); // Description, Date, Amount
        // Header
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(230, 230, 230);
        for($i=0; $i<count($header); $i++)
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        $this->Ln();
        // Data
        $this->SetFont('Arial', '', 10);
        $this->SetFillColor(255, 255, 255);
        $fill = false;
        foreach($data as $row)
        {
            $this->Cell($w[0], 6, $row['description'], 'LR', 0, 'L', $fill);
            $this->Cell($w[1], 6, $row['date'], 'LR', 0, 'C', $fill); // Center date
            $this->Cell($w[2], 6, '$' . number_format($row['amount'], 2), 'LR', 0, 'R', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

// Instantiation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// Add Receipt Info
$pdf->Cell(0, 6, 'Receipt #: ' . $receipt_number, 0, 1);
$pdf->Cell(0, 6, 'Date: ' . $payment_date, 0, 1);
$pdf->Cell(0, 6, 'Client: ' . utf8_decode($client_name), 0, 1); // Use utf8_decode for potential special characters
$pdf->Ln(10);

// Prepare table data
$header = array('Description', 'Date', 'Amount');
$table_data = [];
if (!empty($items)) {
    foreach($items as $item) {
        $table_data[] = [
            'description' => utf8_decode($item['description']), // Use utf8_decode here too
            'date' => $item['date'],
            'amount' => $item['amount']
        ];
    }
}

// Add table
$pdf->ReceiptTable($header, $table_data);
$pdf->Ln(1);

// Add Totals
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(130, 6, 'Subtotal:', 0, 0, 'R');
$pdf->Cell(40, 6, '$' . number_format($subtotal, 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(130, 6, 'Total:', 0, 0, 'R');
$pdf->Cell(40, 6, '$' . number_format($total_amount, 2), 0, 1, 'R');

// 6. Output PDF
// D -> Force download, I -> Output inline in browser, F -> Save to file, S -> Return as string
$pdf->Output('D', 'receipt-' . $receipt_number . '.pdf'); 
exit;

?> 