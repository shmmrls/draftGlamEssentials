<?php
/**
 * Mailtrap Email Configuration
 * 
 * Instructions:
 * 1. Sign up for a free account at https://mailtrap.io
 * 2. Go to Email Testing > Inboxes > Your Inbox
 * 3. Select "SMTP Settings" and choose "PHP"
 * 4. Copy your credentials below
 */

// Mailtrap SMTP Settings
define('MAILTRAP_HOST', 'sandbox.smtp.mailtrap.io');
define('MAILTRAP_PORT', 2525);
define('MAILTRAP_USERNAME', 'a87cd38542b37e');
define('MAILTRAP_PASSWORD', 'dd36e50f7ab566');
// Email Settings
define('EMAIL_FROM_ADDRESS', 'orders@glamessentialscompany.com');
define('EMAIL_FROM_NAME', 'GlamEssentials');

// Email Templates Settings
define('STORE_NAME', 'GlamEssentials');
define('STORE_SUPPORT_EMAIL', 'support@glamessentialscompany.com');
define('STORE_PHONE', '+1 (555) 123-4567');

?>