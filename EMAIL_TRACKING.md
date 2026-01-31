# Email Campaign Tracking System

Complete guide to the Data Signals email campaign tracking system with UTM parameters and link-level attribution.

## Features

✅ **UTM Parameter Parsing** - Automatically extract and store campaign parameters  
✅ **Email Link Click Tracking** - Track individual link clicks with redirect system  
✅ **Campaign Performance** - ROI, CTR, conversion rates, revenue metrics  
✅ **Link-level Attribution** - Know which CTAs convert best  
✅ **Email → Sale Attribution** - Track from email click to purchase  
✅ **Multi-touch Attribution** - Credit multiple touchpoints in customer journey  
✅ **WooCommerce Integration** - Automatic revenue attribution  
✅ **Privacy-First** - IP anonymization, rate limiting, no PII storage

---

## Quick Start

### 1. Create Tracking Links

Use the tracking URL format for all links in your emails:

```
https://yoursite.com/ds-track/email/?url=DESTINATION&campaign=CAMPAIGN_ID
```

**Example:**

```html
<!-- Regular link -->
<a href="https://yoursite.com/products/awesome-product">Buy Now</a>

<!-- Tracked link -->
<a href="https://yoursite.com/ds-track/email/?url=https%3A%2F%2Fyoursite.com%2Fproducts%2Fawesome-product&campaign=summer-sale-2026">
  Buy Now
</a>
```

### 2. Add UTM Parameters

Include UTM parameters in your destination URLs for better tracking:

```
utm_source=email
utm_medium=newsletter
utm_campaign=summer-sale-2026
utm_content=cta-buy-now
utm_term=product-awesome
```

**Full example:**

```
https://yoursite.com/ds-track/email/?url=https%3A%2F%2Fyoursite.com%2Fproducts%2Fawesome-product%3Futm_source%3Demail%26utm_medium%3Dnewsletter%26utm_campaign%3Dsummer-sale-2026%26utm_content%3Dcta-buy-now&campaign=summer-sale-2026
```

### 3. Use Helper Function

PHP helper to generate tracking URLs:

```php
use DataSignals\Email_Tracker;

$destination = 'https://yoursite.com/products/awesome-product';
$campaign_id = 'summer-sale-2026';

$tracking_url = Email_Tracker::build_tracking_url( $destination, $campaign_id );

echo $tracking_url;
// https://yoursite.com/ds-track/email/?url=https%3A%2F%2F...&campaign=summer-sale-2026
```

---

## REST API Endpoints

### Track Email Click (POST)

**Endpoint:** `POST /wp-json/data-signals/v1/track/email-click`

Track an email link click programmatically.

**Authentication:** Public (rate limited)

**Parameters:**
- `campaign_id` (required) - Campaign identifier
- `link_url` (required) - Destination URL
- `session_id` (optional) - Session identifier (auto-generated if not provided)

**Example:**

```bash
curl -X POST https://yoursite.com/wp-json/data-signals/v1/track/email-click \
  -H "Content-Type: application/json" \
  -d '{
    "campaign_id": "summer-sale-2026",
    "link_url": "https://yoursite.com/products/awesome-product"
  }'
```

**Response:**

```json
{
  "success": true,
  "click_id": 123,
  "session_id": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
}
```

---

### Get Campaign Performance (GET)

**Endpoint:** `GET /wp-json/data-signals/v1/campaigns/performance`

Get performance metrics for all campaigns.

**Authentication:** Admin required

**Parameters:**
- `start_date` (optional) - Start date (YYYY-MM-DD)
- `end_date` (optional) - End date (YYYY-MM-DD)
- `limit` (optional) - Limit results (default: 50)

**Example:**

```bash
curl https://yoursite.com/wp-json/data-signals/v1/campaigns/performance?start_date=2026-01-01&end_date=2026-01-31 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**

```json
{
  "success": true,
  "campaigns": [
    {
      "campaign_id": "summer-sale-2026",
      "total_clicks": 1234,
      "unique_clicks": 890,
      "conversions": 45,
      "total_revenue": 4500.00,
      "conversion_rate": 5.06,
      "revenue_per_click": 5.06,
      "first_click": "2026-01-15 10:00:00",
      "last_click": "2026-01-30 18:30:00"
    }
  ],
  "total": 1
}
```

---

### Get Campaign Links (GET)

**Endpoint:** `GET /wp-json/data-signals/v1/campaigns/{campaign_id}/links`

Get performance for each link in a campaign.

**Authentication:** Admin required

**Parameters:**
- `campaign_id` (required) - Campaign identifier
- `start_date` (optional) - Start date (YYYY-MM-DD)
- `end_date` (optional) - End date (YYYY-MM-DD)

**Example:**

```bash
curl https://yoursite.com/wp-json/data-signals/v1/campaigns/summer-sale-2026/links \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**

