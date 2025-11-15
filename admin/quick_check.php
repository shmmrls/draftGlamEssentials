<?php
session_start();
require_once('../includes/config.php');

echo "<h1>Quick Email System Check</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .pass{color:green;} .fail{color:red;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;}</style>";

// Check 1: Session
echo "<h2>1. Session Check</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p class='pass'>✓ User logged in: ID = " . $_SESSION['user_id'] . "</p>";
    echo "<p class='pass'>✓ Role: " . ($_SESSION['role'] ?? 'NOT SET') . "</p>";
} else {
    echo "<p class='fail'>✗ User NOT logged in</p>";
    echo "<p>You must be logged in as admin to send emails.</p>";
}

// Check 2: Database - Find an order with email
echo "<h2>2. Database Check - Orders with Email</h2>";
$sql = "SELECT 
    o.order_id,
    o.transaction_id,
    c.fullname as customer_name,
    u.email as customer_email,
    o.order_status
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN users u ON c.user_id = u.user_id
WHERE u.email IS NOT NULL
LIMIT 5";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    echo "<p class='pass'>✓ Found orders with customer emails:</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>Order ID</th><th>Customer</th><th>Email</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['order_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_email']) . "</td>";
        echo "<td>" . $row['order_status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><strong>You can test with any of these order IDs.</strong></p>";
} else {
    echo "<p class='fail'>✗ No orders found with customer emails</p>";
    echo "<p>This is the problem! Orders don't have customer emails.</p>";
    
    // Check if users have emails at all
    $user_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE email IS NOT NULL AND email != ''");
    $user_row = $user_check->fetch_assoc();
    echo "<p>Users with email addresses: " . $user_row['count'] . "</p>";
}

