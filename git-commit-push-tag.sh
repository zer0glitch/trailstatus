#!/bin/bash
# Git commit, push, and tag script for LCFTF Trail Status project

echo "=== GIT COMMIT, PUSH, AND TAG ==="
echo "Preparing to commit LCFTF Trail Status website changes..."
echo ""

# Check git status
echo "1. Current git status:"
git status --porcelain

echo ""
echo "2. Adding all changes:"
git add .

echo ""
echo "3. Showing what will be committed:"
git diff --cached --name-only

echo ""
echo "4. Creating commit with comprehensive message:"
git commit -m "Upgrade push notifications and finalize LCFTF Trail Status system

- Enhanced push notification system for PHP 8.0+ compatibility
- Added modern notification system with strict types and better error handling
- Created comprehensive push notification compatibility assessment
- Added migration tools for notification system upgrade
- Improved security with proper SSL verification and input validation
- Simplified JWT implementation for better reliability
- Added detailed documentation for push notification troubleshooting

Previous achievements:
- Fixed PHP-FPM handler configuration in Apache vhosts
- Added proper PHP execution for both HTTP and HTTPS
- Resolved 403 Forbidden errors with directory permissions  
- Updated SELinux configuration for home directory serving
- Modernized PHP codebase for PHP 8.0+ compatibility
- Added comprehensive diagnostic and deployment scripts
- Implemented secure trail status management system
- Created admin panel with user authentication
- Established JSON-based file database system
- Added branded LCFTF design with wet trail policy

Technical improvements:
- Apache 2.4.62 with proper virtual host configs
- PHP-FPM integration with Unix socket handler
- SELinux boolean configuration for web serving
- File permission optimization for security
- Modern PHP with strict types and error handling
- Responsive design with mobile support
- Enhanced push notification reliability and security

All features production ready:
✓ Public trail status display
✓ Admin authentication and management
✓ Trail status updates with notifications  
✓ Modern push notification system (PHP 8.0+ compatible)
✓ User management and permissions
✓ Security restrictions for sensitive files
✓ HTTPS redirect and SSL configuration
✓ Comprehensive diagnostic and migration tools"

if [ $? -eq 0 ]; then
    echo "✓ Commit successful"
else
    echo "❌ Commit failed"
    exit 1
fi

echo ""
echo "5. Pushing to remote repository:"
git push origin main

if [ $? -eq 0 ]; then
    echo "✓ Push successful"
else
    echo "❌ Push failed"
    exit 1
fi

echo ""
echo "6. Creating release tag:"
TAG_VERSION="v1.1.0-production"
TAG_MESSAGE="LCFTF Trail Status v1.1.0 - Enhanced Push Notifications

Complete mountain bike trail status management system with upgraded notifications.

New in v1.1.0:
- PHP 8.0+ compatible push notification system
- Enhanced security with proper SSL verification
- Improved error handling and logging
- Simplified JWT implementation for better reliability
- Comprehensive migration tools and documentation
- Push notification compatibility assessment
- Better troubleshooting and diagnostic tools

Core Features:
- Real-time trail status updates (Open/Caution/Closed)
- Admin panel with secure authentication
- Modern push notifications for status changes
- Mobile-responsive design with LCFTF branding
- JSON-based database system
- User management and permissions
- Wet trail policy display

Technical Stack:
- PHP 8.0+ with strict types and modern syntax
- Apache 2.4.62 with PHP-FPM
- AlmaLinux 9 with SELinux
- Let's Encrypt SSL/TLS
- Progressive Web App features
- Modern responsive CSS
- Enhanced push notification system

Deployment:
- Production site: https://zeroglitch.com/trailstatus/
- Home directory web root: /home/jamie/www/zeroglitch.com/trailstatus/
- Data directory: /home/jamie/www/zeroglitch.com/trailstatus/data/
- Apache vhosts: /etc/httpd/domains.d/

Migration Tools:
- migrate-push-notifications.sh - Upgrade notification system
- PUSH-NOTIFICATIONS-ASSESSMENT.md - Compatibility guide
- Comprehensive diagnostic scripts

All systems operational and ready for production use."

git tag -a "$TAG_VERSION" -m "$TAG_MESSAGE"

if [ $? -eq 0 ]; then
    echo "✓ Tag created: $TAG_VERSION"
else
    echo "❌ Tag creation failed"
    exit 1
fi

echo ""
echo "7. Pushing tag to remote:"
git push origin "$TAG_VERSION"

if [ $? -eq 0 ]; then
    echo "✓ Tag pushed successfully"
else
    echo "❌ Tag push failed"
    exit 1
fi

echo ""
echo "=== SUCCESS ==="
echo "✓ All changes committed and pushed"
echo "✓ Production tag created: $TAG_VERSION"
echo "✓ Repository updated with enhanced LCFTF Trail Status system"
echo ""
echo "Latest commit:"
git log --oneline -1
echo ""
echo "Available tags:"
git tag -l
echo ""
echo "🎉 LCFTF Trail Status website is now production ready!"
echo "📍 Live site: https://zeroglitch.com/trailstatus/"
