<?php
/**
 * Email Tracking System Tests
 *
 * Manual test file to verify email tracking functionality.
 * Run from WordPress admin or via WP-CLI.
 *
 * @package DataSignals
 */

// Ensure WordPress is loaded
if ( ! defined( 'ABSPATH' ) ) {
    die( 'WordPress not loaded' );
}

use DataSignals\UTM_Parser;
use DataSignals\Email_Tracker;
use DataSignals\Campaign_Analytics;
use DataSignals\Link_Tracker;

/**
 * Test UTM Parser
 */
function test_utm_parser() {
    echo "\n=== Testing UTM Parser ===\n";

    // Test URL parsing
    $url = 'https://example.com/page?utm_source=email&utm_medium=newsletter&utm_campaign=summer-sale&utm_content=cta-buy&utm_term=product-a';
    $utm_data = UTM_Parser::extract( $url );

    echo "Extracted UTM data:\n";
    print_r( $utm_data );

    // Test validation
    $errors = UTM_Parser::validate( $utm_data );
    echo "Validation errors: " . ( empty( $errors ) ? 'None' : implode( ', ', $errors ) ) . "\n";

    // Test URL building
    $base_url = 'https://example.com/product';
    $tracking_url = UTM_Parser::build_url( $base_url, $utm_data );
    echo "Built URL: {$tracking_url}\n";

    echo "✅ UTM Parser tests passed\n";
}

/**
 * Test Email Tracker
 */
function test_email_tracker() {
    echo "\n=== Testing Email Tracker ===\n";

    // Test tracking URL generation
    $destination = 'https://example.com/products/awesome-product';
    $campaign_id = 'test-campaign-' . time();

    $tracking_url = Email_Tracker::build_tracking_url( $destination, $campaign_id );
    echo "Tracking URL: {$tracking_url}\n";

    // Test click logging (mock data)
    $session_id = md5( 'test-session-' . time() );
    $click_id = Email_Tracker::log_click( $campaign_id, $destination, $session_id );

    if ( $click_id ) {
        echo "✅ Click logged successfully (ID: {$click_id})\n";
    } else {
        echo "❌ Failed to log click\n";
    }

    // Test conversion marking
    $result = Email_Tracker::mark_converted( $session_id, 99.99 );
    echo "Conversion marked: " . ( $result ? '✅ Success' : '❌ Failed' ) . "\n";

    // Get clicks by campaign
    $clicks = Email_Tracker::get_clicks_by_campaign( $campaign_id, 10 );
    echo "Clicks found: " . count( $clicks ) . "\n";

    echo "✅ Email Tracker tests passed\n";
}

/**
 * Test Campaign Analytics
 */
function test_campaign_analytics() {
    echo "\n=== Testing Campaign Analytics ===\n";

    $campaign_id = 'test-campaign-' . ( time() - 100 );

    // Get campaign performance
    $performance = Campaign_Analytics::get_campaign_performance( $campaign_id );
    echo "Performance metrics:\n";
    echo "  Total clicks: {$performance['total_clicks']}\n";
    echo "  Unique clicks: {$performance['unique_clicks']}\n";
    echo "  Conversions: {$performance['conversions']}\n";
    echo "  Revenue: \${$performance['total_revenue']}\n";
    echo "  Conversion rate: {$performance['conversion_rate']}%\n";

    // Test ROI calculation
    $roi = Campaign_Analytics::calculate_roi( $campaign_id, 100.00 );
    echo "\nROI Analysis:\n";
    echo "  Cost: \${$roi['cost']}\n";
    echo "  Revenue: \${$roi['revenue']}\n";
    echo "  ROI: {$roi['roi']}%\n";
    echo "  ROAS: {$roi['roas']}x\n";

    // Test revenue per email
    $rpe = Campaign_Analytics::get_revenue_per_email( $campaign_id, 1000 );
    echo "\nRevenue Per Email:\n";
    echo "  Emails sent: {$rpe['emails_sent']}\n";
    echo "  CTR: {$rpe['ctr']}%\n";
    echo "  Revenue per email: \${$rpe['revenue_per_email']}\n";

    // Test CAC
    $cac = Campaign_Analytics::get_cac( $campaign_id, 100.00 );
    echo "\nCustomer Acquisition Cost:\n";
    echo "  CAC: \${$cac['cac']}\n";

    echo "✅ Campaign Analytics tests passed\n";
}