// Check 3: Email logs table
echo "<h2>3. Email Logs Table Check</h2>";
$table_check = $conn->query("SHOW TABLES LIKE 'email_logs'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<p class='pass'>✓ email_logs table exists</p>";
    
    $log_count = $conn->query("SELECT COUNT(*) as count FROM email_logs");
    $log_row = $log_count->fetch_assoc();
    echo "<p>Total emails logged: " . $log_row['count'] . "</p>";
    
    if ($log_row['count'] > 0) {
        $recent = $conn->query("SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 3");
        echo "<p>Recent email logs:</p>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>Order ID</th><th>Recipient</th><th>Subject</th><th>Sent At</th><th>Status</th></tr>";
        while ($log = $recent->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $log['order_id'] . "</td>";
            echo "<td>" . htmlspecialchars($log['recipient_email']) . "</td>";
            echo "<td>" . htmlspecialchars($log['subject']) . "</td>";
            echo "<td>" . $log['sent_at'] . "</td>";
            echo "<td>" . $log['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p class='fail'>✗ email_logs table does NOT exist</p>";
    echo "<p>Run this SQL to create it:</p>";
    echo "<pre>CREATE TABLE email_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent', 'failed') DEFAULT 'sent',
    error_message TEXT NULL
);</pre>";
}

// Check 4: SMTP Connection
echo "<h2>4. SMTP Connection Test</h2>";
$host = 'sandbox.smtp.mailtrap.io';
$port = 2525;

$socket = @fsockopen($host, $port, $errno, $errstr, 10);
if ($socket) {
    echo "<p class='pass'>✓ Can connect to $host:$port</p>";
    $response = fgets($socket, 515);
    echo "<p>Server says: <code>" . htmlspecialchars(trim($response)) . "</code></p>";
    fclose($socket);
} else {
    echo "<p class='fail'>✗ Cannot connect to $host:$port</p>";
    echo "<p class='fail'>Error: $errstr ($errno)</p>";
    echo "<p><strong>This is a problem!</strong> Your server cannot reach Mailtrap.</p>";
    echo "<p>Try alternative port 587:</p>";
    
    $socket = @fsockopen($host, 587, $errno, $errstr, 10);
    if ($socket) {
        echo "<p class='pass'>✓ Port 587 works! Use this port instead.</p>";
        fclose($socket);
    } else {
        echo "<p class='fail'>✗ Port 587 also blocked</p>";
    }
}

// Check 5: File permissions
echo "<h2>5. File Check</h2>";
$files_to_check = [
    'send_order_email.php',
    'debug_send_order_email.php',
    'email_sender.php',
    'get_order_details.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<p class='pass'>✓ $file exists</p>";
    } else {
        echo "<p class='fail'>✗ $file NOT found</p>";
    }
}

// Check if log file exists
if (file_exists('email_debug.log')) {
    echo "<p class='pass'>✓ email_debug.log exists</p>";
    $log_content = file_get_contents('email_debug.log');
    if (!empty($log_content)) {
        echo "<p>Last 1000 characters of log:</p>";
        echo "<pre>" . htmlspecialchars(substr($log_content, -1000)) . "</pre>";
    } else {
        echo "<p>Log file is empty (no emails attempted yet)</p>";
    }
} else {
    echo "<p>email_debug.log does not exist yet (will be created on first email attempt)</p>";
}

// Check 6: JavaScript file
echo "<h2>6. JavaScript Validation File</h2>";
if (file_exists('../assets/js/form-validation.js')) {
    echo "<p class='pass'>✓ form-validation.js exists</p>";
} else {
    echo "<p class='fail'>✗ form-validation.js NOT found at ../assets/js/</p>";
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>If all checks pass above, your email system should work.</p>";
echo "<p>If any checks fail, fix those issues first.</p>";

echo "<hr>";
echo "<h2>Manual Test</h2>";
echo "<p>Want to test sending an email right now?</p>";

if (isset($_GET['test_order_id'])) {
    $test_order_id = (int)$_GET['test_order_id'];
    echo "<h3>Testing Order ID: $test_order_id</h3>";
    
    // Fetch order
    $test_sql = "SELECT 
        o.order_id,
        c.fullname,
        u.email
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE o.order_id = ?";
    
    $stmt = $conn->prepare($test_sql);
    $stmt->bind_param("i", $test_order_id);
    $stmt->execute();
    $test_order = $stmt->get_result()->fetch_assoc();
    
    if ($test_order && !empty($test_order['email'])) {
        echo "<p>Order found! Customer: " . htmlspecialchars($test_order['fullname']) . "</p>";
        echo "<p>Email: " . htmlspecialchars($test_order['email']) . "</p>";
        
        echo "<p><strong>Now run this in your browser console:</strong></p>";
        echo "<pre>fetch('debug_send_order_email.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({order_id: $test_order_id})
}).then(r => r.json()).then(d => console.log(d));</pre>";
        
        echo "<p>Or click this button:</p>";
        echo "<button onclick=\"testEmail($test_order_id)\">Send Test Email</button>";
        echo "<div id='result'></div>";
        
        echo "<script>
        function testEmail(orderId) {
            document.getElementById('result').innerHTML = 'Sending...';
            fetch('debug_send_order_email.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({order_id: orderId})
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(err => {
                document.getElementById('result').innerHTML = '<p style=\"color:red\">Error: ' + err + '</p>';
            });
        }
        </script>";
    } else {
        echo "<p class='fail'>Order not found or has no email</p>";
    }
} else {
    // Show available orders
    $orders_sql = "SELECT o.order_id FROM orders o
                   LEFT JOIN customers c ON o.customer_id = c.customer_id
                   LEFT JOIN users u ON c.user_id = u.user_id
                   WHERE u.email IS NOT NULL
                   LIMIT 1";
    $orders_result = $conn->query($orders_sql);
    if ($orders_result && $orders_result->num_rows > 0) {
        $first_order = $orders_result->fetch_assoc();
        echo "<p><a href='?test_order_id=" . $first_order['order_id'] . "'>Click here to test with Order #" . $first_order['order_id'] . "</a></p>";
    }
}
?>