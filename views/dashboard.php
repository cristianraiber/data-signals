<?php
/**
 * Dashboard view template with tabs
 */

defined('ABSPATH') or exit;

$dashboard_url = admin_url('admin.php?page=data-signals');
$logo_url = plugins_url('assets/images/logo.svg', DS_FILE);

// Helper function for percentage
function ds_percent($value, $total) {
    if ($total == 0) return '0%';
    return round(($value / $total) * 100, 1) . '%';
}

// Helper for country flag emoji
function ds_flag($code) {
    if (empty($code) || strlen($code) !== 2) return '🌍';
    $code = strtoupper($code);
    return implode('', array_map(fn($c) => mb_chr(ord($c) - ord('A') + 0x1F1E6), str_split($code)));
}
?>

<style>
:root {
    --ds-primary: #4F46E5;
    --ds-primary-dark: #4338CA;
    --ds-secondary: #06B6D4;
    --ds-success: #10B981;
    --ds-warning: #F59E0B;
    --ds-danger: #EF4444;
    --ds-border: #E5E7EB;
    --ds-border-dark: #D1D5DB;
    --ds-bg: #F9FAFB;
    --ds-bg-card: #FFFFFF;
    --ds-text: #111827;
    --ds-text-muted: #6B7280;
    --ds-text-light: #9CA3AF;
    --ds-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
    --ds-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --ds-radius: 8px;
}

* { box-sizing: border-box; }

#ds-dashboard {
    margin-left: -20px;
    background: var(--ds-bg);
    min-height: calc(100vh - 32px);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

/* Header */
.ds-header {
    background: linear-gradient(135deg, var(--ds-primary) 0%, #7C3AED 100%);
    padding: 0;
    position: relative;
    overflow: hidden;
}

.ds-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    opacity: 0.5;
}

.ds-header-inner {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ds-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.ds-logo {
    width: 48px;
    height: 48px;
    background: rgba(255,255,255,0.15);
    border-radius: 12px;
    padding: 8px;
    backdrop-filter: blur(8px);
}

.ds-logo img {
    width: 100%;
    height: 100%;
    filter: brightness(0) invert(1);
}

.ds-header-title {
    color: #fff;
}

.ds-header-title h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -0.5px;
}

.ds-header-title .ds-version {
    font-size: 12px;
    opacity: 0.7;
    margin-top: 2px;
}

.ds-header-right {
    display: flex;
    align-items: center;
    gap: 16px;
}

.ds-realtime {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(8px);
    padding: 10px 16px;
    border-radius: var(--ds-radius);
    color: #fff;
    font-size: 14px;
    font-weight: 500;
}

.ds-realtime .dot {
    width: 8px;
    height: 8px;
    background: var(--ds-success);
    border-radius: 50%;
    box-shadow: 0 0 8px var(--ds-success);
    animation: ds-pulse 2s infinite;
}

@keyframes ds-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.2); }
}

.ds-settings-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.15);
    border-radius: var(--ds-radius);
    color: #fff;
    text-decoration: none;
    transition: background 0.2s;
}

.ds-settings-link:hover {
    background: rgba(255,255,255,0.25);
    color: #fff;
}

/* Navigation Tabs */
.ds-nav {
    background: var(--ds-bg-card);
    border-bottom: 1px solid var(--ds-border);
    position: sticky;
    top: 32px;
    z-index: 100;
}

.ds-nav-inner {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    gap: 0;
    padding: 0 24px;
    overflow-x: auto;
}

.ds-nav a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px 20px;
    text-decoration: none;
    color: var(--ds-text-muted);
    font-size: 14px;
    font-weight: 500;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    white-space: nowrap;
    transition: all 0.15s;
}

.ds-nav a:hover {
    color: var(--ds-text);
    background: var(--ds-bg);
}

.ds-nav a.active {
    color: var(--ds-primary);
    border-bottom-color: var(--ds-primary);
}

.ds-nav-icon {
    font-size: 16px;
}

/* Content */
.ds-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
}

/* Toolbar */
.ds-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    gap: 12px;
}

/* Export Dropdown */
.ds-export-dropdown {
    position: relative;
}

.ds-export-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    background: var(--ds-bg-card);
    border: 1px solid var(--ds-border-dark);
    border-radius: var(--ds-radius);
    font-size: 14px;
    font-weight: 500;
    color: var(--ds-text);
    cursor: pointer;
    transition: all 0.15s;
}

.ds-export-btn:hover {
    background: var(--ds-bg);
    border-color: var(--ds-primary);
}

.ds-export-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 4px;
    background: var(--ds-bg-card);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius);
    box-shadow: var(--ds-shadow-md);
    min-width: 160px;
    z-index: 100;
    overflow: hidden;
}

.ds-export-menu.show {
    display: block;
}

