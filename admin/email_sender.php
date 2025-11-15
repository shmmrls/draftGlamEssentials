<?php
/**
 * Reusable Email Sender Function
 * Can be included in any file that needs to send order emails
 */

/**
 * Send order email to customer
 * 
 * @param mysqli $conn Database connection
 * @param int $order_id Order ID to send email for
 * @return array Response with success status and message
 */
function sendOrderEmailNotification($conn, $order_id) {
    // Fetch order details with customer email
    $order_sql = "SELECT 
        o.order_id,
        o.transaction_id,
        o.customer_id,
        c.fullname as customer_name,
        c.address as customer_address,
        c.contact_no as customer_contact,
        o.shipping_name,
        o.shipping_address,
        o.shipping_contact,
        o.payment_method,
        o.payment_status,
        o.order_status,
        o.total_amount,
        o.order_date,
        u.email as customer_email
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE o.order_id = ?";

    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->bind_param("i", $order_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order = $order_result->fetch_assoc();
    $order_stmt->close();

    if (!$order) {
        return ['success' => false, 'message' => 'Order not found'];
    }

    if (empty($order['customer_email'])) {
        return ['success' => false, 'message' => 'Customer email not found'];
    }

    // Fetch order items
    $items_sql = "SELECT 
        oi.order_item_id,
        oi.product_id,
        p.product_name,
        p.main_img_name,
        oi.quantity,
        oi.price,
        oi.subtotal
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?";

    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $items = $items_result->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();

    // Build email HTML
    $email_html = buildOrderEmailHTML($order, $items);

    // Mailtrap SMTP Configuration
    require_once __DIR__ . '/mailtrap_config.php';
    $mailtrap_host = MAILTRAP_HOST;
    $mailtrap_port = MAILTRAP_PORT;
    $mailtrap_username = MAILTRAP_USERNAME;
    $mailtrap_password = MAILTRAP_PASSWORD;
    $from_email = EMAIL_FROM_ADDRESS;
    $from_name = EMAIL_FROM_NAME;

    // Send email
    try {
        $to = $order['customer_email'];
        $subject = getEmailSubject($order);
        
        $result = sendSMTPEmail(
            $mailtrap_host,
            $mailtrap_port,
            $mailtrap_username,
            $mailtrap_password,
            $from_email,
            $from_name,
            $to,
            $order['customer_name'],
            $subject,
            $email_html
        );
        
        if ($result) {
            // Log the email sent (optional - create table if needed)
            try {
                $log_stmt = $conn->prepare("INSERT INTO email_logs (order_id, recipient_email, subject, sent_at, status) VALUES (?, ?, ?, NOW(), 'sent')");
                if ($log_stmt) {
                    $log_stmt->bind_param("iss", $order_id, $to, $subject);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
            } catch (Exception $e) {
                // Silently fail if table doesn't exist
                error_log("Email log warning: " . $e->getMessage());
            }
            
            return ['success' => true, 'message' => 'Email sent successfully to ' . $to];
        } else {
            return ['success' => false, 'message' => 'Failed to send email via SMTP'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Build HTML email template
 */
function buildOrderEmailHTML($order, $items) {
    $order_status_color = getStatusColor($order['order_status']);
    $payment_status_color = getStatusColor($order['payment_status']);
    
    $items_html = '';
    foreach ($items as $item) {
        $items_html .= '
        <tr>
            <td style="padding: 15px; border-bottom: 1px solid #e5e7eb;">
                <div style="font-size: 14px; font-weight: 500; color: #111827; margin-bottom: 5px;">
                    ' . htmlspecialchars($item['product_name']) . '
                </div>
                <div style="font-size: 12px; color: #6b7280;">
                    Qty: ' . $item['quantity'] . ' &times; &#8369;' . number_format($item['price'], 2) . '
                </div>
            </td>
            <td style="padding: 15px; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600; color: #166534;">
                &#8369;' . number_format($item['subtotal'], 2) . '
            </td>
        </tr>';
    }
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Update - #' . $order['order_id'] . '</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 20px;">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <tr>
                            <td style="background: linear-gradient(135deg, #0a0a0a 0%, #2a2a2a 100%); padding: 40px; text-align: center;">
                                <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 300; letter-spacing: 2px;">
                                    GLAMESSENTIALS
                                </h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 40px;">
                                <h2 style="margin: 0 0 20px 0; color: #111827; font-size: 24px; font-weight: 400;">
                                    Order Update
                                </h2>
                                <p style="margin: 0 0 30px 0; color: #4b5563; font-size: 14px; line-height: 1.6;">
                                    Hello ' . htmlspecialchars($order['customer_name']) . ',<br><br>
                                    Your order status has been updated. Here are the details:
                                </p>
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
                                    <tr>
                                        <td style="padding: 15px; background-color: #f9fafb; border-left: 3px solid #0a0a0a;">
                                            <table width="100%" cellpadding="5" cellspacing="0">
                                                <tr>
                                                    <td style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Order ID</td>
                                                    <td style="color: #111827; font-size: 14px; font-weight: 600; text-align: right;">#' . $order['order_id'] . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Transaction ID</td>
                                                    <td style="color: #111827; font-size: 14px; text-align: right; font-family: monospace;">' . htmlspecialchars($order['transaction_id']) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Order Status</td>
                                                    <td style="text-align: right;">
                                                        <span style="display: inline-block; padding: 5px 12px; background-color: ' . $order_status_color['bg'] . '; color: ' . $order_status_color['text'] . '; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; border-radius: 3px;">
                                                            ' . htmlspecialchars($order['order_status']) . '
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Payment Status</td>
                                                    <td style="text-align: right;">
                                                        <span style="display: inline-block; padding: 5px 12px; background-color: ' . $payment_status_color['bg'] . '; color: ' . $payment_status_color['text'] . '; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; border-radius: 3px;">
                                                            ' . htmlspecialchars($order['payment_status']) . '
                                                        </span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                                <h3 style="margin: 0 0 15px 0; color: #111827; font-size: 16px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                    Order Items
                                </h3>
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px; border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                                    ' . $items_html . '
                                    <tr>
                                        <td style="padding: 20px; text-align: right; font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">
                                            Total Amount
                                        </td>
                                        <td style="padding: 20px; text-align: right; font-size: 18px; font-weight: 700; color: #0a0a0a;">
                                            &#8369;' . number_format($order['total_amount'], 2) . '
                                        </td>
                                    </tr>
                                </table>
                                <h3 style="margin: 0 0 15px 0; color: #111827; font-size: 16px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                    Shipping Information
                                </h3>
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
                                    <tr>
                                        <td style="padding: 15px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px;">
                                            <div style="font-size: 14px; color: #111827; margin-bottom: 5px; font-weight: 600;">
                                                ' . htmlspecialchars($order['shipping_name']) . '
                                            </div>
                                            <div style="font-size: 13px; color: #4b5563; margin-bottom: 3px;">
                                                ' . htmlspecialchars($order['shipping_address']) . '
                                            </div>
                                            <div style="font-size: 13px; color: #4b5563;">
                                                Contact: ' . htmlspecialchars($order['shipping_contact']) . '
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin: 30px 0 0 0; color: #6b7280; font-size: 13px; line-height: 1.6;">
                                    If you have any questions about your order, please don\'t hesitate to contact us.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 30px; background-color: #f9fafb; text-align: center; border-top: 1px solid #e5e7eb;">
                                <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 12px;">
                                    Thank you for shopping with us!
                                </p>
                                <p style="margin: 0; color: #9ca3af; font-size: 11px;">
                                    &copy; ' . date('Y') . ' GlamEssentials. All rights reserved.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
    
    return $html;
}

/**
 * Get email subject based on order status
 */
function getEmailSubject($order) {
    $status = $order['order_status'];
    $order_id = $order['order_id'];
    
    switch ($status) {
        case 'Shipped':
            return "Your Order #{$order_id} Has Been Shipped!";
        case 'Delivered':
            return "Your Order #{$order_id} Has Been Delivered!";
        case 'Cancelled':
            return "Order #{$order_id} Cancellation Notice";
        default:
            return "Order #{$order_id} Status Update";
    }
}

/**
 * Get status badge colors
 */
function getStatusColor($status) {
    $colors = [
        'Pending' => ['bg' => '#fef3c7', 'text' => '#92400e'],
        'Shipped' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
        'Delivered' => ['bg' => '#dcfce7', 'text' => '#166534'],
        'Cancelled' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
        'Paid' => ['bg' => '#dcfce7', 'text' => '#166534'],
        'Refunded' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
    ];
    
    return $colors[$status] ?? ['bg' => '#f3f4f6', 'text' => '#374151'];
}

/**
 * Send email via SMTP (Mailtrap)
 */
function sendSMTPEmail($host, $port, $username, $password, $from_email, $from_name, $to_email, $to_name, $subject, $html_body) {
    try {
        // Connect to SMTP server
        $socket = @fsockopen($host, $port, $errno, $errstr, 30);
        if (!$socket) {
            error_log("SMTP Connection Error: $errstr ($errno)");
            return false;
        }
        
        // Read greeting
        $response = fgets($socket, 515);
        
        // Send EHLO
        fputs($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
        
        // Read all EHLO response lines (multi-line response)
        do {
            $response = fgets($socket, 515);
        } while (substr(trim($response), 0, 3) === '250' && substr(trim($response), 3, 1) === '-');
        
        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '334') === false) {
            error_log("AUTH LOGIN failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send username
        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '334') === false) {
            error_log("Username auth failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send password
        fputs($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '235') === false) {
            error_log("Password auth failed: $response");
            fclose($socket);
            return false;
        }
        
        // MAIL FROM
        fputs($socket, "MAIL FROM: <{$from_email}>\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '250') === false) {
            error_log("MAIL FROM failed: $response");
            fclose($socket);
            return false;
        }
        
        // RCPT TO
        fputs($socket, "RCPT TO: <{$to_email}>\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '250') === false) {
            error_log("RCPT TO failed: $response");
            fclose($socket);
            return false;
        }
        
        // DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        
        if (strpos($response, '354') === false) {
            error_log("DATA command failed: $response");
            fclose($socket);
            return false;
        }
        
        // Build message
        $message = "From: {$from_name} <{$from_email}>\r\n";
        $message .= "To: {$to_name} <{$to_email}>\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "\r\n";
        $message .= $html_body;
        $message .= "\r\n.\r\n";
        
        // Send message
        fputs($socket, $message);
        $response = fgets($socket, 515);
        
        // QUIT
        fputs($socket, "QUIT\r\n");
        fgets($socket, 515);
        
        fclose($socket);
        
        // Check if successful
        if (strpos($response, '250') !== false) {
            return true;
        } else {
            error_log("Email send failed: $response");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("SMTP Exception: " . $e->getMessage());
        return false;
    }
}
?>