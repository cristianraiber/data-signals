# Data Signals

Privacy-friendly, lightweight analytics for WordPress. Inspired by [Koko Analytics](https://github.com/ibericode/koko-analytics) and [Independent Analytics](https://independentwp.com).

## Features

### Core (v2.0)
- **Privacy-first**: No cookies, no external services, GDPR-compliant by design
- **Lightweight**: ~500 bytes tracking script, minimal database impact
- **Fast**: Buffer-based collection with cron aggregation (no direct DB writes on pageview)
- **Simple**: Clean PHP dashboard, no complex JavaScript framework required
- **Self-hosted**: All data stays on your server

### Enhanced Tracking (v2.1)
- **Device Detection**: Track device type (desktop/mobile/tablet), browser, and OS
- **Geographic Analytics**: Country-level tracking via IP geolocation (cached, no external DB required)
- **Campaign Tracking**: Full UTM parameter support (source, medium, campaign, content, term)
- **Tabbed Dashboard**: Overview, Devices, Geographic, Campaigns, Referrers

## How It Works

### Tracking
1. A tiny JavaScript snippet (~500 bytes) sends pageview data to the server
2. Data is written to a buffer file (not directly to the database)
3. A cron job runs every minute to aggregate buffer data into the database
4. Session uniqueness is determined via a privacy-friendly fingerprint (daily rotating seed + user agent + IP hash)

### Database Schema
- `ds_site_stats` - Daily site-wide totals (visitors, pageviews)
- `ds_page_stats` - Daily per-page stats
- `ds_paths` - URL path lookup table
- `ds_referrer_stats` - Daily referrer stats
- `ds_referrers` - Referrer URL lookup table
- `ds_device_stats` - Daily device/browser/OS stats
- `ds_geo_stats` - Daily country-level stats
- `ds_campaign_stats` - Daily UTM campaign stats
- `ds_dates` - Helper table for date ranges

### Privacy
- No cookies by default
- IP addresses are never stored (only used for temporary geo lookup, then discarded)
- Session fingerprints rotate daily
- Aggregated data only (no individual tracking)
- Configurable data retention

## REST API

All endpoints require `view_data_signals` capability (or public dashboard enabled).

```
# Core Stats
GET /wp-json/data-signals/v1/stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&group=day|week|month
GET /wp-json/data-signals/v1/totals?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
GET /wp-json/data-signals/v1/pages?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&offset=0&limit=10
GET /wp-json/data-signals/v1/referrers?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&offset=0&limit=10
GET /wp-json/data-signals/v1/realtime?since=-1 hour

# Enhanced Stats (v2.1)
GET /wp-json/data-signals/v1/devices?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&group_by=device_type|browser|os
GET /wp-json/data-signals/v1/countries?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&limit=20
GET /wp-json/data-signals/v1/campaigns?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&limit=20

# Settings (admin only)
GET /wp-json/data-signals/v1/settings
POST /wp-json/data-signals/v1/settings
```

## UTM Campaign Tracking

Add UTM parameters to your links to track campaigns:

```
https://yoursite.com/?utm_source=facebook&utm_medium=social&utm_campaign=summer-sale
```

Supported parameters:
- `utm_source` - Traffic source (e.g., facebook, google, newsletter)
- `utm_medium` - Marketing medium (e.g., cpc, social, email)
- `utm_campaign` - Campaign name (e.g., summer-sale, black-friday)
- `utm_content` - Content variant (e.g., banner-a, button-blue)
- `utm_term` - Paid search keyword

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Upload the plugin to `/wp-content/plugins/data-signals`
2. Activate the plugin
3. Visit **Analytics** in the admin menu

## Hooks & Filters

### `ds_is_request_excluded`
Filter to exclude specific requests from tracking.

```php
add_filter('ds_is_request_excluded', function($excluded) {
    // Don't track specific paths
    if (str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
        return true;
    }
    return $excluded;
});
```

## Changelog

### 2.1.0
- Added device detection (type, browser, OS)
- Added geographic tracking (country-level via IP geolocation)
- Added UTM campaign tracking
- New tabbed dashboard interface
- REST API endpoints for devices, countries, campaigns

### 2.0.0
- Initial release with Koko Analytics-style architecture
- Buffer-based collection with cron aggregation
- Privacy-friendly fingerprint sessions
- PHP dashboard with Chart.js
- React settings page with WordPress components

## Credits

Architecture inspired by [Koko Analytics](https://github.com/ibericode/koko-analytics) by Danny van Kooten.
Feature set inspired by [Independent Analytics](https://independentwp.com).

## License

GPL-3.0-or-later