.ds-export-menu a {
    display: block;
    padding: 10px 16px;
    color: var(--ds-text);
    text-decoration: none;
    font-size: 14px;
    transition: background 0.1s;
}

.ds-export-menu a:hover {
    background: var(--ds-bg);
    color: var(--ds-primary);
}

.ds-date-picker {
    display: flex;
    gap: 8px;
    align-items: center;
}

.ds-date-picker select,
.ds-date-picker input[type="date"] {
    padding: 10px 14px;
    border: 1px solid var(--ds-border-dark);
    border-radius: var(--ds-radius);
    font-size: 14px;
    min-height: 42px;
    background: var(--ds-bg-card);
    color: var(--ds-text);
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.ds-date-picker select:focus,
.ds-date-picker input[type="date"]:focus {
    border-color: var(--ds-primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    outline: none;
}

.ds-date-picker .button {
    min-height: 42px;
    padding: 10px 20px;
    background: var(--ds-primary);
    color: #fff;
    border: none;
    border-radius: var(--ds-radius);
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
}

.ds-date-picker .button:hover {
    background: var(--ds-primary-dark);
}

/* Stats Cards */
.ds-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.ds-stat-card {
    background: var(--ds-bg-card);
    padding: 24px;
    border-radius: var(--ds-radius);
    box-shadow: var(--ds-shadow);
    border: 1px solid var(--ds-border);
    transition: box-shadow 0.2s, transform 0.2s;
}

.ds-stat-card:hover {
    box-shadow: var(--ds-shadow-md);
    transform: translateY(-2px);
}

.ds-stat-card h3 {
    margin: 0 0 12px;
    font-size: 12px;
    color: var(--ds-text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ds-stat-card .value {
    font-size: 36px;
    font-weight: 700;
    color: var(--ds-text);
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.ds-stat-card .sub {
    font-size: 13px;
    color: var(--ds-text-muted);
    margin-top: 6px;
}

.ds-stat-card .change {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    margin-top: 12px;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 600;
}

.ds-stat-card .change.positive {
    color: var(--ds-success);
    background: rgba(16, 185, 129, 0.1);
}

.ds-stat-card .change.negative {
    color: var(--ds-danger);
    background: rgba(239, 68, 68, 0.1);
}

/* Cards */
.ds-card {
    background: var(--ds-bg-card);
    border-radius: var(--ds-radius);
    box-shadow: var(--ds-shadow);
    border: 1px solid var(--ds-border);
    margin-bottom: 24px;
    overflow: hidden;
}

.ds-card-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--ds-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ds-card-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: var(--ds-text);
}

.ds-card-body {
    padding: 24px;
}

.ds-chart {
    min-height: 320px;
}

/* Tables Grid */
.ds-tables {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 24px;
}

/* Tables */
.ds-table {
    width: 100%;
    border-collapse: collapse;
}

.ds-table th,
.ds-table td {
    padding: 14px 24px;
    text-align: left;
    border-bottom: 1px solid var(--ds-border);
}

.ds-table th {
    font-weight: 600;
    color: var(--ds-text-muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: var(--ds-bg);
}

.ds-table td {
    font-size: 14px;
    color: var(--ds-text);
}

.ds-table td.num {
    text-align: right;
    font-variant-numeric: tabular-nums;
    font-weight: 500;
}

.ds-table .url {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ds-table a {
    color: var(--ds-primary);
    text-decoration: none;
    font-weight: 500;
}

.ds-table a:hover {
    text-decoration: underline;
}

.ds-table tbody tr {
    transition: background 0.1s;
}

.ds-table tbody tr:hover {
    background: var(--ds-bg);
}

.ds-table tbody tr:last-child td {
    border-bottom: none;
}

.ds-empty {
    color: var(--ds-text-light);
    font-style: italic;
    text-align: center;
    padding: 40px !important;
}

/* Bar Charts */
.ds-bar-chart {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.ds-bar-row {
    display: flex;
    align-items: center;
    gap: 16px;
}

.ds-bar-label {
    width: 120px;
    font-size: 14px;
    font-weight: 500;
    color: var(--ds-text);
    flex-shrink: 0;
}

.ds-bar-track {
    flex: 1;
    height: 28px;
    background: var(--ds-bg);
    border-radius: 6px;
    overflow: hidden;
}

.ds-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--ds-primary), var(--ds-secondary));
    border-radius: 6px;
    transition: width 0.5s ease;
}

.ds-bar-fill.mobile {
    background: linear-gradient(90deg, var(--ds-success), #34D399);
}

.ds-bar-fill.tablet {
    background: linear-gradient(90deg, var(--ds-warning), #FBBF24);
}

.ds-bar-value {
    width: 80px;
    text-align: right;
    font-size: 14px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--ds-text);
}

/* Responsive */
@media (max-width: 782px) {
    #ds-dashboard {
        margin-left: -10px;
    }
    
    .ds-header-inner {
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }
    
    .ds-header-left {
        flex-direction: column;
    }
    
    .ds-content {
        padding: 16px;
    }
    
    .ds-tables {
        grid-template-columns: 1fr;
    }
    
    .ds-stat-card .value {
        font-size: 28px;
    }
    
    .ds-nav-inner {
        padding: 0 16px;
    }
    
    .ds-toolbar {
        justify-content: center;
    }
}
</style>

<div id="ds-dashboard">
    <!-- Header -->
    <div class="ds-header">
        <div class="ds-header-inner">
            <div class="ds-header-left">
                <div class="ds-logo">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Data Signals">
                </div>
                <div class="ds-header-title">
                    <h1>Data Signals</h1>
                    <div class="ds-version">v<?php echo esc_html(DS_VERSION); ?> • Privacy-first Analytics</div>
                </div>
            </div>
            <div class="ds-header-right">
                <div class="ds-realtime">
                    <span class="dot"></span>
                    <span><strong><?php echo number_format_i18n($realtime); ?></strong> <?php esc_html_e('visitors now', 'data-signals'); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=data-signals-settings')); ?>" class="ds-settings-link" title="<?php esc_attr_e('Settings', 'data-signals'); ?>">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="ds-nav">
        <div class="ds-nav-inner">
            <?php 
            $tab_icons = [
                'overview' => '📊',
                'devices' => '💻',
                'geographic' => '🌍',
                'campaigns' => '📣',
                'referrers' => '🔗',
                'clicks' => '👆',
            ];
            foreach ($tabs as $key => $label): 
            ?>
                <a href="<?php echo esc_url(add_query_arg(['tab' => $key, 'view' => $range], $dashboard_url)); ?>" 
                   class="<?php echo $tab === $key ? 'active' : ''; ?>">
                    <span class="ds-nav-icon"><?php echo $tab_icons[$key] ?? '📈'; ?></span>
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    
    <!-- Content -->
    <div class="ds-content">
        <!-- Toolbar -->
        <div class="ds-toolbar">
            <div class="ds-export-dropdown">
                <button type="button" class="ds-export-btn" onclick="this.nextElementSibling.classList.toggle('show')">
                    📥 <?php esc_html_e('Export CSV', 'data-signals'); ?>
                    <span style="margin-left: 4px;">▾</span>
                </button>
                <div class="ds-export-menu">
                    <?php
                    $export_types = [
                        'overview' => __('Daily Stats', 'data-signals'),
                        'pages' => __('Pages', 'data-signals'),
                        'referrers' => __('Referrers', 'data-signals'),
                        'devices' => __('Devices', 'data-signals'),
                        'countries' => __('Countries', 'data-signals'),
                        'campaigns' => __('Campaigns', 'data-signals'),
                        'clicks' => __('Clicks', 'data-signals'),
                    ];
                    foreach ($export_types as $type => $label):
                        $export_url = wp_nonce_url(add_query_arg([
                            'ds_export' => $type,
                            'start_date' => $start_str,
                            'end_date' => $end_str,
                        ], admin_url('admin.php')), 'ds_export');
                    ?>
                        <a href="<?php echo esc_url($export_url); ?>"><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <form method="get" class="ds-date-picker">
                <input type="hidden" name="page" value="data-signals">
                <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
                
                <select name="view" onchange="this.form.submit()">
                    <?php foreach ($presets as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($range, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="custom" <?php selected($range, 'custom'); ?>><?php esc_html_e('Custom Range', 'data-signals'); ?></option>
                </select>
                
                <?php if ($range === 'custom'): ?>
                    <input type="date" name="start_date" value="<?php echo esc_attr($date_start->format('Y-m-d')); ?>">
                    <span style="color: var(--ds-text-muted);">→</span>
                    <input type="date" name="end_date" value="<?php echo esc_attr($date_end->format('Y-m-d')); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Apply', 'data-signals'); ?></button>
                <?php endif; ?>
            </form>
        </div>
        
        <?php
        // Render tab content
        switch ($tab) {
            case 'devices':
                include DS_DIR . '/views/tabs/devices.php';
                break;
            case 'geographic':
                include DS_DIR . '/views/tabs/geographic.php';
                break;
            case 'campaigns':
                include DS_DIR . '/views/tabs/campaigns.php';
                break;
            case 'referrers':
                include DS_DIR . '/views/tabs/referrers.php';
                break;
            case 'clicks':
                include DS_DIR . '/views/tabs/clicks.php';
                break;
            default:
                include DS_DIR . '/views/tabs/overview.php';
                break;
        }
        ?>
    </div>
</div>
