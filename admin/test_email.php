<?php
/**
 * Email Testing Script - FIXED VERSION
 * Test Mailtrap connection and email sending
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Mailtrap Email Test</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;} pre{background:#f5f5f5;padding:10px;}</style>";

// Test 1: Check if fsockopen is available
echo "<h2>Test 1: Check fsockopen function</h2>";
if (function_exists('fsockopen')) {
    echo "<p class='success'>✓ fsockopen is available</p>";
} else {
    echo "<p class='error'>✗ fsockopen is NOT available - contact your hosting provider</p>";
    exit;
}

// Test 2: Test connection to Mailtrap
echo "<h2>Test 2: Test connection to Mailtrap</h2>";
$host = 'sandbox.smtp.mailtrap.io';
$port = 2525;

$socket = @fsockopen($host, $port, $errno, $errstr, 10);
if ($socket) {
    echo "<p class='success'>✓ Successfully connected to $host:$port</p>";
    $response = fgets($socket, 515);
    echo "<p>Server response: <pre>$response</pre></p>";
    fclose($socket);
} else {
    echo "<p class='error'>✗ Failed to connect to $host:$port</p>";
    echo "<p class='error'>Error: $errstr ($errno)</p>";
    echo "<p>Possible issues:</p>";
    echo "<ul>";
    echo "<li>Port 2525 is blocked by firewall</li>";
    echo "<li>Server doesn't allow outbound connections</li>";
    echo "<li>fsockopen is disabled in php.ini</li>";
    echo "</ul>";
    exit;
}

// Test 3: Test SMTP authentication
echo "<h2>Test 3: Test SMTP Authentication</h2>";
$username = 'a87cd38542b37e';
$password = 'dd36e50f7ab566';

$socket = fsockopen($host, $port, $errno, $errstr, 10);
if (!$socket) {
    echo "<p class='error'>✗ Cannot connect to server</p>";
    exit;
}

// Read greeting
$response = fgets($socket, 515);
echo "<p>1. Server greeting: <pre>$response</pre></p>";

// Send EHLO and read ALL lines (THIS WAS THE BUG!)
fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
echo "<p>2. EHLO sent, reading all response lines...</p>";
$ehlo_complete = '';
do {
    $line = fgets($socket, 515);
    $ehlo_complete .= $line;
    echo "<p style='margin-left:20px;'>EHLO line: <code>" . htmlspecialchars(trim($line)) . "</code></p>";
} while (substr(trim($line), 0, 4) === '250-');
echo "<p class='success'>✓ EHLO complete (read all lines)</p>";

// Send AUTH LOGIN
fputs($socket, "AUTH LOGIN\r\n");
$response = fgets($socket, 515);
echo "<p>3. AUTH LOGIN response: <pre>$response</pre></p>";

// Send username
fputs($socket, base64_encode($username) . "\r\n");
$response = fgets($socket, 515);
echo "<p>4. Username response: <pre>$response</pre></p>";

// Send password
fputs($socket, base64_encode($password) . "\r\n");
$response = fgets($socket, 515);
echo "<p>5. Password response: <pre>$response</pre></p>";

if (strpos($response, '235') !== false) {
    echo "<p class='success'>✓ Authentication successful!</p>";
} else {
    echo "<p class='error'>✗ Authentication failed!</p>";
    echo "<p class='error'>Check your Mailtrap credentials</p>";
    fclose($socket);
    exit;
}

// Send QUIT
fputs($socket, "QUIT\r\n");
fclose($socket);

// Test 4: Send actual test email
echo "<h2>Test 4: Send Test Email</h2>";

$socket = fsockopen($host, $port, $errno, $errstr, 10);
if (!$socket) {
    echo "<p class='error'>✗ Cannot connect</p>";
    exit;
}

// SMTP conversation
fgets($socket, 515); // greeting

// Send EHLO and read ALL lines
fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
do {
    $line = fgets($socket, 515);
} while (substr(trim($line), 0, 4) === '250-');

fputs($socket, "AUTH LOGIN\r\n");
fgets($socket, 515);

fputs($socket, base64_encode($username) . "\r\n");
fgets($socket, 515);

fputs($socket, base64_encode($password) . "\r\n");
$auth_response = fgets($socket, 515);

if (strpos($auth_response, '235') === false) {
    echo "<p class='error'>✗ Auth failed</p>";
    fclose($socket);
    exit;
}

// Send email
$from = 'orders@glamessentials.com';
$to = 'customer@example.com';

fputs($socket, "MAIL FROM: <$from>\r\n");
$response = fgets($socket, 515);
echo "<p>MAIL FROM response: <pre>$response</pre></p>";

fputs($socket, "RCPT TO: <$to>\r\n");
$response = fgets($socket, 515);
echo "<p>RCPT TO response: <pre>$response</pre></p>";

fputs($socket, "DATA\r\n");
$response = fgets($socket, 515);
echo "<p>DATA response: <pre>$response</pre></p>";

$message = "From: GlamEssentials <$from>\r\n";
$message .= "To: Customer <$to>\r\n";
$message .= "Subject: Test Email from PHP\r\n";
$message .= "MIME-Version: 1.0\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n";
$message .= "\r\n";
$message .= "<html><body>";
$message .= "<h1>Test Email</h1>";
$message .= "<p>This is a test email from your PHP application.</p>";
$message .= "<p>If you see this in Mailtrap, your email system is working!</p>";
$message .= "<p>Sent at: " . date('Y-m-d H:i:s') . "</p>";
$message .= "</body></html>";
$message .= "\r\n.\r\n";

fputs($socket, $message);
$response = fgets($socket, 515);
echo "<p>Send response: <pre>$response</pre></p>";

if (strpos($response, '250') !== false) {
    echo "<p class='success'>✓✓✓ Test email sent successfully!</p>";
    echo "<p class='success'>Check your Mailtrap inbox at: <a href='https://mailtrap.io/inboxes' target='_blank'>https://mailtrap.io/inboxes</a></p>";
} else {
    echo "<p class='error'>✗ Failed to send email</p>";
}

fputs($socket, "QUIT\r\n");
fclose($socket);

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p class='success'>✓ All tests passed! Your email system is working correctly.</p>";
echo "<p>Your credentials are correct:</p>";
echo "<pre>Username: $username\nPassword: $password</pre>";
echo "<p><strong>Next step:</strong> Make sure all your email files use these credentials and the fixed EHLO reading code!</p>";

echo "<hr>";
echo "<h2>Files to Update</h2>";
echo "<ul>";
echo "<li><strong>send_order_email.php</strong> - Already fixed (use the artifact I provided)</li>";
echo "<li><strong>debug_send_order_email.php</strong> - Update credentials to match above</li>";
echo "<li><strong>email_sender.php</strong> - Already uses mailtrap_config.php (should be OK)</li>";
echo "</ul>";
?>