```json
{
  "success": true,
  "campaign_id": "summer-sale-2026",
  "links": [
    {
      "link_url": "https://yoursite.com/products/awesome-product",
      "link_label": "Awesome Product",
      "total_clicks": 567,
      "unique_clicks": 412,
      "conversions": 23,
      "total_revenue": 2300.00,
      "conversion_rate": 5.58,
      "revenue_per_click": 5.58
    }
  ],
  "total": 1
}
```

---

### Get Campaign Revenue (GET)

**Endpoint:** `GET /wp-json/data-signals/v1/campaigns/{campaign_id}/revenue`

Get comprehensive revenue analytics for a campaign.

**Authentication:** Admin required

**Parameters:**
- `campaign_id` (required) - Campaign identifier
- `cost` (optional) - Campaign cost for ROI calculation
- `emails_sent` (optional) - Number of emails sent for CTR calculation
- `start_date` (optional) - Start date (YYYY-MM-DD)
- `end_date` (optional) - End date (YYYY-MM-DD)

**Example:**

```bash
curl "https://yoursite.com/wp-json/data-signals/v1/campaigns/summer-sale-2026/revenue?cost=500&emails_sent=10000" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**

```json
{
  "success": true,
  "campaign_id": "summer-sale-2026",
  "performance": {
    "total_clicks": 1234,
    "unique_clicks": 890,
    "conversions": 45,
    "total_revenue": 4500.00,
    "conversion_rate": 5.06
  },
  "roi": {
    "revenue": 4500.00,
    "cost": 500.00,
    "profit": 4000.00,
    "roi": 800.00,
    "roas": 9.00
  },
  "revenue_per_email": {
    "emails_sent": 10000,
    "ctr": 8.90,
    "conversion_rate": 5.06,
    "revenue_per_email": 0.45
  },
  "cac": {
    "cost": 500.00,
    "conversions": 45,
    "cac": 11.11
  },
  "time_to_conversion": {
    "avg_time_minutes": 45.30,
    "avg_time_formatted": "45 minutes"
  },
  "link_attribution": [
    {
      "link_url": "https://yoursite.com/products/awesome-product",
      "link_label": "Awesome Product",
      "sales_count": 23,
      "sales_revenue": 2300.00,
      "conversion_rate": 5.58
    }
  ]
}
```

---

## PHP Usage Examples

### Track Email Click

```php
use DataSignals\Email_Tracker;

$campaign_id = 'summer-sale-2026';
$link_url = 'https://yoursite.com/products/awesome-product';
$session_id = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6';

$click_id = Email_Tracker::log_click( $campaign_id, $link_url, $session_id );

if ( $click_id ) {
    echo "Click tracked! ID: {$click_id}";
}
```

### Extract UTM Parameters

```php
use DataSignals\UTM_Parser;

// From current request
$utm_data = UTM_Parser::extract();

// From specific URL
$url = 'https://yoursite.com/page?utm_source=email&utm_campaign=test';
$utm_data = UTM_Parser::extract( $url );

// Store in session
UTM_Parser::store_in_session( $session_id, $utm_data );
```

### Get Campaign Performance

```php
use DataSignals\Campaign_Analytics;

$campaign_id = 'summer-sale-2026';
$date_range = [
    'start' => '2026-01-01',
    'end'   => '2026-01-31',
];

$performance = Campaign_Analytics::get_campaign_performance( $campaign_id, $date_range );

echo "Revenue: $" . $performance['total_revenue'];
echo "Conversion Rate: " . $performance['conversion_rate'] . "%";
```

### Calculate ROI

```php
use DataSignals\Campaign_Analytics;

$campaign_id = 'summer-sale-2026';
$cost = 500.00;

$roi_data = Campaign_Analytics::calculate_roi( $campaign_id, $cost );

echo "ROI: " . $roi_data['roi'] . "%";
echo "ROAS: " . $roi_data['roas'] . "x";
```

### Get Link Performance

```php
use DataSignals\Link_Tracker;

$campaign_id = 'summer-sale-2026';

$links = Link_Tracker::get_campaign_links( $campaign_id );

foreach ( $links as $link ) {
    echo $link['link_label'] . ": ";
    echo $link['conversions'] . " conversions, ";
    echo "$" . $link['total_revenue'] . " revenue\n";
}
```

### Compare CTAs

```php
use DataSignals\Link_Tracker;

