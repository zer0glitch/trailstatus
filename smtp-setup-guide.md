# SMTP Email Setup Guide

The notification system has been updated to support SMTP authentication to fix email delivery issues.

## Problem
Your server's IP is not authorized to send email directly to Gmail and other major email providers. This is common with shared hosting and VPS setups.

## Solution
Configure SMTP authentication to use your hosting provider's mail server.

## Step 1: Get SMTP Settings from Your Hosting Provider

Contact your hosting provider (or check their documentation) for these SMTP settings:
- **SMTP Server**: Usually something like `mail.yourdomain.com` or `smtp.hostingprovider.com`
- **Port**: Usually 587 (TLS) or 465 (SSL)
- **Security**: TLS or SSL
- **Username**: Usually your email address
- **Password**: Your email password or app-specific password

## Step 2: Update Configuration

Edit `/includes/notifications.php` and update these lines:

```php
// SMTP Configuration - Update these with your hosting provider's SMTP settings
define('SMTP_ENABLED', true);  // Set to true to enable SMTP
define('SMTP_HOST', 'mail.zeroglitch.com');  // Your SMTP server
define('SMTP_PORT', 587);  // Your SMTP port
define('SMTP_SECURE', 'tls');  // 'tls', 'ssl', or false
define('SMTP_AUTH', true);  // Set to true for authentication
define('SMTP_USERNAME', 'noreply@zeroglitch.com');  // Your email username
define('SMTP_PASSWORD', 'YOUR_PASSWORD_HERE');  // Your email password
```

## Step 3: Common Hosting Provider Settings

### cPanel/Shared Hosting
```php
define('SMTP_HOST', 'mail.yourdomain.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'noreply@yourdomain.com');
define('SMTP_PASSWORD', 'your-email-password');
```

### Gmail (if allowed)
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'your-gmail@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');  // Use App Password, not regular password
```

### Office 365/Outlook
```php
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'your-email@yourdomain.com');
define('SMTP_PASSWORD', 'your-password');
```

## Step 4: Test Email Delivery

1. Update the SMTP settings in the configuration
2. Try changing a trail status in the admin panel
3. Check if notification emails are delivered
4. Check server logs for any SMTP errors

## Fallback Option

If SMTP doesn't work, you can disable it by setting:
```php
define('SMTP_ENABLED', false);
```

The system will fall back to the basic `mail()` function, but delivery to Gmail/Yahoo may still fail.

## Security Notes

- Never commit passwords to version control
- Consider using environment variables for sensitive data
- Use app-specific passwords when available
- Keep SMTP credentials secure

## Troubleshooting

1. **Connection Failed**: Check SMTP_HOST and SMTP_PORT
2. **Authentication Failed**: Verify SMTP_USERNAME and SMTP_PASSWORD
3. **TLS/SSL Errors**: Try changing SMTP_SECURE setting
4. **Still Bouncing**: Contact your hosting provider for proper SMTP settings

The notification system will automatically fall back to basic mail() if SMTP fails.
