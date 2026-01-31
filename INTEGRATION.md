# WooCommerce & EDD Integration - Documentation

## Overview

The Data Signals plugin now includes comprehensive revenue attribution for WooCommerce and Easy Digital Downloads, tracking the complete customer journey from first visit to purchase.

## Features Implemented

### 1. WooCommerce Integration

**Tracked Events:**
- `woocommerce_payment_complete` - Order completion
- `woocommerce_add_to_cart` - Cart creation (first item)
- `woocommerce_cart_updated` - Cart modifications
- `woocommerce_checkout_order_processed` - Checkout initiation

**Captured Data:**
- Order ID, total amount, currency
- Product details (ID, name, quantity, price)
- Customer email, payment method
- Session ID for attribution

### 2. Easy Digital Downloads Integration

**Tracked Events:**
- `edd_complete_purchase` - Purchase completion
- `edd_post_add_to_cart` - Cart creation
- `edd_checkout_before_gateway` - Checkout started
- Download product views (template_redirect)

**Captured Data:**
- Payment ID, total amount, currency
- Download details (ID, name, price, quantity)
- Customer email, payment gateway
- Session ID for attribution

### 3. Revenue Attribution Models

All purchases are attributed using four different models:

#### First-Click Attribution
- 100% credit to the first page visited in the session
- Best for understanding initial acquisition sources

#### Last-Click Attribution
- 100% credit to the last page before purchase
- Best for understanding direct conversion triggers

#### Linear Attribution
- Equal credit distributed across all touchpoints
- Best for understanding the full journey

#### Time-Decay Attribution
- More credit to recent touchpoints (exponential decay)
- Half-life: 7 days
- Best for balancing early and late influences

### 4. Product Performance Analytics

Track which products convert best by:
- Traffic source
- Conversion rate (views → purchases)
- Average order value
- Revenue per view

### 5. Cart Abandonment Tracking

Monitor:
- Sessions with cart created but no purchase
- Abandonment rate by traffic source
- Total abandoned cart value
- Time-based abandonment (default: 24 hours)

### 6. Purchase Funnel Analysis

Track drop-offs at each stage:
1. Product View
2. Cart Created
3. Checkout Started
4. Purchase Completed

## REST API Endpoints

All endpoints require `manage_options` capability.

### Revenue Analytics

#### Get Revenue by Source
```
GET /wp-json/data-signals/v1/revenue/by-source
```

**Parameters:**
- `start_date` (string) - YYYY-MM-DD format (default: -30 days)
- `end_date` (string) - YYYY-MM-DD format (default: today)
- `attribution_type` (string) - first_click|last_click|linear|time_decay (default: last_click)
- `group_by` (string) - utm_source|utm_medium|utm_campaign|referrer|country_code (default: utm_source)

**Response:**
```json
[
  {
    "source": "google",
    "orders": 45,
    "total_revenue": 4500.00,
    "avg_revenue": 100.00,
    "sessions": 42,
    "total_sessions": 1200,
    "conversion_rate": 3.5,
    "revenue_per_visit": 3.75
  }
]
```

#### Get Revenue by Page
```
GET /wp-json/data-signals/v1/revenue/by-page
```

**Parameters:**
- `start_date`, `end_date`, `attribution_type` (same as above)
- `limit` (int) - Number of results (default: 50)

**Response:**
```json
[
  {
    "page_id": 123,
    "page_title": "Best WordPress Hosting",
    "page_url": "/blog/best-wordpress-hosting",
    "orders": 25,
    "total_revenue": 2500.00,
    "avg_revenue": 100.00
  }
]
```

#### Get Customer Journey
```
GET /wp-json/data-signals/v1/revenue/customer-journey/{order_id}
```

**Response:**
```json
{
  "session_id": "abc123...",
  "touchpoints": [
    {
      "page_id": 10,
      "url": "/blog/article",
      "referrer": "https://google.com",
      "utm_source": "google",
      "created_at": "2026-01-15 10:30:00"
    }
  ],
  "events": [
    {
      "event_type": "cart_created",
      "event_value": 99.00,
      "created_at": "2026-01-15 10:35:00"
    }
  ],
  "attribution": [
    {
      "page_id": 10,
      "attribution_type": "first_click",
      "revenue_share": 99.00
    }
  ]
}
```

#### Get Time to Conversion
```
GET /wp-json/data-signals/v1/revenue/time-to-conversion
```

**Response:**
```json
{
  "distribution": [
    {
      "hours_to_conversion": 0,
      "conversions": 15
    },
    {
      "hours_to_conversion": 24,
      "conversions": 8
    }
  ],
  "total_conversions": 45,
  "avg_hours": 12.5,
  "avg_days": 0.52
}
```

#### Get RPV (Revenue Per Visitor)
```
GET /wp-json/data-signals/v1/revenue/rpv
```

**Response:**
```json
[
  {
    "source": "google",
    "total_visitors": 1000,
    "total_revenue": 5000.00,
    "rpv": 5.00,
    "converted_visitors": 50,
    "conversion_rate": 5.0
  }
]
```

#### Get Multi-Touch Summary
```
GET /wp-json/data-signals/v1/revenue/multitouch-summary
```

**Response:**
```json
{
  "avg_touchpoints": 3.2,
  "distribution": [
    {
      "touchpoint_count": 1,
      "conversions": 10
    },
    {
      "touchpoint_count": 2,
      "conversions": 15
    }
  ]
}
```

### Product Performance

#### Get Products Performance
```
GET /wp-json/data-signals/v1/products/performance
```

**Parameters:**
- `start_date`, `end_date` (same as above)
- `source` (string) - Filter by traffic source (optional)
- `limit` (int) - Number of results (default: 50)