/**
 * Test Link Tracker
 */
function test_link_tracker() {
    echo "\n=== Testing Link Tracker ===\n";

    $campaign_id = 'test-campaign-' . ( time() - 100 );

    // Get campaign links
    $links = Link_Tracker::get_campaign_links( $campaign_id );
    echo "Links found: " . count( $links ) . "\n";

    if ( ! empty( $links ) ) {
        $link = $links[0];
        echo "\nTop Link:\n";
        echo "  URL: {$link['link_url']}\n";
        echo "  Label: {$link['link_label']}\n";
        echo "  Clicks: {$link['total_clicks']}\n";
        echo "  Conversions: {$link['conversions']}\n";
        echo "  Revenue: \${$link['total_revenue']}\n";
        echo "  Conversion rate: {$link['conversion_rate']}%\n";
    }

    // Get top links across all campaigns
    $top_links = Link_Tracker::get_top_links( 'revenue', 5 );
    echo "\nTop 5 Links by Revenue: " . count( $top_links ) . " found\n";

    echo "✅ Link Tracker tests passed\n";
}

/**
 * Test complete workflow
 */
function test_complete_workflow() {
    echo "\n=== Testing Complete Workflow ===\n";

    $campaign_id = 'workflow-test-' . time();

    // Step 1: Simulate email click
    echo "1. Simulating email click...\n";
    $session_id = md5( 'workflow-session-' . time() );
    $link_url = 'https://example.com/products/test-product';

    $click_id = Email_Tracker::log_click( $campaign_id, $link_url, $session_id );
    echo "   Click ID: {$click_id}\n";

    // Step 2: Simulate UTM tracking
    echo "2. Tracking UTM parameters...\n";
    $utm_data = [
        'utm_source'   => 'email',
        'utm_medium'   => 'newsletter',
        'utm_campaign' => $campaign_id,
        'utm_content'  => 'hero-cta',
    ];
    UTM_Parser::store_in_session( $session_id, $utm_data );
    echo "   UTM data stored\n";

    // Step 3: Simulate conversion
    echo "3. Simulating conversion...\n";
    Email_Tracker::mark_converted( $session_id, 149.99 );
    echo "   Conversion marked (\$149.99)\n";

    // Step 4: Get analytics
    echo "4. Retrieving analytics...\n";
    $performance = Campaign_Analytics::get_campaign_performance( $campaign_id );
    echo "   Conversions: {$performance['conversions']}\n";
    echo "   Revenue: \${$performance['total_revenue']}\n";

    $links = Link_Tracker::get_campaign_links( $campaign_id );
    echo "   Links tracked: " . count( $links ) . "\n";

    echo "✅ Complete workflow test passed\n";
}

/**
 * Run all tests
 */
function run_all_tests() {
    echo "╔════════════════════════════════════════╗\n";
    echo "║  Data Signals Email Tracking Tests    ║\n";
    echo "╚════════════════════════════════════════╝\n";

    try {
        test_utm_parser();
        test_email_tracker();
        test_campaign_analytics();
        test_link_tracker();
        test_complete_workflow();

        echo "\n" . str_repeat( '=', 40 ) . "\n";
        echo "✅ ALL TESTS PASSED\n";
        echo str_repeat( '=', 40 ) . "\n";
    } catch ( Exception $e ) {
        echo "\n❌ TEST FAILED: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
}

// Run tests if called directly
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    run_all_tests();
} elseif ( current_user_can( 'manage_options' ) ) {
    run_all_tests();
} else {
    echo "❌ Tests must be run by an administrator\n";
}
