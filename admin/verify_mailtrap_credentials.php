<?php
/**
 * Mailtrap Credential Verification Script
 * Tests different credential combinations to find the correct one
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Mailtrap Credential Verification</h1>";
echo "<style>
body{font-family:sans-serif;padding:20px;} 
.success{color:green;font-weight:bold;} 
.error{color:red;} 
pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;margin:10px 0;}
table{border-collapse:collapse;margin:20px 0;}
td,th{border:1px solid #ddd;padding:10px;text-align:left;}
th{background:#f0f0f0;}
</style>";

$host = 'sandbox.smtp.mailtrap.io';
$port = 2525;

// Test both credential sets
$credential_sets = [
    [
        'name' => 'From mailtrap_config.php',
        'username' => 'a87cd38542b37e',
        'password' => 'dd36e50f7ab566'
    ],
    [
        'name' => 'From debug_send_order_email.php',
        'username' => '782ba9dc97ca33',
        'password' => 'e07cd002a82e4f'
    ]
];

echo "<h2>Testing Credential Sets</h2>";
echo "<table>";
echo "<tr><th>Source</th><th>Username</th><th>Status</th><th>Details</th></tr>";

foreach ($credential_sets as $cred) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($cred['name']) . "</td>";
    echo "<td><code>" . htmlspecialchars($cred['username']) . "</code></td>";
    
    // Test connection
    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) {
        echo "<td class='error'>Connection Failed</td>";
        echo "<td>" . htmlspecialchars($errstr) . "</td>";
        echo "</tr>";
        continue;
    }
    
    // Read greeting
    $response = fgets($socket, 515);
    
    // Send EHLO
    fputs($socket, "EHLO localhost\r\n");
    // Read ALL EHLO lines
    do {
        $line = fgets($socket, 515);
    } while (substr(trim($line), 0, 4) === '250-');
    
    // Send AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket, 515);
    
    // Send username
    fputs($socket, base64_encode($cred['username']) . "\r\n");
    $response = fgets($socket, 515);
    
    // Send password
    fputs($socket, base64_encode($cred['password']) . "\r\n");
    $response = fgets($socket, 515);
    
    // Send QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    // Check if authentication succeeded
    if (strpos($response, '235') !== false) {
        echo "<td class='success'>✓ SUCCESS</td>";
        echo "<td class='success'>Authentication successful!</td>";
    } else {
        echo "<td class='error'>✗ FAILED</td>";
        echo "<td class='error'>" . htmlspecialchars(trim($response)) . "</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h2>How to Get Fresh Credentials from Mailtrap</h2>";
echo "<ol>";
echo "<li>Go to <a href='https://mailtrap.io/signin' target='_blank'>https://mailtrap.io/signin</a></li>";
echo "<li>Log in to your account</li>";
echo "<li>Click on 'Email Testing' in the left sidebar</li>";
echo "<li>Select your inbox (or create a new one)</li>";
echo "<li>Click on 'SMTP Settings'</li>";
echo "<li>Copy the credentials shown there</li>";
echo "</ol>";

echo "<h2>Manual Test</h2>";
echo "<p>Enter your Mailtrap credentials to test them:</p>";

if (isset($_POST['test_username']) && isset($_POST['test_password'])) {
    $test_user = $_POST['test_username'];
    $test_pass = $_POST['test_password'];
    
    echo "<h3>Testing Custom Credentials</h3>";
    echo "<p>Username: <code>" . htmlspecialchars($test_user) . "</code></p>";
    
    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) {
        echo "<p class='error'>✗ Cannot connect to server: $errstr</p>";
    } else {
        // Connection test
        $response = fgets($socket, 515);
        echo "<p>1. Connected to server</p>";
        
        // EHLO
        fputs($socket, "EHLO localhost\r\n");
        do {
            $line = fgets($socket, 515);
        } while (substr(trim($line), 0, 4) === '250-');
        echo "<p>2. EHLO sent</p>";
        
        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        echo "<p>3. AUTH LOGIN: " . htmlspecialchars(trim($response)) . "</p>";
        
        // Username
        fputs($socket, base64_encode($test_user) . "\r\n");
        $response = fgets($socket, 515);
        echo "<p>4. Username sent: " . htmlspecialchars(trim($response)) . "</p>";
        
        // Password
        fputs($socket, base64_encode($test_pass) . "\r\n");
        $response = fgets($socket, 515);
        echo "<p>5. Password sent: " . htmlspecialchars(trim($response)) . "</p>";
        
        if (strpos($response, '235') !== false) {
            echo "<p class='success'>✓✓✓ AUTHENTICATION SUCCESSFUL! ✓✓✓</p>";
            echo "<p class='success'>Use these credentials in your mailtrap_config.php:</p>";
            echo "<pre>";
            echo "define('MAILTRAP_USERNAME', '" . htmlspecialchars($test_user) . "');\n";
            echo "define('MAILTRAP_PASSWORD', '" . htmlspecialchars($test_pass) . "');";
            echo "</pre>";
        } else {
            echo "<p class='error'>✗ Authentication failed</p>";
            echo "<p class='error'>The credentials you entered are incorrect or expired.</p>";
        }
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);
    }
}

echo "<form method='POST' style='background:#f9f9f9;padding:20px;border:1px solid #ddd;margin:20px 0;'>";
echo "<div style='margin-bottom:15px;'>";
echo "<label>Mailtrap Username:<br>";
echo "<input type='text' name='test_username' style='width:300px;padding:8px;font-family:monospace;' placeholder='e.g., 782ba9dc97ca33' required>";
echo "</label>";
echo "</div>";
echo "<div style='margin-bottom:15px;'>";
echo "<label>Mailtrap Password:<br>";
echo "<input type='text' name='test_password' style='width:300px;padding:8px;font-family:monospace;' placeholder='e.g., e07cd002a82e4f' required>";
echo "</label>";
echo "</div>";
echo "<button type='submit' style='padding:10px 20px;background:#2563eb;color:white;border:none;cursor:pointer;'>Test These Credentials</button>";
echo "</form>";

echo "<hr>";
echo "<h2>Important Notes</h2>";
echo "<ul>";
echo "<li><strong>Mailtrap credentials can expire</strong> if you regenerate them or reset your inbox</li>";
echo "<li>Each inbox has its own unique credentials</li>";
echo "<li>Make sure you're copying from the correct inbox in Mailtrap</li>";
echo "<li>Check if you have multiple Mailtrap accounts</li>";
echo "</ul>";

echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>Find which credential set works above (if any)</li>";
echo "<li>Or enter fresh credentials from Mailtrap in the form</li>";
echo "<li>Update <strong>mailtrap_config.php</strong> with the correct credentials</li>";
echo "<li>Update <strong>debug_send_order_email.php</strong> to use the same credentials</li>";
echo "<li>Test sending an email again</li>";
echo "</ol>";
?>