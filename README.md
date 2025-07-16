# 🚵‍♂️ LCFTF Trail Status Website

A modern, responsive web application for managing and displaying mountain bike trail conditions. Built with PHP and a file-based JSON database, perfect for small to medium-sized mountain bike clubs.

## Features

- **Public Trail Status Display**: Clean, responsive interface showing current trail conditions
- **Color-coded Status System**: 
  - 🟢 Open (Green) - Trail is in good condition
  - 🟡 Caution (Yellow) - Use caution, conditions may vary  
  - 🔴 Closed (Red) - Trail is closed to riders
- **User Authentication**: Secure login system for trail managers
- **Admin Panel**: Easy-to-use interface for updating trail status
- **File-based Database**: No complex database setup required
- **Responsive Design**: Works great on desktop, tablet, and mobile devices
- **Auto-refresh**: Public page automatically refreshes every 5 minutes
- **Real-time Updates**: Changes are reflected immediately

## Requirements

- **Web Server**: Apache HTTP Server with mod_rewrite
- **PHP**: Version 7.4 or higher
- **PHP Extensions**: JSON, Session support
- **File System**: Write permissions for data storage

## Installation

### 1. Download and Extract

Clone or download this repository to your web server's document root:

```bash
cd /var/www/html  # or your web server's document root
git clone https://github.com/yourusername/trailstatus.git
cd trailstatus
```

### 2. Set Permissions

Ensure the web server can write to the data directory:

```bash
chmod 755 data/
chmod 644 data/*.json  # if data files exist
chown -R www-data:www-data .  # on Ubuntu/Debian
# or
chown -R apache:apache .      # on CentOS/RHEL
```

### 3. Run Setup

#### Option A: Web-based Setup
Navigate to `http://yourserver/trailstatus/setup.php` in your browser and follow the instructions.

#### Option B: Command Line Setup
```bash
php setup.php
```

### 4. Apache Configuration

Make sure your Apache virtual host or `.htaccess` allows:
- PHP execution
- URL rewriting (mod_rewrite)
- Override directives

Example Apache virtual host:
```apache
<VirtualHost *:80>
    ServerName trailstatus.yourdomain.com
    DocumentRoot /var/www/html/trailstatus
    
    <Directory /var/www/html/trailstatus>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Default Login

After setup, use these credentials to access the admin panel:

- **Username**: `admin`
- **Password**: `admin123`

**⚠️ Important**: Change the default password immediately after first login!

## Usage

### Public Access
- Visit `index.php` to view current trail status
- No login required for viewing trail conditions
- Page auto-refreshes every 5 minutes for latest updates

### Admin Access
1. Go to `login.php` or click "Admin Login"
2. Enter your credentials
3. Access the admin panel to:
   - Update trail status (Open/Caution/Closed)
   - Add new trails
   - Delete existing trails
   - Bulk update all trails to the same status

### Managing Trails

#### Adding New Trails
1. Login to admin panel
2. Use the "Add New Trail" form
3. Enter trail name and initial status
4. Click "Add Trail"

#### Updating Trail Status
1. In the admin panel, find the trail in the table
2. Use the dropdown to select new status
3. Status updates automatically when you change the dropdown

#### Deleting Trails
1. In the admin panel, find the trail in the table
2. Click the red "Delete" button
3. Confirm the deletion

## File Structure

```
trailstatus/
├── index.php              # Public trail status page
├── login.php              # Admin login page
├── admin.php              # Admin panel
├── logout.php             # Logout handler
├── setup.php              # Initial setup script
├── 404.php                # Error page
├── .htaccess              # Apache configuration
├── includes/
│   └── config.php         # Configuration and functions
├── css/
│   └── style.css          # Stylesheet
├── data/
│   ├── users.json         # User accounts (auto-created)
│   └── trails.json        # Trail data (auto-created)
└── README.md              # This file
```

## Data Storage

The application uses JSON files for data storage:

### users.json
Stores user account information:
```json
[
  {
    "id": 1,
    "username": "admin",
    "password": "$2y$10$...",
    "created_at": "2025-01-01 12:00:00"
  }
]
```

### trails.json
Stores trail information:
```json
[
  {
    "id": 1,
    "name": "Blue Trail",
    "status": "open",
    "updated_at": "2025-01-01 12:00:00",
    "updated_by": "admin"
  }
]
```

## Customization

### Adding More Users
Currently, users must be added manually to the `data/users.json` file. Hash passwords using PHP's `password_hash()` function.

### Styling
Modify `css/style.css` to customize the appearance. The CSS uses modern techniques like:
- CSS Grid for responsive layouts
- Backdrop filters for modern glass effects
- CSS custom properties for easy color changes

### Trail Statuses
To add more status types, modify the constants in `includes/config.php`:
```php
define('STATUS_CUSTOM', 'custom');
```

## Security Considerations

- **File Protection**: `.htaccess` prevents direct access to JSON data files
- **Password Security**: Uses PHP's `password_hash()` and `password_verify()`
- **Session Management**: Secure session handling for authentication
- **Input Validation**: All user inputs are validated and sanitized
- **CSRF Protection**: Forms include basic CSRF protection

## Backup

To backup your trail data:
```bash
cp data/users.json users_backup_$(date +%Y%m%d).json
cp data/trails.json trails_backup_$(date +%Y%m%d).json
```

## Troubleshooting

### Common Issues

1. **White page/errors**: Check PHP error logs and ensure file permissions are correct
2. **Can't login**: Verify `data/users.json` exists and has correct format
3. **Trails not updating**: Check write permissions on `data/` directory
4. **Styles not loading**: Verify `css/style.css` exists and is accessible

### Permissions Issues
```bash
# Fix permissions
chmod 755 data/
chmod 644 data/*.json
chown -R www-data:www-data .  # Ubuntu/Debian
```

### Debug Mode
Add to the top of `includes/config.php` for debugging:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is open source and available under the [MIT License](LICENSE).

## Support

For support or questions:
- Open an issue on GitHub
- Check the troubleshooting section above
- Review Apache and PHP error logs

---

Built with ❤️ for the mountain biking community
