# Data Signals - Privacy-Focused Revenue Analytics

**Inspired by:** DataFa.st, Burst Statistics, Koko Analytics, Plausible  
**Focus:** Privacy + Revenue Attribution + Conversion Tracking  
**Tech:** WordPress Plugin, REST API only, React components, optimized DB  
**Scale:** 10,000 visits/minute (166 req/sec), 5M+ visits stored

---

## Core Value Proposition

**"Know which content, campaigns, and traffic sources make you money — not just vanity metrics."**

Unlike traditional analytics (pageviews, bounce rate), Data Signals tracks:
- **Blog posts → Sales** (which articles drive pricing clicks → conversions)
- **Email campaigns → Revenue** (which links in emails convert)
- **Products → Performance** (EDD + WooCommerce sales attribution)
- **Traffic sources → ROI** (which channels bring paying customers)
- **Revenue estimates** (Google Search Console integration for SEO value)

---

## Key Features (DataFa.st-Inspired)

### 1. Revenue Attribution 💰
- Track which pages lead to pricing clicks → sales
- Attribution models: first-click, last-click, multi-touch
- Customer journey mapping (first visit → conversion)
- Revenue per Visitor (RPV) metric
- Customer Lifetime Value (LTV) by source

### 2. Content Performance → Sales 📝
- Blog post tracking with conversion correlation
- "Money pages" identification (high conversion rate)
- Content funnel analysis (blog → pricing → checkout)
- Time-to-conversion tracking
- Drop-off points identification

### 3. Email Campaign Tracking 📧
- UTM parameter parsing (campaign, source, medium, content)
- Link-level click tracking (which CTA in email converts)
- Email → Sale attribution
- Campaign ROI calculation
- A/B test comparison

### 4. E-commerce Integration 🛒
- **WooCommerce:** Order tracking, product attribution, cart analysis
- **Easy Digital Downloads:** Download tracking, license attribution
- Product performance by traffic source
- Average Order Value (AOV) by channel
- Purchase funnel drop-off

### 5. Traffic Source Revenue 🚦
- Organic, Paid, Social, Referral, Direct attribution
- Cost-per-acquisition (CPA) tracking (if ad spend inputted)
- Return on Ad Spend (ROAS) calculation
- Channel comparison dashboard
- Traffic quality scoring (not just volume)

### 6. Google Search Console Integration 🔍
- Keyword → Revenue estimation
- Impressions/clicks → conversion correlation
- SEO value calculation (est. revenue from organic)
- Position tracking for money keywords
- Content gap analysis

### 7. Privacy-First Tracking 🔒
- No cookies, no fingerprinting
- IP anonymization (last octet zeroed)
- Aggregate data only (no personal tracking)
- GDPR/CCPA compliant by design
- Cookieless session tracking (server-side IDs)

### 8. Performance at Scale ⚡
- Optimized DB schema (partitioned by date)
- Batch inserts (100 events/query)
- Redis caching for real-time stats
- Indexed queries (< 50ms response)
- Archival strategy (90+ days → cold storage)

---

## Database Schema (Optimized)

### Core Tables

#### 1. `wp_ds_pageviews` (partitioned by month)
```sql
CREATE TABLE wp_ds_pageviews (
  id BIGINT UNSIGNED AUTO_INCREMENT,
  session_id CHAR(32) NOT NULL,
  page_id BIGINT UNSIGNED,
  url VARCHAR(500),
  referrer VARCHAR(500),
  utm_source VARCHAR(100),
  utm_medium VARCHAR(100),
  utm_campaign VARCHAR(100),
  utm_content VARCHAR(100),
  utm_term VARCHAR(100),
  country_code CHAR(2),
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id, created_at),
  INDEX idx_session (session_id, created_at),
  INDEX idx_page (page_id, created_at),
  INDEX idx_utm (utm_campaign, created_at)
) PARTITION BY RANGE (TO_DAYS(created_at)) (
  PARTITION p202601 VALUES LESS THAN (TO_DAYS('2026-02-01')),
  PARTITION p202602 VALUES LESS THAN (TO_DAYS('2026-03-01')),
  -- auto-create monthly partitions
);
```

#### 2. `wp_ds_events` (conversions, clicks, signups)
```sql
CREATE TABLE wp_ds_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id CHAR(32) NOT NULL,
  event_type VARCHAR(50) NOT NULL, -- 'pricing_click', 'purchase', 'signup'
  event_value DECIMAL(10,2),
  page_id BIGINT UNSIGNED,
  product_id BIGINT UNSIGNED,
  metadata JSON,
  created_at DATETIME NOT NULL,
  INDEX idx_session (session_id),
  INDEX idx_type (event_type, created_at),
  INDEX idx_product (product_id, created_at)
);
```

#### 3. `wp_ds_sessions` (aggregated per session)
```sql
CREATE TABLE wp_ds_sessions (
  session_id CHAR(32) PRIMARY KEY,
  first_page_id BIGINT UNSIGNED,
  first_referrer VARCHAR(500),
  utm_source VARCHAR(100),
  utm_medium VARCHAR(100),
  utm_campaign VARCHAR(100),
  country_code CHAR(2),
  total_pageviews SMALLINT UNSIGNED DEFAULT 1,
  total_revenue DECIMAL(10,2) DEFAULT 0,
  first_seen DATETIME NOT NULL,
  last_seen DATETIME NOT NULL,
  INDEX idx_campaign (utm_campaign),
  INDEX idx_source (utm_source),
  INDEX idx_revenue (total_revenue DESC)
);
```

