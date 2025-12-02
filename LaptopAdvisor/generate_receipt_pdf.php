<?php
require_once 'includes/auth_check.php';
require_once __DIR__ . '/vendor/autoload.php';

// Validate order ID
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header("Location: profile.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];

// Fetch main order details, ensuring it belongs to the current user
$order_stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$order_stmt->bind_param("ii", $order_id, $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows != 1) {
    die("Order not found or you do not have permission to view it.");
}
$order = $order_result->fetch_assoc();
$order_stmt->close();

// Fetch user details
$user_stmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Fetch all items associated with this order
$items = [];
$items_stmt = $conn->prepare(
    "SELECT oi.quantity, oi.price_at_purchase, p.product_name 
     FROM order_items oi 
     JOIN products p ON oi.product_id = p.product_id 
     WHERE oi.order_id = ?"
);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}
$items_stmt->close();

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('LaptopAdvisor');
$pdf->SetAuthor('LaptopAdvisor');
$pdf->SetTitle('Order Receipt #' . $order_id);
$pdf->SetSubject('Order Receipt');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Define colors
$primaryColor = array(44, 62, 80);
$accentColor = array(52, 152, 219);
$lightGray = array(236, 240, 241);
$darkGray = array(127, 140, 141);

// Company Header with colored background
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Rect(0, 0, 210, 40, 'F');

// Company Logo/Name
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 24);
$pdf->SetXY(15, 12);
$pdf->Cell(0, 10, 'LaptopAdvisor', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(15, 24);
$pdf->Cell(0, 5, 'Your Trusted Laptop Shopping Partner', 0, 1, 'L');

// Receipt Title
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetXY(15, 50);
$pdf->Cell(0, 10, 'ORDER RECEIPT', 0, 1, 'R');

// Reset text color
$pdf->SetTextColor(0, 0, 0);

// Order Information Box
$pdf->SetY(70);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
$pdf->Cell(90, 8, 'Order Information', 0, 0, 'L', true);
$pdf->Cell(90, 8, 'Customer Information', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 9);

// Left column - Order Info
$yPos = $pdf->GetY();
$pdf->SetXY(15, $yPos);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(35, 6, 'Order Number:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(55, 6, '#' . str_pad($order_id, 6, '0', STR_PAD_LEFT), 0, 1);

$pdf->SetX(15);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(35, 6, 'Order Date:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(55, 6, date("F j, Y", strtotime($order['order_date'])), 0, 1);

$pdf->SetX(15);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(35, 6, 'Order Status:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]);
$pdf->Cell(55, 6, strtoupper($order['order_status']), 0, 1);
$pdf->SetTextColor(0, 0, 0);

// Right column - Customer Info
$pdf->SetXY(105, $yPos);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(35, 6, 'Customer Name:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(55, 6, $user['full_name'], 0, 1);

$pdf->SetX(105);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(35, 6, 'Email:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(55, 6, $user['email'], 0, 1);

// Shipping Address Section
if (!empty($order['shipping_name'])) {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
    $pdf->Cell(180, 8, 'Shipping Address', 0, 1, 'L', true);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(180, 5, $order['shipping_name'], 0, 1);
    $pdf->Cell(180, 5, $order['shipping_address'], 0, 1);
    $pdf->Cell(180, 5, $order['shipping_city'] . ', ' . $order['shipping_state'] . ' ' . $order['shipping_zip'], 0, 1);
    $pdf->Cell(180, 5, $order['shipping_country'], 0, 1);
    $pdf->Cell(180, 5, 'Phone: ' . $order['shipping_phone'], 0, 1);
}

// Order Items Section
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(180, 8, 'Order Items', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

// Table Header
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
$pdf->Cell(90, 7, 'Product Name', 1, 0, 'L', true);
$pdf->Cell(30, 7, 'Quantity', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'Unit Price', 1, 0, 'R', true);
$pdf->Cell(30, 7, 'Subtotal', 1, 1, 'R', true);

// Table Body
$pdf->SetFont('helvetica', '', 9);
$subtotal = 0;
foreach ($items as $item) {
    $itemTotal = $item['price_at_purchase'] * $item['quantity'];
    $subtotal += $itemTotal;
    
    $pdf->Cell(90, 6, $item['product_name'], 1, 0, 'L');
    $pdf->Cell(30, 6, $item['quantity'], 1, 0, 'C');
    $pdf->Cell(30, 6, '$' . number_format($item['price_at_purchase'], 2), 1, 0, 'R');
    $pdf->Cell(30, 6, '$' . number_format($itemTotal, 2), 1, 1, 'R');
}

// Summary Section
$pdf->Ln(3);
$pdf->SetFont('helvetica', 'B', 9);

// Subtotal
$pdf->Cell(150, 6, 'Subtotal:', 0, 0, 'R');
$pdf->Cell(30, 6, '$' . number_format($subtotal, 2), 0, 1, 'R');

// Shipping
$pdf->Cell(150, 6, 'Shipping:', 0, 0, 'R');
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]);
$pdf->Cell(30, 6, 'FREE', 0, 1, 'R');
$pdf->SetTextColor(0, 0, 0);

// Total
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(150, 10, 'TOTAL:', 0, 0, 'R', true);
$pdf->Cell(30, 10, '$' . number_format($order['total_amount'], 2), 0, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Footer
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
$pdf->MultiCell(180, 4, "Thank you for your purchase!\n\nThis is a computer-generated receipt. For any questions or concerns, please contact our customer support.\n\nLaptopAdvisor © " . date('Y') . " | www.laptopadvisor.com | support@laptopadvisor.com", 0, 'C');

// Output PDF
$pdf->Output('Receipt_Order_' . $order_id . '.pdf', 'D');
?>
