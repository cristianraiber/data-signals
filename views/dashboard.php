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
    if (empty($code) || strlen($code) !== 2) return '';
    $code = strtoupper($code);
    return implode('', array_map(fn($c) => mb_chr(ord($c) - ord('A') + 0x1F1E6), str_split($code)));
}
?>

<style>
#ds-dashboard {
    --ds-primary: var(--wp-admin-theme-color, #2271b1);
    --ds-primary-hover: var(--wp-admin-theme-color-darker-10, #135e96);
    --ds-primary-active: var(--wp-admin-theme-color-darker-20, #0a4b78);
    --ds-success: #00a32a;
    --ds-warning: #dba617;
    --ds-danger: #d63638;
    --ds-border: #c3c4c7;
    --ds-border-light: #e2e4e7;
    --ds-bg: #f0f0f1;
    --ds-bg-card: #fff;
    --ds-text: #1d2327;
    --ds-text-muted: #646970;
    --ds-text-light: #8c8f94;
    --ds-shadow: 0 1px 1px rgba(0,0,0,.04);
    --ds-shadow-md: 0 1px 3px rgba(0,0,0,.1);
    --ds-radius: 4px;
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
    background: var(--ds-primary);
    padding: 0;
}

.ds-header-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ds-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.ds-logo {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.2);
    border-radius: 6px;
    padding: 6px;
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
    font-size: 20px;
    font-weight: 600;
}

.ds-header-title .ds-version {
    font-size: 11px;
    opacity: 0.8;
    margin-top: 2px;
}

.ds-header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ds-realtime {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.15);
    padding: 8px 14px;
    border-radius: var(--ds-radius);
    color: #fff;
    font-size: 13px;
}

.ds-realtime .dot {
    width: 8px;
    height: 8px;
    background: #68de7c;
    border-radius: 50%;
    animation: ds-pulse 2s infinite;
}

@keyframes ds-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.ds-settings-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.15);
    border-radius: var(--ds-radius);
    color: #fff;
    text-decoration: none;
    transition: background 0.15s;
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
    gap: 6px;
    padding: 14px 18px;
    text-decoration: none;
    color: var(--ds-text-muted);
    font-size: 13px;
    font-weight: 500;
    border-bottom: 3px solid transparent;
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
    width: 18px;
    height: 18px;
    fill: currentColor;
    opacity: 0.7;
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
    margin-bottom: 20px;
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
    padding: 0 12px;
    height: 30px;
    background: var(--ds-bg-card);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius);
    font-size: 13px;
    color: var(--ds-text);
    cursor: pointer;
    transition: all 0.15s;
}

.ds-export-btn:hover {
    background: var(--ds-bg);
    border-color: var(--ds-primary);
    color: var(--ds-primary);
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
    min-width: 150px;
    z-index: 100;
    overflow: hidden;
}

.ds-export-menu.show {
    display: block;
}

.ds-export-menu a {
    display: block;
    padding: 8px 14px;
    color: var(--ds-text);
    text-decoration: none;
    font-size: 13px;
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
    padding: 0 8px;
    height: 30px;
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius);
    font-size: 13px;
    background: var(--ds-bg-card);
    color: var(--ds-text);
    cursor: pointer;
}

.ds-date-picker select:focus,
.ds-date-picker input[type="date"]:focus {
    border-color: var(--ds-primary);
    box-shadow: 0 0 0 1px var(--ds-primary);
    outline: none;
}

.ds-date-picker .button {
    height: 30px;
    padding: 0 12px;
}

/* Stats Cards */
.ds-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.ds-stat-card {
    background: var(--ds-bg-card);
    padding: 20px;
    border-radius: var(--ds-radius);
    box-shadow: var(--ds-shadow);
    border: 1px solid var(--ds-border-light);
}

