<?php
/**
 * WooCommerce Integration
 * Tracks eCommerce events: product views, add to cart, purchases
 */

namespace DataSignals;

class WooCommerce_Tracker {
    
    /**
     * Initialize WooCommerce tracking hooks
     */
    public static function init(): void {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Track product views
        add_action('woocommerce_after_single_product', [self::class, 'track_product_view']);
        
        // Track add to cart
        add_action('woocommerce_add_to_cart', [self::class, 'track_add_to_cart'], 10, 6);
        
        // Track purchases
        add_action('woocommerce_thankyou', [self::class, 'track_purchase'], 10, 1);
        
        // Track remove from cart
        add_action('woocommerce_remove_cart_item', [self::class, 'track_remove_from_cart'], 10, 2);
    }
    
    /**
     * Track product view
     */
    public static function track_product_view(): void {
        global $product;
        
        if (!$product) {
            return;
        }
        
        self::record_event('product_view', [
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'product_price' => $product->get_price(),
            'product_category' => self::get_product_category($product),
        ]);
    }
    
    /**
     * Track add to cart
     */
    public static function track_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data): void {
        $product = wc_get_product($variation_id ?: $product_id);
        
        if (!$product) {
            return;
        }
        
        self::record_event('add_to_cart', [
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'product_name' => $product->get_name(),
            'product_price' => $product->get_price(),
            'quantity' => $quantity,
            'product_category' => self::get_product_category($product),
        ]);
    }
    
    /**
     * Track remove from cart
     */
    public static function track_remove_from_cart($cart_item_key, $cart): void {
        $item = $cart->get_cart_item($cart_item_key);
        
        if (!$item) {
            return;
        }
        
        $product = $item['data'];
        
        self::record_event('remove_from_cart', [
            'product_id' => $item['product_id'],
            'product_name' => $product->get_name(),
            'quantity' => $item['quantity'],
        ]);
    }
    
    /**
     * Track purchase
     */
    public static function track_purchase($order_id): void {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Check if already tracked
        if ($order->get_meta('_ds_tracked')) {
            return;
        }
        
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = [
                'product_id' => $item->get_product_id(),
                'product_name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total(),
            ];
        }
        
        self::record_event('purchase', [
            'order_id' => $order_id,
            'order_total' => $order->get_total(),
            'order_currency' => $order->get_currency(),
            'items_count' => count($items),
            'items' => $items,
            'payment_method' => $order->get_payment_method(),
            'coupon_used' => !empty($order->get_coupon_codes()),
        ]);
        
        // Mark as tracked
        $order->update_meta_data('_ds_tracked', time());
        $order->save();
    }
    
    /**
     * Record eCommerce event
     */
    private static function record_event(string $event_type, array $data): void {
        $buffer_file = get_buffer_filename();
        $dir = dirname($buffer_file);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $event = [
            'e',                    // type indicator (ecommerce)
            time(),                 // timestamp
            $event_type,            // event type
            wp_json_encode($data),  // event data as JSON
        ];
        
        $content = serialize($event) . PHP_EOL;
        file_put_contents($buffer_file, $content, FILE_APPEND);
    }
    
    /**
     * Get product's primary category
     */
    private static function get_product_category($product): string {
        $categories = get_the_terms($product->get_id(), 'product_cat');
        
        if (!$categories || is_wp_error($categories)) {
            return '';
        }
        
        return $categories[0]->name ?? '';
    }
}
