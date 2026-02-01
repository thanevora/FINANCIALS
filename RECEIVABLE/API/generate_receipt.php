<?php
session_start();
include("../../API_gateway.php");

// Database configuration
$db_name = "fina_budget";

// Check database connection
if (!isset($connections[$db_name])) {
    die("❌ Connection not found for $db_name");
}

$conn = $connections[$db_name];

// Get invoice ID
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;

if ($invoice_id <= 0) {
    die("Invalid invoice ID");
}

// Fetch invoice details
$sql = "SELECT ar.*, c.company_name, c.company_address, c.company_phone, c.company_email, c.company_logo 
        FROM accounts_receivable ar
        LEFT JOIN company_info c ON 1=1
        WHERE ar.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $invoice_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$row = mysqli_fetch_assoc($result)) {
    die("Invoice not found");
}

// Get company info (default if not in database)
$company_name = $row['company_name'] ?? 'Your Company Name';
$company_address = $row['company_address'] ?? '123 Business Street, City, Country 12345';
$company_phone = $row['company_phone'] ?? '+1 (123) 456-7890';
$company_email = $row['company_email'] ?? 'info@company.com';
$company_logo = $row['company_logo'] ?? '';

// Generate receipt number
$receipt_number = 'RCPT-' . date('Ymd') . '-' . str_pad($invoice_id, 4, '0', STR_PAD_LEFT);

// Generate receipt date
$receipt_date = date('F d, Y');
$receipt_time = date('h:i A');

// Calculate VAT if applicable (assuming 12% VAT in Philippines)
$vat_rate = 0.12;
$amount = floatval($row['amount']);
$vat_amount = $amount * $vat_rate;
$subtotal = $amount - $vat_amount;

// Format amounts
$formatted_subtotal = number_format($subtotal, 2);
$formatted_vat = number_format($vat_amount, 2);
$formatted_total = number_format($amount, 2);
$formatted_balance = number_format(floatval($row['balance']), 2);
$formatted_amount_paid = number_format($amount - floatval($row['balance']), 2);

