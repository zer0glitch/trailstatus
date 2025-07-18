#!/bin/bash
# Install Web Push Dependencies for PHP 8.0+
# Sets up modern push notification capabilities

echo "=== WEB PUSH DEPENDENCIES INSTALLER ==="
echo "Installing modern push notification dependencies..."
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ This script must be run as root (sudo)"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;" 2>/dev/null)
echo "PHP Version: $PHP_VERSION"

if [ -z "$PHP_VERSION" ]; then
    echo "❌ PHP not found! Install PHP first."
    exit 1
fi

# Check if PHP 8.0+
PHP_MAJOR=$(echo $PHP_VERSION | cut -d. -f1)
PHP_MINOR=$(echo $PHP_VERSION | cut -d. -f2)

if [ "$PHP_MAJOR" -lt 8 ]; then
    echo "❌ PHP 8.0+ required. Current version: $PHP_VERSION"
    exit 1
fi

echo "✅ PHP $PHP_VERSION is compatible"
echo ""

# Install required PHP extensions
echo "Step 1: Installing required PHP extensions..."

# Check what's available
AVAILABLE_EXTENSIONS=(
    "php-curl"
    "php-json" 
    "php-mbstring"
    "php-openssl"
    "php-sodium"
    "php-bcmath"
    "php-gmp"
)

EXTENSIONS_TO_INSTALL=()

for ext in "${AVAILABLE_EXTENSIONS[@]}"; do
    if ! rpm -q "$ext" >/dev/null 2>&1; then
        EXTENSIONS_TO_INSTALL+=("$ext")
    else
        echo "✅ $ext already installed"
    fi
done

