# Google Search Console Integration

This integration provides SEO revenue estimation and keyword tracking for the Data Signals plugin.

## Features

### 1. OAuth 2.0 Flow
- Secure Google API authentication
- Encrypted token storage in wp_options
- Automatic token refresh

### 2. Daily Keyword Sync
- Runs daily at 2 AM via WP Cron
- Fetches last 30 days of keyword data
- Stores: keywords, impressions, clicks, position, CTR
- Batch insert optimization

### 3. Keyword → Revenue Estimation
- Correlates organic keywords with conversions
- Calculates average revenue per organic click
- Estimates value per keyword
- Tracks conversion attribution

### 4. Position Tracking
- Monitors SERP position changes
- Alerts on drops > 5 positions
- Severity scoring (low, medium, high, critical)
- Historical trend analysis

### 5. Content Gap Analysis
- Identifies high-volume keywords with low CTR
- Calculates opportunity scores
- Estimates potential clicks/revenue
- Prioritizes content improvements

## Setup

### 1. Get Google API Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (or select existing)
3. Enable "Google Search Console API"
4. Go to "Credentials" → "Create Credentials" → "OAuth 2.0 Client ID"
5. Application type: Web application
6. Add redirect URI: `https://yoursite.com/wp-json/data-signals/v1/gsc/callback`
7. Copy Client ID and Client Secret

### 2. Configure Plugin

```php
// In wp-config.php or Settings page
update_option( 'ds_gsc_client_id', 'YOUR_CLIENT_ID' );
update_option( 'ds_gsc_client_secret', 'YOUR_CLIENT_SECRET' );
update_option( 'ds_gsc_property_url', home_url() ); // or 'sc-domain:example.com'
```

### 3. Authorize

Send POST request to `/wp-json/data-signals/v1/gsc/authorize` (requires `manage_options` capability).

Response includes `authorization_url` - redirect user to this URL to grant access.

After authorization, Google redirects to callback endpoint which stores tokens.

## API Endpoints

### POST /wp-json/data-signals/v1/gsc/authorize

Initiate OAuth flow.

**Response:**
```json
{
  "authorization_url": "https://accounts.google.com/o/oauth2/v2/auth?..."
}
```

### POST /wp-json/data-signals/v1/gsc/callback

OAuth callback (handled automatically by Google redirect).

**Parameters:**
- `code`: Authorization code (from Google)
- `state`: CSRF token

**Response:**
```json
{
  "success": true,
  "message": "Successfully connected to Google Search Console."
}
```

### GET /wp-json/data-signals/v1/gsc/keywords

Get keyword performance data.

**Parameters:**
- `limit`: Number of keywords (default: 100)
- `offset`: Pagination offset (default: 0)
- `order_by`: Sort field (keyword, impressions, clicks, position, ctr, revenue_estimate, date)
- `order`: Sort direction (ASC, DESC)

**Response:**
```json
{
  "keywords": [
    {
      "keyword": "wordpress analytics plugin",
      "total_impressions": 5420,
      "total_clicks": 312,
      "avg_position": 4.2,
      "avg_ctr": 0.0576,
      "avg_revenue_estimate": 12.45,
      "last_seen": "2026-01-31"
    }
  ],
  "count": 100
}
```

### GET /wp-json/data-signals/v1/gsc/revenue-estimate

Get total SEO revenue estimate.

**Parameters:**
- `days`: Analysis period (default: 30)

**Response:**
```json
{
  "total_revenue": 1245.67,
  "total_clicks": 8934,
  "avg_position": 6.8,
  "days": 30
}
```

### POST /wp-json/data-signals/v1/gsc/sync

Manually trigger keyword sync.

**Response:**
```json
{
  "success": true,
  "message": "Keyword sync completed.",
  "last_sync": 1738358400
}
```

## Database Schema

### wp_ds_gsc_keywords

```sql
CREATE TABLE wp_ds_gsc_keywords (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  keyword VARCHAR(255) NOT NULL,
  date DATE NOT NULL,
  impressions INT UNSIGNED,
  clicks INT UNSIGNED,
  position DECIMAL(4,2),
  ctr DECIMAL(5,4),
  revenue_estimate DECIMAL(10,2) DEFAULT 0,
  UNIQUE KEY unique_keyword_date (keyword, date),
  INDEX idx_date (date),
  INDEX idx_revenue (revenue_estimate DESC)
);
```

### wp_ds_keyword_attribution

```sql
CREATE TABLE wp_ds_keyword_attribution (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id CHAR(32) NOT NULL,
  keyword VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_session (session_id),
  INDEX idx_keyword (keyword),
  INDEX idx_created (created_at)
);
```

## Usage Examples

### Keyword Analyzer