// Generate HTML receipt
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo htmlspecialchars($row['invoice_number']); ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 20mm;
            }
            
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
                font-size: 12px;
                line-height: 1.4;
                color: #333;
                background: #fff;
            }
            
            .no-print {
                display: none !important;
            }
            
            .receipt-container {
                box-shadow: none;
                border: none;
            }
        }
        
        @media screen {
            body {
                font-family: Arial, sans-serif;
                font-size: 14px;
                line-height: 1.6;
                color: #333;
                background: #f5f5f5;
                margin: 0;
                padding: 20px;
            }
            
            .receipt-container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                padding: 40px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                border-radius: 8px;
            }
        }
        
        /* Common Styles */
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .company-logo {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 10px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
            color: #2c3e50;
        }
        
        .company-info {
            font-size: 12px;
            color: #666;
            line-height: 1.4;
        }
        
        .receipt-title {
            text-align: center;
            margin: 30px 0;
        }
        
        .receipt-title h1 {
            font-size: 28px;
            margin: 0;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .receipt-title .receipt-number {
            font-size: 18px;
            color: #666;
            margin-top: 10px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin: 30px 0;
        }
        
        .detail-section {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .detail-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: bold;
            color: #555;
        }
        
        .detail-value {
            color: #333;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        
        .items-table th {
            background: #2c3e50;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        
        .items-table tr:hover {
            background: #f5f5f5;
        }
        
        .amounts-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .total-row {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            border-top: 2px solid #2c3e50;
            border-bottom: none;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .payment-info {
            margin: 30px 0;
            padding: 20px;
            background: #e8f4fd;
            border-radius: 5px;
            border-left: 4px solid #3498db;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            padding-top: 20px;
            border-top: 2px solid #333;
            color: #666;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            padding: 20px;
            width: 45%;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin: 40px 0 10px;
        }
        
        .print-buttons {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
        }
        
        .print-btn {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 10px;
            transition: background 0.3s;
        }
        
        .print-btn:hover {
            background: #1a252f;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-paid {
            background: #27ae60;
            color: white;
        }
        
        .status-pending {
            background: #f39c12;
            color: white;
        }
        
        .status-received {
            background: #3498db;
            color: white;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(0,0,0,0.1);
            z-index: -1;
            white-space: nowrap;
        }
        
        .receipt-container {
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="watermark"><?php echo strtoupper($row['status']); ?></div>
    
    <div class="receipt-container">
        <!-- Company Header -->
        <div class="header">
            <?php if (!empty($company_logo)): ?>
                <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="Company Logo" class="company-logo">
            <?php endif; ?>
            <h1 class="company-name"><?php echo htmlspecialchars($company_name); ?></h1>
            <div class="company-info">
                <?php echo htmlspecialchars($company_address); ?><br>
                Phone: <?php echo htmlspecialchars($company_phone); ?> | 
                Email: <?php echo htmlspecialchars($company_email); ?>
            </div>
        </div>
        
        <!-- Receipt Title -->
        <div class="receipt-title">
            <h1>OFFICIAL RECEIPT</h1>
            <div class="receipt-number">Receipt No: <strong><?php echo htmlspecialchars($receipt_number); ?></strong></div>
            <div>Date: <?php echo $receipt_date; ?> | Time: <?php echo $receipt_time; ?></div>
        </div>
        
        <!-- Customer and Invoice Details -->
        <div class="details-grid">
            <div class="detail-section">
                <h3>Customer Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($row['customer_name']); ?></span>
                </div>
                <?php if (!empty($row['customer_email'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($row['customer_email']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($row['customer_phone'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($row['customer_phone']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="detail-section">
                <h3>Invoice Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Invoice No:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($row['invoice_number']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Service Type:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($row['service_type'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                            <?php echo htmlspecialchars(strtoupper($row['status'])); ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Due Date:</span>
                    <span class="detail-value"><?php echo date('F d, Y', strtotime($row['due_date'])); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Items/Service Details -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($row['service_type'] ?? 'Service'); ?></td>
                    <td>1</td>
                    <td>₱<?php echo $formatted_subtotal; ?></td>
                    <td>₱<?php echo $formatted_subtotal; ?></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right; font-weight: bold;">Subtotal:</td>
                    <td>₱<?php echo $formatted_subtotal; ?></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right; font-weight: bold;">VAT (12%):</td>
                    <td>₱<?php echo $formatted_vat; ?></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Amounts Summary -->
        <div class="amounts-section">
            <div class="amount-row">
                <span class="detail-label">Subtotal:</span>
                <span class="detail-value">₱<?php echo $formatted_subtotal; ?></span>
            </div>
            <div class="amount-row">
                <span class="detail-label">VAT (12%):</span>
                <span class="detail-value">₱<?php echo $formatted_vat; ?></span>
            </div>
            <div class="amount-row total-row">
                <span class="detail-label">Total Amount:</span>
                <span class="detail-value">₱<?php echo $formatted_total; ?></span>
            </div>
            <div class="amount-row">
                <span class="detail-label">Amount Paid:</span>
                <span class="detail-value">₱<?php echo $formatted_amount_paid; ?></span>
            </div>
            <div class="amount-row total-row">
                <span class="detail-label">Balance Due:</span>
                <span class="detail-value">₱<?php echo $formatted_balance; ?></span>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="payment-info">
            <h3>Payment Information</h3>
            <div class="detail-row">
                <span class="detail-label">Payment Date:</span>
                <span class="detail-value">
                    <?php echo !empty($row['payment_date']) ? date('F d, Y', strtotime($row['payment_date'])) : 'Pending'; ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value"><?php echo htmlspecialchars($row['payment_method'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Transaction Reference:</span>
                <span class="detail-value"><?php echo htmlspecialchars($row['invoice_number']); ?></span>
            </div>
        </div>
        
        <!-- Notes -->
        <?php if (!empty($row['notes'])): ?>
        <div class="payment-info">
            <h3>Notes</h3>
            <p><?php echo nl2br(htmlspecialchars($row['notes'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Signatures -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <p>Prepared by</p>
                <p><strong>Accounts Department</strong></p>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <p>Received by</p>
                <p><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>Thank you for your business!</strong></p>
            <p>This is a computer-generated receipt. No signature required.</p>
            <p>For inquiries, please contact <?php echo htmlspecialchars($company_phone); ?> or <?php echo htmlspecialchars($company_email); ?></p>
            <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
        </div>
    </div>
    
    <!-- Print Buttons (only shown on screen) -->
    <div class="print-buttons no-print">
        <button onclick="window.print()" class="print-btn">
            <i data-lucide="printer"></i> Print Receipt
        </button>
        <button onclick="window.close()" class="print-btn">
            <i data-lucide="x"></i> Close Window
        </button>
        <button onclick="downloadAsPDF()" class="print-btn">
            <i data-lucide="download"></i> Download PDF
        </button>
    </div>
    
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Auto-print when page loads (optional)
        window.addEventListener('load', function() {
            // Uncomment the line below to auto-print when page loads
            // window.print();
        });
        
        // Function to download as PDF
        function downloadAsPDF() {
            const element = document.querySelector('.receipt-container');
            const options = {
                margin: [10, 10, 10, 10],
                filename: 'Receipt_<?php echo $receipt_number; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    letterRendering: true
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait' 
                }
            };
            
            html2pdf().set(options).from(element).save();
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + P to print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            // Ctrl/Cmd + S to save as PDF
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                downloadAsPDF();
            }
            // Escape to close
            if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
</body>
</html>