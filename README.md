# Data Signals

**Privacy-focused revenue analytics for WordPress.**

Track which content, campaigns, and traffic sources make you money — not just vanity metrics.

## Features

- 🔒 **Privacy-First**: Cookieless tracking, IP anonymization, GDPR/CCPA compliant
- 💰 **Revenue Attribution**: Track blog posts → sales, email campaigns → conversions
- ⚡ **High Performance**: Optimized for 10,000 visits/minute (166 req/sec)
- 📊 **Smart Analytics**: Focus on revenue, not vanity metrics
- 🛒 **E-commerce Integration**: WooCommerce & Easy Digital Downloads support

## Requirements

- **WordPress**: 6.0+
- **PHP**: 8.0+
- **MySQL**: 8.0+ (with partitioning support)
- **Optional**: Redis (for improved caching)

## Installation

1. Upload the `data-signals` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Visit **Data Signals** in the admin menu to view analytics

## Database Schema

The plugin creates 6 optimized tables:

1. **wp_ds_pageviews** - Partitioned by month for scalability
2. **wp_ds_events** - Conversion events (purchases, signups, clicks)
3. **wp_ds_sessions** - Aggregated session data
4. **wp_ds_revenue_attribution** - Multi-touch attribution tracking
5. **wp_ds_email_clicks** - Email campaign tracking
6. **wp_ds_aggregates** - Pre-computed daily statistics

## REST API

### Track Pageview

```bash
POST /wp-json/data-signals/v1/track
Content-Type: application/json

{
  "type": "pageview",
  "data": {
    "url": "https://example.com/blog/post-title",
    "page_id": 123,
    "referrer": "https://google.com"
  }
}
```

### Track Event

```bash
POST /wp-json/data-signals/v1/track
Content-Type: application/json

{
  "type": "event",
  "data": {
    "event_type": "pricing_click",
    "page_id": 456,
    "value": 99.00,
    "metadata": {
      "plan": "pro",
      "billing": "annual"
    }
  }
}
```

### Get Stats (Admin Only)

```bash
GET /wp-json/data-signals/v1/stats
```

## Performance Optimization

- **Batch Processing**: Inserts up to 100 records per query
- **Automatic Partitioning**: Monthly partitions created automatically
- **Indexed Queries**: 15+ strategic indexes for fast queries
- **Cron Jobs**: Background processing for aggregations

## Privacy & Security

- ✅ Cookieless session tracking (server-side SHA-256 IDs)
- ✅ IP anonymization (last octet zeroed)
- ✅ No personal data storage
- ✅ Prepared statements (SQL injection protection)
- ✅ Input sanitization & validation
- ✅ Capability checks for admin endpoints

## Development

This plugin follows WordPress coding standards and uses:

- **Namespace**: `DataSignals\`
- **Autoloader**: PSR-4 compliant
- **PHP Version**: 8.0+ (typed properties, modern syntax)
- **Database**: MySQL 8.0+ features (JSON, partitioning)

## Cron Jobs

The plugin schedules three cron jobs:

1. **data_signals_process_batch** - Every 5 minutes (process queued events)
2. **data_signals_aggregate_stats** - Hourly (compute statistics)
3. **data_signals_create_partitions** - Daily (create next month's partition)

## License

GPL v2 or later

## Credits

Inspired by DataFa.st, Burst Statistics, Koko Analytics, and Plausible.