$campaign_id = 'summer-sale-2026';
$cta_urls = [
    'https://yoursite.com/products/a',
    'https://yoursite.com/products/b',
];

$comparison = Link_Tracker::compare_ctas( $campaign_id, $cta_urls );

foreach ( $comparison as $cta ) {
    echo $cta['link_url'] . ": ";
    echo $cta['conversion_rate'] . "% conversion rate\n";
}
```

---

## Email Service Provider Integration

### Mailchimp

Use merge tags to personalize tracking URLs:

```html
<a href="https://yoursite.com/ds-track/email/?url=https%3A%2F%2Fyoursite.com%2Fproducts%2F*|PRODUCT_URL|*&campaign=*|CAMPAIGN:campaign_id|*">
  Buy Now
</a>
```

### ConvertKit

Use liquid syntax:

```html
<a href="https://yoursite.com/ds-track/email/?url={{ product_url | url_encode }}&campaign={{ campaign_id }}">
  Buy Now
</a>
```

### Custom PHP Mailer

```php
use DataSignals\Email_Tracker;

function send_campaign_email( $recipient, $campaign_id, $product_url ) {
    $tracking_url = Email_Tracker::build_tracking_url( $product_url, $campaign_id );
    
    $message = sprintf(
        '<a href="%s">Click here to buy</a>',
        esc_url( $tracking_url )
    );
    
    wp_mail( $recipient, 'Summer Sale!', $message );
}
```

---

## Security

### Rate Limiting

The tracking endpoint is rate-limited to **100 requests per minute per IP**.

**Headers returned:**
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
```

### URL Validation

Only internal URLs and whitelisted domains are allowed for redirects.

**Whitelist custom domains:**

```php
add_filter( 'data_signals_allowed_redirect_hosts', function( $hosts ) {
    $hosts[] = 'partner-site.com';
    $hosts[] = 'affiliate.com';
    return $hosts;
} );
```

### IP Anonymization

Client IPs are anonymized (last octet zeroed) before storage:
- `192.168.1.100` → `192.168.1.0`

---

## Best Practices

### 1. Consistent Campaign IDs
Use descriptive, consistent campaign IDs:
```
✅ summer-sale-2026
✅ product-launch-jan-2026
❌ campaign1
❌ test
```

### 2. Meaningful UTM Content
Track different CTAs within the same email:
```
utm_content=hero-cta
utm_content=footer-cta
utm_content=product-image
```

### 3. Test Before Sending
Always test tracking links before sending campaigns:

```bash
curl -I "https://yoursite.com/ds-track/email/?url=https%3A%2F%2Fyoursite.com%2Fproducts%2Ftest&campaign=test-campaign"
```

### 4. Monitor Rate Limits
Check your campaign volume doesn't exceed rate limits (100 req/min per IP).

### 5. Review Attribution
Regularly review link-level attribution to optimize email content:

```php
$links = Link_Tracker::get_campaign_links( 'summer-sale-2026' );
usort( $links, fn($a, $b) => $b['conversion_rate'] <=> $a['conversion_rate'] );

echo "Best performing link: " . $links[0]['link_label'];
```

---

## Troubleshooting

### Links Not Tracking

1. Check rewrite rules are flushed:
   ```php
   flush_rewrite_rules();
   ```

2. Verify table exists:
   ```sql
   SHOW TABLES LIKE 'wp_ds_email_clicks';
   ```

3. Check error logs:
   ```bash
   tail -f wp-content/debug.log
   ```

### Zero Conversions

1. Verify WooCommerce integration is active
2. Check session cookie is set
3. Confirm `ds_session` cookie is present in browser

### Performance Issues

1. Add database indexes (already included)
2. Enable Redis caching
3. Archive old data (>90 days)

---

## Database Schema

### wp_ds_email_clicks

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| campaign_id | VARCHAR(100) | Campaign identifier |
| link_url | VARCHAR(500) | Clicked URL |
| session_id | CHAR(32) | Session identifier |
| clicked_at | DATETIME | Click timestamp |
| converted | BOOLEAN | Whether click led to conversion |
| revenue | DECIMAL(10,2) | Revenue attributed to click |

**Indexes:**
- `idx_campaign` (campaign_id, clicked_at)
- `idx_session` (session_id)

---

## Support

For issues, feature requests, or questions:
- GitHub Issues: https://github.com/yourusername/data-signals/issues
- Documentation: https://yoursite.com/docs/data-signals
- Email: support@yoursite.com
