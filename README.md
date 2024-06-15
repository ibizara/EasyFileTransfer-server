# File Upload System

This application provides a secure interface for uploading, downloading, and managing files. It includes a user login system and is compatible with modern web browsers. Below are the system requirements and compatibility details.

## System Compatibility

### Server Requirements

- **Web Server**: Apache 2.4 or higher
- **PHP**: Version 7.4 or higher
- **Extensions**: 
  - `session`
  - `fileinfo`
  - `json`

### Browser Compatibility

The following browsers are supported and tested:

- **Google Chrome**: Latest version
- **Mozilla Firefox**: Latest version
- **Microsoft Edge**: Latest version
- **Apple Safari**: Latest version

## Configuration

Before deploying the application, configure the following options at the top of the `files.php` file:

```php
// User Configuration Options
$valid_username = 'user';
$valid_password = 'password';
$uploadDir = '/uploads/';
