<?php
/**
 * Revenue Tracking & Aggregation
 * 
 * Aggregates revenue data from custom events with amount/currency fields.
 * Supports multi-currency tracking with automatic aggregation.
 * 
 * @package DataSignals
 */

namespace DataSignals;

defined('ABSPATH') or exit;

class Revenue_Tracker {
    
    /** @var array Event names that trigger revenue tracking */
    private static $revenue_events = ['purchase', 'order_completed', 'sale', 'payment', 'subscription'];
    
    /** @var array Field names to look for amount */
    private static $amount_fields = ['amount', 'total', 'revenue', 'price', 'value', 'order_total'];
    
    /** @var array Field names to look for currency */
    private static $currency_fields = ['currency', 'currency_code'];
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Aggregate revenue after event is tracked
        add_action('data_signals_event_tracked', [__CLASS__, 'maybe_aggregate_event'], 10, 3);
    }
    
    /**
     * Check if event should trigger revenue aggregation
     */
    public static function maybe_aggregate_event($event_name, $data, $event_id) {
        // Check if this is a revenue event
        if (!self::is_revenue_event($event_name)) {
            return;
        }
        
        // Extract amount and currency from data
        $amount = self::extract_amount($data);
        $currency = self::extract_currency($data);
        
        if ($amount === null || $amount <= 0) {
            return;
        }
        
        // Aggregate immediately
        self::aggregate_transaction($event_name, $amount, $currency, $data);
    }
    
    /**
     * Check if event name is a revenue event
     */
    public static function is_revenue_event($event_name) {
        // Check predefined list
        if (in_array($event_name, self::$revenue_events, true)) {
            return true;
        }
        
        // Check if event name contains revenue-related keywords
        $keywords = ['purchase', 'order', 'sale', 'payment', 'checkout', 'buy', 'subscribe'];
        foreach ($keywords as $keyword) {
            if (stripos($event_name, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract amount from event data
     */
    private static function extract_amount($data) {
        if (!is_array($data)) {
            return null;
        }
        
        foreach (self::$amount_fields as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                return (float) $data[$field];
            }
        }
        
        return null;
    }
    
    /**
     * Extract currency from event data
     */
    private static function extract_currency($data) {
        if (!is_array($data)) {
            return 'USD';
        }
        
        foreach (self::$currency_fields as $field) {
            if (isset($data[$field]) && is_string($data[$field]) && strlen($data[$field]) === 3) {
                return strtoupper($data[$field]);
            }
        }
        
        return 'USD'; // Default currency
    }
    
    /**
     * Aggregate a single transaction
     */
    private static function aggregate_transaction($event_name, $amount, $currency, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ds_revenue_stats';
        $today = current_time('Y-m-d');
        
        // Upsert revenue stats
        $wpdb->query($wpdb->prepare("
            INSERT INTO {$table} (date, event_name, currency, total_amount, transaction_count, avg_amount, min_amount, max_amount)
            VALUES (%s, %s, %s, %f, 1, %f, %f, %f)
            ON DUPLICATE KEY UPDATE
                total_amount = total_amount + VALUES(total_amount),
                transaction_count = transaction_count + 1,
                avg_amount = (total_amount + VALUES(total_amount)) / (transaction_count + 1),
                min_amount = LEAST(COALESCE(min_amount, VALUES(min_amount)), VALUES(min_amount)),
                max_amount = GREATEST(COALESCE(max_amount, VALUES(max_amount)), VALUES(max_amount))
        ", $today, $event_name, $currency, $amount, $amount, $amount, $amount));
        
        // Also aggregate product-level if product_id is present
        $product_id = $data['product_id'] ?? $data['item_id'] ?? $data['sku'] ?? null;
        if ($product_id) {
            $product_name = $data['product_name'] ?? $data['item_name'] ?? $data['name'] ?? null;
            $quantity = $data['quantity'] ?? $data['qty'] ?? 1;
            
            $product_table = $wpdb->prefix . 'ds_product_revenue';
            
            $wpdb->query($wpdb->prepare("
                INSERT INTO {$product_table} (date, product_id, product_name, currency, total_amount, quantity, transaction_count)
                VALUES (%s, %s, %s, %s, %f, %d, 1)
                ON DUPLICATE KEY UPDATE
                    product_name = COALESCE(VALUES(product_name), product_name),
                    total_amount = total_amount + VALUES(total_amount),
                    quantity = quantity + VALUES(quantity),
                    transaction_count = transaction_count + 1
            ", $today, $product_id, $product_name, $currency, $amount, $quantity));
        }
    }
    
    /**
     * Get revenue stats for date range
     * 
     * @param string $start Start date (Y-m-d)
     * @param string $end End date (Y-m-d)
     * @param string|null $currency Filter by currency (null for all)
     * @return array
     */
    public static function get_stats($start, $end, $currency = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ds_revenue_stats';
        
        $where_currency = $currency ? $wpdb->prepare("AND currency = %s", $currency) : "";
        
        // Totals by currency
        $totals_by_currency = $wpdb->get_results($wpdb->prepare("
            SELECT 
                currency,
                SUM(total_amount) as total_revenue,
                SUM(transaction_count) as total_transactions,
                AVG(avg_amount) as avg_order_value,
                MIN(min_amount) as min_order,
                MAX(max_amount) as max_order
            FROM {$table}
            WHERE date BETWEEN %s AND %s
            GROUP BY currency
            ORDER BY total_revenue DESC
        ", $start, $end));
        
        // Daily revenue (for chart)
        $daily = $wpdb->get_results($wpdb->prepare("
            SELECT 
                date,
                currency,
                total_amount as revenue,
                transaction_count as transactions
            FROM {$table}
            WHERE date BETWEEN %s AND %s
            {$where_currency}
            ORDER BY date ASC, currency ASC
        ", $start, $end));
        
        // Grand totals
        $grand_total = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(total_amount) as total_revenue,
                SUM(transaction_count) as total_transactions
            FROM {$table}
            WHERE date BETWEEN %s AND %s
            {$where_currency}
        ", $start, $end));
        
        return [
            'by_currency' => $totals_by_currency,
            'daily' => $daily,
            'totals' => $grand_total,
            'currencies' => array_column($totals_by_currency, 'currency'),
        ];
    }
    
    /**
     * Get top products by revenue
     * 
     * @param string $start Start date
     * @param string $end End date
     * @param int $limit
     * @param string|null $currency Filter by currency
     * @return array
     */
    public static function get_top_products($start, $end, $limit = 10, $currency = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ds_product_revenue';
        
        $where_currency = $currency ? $wpdb->prepare("AND currency = %s", $currency) : "";
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                product_id,
                MAX(product_name) as product_name,
                currency,
                SUM(total_amount) as total_revenue,
                SUM(quantity) as total_quantity,
                SUM(transaction_count) as total_transactions
            FROM {$table}
            WHERE date BETWEEN %s AND %s
            {$where_currency}
            GROUP BY product_id, currency
            ORDER BY total_revenue DESC
            LIMIT %d
        ", $start, $end, $limit));
    }
    
    /**
     * Get revenue comparison with previous period
     * 
     * @param string $start Current period start
     * @param string $end Current period end
     * @param string $prev_start Previous period start
     * @param string $prev_end Previous period end
     * @return array
     */
    public static function get_comparison($start, $end, $prev_start, $prev_end) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ds_revenue_stats';
        
        $current = $wpdb->get_results($wpdb->prepare("
            SELECT 
                currency,
                SUM(total_amount) as revenue,
                SUM(transaction_count) as transactions
            FROM {$table}
            WHERE date BETWEEN %s AND %s
            GROUP BY currency
        ", $start, $end), OBJECT_K);
        
        $previous = $wpdb->get_results($wpdb->prepare("
            SELECT 
                currency,
                SUM(total_amount) as revenue,
                SUM(transaction_count) as transactions
            FROM {$table}
            WHERE date BETWEEN %s AND %s
            GROUP BY currency
        ", $prev_start, $prev_end), OBJECT_K);
        
        $comparison = [];
        
        // Get all currencies from both periods
        $currencies = array_unique(array_merge(array_keys($current), array_keys($previous)));
        
        foreach ($currencies as $curr) {
            $curr_revenue = $current[$curr]->revenue ?? 0;
            $prev_revenue = $previous[$curr]->revenue ?? 0;
            
            $curr_trans = $current[$curr]->transactions ?? 0;
            $prev_trans = $previous[$curr]->transactions ?? 0;
            
            $comparison[$curr] = [
                'currency' => $curr,
                'current_revenue' => $curr_revenue,
                'previous_revenue' => $prev_revenue,
                'revenue_change' => $prev_revenue > 0 ? (($curr_revenue - $prev_revenue) / $prev_revenue) * 100 : 0,
                'current_transactions' => $curr_trans,
                'previous_transactions' => $prev_trans,
                'transactions_change' => $prev_trans > 0 ? (($curr_trans - $prev_trans) / $prev_trans) * 100 : 0,
            ];
        }
        
        return $comparison;
    }
    
    /**
     * Get available currencies in the data
     */
    public static function get_currencies($start = null, $end = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ds_revenue_stats';
        
        if ($start && $end) {
            return $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT currency FROM {$table}
                WHERE date BETWEEN %s AND %s
                ORDER BY currency
            ", $start, $end));
        }
        
        return $wpdb->get_col("SELECT DISTINCT currency FROM {$table} ORDER BY currency");
    }
    
    /**
     * Format currency amount
     */
    public static function format_amount($amount, $currency = 'USD') {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'RON' => 'lei ',
            'PLN' => 'zł',
            'CZK' => 'Kč',
            'HUF' => 'Ft',
            'CHF' => 'CHF ',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'JPY' => '¥',
            'CNY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'INR' => '₹',
            'BRL' => 'R$',
        ];
        
        $symbol = $symbols[$currency] ?? $currency . ' ';
        $decimals = in_array($currency, ['JPY', 'HUF']) ? 0 : 2;
        
        return $symbol . number_format($amount, $decimals);
    }
}
