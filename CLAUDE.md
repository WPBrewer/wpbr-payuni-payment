# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin that integrates PAYUNi (統一金流) payment gateway with WooCommerce. The plugin supports multiple payment methods including credit cards, digital wallets, and alternative payment options.

**Plugin Name**: Pay with PAYUNi  
**Version**: 1.7.0  
**Namespace**: `WPBrewer\Payuni\Payment`  
**Minimum Requirements**: WordPress 5.9+, PHP 7.4+, WooCommerce

## Development Commands

```bash
# Install development dependencies
composer install

# Install production dependencies only (for deployment)
composer update --no-dev --optimize-autoloader

# Run PHP CodeSniffer to check coding standards
vendor/bin/phpcs

# Fix auto-fixable coding standard issues
vendor/bin/phpcbf
```

## Architecture Overview

### Payment Gateway Structure

The plugin implements WooCommerce payment gateways using an abstract base class pattern:

1. **Base Gateway**: `src/Gateways/GatewayBase.php` - All payment methods extend this abstract class
2. **Gateway Implementations**: Located in `src/Gateways/` - Each payment method has its own class
3. **Settings**: Each gateway has corresponding settings in `includes/settings/`

### Core Components

- **Main Plugin Class**: `src/PayuniPayment.php` - Singleton pattern, initializes all components
- **API Layer**: `src/Api/` - Handles payment requests and responses with PAYUNi API
- **Admin Enhancements**: `src/Admin/` - Order list and meta box customizations
- **Utilities**: `src/Utils/` - Shared traits and constants

### Key Constants

The main plugin file defines these important constants:
- `WPBR_PAYUNI_PLUGIN_URL` - Plugin URL
- `WPBR_PAYUNI_PLUGIN_DIR` - Plugin directory path
- `WPBR_PAYUNI_BASENAME` - Plugin basename
- `WPBR_PAYUNI_PAYMENT_VERSION` - Current version

## Deployment Process

The plugin uses GitHub Actions for automated deployment:

1. **Release Deployment**: Creating a GitHub release triggers deployment to WordPress.org
2. **Asset Updates**: Pushing to main branch updates readme and assets on WordPress.org

The deployment workflow:
- Builds production dependencies
- Excludes development files using `.distignore`
- Creates a zip file attached to GitHub release
- Deploys to WordPress.org SVN repository

## Important Development Considerations

1. **HPOS Compatibility**: The plugin declares High-Performance Order Storage compatibility
2. **Coding Standards**: Follows WordPress Coding Standards (WPCS) - enforced via PHPCS
3. **Internationalization**: Text domain is `wpbr-payuni-payment`, translations in `/languages/`
4. **PSR-4 Autoloading**: Classes are autoloaded via Composer
5. **WooCommerce Dependency**: Plugin auto-deactivates if WooCommerce is not active

## Payment Gateway Implementation Pattern

When adding new payment gateways:

1. Create a new class in `src/Gateways/` extending `GatewayBase`
2. Create corresponding settings class in `includes/settings/`
3. Register the gateway in the main plugin class
4. Follow the existing pattern for method naming and structure

## Testing Considerations

- No automated tests are currently configured
- Use PAYUNi sandbox environment for testing (credentials required)
- Test with HPOS enabled and disabled
- Verify all payment methods and refund functionality