#### 4. `wp_ds_revenue_attribution` (many-to-many)
```sql
CREATE TABLE wp_ds_revenue_attribution (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  session_id CHAR(32) NOT NULL,
  page_id BIGINT UNSIGNED,
  attribution_type ENUM('first_click', 'last_click', 'linear', 'time_decay') NOT NULL,
  revenue_share DECIMAL(10,2) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_order (order_id),
  INDEX idx_session (session_id),
  INDEX idx_page (page_id, created_at)
);
```

#### 5. `wp_ds_email_clicks` (email link tracking)
```sql
CREATE TABLE wp_ds_email_clicks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id VARCHAR(100) NOT NULL,
  link_url VARCHAR(500) NOT NULL,
  session_id CHAR(32),
  clicked_at DATETIME NOT NULL,
  converted BOOLEAN DEFAULT FALSE,
  revenue DECIMAL(10,2) DEFAULT 0,
  INDEX idx_campaign (campaign_id, clicked_at),
  INDEX idx_session (session_id)
);
```

#### 6. `wp_ds_aggregates` (pre-computed stats)
```sql
CREATE TABLE wp_ds_aggregates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  metric_type VARCHAR(50) NOT NULL, -- 'pageviews', 'revenue', 'conversions'
  dimension VARCHAR(100), -- 'page_id', 'utm_campaign', 'country'
  dimension_value VARCHAR(255),
  value DECIMAL(15,2) NOT NULL,
  UNIQUE KEY unique_metric (date, metric_type, dimension, dimension_value),
  INDEX idx_date (date, metric_type)
);
```

---

## Tech Stack

### Backend (WordPress)
- **Core:** PHP 8.0+ (typed properties, match expressions)
- **Database:** MySQL 8.0+ (JSON support, partitioning, CTEs)
- **Caching:** Redis (session data, real-time stats)
- **REST API:** WordPress REST API v2 (custom endpoints)
- **Security:** Nonces, capability checks, prepared statements

### Frontend (React)
- **Framework:** React 18+ (hooks, concurrent features)
- **State:** Zustand (lightweight state management)
- **Charts:** Recharts (declarative, responsive)
- **UI Components:** WordPress dataForm + custom React
- **Data Fetching:** wp.apiFetch (WordPress built-in)

### External Integrations
- **WooCommerce:** Action hooks (`woocommerce_new_order`, `woocommerce_payment_complete`)
- **Easy Digital Downloads:** `edd_complete_purchase` hook
- **Google Search Console API:** OAuth 2.0, daily sync
- **Email Providers:** UTM parsing, webhook support (optional)

---

## Performance Targets

### Request Handling
- **10,000 visits/min** = 166 req/sec
- **Batch inserts:** 100 pageviews/query (reduce DB load)
- **Async processing:** WP Cron for aggregation
- **Response time:** < 50ms for tracking endpoint

### Database Optimization
- **Partitioning:** Monthly partitions (auto-prune old data)
- **Indexes:** 15+ strategic indexes (covering queries)
- **Aggregates:** Pre-computed daily (cron job)
- **Archival:** 90+ days → compressed JSON export

### Caching Strategy
- **Redis:** Session data (1hr TTL), real-time counts (5min TTL)
- **Transients:** Dashboard stats (15min cache)
- **Object Cache:** WordPress persistent cache (if available)

---

## Security Checklist

- ✅ **Input Sanitization:** All REST params sanitized
- ✅ **SQL Injection:** Prepared statements only
- ✅ **XSS Prevention:** esc_html(), esc_attr() everywhere
- ✅ **CSRF Protection:** Nonces on all forms
- ✅ **Capability Checks:** `manage_options` for admin endpoints
- ✅ **Rate Limiting:** 1000 req/min per IP (tracking endpoint)
- ✅ **Data Privacy:** IP anonymization, no PII storage
- ✅ **Encryption:** Session IDs hashed (SHA-256)

---

## Sub-Agent Tasks

### 1. Core Plugin Architecture + DB Schema
- Plugin bootstrap, autoloader, namespace
- Database schema creation, migration system
- Session tracking logic (cookieless, server-side)
- Batch insert optimization
- Partitioning setup

### 2. WooCommerce + EDD Integration
- Order event tracking
- Product attribution (first/last touch)
- Revenue calculation per session
- Cart abandonment tracking
- Purchase funnel analysis

### 3. Email Campaign Tracking
- UTM parameter parser
- Link click tracking (redirect + log)
- Campaign performance dashboard
- Email → Sale attribution
- ROI calculation

### 4. Google Search Console Integration
- OAuth 2.0 flow
- Daily keyword/impressions sync
- Keyword → Revenue estimation
- Position tracking
- SEO value calculation

### 5. React Dashboard
- Main analytics view (pageviews, revenue, conversions)
- Revenue attribution charts (by source, campaign, page)
- Content performance table (blog → sales)
- Traffic sources breakdown
- Real-time stats widget

### 6. Security Audit + Performance Testing
- Security review (OWASP checklist)
- Load testing (simulate 10k req/min)
- Query optimization (EXPLAIN queries)
- Redis caching verification
- Final code review

---

## Deliverables

- ✅ WordPress plugin (data-signals/)
- ✅ REST API endpoints (10+)
- ✅ React dashboard (5+ views)
- ✅ Database schema (6 tables, partitioned)
- ✅ WooCommerce integration
- ✅ EDD integration
- ✅ Google Search Console sync
- ✅ Email tracking system
- ✅ Documentation (setup, API, privacy)
- ✅ Security audit report
- ✅ Performance benchmarks

---

**Timeline:** 60-90 minutes (6 sub-agents parallelized)  
**Target:** Production-ready, scalable, privacy-first revenue analytics
