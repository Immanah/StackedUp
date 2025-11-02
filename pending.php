<?php
session_start();

// Check if there's a booking in session storage (passed from checkout)
$booking = null;
if (isset($_GET['ref']) && isset($_GET['method'])) {
    $booking = [
        'ref' => $_GET['ref'],
        'method' => $_GET['method'],
        'status' => 'pending'
    ];
}

// If no booking data, redirect to cart
if (!$booking) {
    header("Location: cart.php");
    exit;
}

// Set page title and messages based on payment method
$page_title = "Order Pending - OZYDE";
$method_titles = [
    'store' => 'Pay in Store',
    'eft' => 'Bank Transfer'
];
$method_icons = [
    'store' => '🏪',
    'eft' => '🏦'
];
$method_descriptions = [
    'store' => 'Your order is reserved pending payment in store',
    'eft' => 'Your order is pending payment verification'
];
$method_instructions = [
    'store' => 'Please visit our store to complete your payment using the reference below.',
    'eft' => 'We are verifying your bank transfer. You will receive confirmation via email once processed.'
];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?php echo $page_title; ?></title>
    <style>
        :root {
            --bg: #fff;
            --text: #222;
            --muted: #7a7a7a;
            --accent: #111;
            --card-bg: #0b0b0b;
            --radius: 14px;
            --max-width: 1100px;
            --gold: #c6a04a;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            font-family: Inter, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            color: var(--text);
            background: linear-gradient(180deg, #ffffff 0%, #f5f5f7 100%);
            padding: 28px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .container {
            width: 100%;
            max-width: 600px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 20px 60px rgba(16, 16, 24, 0.06);
            overflow: hidden;
            padding: 40px;
            text-align: center;
        }
        
        .status-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .status-badge {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
        
        h1 {
            margin: 0 0 12px 0;
            font-size: 28px;
            color: var(--accent);
        }
        
        .subtitle {
            color: var(--muted);
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .ref-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 24px;
            margin: 30px 0;
            border: 1px solid #e9ecef;
        }
        
        .ref-label {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .ref-number {
            font-family: 'Courier New', monospace;
            font-size: 24px;
            font-weight: 700;
            color: var(--accent);
            margin: 12px 0;
            padding: 12px 20px;
            background: #fff;
            border-radius: 8px;
            border: 2px dashed rgba(198, 160, 74, 0.3);
            display: inline-block;
        }
        
        .instructions {
            background: #e8f4fd;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
            border-left: 4px solid #2196F3;
        }
        
        .instructions h3 {
            margin: 0 0 12px 0;
            font-size: 16px;
            color: #0b5ed7;
        }
        
        .instructions p {
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
            color: #084298;
        }
        
        .store-details {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
            text-align: left;
        }
        
        .store-details h3 {
            margin: 0 0 12px 0;
            font-size: 16px;
            color: var(--accent);
        }
        
        .store-info {
            font-size: 14px;
            line-height: 1.6;
            color: var(--muted);
        }
        
        .store-info strong {
            color: var(--accent);
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: 0;
            cursor: pointer;
            background: var(--accent);
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            transition: all 0.2s ease;
        }
        
        .btn:hover {
            background: #000;
            transform: translateY(-1px);
        }
        
        .btn.ghost {
            background: #fff;
            color: var(--accent);
            border: 1px solid rgba(0, 0, 0, 0.06);
        }
        
        .btn.ghost:hover {
            background: #f8f9fa;
        }
        
        .contact-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .contact-info p {
            margin: 8px 0;
            font-size: 14px;
            color: var(--muted);
        }
        
        .glam-line {
            height: 2px;
            background: linear-gradient(90deg, var(--gold), #ffd27a);
            border-radius: 2px;
            margin: 20px 0;
        }
        
        .bank-details {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
            text-align: left;
        }
        
        .bank-details h3 {
            margin: 0 0 12px 0;
            font-size: 16px;
            color: var(--accent);
        }
        
        .bank-info {
            font-size: 14px;
            line-height: 1.6;
            color: var(--muted);
        }
        
        .bank-info strong {
            color: var(--accent);
        }
        
        .timeline {
            margin: 25px 0;
            text-align: left;
        }
        
        .timeline-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .timeline-icon {
            font-size: 20px;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-title {
            font-weight: 600;
            margin: 0 0 4px 0;
            font-size: 14px;
        }
        
        .timeline-desc {
            font-size: 13px;
            color: var(--muted);
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 24px;
                margin: 20px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .ref-number {
                font-size: 20px;
                padding: 10px 16px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Status Icon -->
        <div class="status-icon">⏳</div>
        
        <!-- Status Badge -->
        <div class="status-badge">Order Pending</div>
        
        <!-- Main Title -->
        <h1>Payment <?php echo $method_titles[$booking['method']]; ?> Processing</h1>
        
        <!-- Subtitle -->
        <p class="subtitle"><?php echo $method_descriptions[$booking['method']]; ?></p>
        
        <!-- Glam Line -->
        <div class="glam-line"></div>
        
        <!-- Reference Card -->
        <div class="ref-card">
            <div class="ref-label">Your Order Reference</div>
            <div class="ref-number"><?php echo htmlspecialchars($booking['ref']); ?></div>
            <div class="subtitle" style="font-size: 14px; margin: 0;">Keep this reference for all communications</div>
        </div>
        
        <!-- Payment Method Specific Content -->
        <?php if ($booking['method'] === 'store'): ?>
            <!-- Store Payment Instructions -->
            <div class="instructions">
                <h3>📋 Next Steps - Pay in Store</h3>
                <p><?php echo $method_instructions['store']; ?></p>
            </div>
            
            <!-- Store Details -->
            <div class="store-details">
                <h3>OZYDE Store Location</h3>
                <div class="store-info">
                    <p><strong>Address:</strong> 5 Liebenberg Rd, Noordwyk, Midrand 1687</p>
                    <p><strong>Hours:</strong> Mon-Fri: 9:00-18:00 | Sat: 9:00-16:00 | Sun: 10:00-14:00</p>
                    <p><strong>Phone:</strong> +27 11 123 4567</p>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-icon">1️⃣</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Visit Our Store</div>
                        <div class="timeline-desc">Bring your reference number to our store</div>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">2️⃣</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Complete Payment</div>
                        <div class="timeline-desc">Pay the total amount at the counter</div>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">3️⃣</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Receive Confirmation</div>
                        <div class="timeline-desc">Get instant confirmation and booking details</div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($booking['method'] === 'eft'): ?>
            <!-- EFT Payment Instructions -->
            <div class="instructions">
                <h3>🔄 Payment Verification</h3>
                <p><?php echo $method_instructions['eft']; ?></p>
            </div>
            
            <!-- Bank Details Recap -->
            <div class="bank-details">
                <h3>🏦 Bank Transfer Details</h3>
                <div class="bank-info">
                    <p><strong>Bank:</strong> Standard Bank</p>
                    <p><strong>Account:</strong> 10202732310</p>
                    <p><strong>Branch Code:</strong> 051001</p>
                    <p><strong>Reference:</strong> <?php echo htmlspecialchars($booking['ref']); ?></p>
                    <p><strong>Amount:</strong> Use the total amount from your order</p>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-icon">1️⃣</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Bank Transfer Sent</div>
                        <div class="timeline-desc">You've submitted proof of payment</div>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">2️⃣</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Verification in Progress</div>
                        <div class="timeline-desc">We're confirming your payment with our bank</div>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">3️⃣</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Order Confirmation</div>
                        <div class="timeline-desc">You'll receive email confirmation within 24 hours</div>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
        
        <!-- Contact Information -->
        <div class="contact-info">
            <p><strong>Need help?</strong> Contact our support team</p>
            <p>📧 support@ozyde.com | 📞 +27 11 123 4567</p>
        </div>
        
        <!-- Action Buttons -->
        <div style="margin-top: 30px;">
            <a href="index.html" class="btn ghost">Return to Home</a>
            <a href="contact.php" class="btn">Contact Support</a>
        </div>
        
        <!-- Footer Note -->
        <div style="margin-top: 20px; font-size: 12px; color: var(--muted);">
            <p>You will receive email updates about your order status.</p>
        </div>
    </div>

    <script>
        // Utility functions
        const q = (s) => document.querySelector(s);
        const formatR = (n) => 'R' + Number(n).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // Copy reference number functionality
        const refNumber = q('.ref-number');
        if (refNumber) {
            refNumber.style.cursor = 'pointer';
            refNumber.title = 'Click to copy reference number';
            
            refNumber.addEventListener('click', () => {
                const text = refNumber.textContent;
                
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    navigator.clipboard.writeText(text).then(() => {
                        showCopyFeedback();
                    }).catch(() => {
                        fallbackCopy(text);
                    });
                } else {
                    fallbackCopy(text);
                }
            });
        }

        function showCopyFeedback() {
            const originalText = refNumber.textContent;
            refNumber.textContent = 'Copied!';
            refNumber.style.background = '#e8ffef';
            refNumber.style.borderColor = '#2fa46b';
            
            setTimeout(() => {
                refNumber.textContent = originalText;
                refNumber.style.background = '#fff';
                refNumber.style.borderColor = 'rgba(198, 160, 74, 0.3)';
            }, 1500);
        }

        function fallbackCopy(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                showCopyFeedback();
            } catch (e) {
                alert('Could not copy — please copy manually: ' + text);
            }
            document.body.removeChild(ta);
        }

        // Auto-hide alert after 5 seconds
        const alert = document.querySelector('.instructions');
        if (alert) {
            setTimeout(() => {
                alert.style.opacity = '0.9';
            }, 5000);
        }

        // Print functionality
        const printPage = () => {
            window.print();
        };

        // Add print button if needed
        document.addEventListener('DOMContentLoaded', () => {
            const buttonContainer = document.querySelector('.btn').parentNode;
            const printBtn = document.createElement('button');
            printBtn.className = 'btn ghost';
            printBtn.innerHTML = '🖨️ Print Page';
            printBtn.onclick = printPage;
            buttonContainer.appendChild(printBtn);
        });
    </script>
</body>
</html>