**Response:**
```json
[
  {
    "product_id": 456,
    "product_name": "Premium Plugin",
    "views": 500,
    "add_to_carts": 75,
    "purchases": 45,
    "total_revenue": 4500.00,
    "avg_price": 100.00,
    "view_to_cart_rate": 15.0,
    "cart_to_purchase_rate": 60.0,
    "overall_conversion_rate": 9.0,
    "revenue_per_view": 9.00
  }
]
```

### Funnel Analysis

#### Get Funnel Analysis
```
GET /wp-json/data-signals/v1/funnel/analysis
```

**Parameters:**
- `start_date`, `end_date` (same as above)
- `source` (string) - Filter by traffic source (optional)

**Response:**
```json
{
  "funnel": [
    {
      "step": "Product View",
      "sessions": 1000,
      "conversion_rate": 100,
      "drop_off": 0,
      "drop_off_count": 0
    },
    {
      "step": "Cart Created",
      "sessions": 150,
      "conversion_rate": 15.0,
      "drop_off": 85.0,
      "drop_off_count": 850
    },
    {
      "step": "Checkout Started",
      "sessions": 100,
      "conversion_rate": 10.0,
      "drop_off": 33.3,
      "drop_off_count": 50
    },
    {
      "step": "Purchase Completed",
      "sessions": 80,
      "conversion_rate": 8.0,
      "drop_off": 20.0,
      "drop_off_count": 20
    }
  ],
  "overall_conversion": 8.0,
  "total_drop_off": 920,
  "total_sessions": 1000,
  "total_conversions": 80
}
```

#### Get Cart Abandonment
```
GET /wp-json/data-signals/v1/funnel/cart-abandonment
```

**Parameters:**
- `start_date`, `end_date` (same as above)
- `abandonment_hours` (int) - Hours before considering cart abandoned (default: 24)

**Response:**
```json
{
  "abandoned_carts": [
    {
      "session_id": "abc123...",
      "utm_source": "facebook",
      "cart_created_at": "2026-01-30 10:00:00",
      "cart_value": 99.00
    }
  ],
  "by_source": [
    {
      "source": "facebook",
      "abandoned": 15,
      "total_value": 1485.00,
      "avg_value": 99.00
    }
  ],
  "total_abandoned": 50,
  "total_value": 5000.00,
  "avg_cart_value": 100.00,
  "total_carts": 200,
  "abandonment_rate": 25.0
}
```

#### Get AOV by Channel
```
GET /wp-json/data-signals/v1/funnel/aov-by-channel
```

**Parameters:**
- `start_date`, `end_date` (same as above)
- `group_by` (string) - utm_source|utm_medium|utm_campaign (default: utm_source)

**Response:**
```json
[
  {
    "channel": "google",
    "orders": 45,
    "total_revenue": 4500.00,
    "aov": 100.00,
    "min_order": 29.00,
    "max_order": 299.00
  }
]
```

#### Get Drop-off Points
```
GET /wp-json/data-signals/v1/funnel/dropoff-points
```

**Response:**
```json
[
  {
    "step": "Product View → Cart",
    "drop_offs": 850
  },
  {
    "step": "Cart → Checkout",
    "drop_offs": 50
  },
  {
    "step": "Checkout → Purchase",
    "drop_offs": 20
  }
]
```

## Database Schema

### Tables Created

1. `wp_ds_pageviews` - Page view tracking
2. `wp_ds_events` - Event tracking (purchases, cart events, etc.)
3. `wp_ds_sessions` - Session aggregation
4. `wp_ds_revenue_attribution` - Revenue attribution data
5. `wp_ds_email_clicks` - Email campaign tracking
6. `wp_ds_aggregates` - Pre-computed statistics

## Usage Examples

### Example 1: Get Last 7 Days Revenue by Source
```bash
curl -X GET "https://yoursite.com/wp-json/data-signals/v1/revenue/by-source?start_date=2026-01-24&end_date=2026-01-31&attribution_type=last_click" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Example 2: Compare Attribution Models
Fetch the customer journey for a specific order to see how different models attribute revenue:
```bash
curl -X GET "https://yoursite.com/wp-json/data-signals/v1/revenue/customer-journey/123" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Example 3: Analyze Funnel Performance
```bash
curl -X GET "https://yoursite.com/wp-json/data-signals/v1/funnel/analysis?start_date=2026-01-01&end_date=2026-01-31&source=google" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Session Tracking

The plugin uses cookieless session tracking for privacy. Sessions are identified by:
1. Browser cookie `ds_session` (if available)
2. WooCommerce session ID (hashed with SHA-256)
3. EDD session ID (hashed with SHA-256)

Sessions are anonymous and contain no personally identifiable information.

## Privacy & GDPR Compliance

- **No cookies required** for basic tracking
- **IP anonymization** (last octet zeroed)
- **No personal data** stored (except transactional data from orders)
- **Session IDs hashed** for security
- **Aggregate data only** in public reports

## Performance Considerations

- Uses indexed database queries for fast lookups
- Attribution calculations run asynchronously during order completion
- Consider adding Redis caching for high-traffic sites
- Database partitioning recommended for sites with >1M visits/month

## Troubleshooting

### No data appearing?
1. Check that WooCommerce or EDD is active
2. Verify database tables were created (check `wp_ds_*` tables)
3. Test with a real purchase (not just adding to cart)

### Attribution not working?
1. Ensure session tracking is working (check `wp_ds_sessions` table)
2. Verify pageviews are being logged (`wp_ds_pageviews` table)
3. Check that the order hook is firing (`woocommerce_payment_complete` or `edd_complete_purchase`)

## Next Steps

For a complete dashboard UI, implement React components that consume these REST API endpoints. See `PLAN.md` for the full feature roadmap.