```php
use DataSignals\Keyword_Analyzer;

$analyzer = new Keyword_Analyzer();

// Detect position drops
$drops = $analyzer->detect_position_drops( 7 ); // Last 7 days

foreach ( $drops as $drop ) {
    echo "⚠️ {$drop['keyword']}: Position {$drop['previous_position']} → {$drop['recent_position']} ({$drop['severity']})\n";
}

// Identify opportunities
$opportunities = $analyzer->identify_opportunities( 30 );

foreach ( $opportunities as $opp ) {
    echo "💡 {$opp['keyword']}: {$opp['potential_additional_clicks']} potential clicks (score: {$opp['opportunity_score']})\n";
}

// Get money keywords
$money_keywords = $analyzer->get_money_keywords( 30 );

foreach ( $money_keywords as $kw ) {
    echo "💰 {$kw['keyword']}: ${$kw['revenue']} revenue (${$kw['revenue_per_click']} per click)\n";
}

// Content gaps
$gaps = $analyzer->analyze_content_gaps( 30 );

foreach ( $gaps as $gap ) {
    echo "📝 {$gap['keyword']}: Position {$gap['current_position']}, {$gap['potential_clicks']} potential clicks (gap score: {$gap['gap_score']})\n";
}
```

### SEO Revenue Estimator

```php
use DataSignals\SEO_Revenue_Estimator;

$estimator = new SEO_Revenue_Estimator();

// Update all revenue estimates (run daily via cron)
$estimator->update_revenue_estimates();

// Estimate specific keyword
$estimate = $estimator->estimate_keyword_revenue( 'wordpress analytics', 30 );

echo "Keyword: {$estimate['keyword']}\n";
echo "Clicks: {$estimate['clicks']}\n";
echo "Estimated Revenue: ${$estimate['estimated_revenue']}\n";
echo "Confidence: {$estimate['confidence']}\n";

// Calculate total SEO value
$seo_value = $estimator->calculate_seo_value( 30 );

echo "Total SEO Value: ${$seo_value['total_seo_value']}\n";
echo "Confidence: {$seo_value['confidence']}\n";

// Position improvement opportunities
$opportunities = $estimator->calculate_position_opportunity( 30 );

echo "Total Opportunity: ${$opportunities['total_opportunity']}\n";

foreach ( $opportunities['opportunities'] as $opp ) {
    echo "{$opp['keyword']}: ${$opp['additional_revenue']} potential revenue\n";
}
```

## WP-CLI Commands (Future)

```bash
# Authorize GSC
wp data-signals gsc authorize

# Manual sync
wp data-signals gsc sync

# Get keyword stats
wp data-signals gsc stats --days=30

# Position tracking
wp data-signals gsc positions --drops-only

# Revenue estimate
wp data-signals gsc revenue --days=30
```

## Cron Jobs

### ds_gsc_daily_sync
- Runs: Daily at 2:00 AM
- Function: Fetches keyword data from Google Search Console
- Duration: ~30-60 seconds for 25,000 keywords

### ds_update_revenue_estimates
- Runs: Daily at 3:00 AM
- Function: Updates revenue estimates for all keywords
- Duration: ~10-20 seconds

## Security

### Token Storage
- Tokens encrypted with AES-256-CBC
- Encryption key stored in wp_options (auto-generated)
- Never exposed via API responses

### API Access
- All endpoints require `manage_options` capability
- CSRF protection via nonces
- State parameter validation in OAuth flow

### Rate Limiting
- Google API quota: 1,200 requests/minute
- Plugin enforces batch fetching (25,000 rows/request)
- Daily sync minimizes API usage

## Troubleshooting

### Authorization Fails
1. Check Client ID and Secret are correct
2. Verify redirect URI matches in Google Console
3. Check SSL certificate (OAuth requires HTTPS)

### No Keywords Synced
1. Check site is verified in Google Search Console
2. Verify property URL matches (check `ds_gsc_property_url` option)
3. Check error logs: `tail -f wp-content/debug.log`

### Revenue Estimates Are Zero
1. Ensure WooCommerce/EDD orders are being tracked
2. Check `wp_ds_sessions` table has organic sessions with revenue
3. Run manual revenue update: `$estimator->update_revenue_estimates()`

## Performance

### Database Queries
- Aggregated queries use GROUP BY with indexes
- Position ~50ms response time for 100k+ keyword records
- Date-based partitioning recommended for 1M+ records

### API Requests
- Google Search Console API: 1 request per daily sync
- Batch insert: 25,000 keywords in ~2-3 seconds
- No real-time API calls (all data cached locally)

## Roadmap

- [ ] WP-CLI commands
- [ ] Admin dashboard widgets
- [ ] Email alerts for position drops
- [ ] Competitor keyword analysis
- [ ] SERP feature tracking
- [ ] Keyword grouping/tagging
- [ ] Export to CSV/PDF reports