.ds-stat-card h3 {
    margin: 0 0 8px;
    font-size: 11px;
    color: var(--ds-text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.ds-stat-card .value {
    font-size: 32px;
    font-weight: 600;
    color: var(--ds-text);
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.ds-stat-card .sub {
    font-size: 12px;
    color: var(--ds-text-muted);
    margin-top: 4px;
}

.ds-stat-card .change {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 12px;
    margin-top: 8px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 500;
}

.ds-stat-card .change.positive {
    color: var(--ds-success);
    background: rgba(0, 163, 42, 0.1);
}

.ds-stat-card .change.negative {
    color: var(--ds-danger);
    background: rgba(214, 54, 56, 0.1);
}

/* Cards */
.ds-card {
    background: var(--ds-bg-card);
    border-radius: var(--ds-radius);
    box-shadow: var(--ds-shadow);
    border: 1px solid var(--ds-border-light);
    margin-bottom: 20px;
    overflow: hidden;
}

.ds-card-header {
    padding: 14px 20px;
    border-bottom: 1px solid var(--ds-border-light);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ds-card-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--ds-text);
}

.ds-card-body {
    padding: 20px;
}

.ds-chart {
    min-height: 300px;
}

/* Tables Grid */
.ds-tables {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
    gap: 20px;
}

/* Tables */
.ds-table {
    width: 100%;
    border-collapse: collapse;
}

.ds-table th,
.ds-table td {
    padding: 12px 20px;
    text-align: left;
    border-bottom: 1px solid var(--ds-border-light);
}

.ds-table th {
    font-weight: 600;
    color: var(--ds-text-muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    background: var(--ds-bg);
}

.ds-table td {
    font-size: 13px;
    color: var(--ds-text);
}

.ds-table td.num {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.ds-table .url {
    max-width: 280px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ds-table a {
    color: var(--ds-primary);
    text-decoration: none;
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
    padding: 30px !important;
}

/* Bar Charts */
.ds-bar-chart {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.ds-bar-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ds-bar-label {
    width: 100px;
    font-size: 13px;
    font-weight: 500;
    color: var(--ds-text);
    flex-shrink: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ds-bar-track {
    flex: 1;
    height: 24px;
    background: var(--ds-bg);
    border-radius: 3px;
    overflow: hidden;
}

.ds-bar-fill {
    height: 100%;
    background: var(--ds-primary);
    border-radius: 3px;
    transition: width 0.4s ease;
}

.ds-bar-fill.secondary {
    background: var(--ds-success);
}

.ds-bar-fill.tertiary {
    background: var(--ds-warning);
}

.ds-bar-value {
    width: 70px;
    text-align: right;
    font-size: 13px;
    font-weight: 500;
    font-variant-numeric: tabular-nums;
    color: var(--ds-text);
}

/* Info Box */
.ds-info-box {
    background: var(--ds-bg);
    border: 1px dashed var(--ds-border);
    border-radius: var(--ds-radius);
    padding: 16px 20px;
}

.ds-info-box h4 {
    margin: 0 0 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ds-text);
}

.ds-info-box p {
    margin: 0;
    font-size: 13px;
    color: var(--ds-text-muted);
    line-height: 1.5;
}

.ds-info-box ul {
    margin: 10px 0 0;
    padding-left: 18px;
    font-size: 13px;
    color: var(--ds-text-muted);
    line-height: 1.7;
}

/* Responsive */
@media (max-width: 782px) {
    #ds-dashboard {
        margin-left: -10px;
    }
    
    .ds-header-inner {
        flex-direction: column;
        gap: 12px;
        text-align: center;
        padding: 16px;
    }
    
    .ds-header-left {
        flex-direction: column;
        gap: 8px;
    }
    
    .ds-content {
        padding: 16px;
    }
    
    .ds-tables {
        grid-template-columns: 1fr;
    }
    
    .ds-stat-card .value {
        font-size: 26px;
    }
    
    .ds-nav-inner {
        padding: 0 16px;
    }
    
    .ds-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .ds-export-dropdown {
        order: 2;
    }
    
    .ds-date-picker {
        justify-content: flex-end;
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
                    <div class="ds-version">v<?php echo esc_html(DS_VERSION); ?></div>
                </div>
            </div>
            <div class="ds-header-right">
                <div class="ds-realtime">
                    <span class="dot"></span>
                    <span><strong><?php echo number_format_i18n($realtime); ?></strong> <?php esc_html_e('visitors now', 'data-signals'); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=data-signals-settings')); ?>" class="ds-settings-link" title="<?php esc_attr_e('Settings', 'data-signals'); ?>">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="ds-nav">
        <div class="ds-nav-inner">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="<?php echo esc_url(add_query_arg(['tab' => $key, 'view' => $range], $dashboard_url)); ?>" 
                   class="<?php echo $tab === $key ? 'active' : ''; ?>">
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
                    <?php esc_html_e('Export CSV', 'data-signals'); ?>
                    <span>&#9662;</span>
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
                    <span style="color: var(--ds-text-muted);">–</span>
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
