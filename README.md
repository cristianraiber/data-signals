# Data Signals

Privacy-friendly, lightweight analytics for WordPress. Inspired by [Koko Analytics](https://github.com/ibericode/koko-analytics).

## Features

- **Privacy-first**: No cookies, no external services, GDPR-compliant by design
- **Lightweight**: ~400 bytes tracking script, minimal database impact
- **Fast**: Buffer-based collection with cron aggregation (no direct DB writes on pageview)
- **Simple**: Clean PHP dashboard, no complex JavaScript framework required
- **Self-hosted**: All data stays on your server

## How It Works

### Tracking
1. A tiny JavaScript snippet (~400 bytes) sends pageview data to the server
2. Data is written to a buffer file (not directly to the database)
3. A cron job runs every minute to aggregate buffer data into the database
4. Session uniqueness is determined via a privacy-friendly fingerprint (daily rotating seed + user agent + IP hash)

### Database Schema
- `ds_site_stats` - Daily site-wide totals (visitors, pageviews)
- `ds_page_stats` - Daily per-page stats
- `ds_paths` - URL path lookup table
- `ds_referrer_stats` - Daily referrer stats
- `ds_referrers` - Referrer URL lookup table
- `ds_dates` - Helper table for date ranges

### Privacy
- No cookies by default
- IP addresses are never stored
- Session fingerprints rotate daily
- Aggregated data only (no individual tracking)
- Configurable data retention

## REST API

All endpoints require `view_data_signals` capability (or public dashboard enabled).

```
GET /wp-json/data-signals/v1/stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&group=day|week|month
GET /wp-json/data-signals/v1/totals?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
GET /wp-json/data-signals/v1/pages?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&offset=0&limit=10
GET /wp-json/data-signals/v1/referrers?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&offset=0&limit=10
GET /wp-json/data-signals/v1/realtime?since=-1 hour
```

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

## Credits

Architecture inspired by [Koko Analytics](https://github.com/ibericode/koko-analytics) by Danny van Kooten.

## License

GPL-3.0-or-later
