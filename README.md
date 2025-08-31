# Moodle Local Digisign Plugin

A Moodle local plugin that integrates with DocuSeal to allow users to fill out, sign, and submit templates directly within Moodle without navigating to an external website.

## Features

- Display DocuSeal templates in a tile-based interface
- Create new submissions with user information pre-filled
- Continue existing in-progress submissions
- Visual status indicators (not started, created, in-progress, completed)
- User-specific submission filtering
- AJAX-based form embedding
- Robust API integration with SDK and cURL fallback

## Installation

### 1. Plugin Installation

1. Copy the plugin files to your Moodle's `local/digisign/` directory
2. Visit your Moodle admin panel and complete the plugin installation
3. Go to Site administration → Plugins → Local plugins → Digisign
4. Configure your DocuSeal API key and base URL

### 2. DocuSeal SDK Installation

The plugin works with or without the DocuSeal PHP SDK. If the SDK is not available, it will automatically fall back to cURL requests.

#### Option A: Using Composer (Recommended)

```bash
# Navigate to your Moodle root directory
cd /path/to/moodle

# Install the DocuSeal SDK
composer require docusealco/docuseal-php
```

#### Option B: Manual Installation

If Composer is not available, you can manually install the SDK:

```bash
# Navigate to the plugin directory
cd /path/to/moodle/local/digisign

# Run the manual installation script
php install_sdk.php
```

#### Option C: No SDK (cURL Only)

The plugin will work without the SDK using only cURL requests. This is the default fallback behavior.

## Configuration

### Required Settings

1. **API Key**: Your DocuSeal API key
2. **API URL**: Your DocuSeal instance URL (default: `https://sign.operatortraining.academy/api`)
3. **Timeout**: API request timeout in seconds (default: 30)

### API Key Setup

1. Log into your DocuSeal admin panel
2. Go to Settings → API
3. Generate a new API key
4. Copy the key to your Moodle plugin settings

## Usage

### For Users

1. Navigate to the Digisign plugin page
2. Browse available templates
3. Click "Start" to create a new submission or "Continue" for existing ones
4. Fill out and sign the form in the embedded interface
5. Submit the completed form

### For Administrators

- **View Submissions**: Access all user submissions with filtering options
- **Monitor Status**: Track completion status across all templates
- **Debug Issues**: Use the test scripts for troubleshooting

## Troubleshooting

### Connection Issues

Run the diagnostic script to identify problems:

```bash
cd /path/to/moodle/local/digisign
php test_connection.php
```

### Common Issues

| **Error** | **Solution** |
|-----------|--------------|
| `401 Not authenticated` | Check API key in plugin settings |
| `Operation timed out` | Increase timeout in plugin settings |
| `SDK class not present` | Install SDK or rely on cURL fallback |
| `HTTP Code 0` | Check network connectivity |

### Debug Information

The plugin provides detailed debug information when issues occur. Check your Moodle debug output for:

- API request attempts
- Response codes and errors
- SDK vs cURL usage
- Timeout information

## API Integration

### Available Functions

- `local_digisign_fetch_templates()` - Get all templates
- `local_digisign_create_submission()` - Create new submission
- `local_digisign_fetch_submissions()` - Get user's submissions
- `local_digisign_get_submission()` - Get specific submission
- `local_digisign_download_signed_pdf()` - Download completed PDF

### Example Usage

```php
// Get all templates
$templates = local_digisign_fetch_templates();

// Create a submission
$submission = local_digisign_create_submission($template_id, $user_email, $user_name);

// Get user's submissions
$submissions = local_digisign_fetch_submissions(100, [], $user_email);
```

## Pages

- **`index.php`** - Main user interface with template tiles
- **`submissions.php`** - Admin view of all submissions
- **`ajax.php`** - AJAX endpoints for dynamic operations

## AJAX Endpoints

- `create_submission` - Create new submission
- `fetch_submissions` - Get user's submissions
- `get_submission` - Get specific submission details
- `complete_submission` - Mark submission as complete

## Development

### Testing

```bash
# Test API connection
php test_connection.php

# Test template status checking
php test_status.php

# Test SDK installation
php install_sdk.php
```

### File Structure

```
local/digisign/
├── lib.php              # Core API functions
├── index.php            # Main user interface
├── ajax.php             # AJAX endpoints
├── submissions.php      # Admin submissions view
├── settings.php         # Plugin settings
├── lang/en/             # Language strings
├── amd/src/             # JavaScript modules
├── db/                  # Database schema
├── vendor/              # SDK files (if manually installed)
├── test_connection.php  # Connection diagnostic
├── test_status.php      # Status testing
└── install_sdk.php      # SDK installation script
```

## Support

For issues and questions:

1. Check the troubleshooting section above
2. Run the diagnostic scripts
3. Review Moodle debug output
4. Verify API key and URL configuration

## License

This plugin is provided as-is for educational and development purposes.