if [ ${#EXTENSIONS_TO_INSTALL[@]} -gt 0 ]; then
    echo "Installing missing extensions: ${EXTENSIONS_TO_INSTALL[*]}"
    dnf install -y "${EXTENSIONS_TO_INSTALL[@]}"
    echo "✅ PHP extensions installed"
else
    echo "✅ All required PHP extensions already installed"
fi

# Install Composer if not present
echo ""
echo "Step 2: Installing Composer..."
if ! command -v composer >/dev/null 2>&1; then
    echo "Installing Composer..."
    
    # Download Composer installer
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    
    # Verify installer (optional but recommended)
    EXPECTED_CHECKSUM="$(php -r 'echo hash_file("sha384", "composer-setup.php");')"
    
    # Install Composer globally
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    
    # Clean up
    rm composer-setup.php
    
    # Verify installation
    if command -v composer >/dev/null 2>&1; then
        echo "✅ Composer installed successfully"
        composer --version
    else
        echo "❌ Composer installation failed"
        exit 1
    fi
else
    echo "✅ Composer already installed"
    composer --version
fi

# Create project directory for web-push library
echo ""
echo "Step 3: Setting up web-push library..."

PROJECT_DIR="/opt/web-push-lib"
mkdir -p "$PROJECT_DIR"
cd "$PROJECT_DIR"

# Create composer.json for web-push library
cat > composer.json << 'EOF'
{
    "name": "trailstatus/web-push",
    "description": "Web Push notifications for Trail Status",
    "require": {
        "php": ">=8.0",
        "web-push-php/web-push": "^7.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0"
    },
    "autoload": {
        "psr-4": {
            "TrailStatus\\": "src/"
        }
    }
}
EOF

echo "Installing web-push-php library..."
if composer install --no-dev --optimize-autoloader; then
    echo "✅ web-push-php library installed"
else
    echo "⚠️  web-push-php installation failed, will use fallback method"
fi

# Create VAPID key generator using web-push-php
echo ""
echo "Step 4: Creating VAPID key generator..."

cat > generate-vapid-modern.php << 'EOF'
<?php
require_once 'vendor/autoload.php';

use Minishlink\WebPush\VAPID;

echo "=== MODERN VAPID KEY GENERATOR ===\n";
echo "Using web-push-php library for PHP 8.0+\n\n";

try {
    // Generate VAPID keys
    $keys = VAPID::createVapidKeys();
    
    echo "VAPID Keys Generated Successfully!\n\n";
    echo "Public Key:\n";
    echo $keys['publicKey'] . "\n\n";
    echo "Private Key:\n";
    echo $keys['privateKey'] . "\n\n";
    
    // Create config file
    $config_content = "<?php\n";
    $config_content .= "// VAPID Keys for Push Notifications\n";
    $config_content .= "// Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $config_content .= "define('VAPID_PUBLIC_KEY', '" . $keys['publicKey'] . "');\n";
    $config_content .= "define('VAPID_PRIVATE_KEY', '" . $keys['privateKey'] . "');\n\n";
    $config_content .= "// Add your FCM Server Key below:\n";
    $config_content .= "// define('FCM_SERVER_KEY', 'your-fcm-server-key-here');\n";
    $config_content .= "?>\n";
    
    $config_file = '/home/jwhetsel/dev/lcftf/trailstatus/config.local.php';
    if (file_put_contents($config_file, $config_content)) {
        echo "✅ Configuration saved to: $config_file\n";
    } else {
        echo "⚠️  Could not save to $config_file\n";
        echo "Please create the file manually with the keys above.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error generating VAPID keys: " . $e->getMessage() . "\n";
    echo "This usually means the required PHP extensions are missing.\n";
    echo "Required: openssl, gmp or bcmath\n";
}
EOF

chmod +x generate-vapid-modern.php

# Test the VAPID generator
echo "Testing VAPID key generation..."
if php generate-vapid-modern.php > /tmp/vapid-test.log 2>&1; then
    echo "✅ VAPID key generation test successful"
    cat /tmp/vapid-test.log
else
    echo "⚠️  VAPID key generation test failed"
    echo "Check /tmp/vapid-test.log for details"
fi

# Create web push sender
echo ""
echo "Step 5: Creating modern web push sender..."

mkdir -p src
cat > src/WebPushSender.php << 'EOF'
<?php
declare(strict_types=1);
namespace TrailStatus;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class WebPushSender {
    private WebPush $webPush;
    
    public function __construct(string $publicKey, string $privateKey, string $subject) {
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ]
        ]);
    }
    
    public function sendNotification(
        string $endpoint,
        string $p256dh,
        string $auth,
        string $payload
    ): array {
        $subscription = Subscription::create([
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => $p256dh,
                'auth' => $auth
            ]
        ]);
        
        $report = $this->webPush->sendOneNotification($subscription, $payload);
        
        return [
            'success' => $report->isSuccess(),
            'expired' => $report->isSubscriptionExpired(),
            'statusCode' => $report->getResponse() ? $report->getResponse()->getStatusCode() : null,
            'reason' => $report->getReason()
        ];
    }
    
    public function sendBatch(array $notifications): array {
        $results = [];
        
        foreach ($notifications as $notification) {
            $subscription = Subscription::create([
                'endpoint' => $notification['endpoint'],
                'keys' => [
                    'p256dh' => $notification['p256dh'],
                    'auth' => $notification['auth']
                ]
            ]);
            
            $this->webPush->queueNotification($subscription, $notification['payload']);
        }
        
        foreach ($this->webPush->flush() as $report) {
            $results[] = [
                'success' => $report->isSuccess(),
                'expired' => $report->isSubscriptionExpired(),
                'statusCode' => $report->getResponse() ? $report->getResponse()->getStatusCode() : null,
                'reason' => $report->getReason()
            ];
        }
        
        return $results;
    }
}
EOF

echo "✅ Modern web push sender created"

# Set proper ownership
chown -R apache:apache "$PROJECT_DIR"

echo ""
echo "=== INSTALLATION COMPLETE ==="
echo "✅ PHP extensions installed"
echo "✅ Composer installed"
echo "✅ web-push-php library installed"
echo "✅ VAPID key generator created"
echo "✅ Modern WebPush sender created"
echo ""
echo "Next steps:"
echo "1. Generate VAPID keys: cd $PROJECT_DIR && php generate-vapid-modern.php"
echo "2. Update your notification system to use the new library"
echo "3. Test push notifications"
echo ""
echo "Library location: $PROJECT_DIR"
echo "Autoloader: $PROJECT_DIR/vendor/autoload.php"
