<?php
session_start();
require_once('../includes/config.php');

// For testing, bypass admin check
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;
$_SESSION['role'] = $_SESSION['role'] ?? 'admin';

echo "<h1>Order Details Modal Check</h1>";
echo "<style>
body{font-family:sans-serif;padding:20px;} 
.pass{color:green;} 
.fail{color:red;} 
pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;}
.test-button{padding:10px 20px;background:#2563eb;color:white;border:none;cursor:pointer;margin:10px 0;}
</style>";

// Check 1: Does order have customer email?
echo "<h2>1. Check if order has customer email</h2>";

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id == 0) {
    // Find first order
    $find_order = $conn->query("SELECT order_id FROM orders LIMIT 1");
    if ($find_order && $find_order->num_rows > 0) {
        $order_id = $find_order->fetch_assoc()['order_id'];
        echo "<p>Using Order ID: $order_id (first order found)</p>";
        echo "<p><a href='?order_id=$order_id'>Reload with this order</a></p>";
    } else {
        echo "<p class='fail'>No orders found in database!</p>";
        exit;
    }
}

$order_sql = "SELECT 
    o.order_id,
    o.transaction_id,
    c.fullname as customer_name,
    u.email as customer_email,
    o.order_status
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN users u ON c.user_id = u.user_id
WHERE o.order_id = ?";

$stmt = $conn->prepare($order_sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($order) {
    echo "<p class='pass'>✓ Order found</p>";
    echo "<p>Customer: " . htmlspecialchars($order['customer_name']) . "</p>";
    echo "<p>Email: " . htmlspecialchars($order['customer_email'] ?? 'NO EMAIL') . "</p>";
    
    if (!empty($order['customer_email'])) {
        echo "<p class='pass'>✓ Email exists - button SHOULD appear</p>";
    } else {
        echo "<p class='fail'>✗ NO EMAIL - button will NOT appear</p>";
        echo "<p><strong>This is your problem!</strong> This order has no customer email.</p>";
    }
} else {
    echo "<p class='fail'>Order not found</p>";
    exit;
}

// Check 2: Test the actual get_order_details.php
echo "<h2>2. Load Order Details (like the modal does)</h2>";
echo "<div id='orderDetails' style='border:2px solid #ccc;padding:20px;margin:20px 0;'></div>";
echo "<button class='test-button' onclick='loadOrderDetails()'>Load Order Details</button>";

echo "<script>
function loadOrderDetails() {
    document.getElementById('orderDetails').innerHTML = 'Loading...';
    fetch('get_order_details.php?order_id=$order_id')
        .then(response => response.text())
        .then(html => {
            document.getElementById('orderDetails').innerHTML = html;
            
            // Check if button exists
            const emailButton = document.querySelector('.btn-email');
            if (emailButton) {
                console.log('✓ Email button found!');
                console.log('Button HTML:', emailButton.outerHTML);
                console.log('Button clickable:', !emailButton.disabled);
            } else {
                console.log('✗ Email button NOT found!');
            }
        })
        .catch(error => {
            document.getElementById('orderDetails').innerHTML = '<p style=\"color:red\">Error: ' + error + '</p>';
            console.error('Error loading order details:', error);
        });
}

// Auto-load on page load
window.addEventListener('DOMContentLoaded', loadOrderDetails);
</script>";

echo "<h2>3. Check JavaScript Console</h2>";
echo "<p>Open browser console (F12 → Console tab) and look for:</p>";
echo "<ul>";
echo "<li>✓ Email button found! - This is GOOD</li>";
echo "<li>✗ Email button NOT found! - This is the problem</li>";
echo "<li>Any JavaScript errors</li>";
echo "</ul>";

echo "<h2>4. Manual Test Email Function</h2>";
if (!empty($order['customer_email'])) {
    echo "<p>Try clicking this test button (it calls sendOrderEmail directly):</p>";
    echo "<button class='test-button' onclick='testSendEmail()'>Test Send Email Function</button>";
    echo "<div id='emailResult' style='margin-top:10px;'></div>";
    
    echo "<script>
    function testSendEmail() {
        document.getElementById('emailResult').innerHTML = 'Sending...';
        
        fetch('debug_send_order_email.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({order_id: $order_id})
        })
        .then(response => response.json())
        .then(data => {
            const color = data.success ? 'green' : 'red';
            document.getElementById('emailResult').innerHTML = 
                '<div style=\"color:' + color + ';padding:10px;border:1px solid ' + color + ';\">' +
                '<strong>Result:</strong> ' + JSON.stringify(data, null, 2) + 
                '</div>';
            
            if (data.success) {
                alert('Email sent! Check your Mailtrap inbox.');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            document.getElementById('emailResult').innerHTML = 
                '<p style=\"color:red\">Error: ' + error + '</p>';
        });
    }
    </script>";
} else {
    echo "<p class='fail'>Cannot test - no customer email for this order</p>";
}

echo "<h2>5. Check File Exists</h2>";
$files = ['get_order_details.php', 'debug_send_order_email.php', 'send_order_email.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p class='pass'>✓ $file exists</p>";
    } else {
        echo "<p class='fail'>✗ $file NOT found</p>";
    }
}

echo "<hr>";
echo "<h2>What to Look For:</h2>";
echo "<ol>";
echo "<li>Did the order details load above?</li>";
echo "<li>Do you see the 'Send Email' button in the Order Information section?</li>";
echo "<li>Check browser console - does it say '✓ Email button found'?</li>";
echo "<li>If button exists but not clickable, check if it's disabled (grey)</li>";
echo "<li>Try the 'Test Send Email Function' button</li>";
echo "</ol>";

echo "<h2>Common Reasons Button Not Clickable:</h2>";
echo "<ul>";
echo "<li>❌ Order has no customer email (check above)</li>";
echo "<li>❌ Button is disabled (check CSS)</li>";
echo "<li>❌ JavaScript error preventing click handler</li>";
echo "<li>❌ Modal CSS blocking clicks (z-index issue)</li>";
echo "<li>❌ Button not even rendered (email is empty)</li>";
echo "</ul>";
?>