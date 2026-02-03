<?php
/**
 * Dashboard view template with tabs
 */

defined('ABSPATH') or exit;

$dashboard_url = admin_url('admin.php?page=data-signals');

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
    --ds-primary: #3858e9;
    --ds-success: #00a32a;
    --ds-danger: #d63638;
    --ds-border: #e2e4e7;
    --ds-bg: #f0f0f1;
    --ds-text: #1e1e1e;
    --ds-text-muted: #646970;
}

#ds-dashboard {
    margin-left: -20px;
    background: var(--ds-bg);
    min-height: calc(100vh - 32px);
}

.ds-header-bar {
    background: #fff;
    border-bottom: 1px solid var(--ds-border);
    padding: 20px;
    text-align: center;
}

.ds-header-bar-inner {
    display: inline-flex;
    align-items: center;
    gap: 12px;
}

.ds-header-bar .ds-icon { font-size: 28px; }
.ds-header-bar .ds-title {
    font-size: 24px;
    font-weight: 600;
    color: var(--ds-text);
    margin: 0;
}

.ds-tabs {
    display: flex;
    justify-content: center;
    gap: 0;
    background: #fff;
    border-bottom: 1px solid var(--ds-border);
    padding: 0 20px;
}

.ds-tabs a {
    padding: 14px 20px;
    text-decoration: none;
    color: var(--ds-text-muted);
    font-size: 14px;
    font-weight: 500;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: all 0.15s;
}

.ds-tabs a:hover { color: var(--ds-text); }
.ds-tabs a.active {
    color: var(--ds-primary);
    border-bottom-color: var(--ds-primary);
}

.ds-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
}

.ds-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.ds-realtime {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    padding: 10px 16px;
    border: 1px solid var(--ds-border);
    border-radius: 4px;
    font-size: 14px;
}

.ds-realtime .dot {
    width: 8px;
    height: 8px;
    background: var(--ds-success);
    border-radius: 50%;
    animation: ds-pulse 2s infinite;
}

@keyframes ds-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

.ds-date-picker {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.ds-date-picker select,
.ds-date-picker input[type="date"] {
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
    min-height: 40px;
    background: #fff;
}

.ds-date-picker select:focus,
.ds-date-picker input[type="date"]:focus {
    border-color: var(--ds-primary);
    box-shadow: 0 0 0 1px var(--ds-primary);
    outline: none;
}

.ds-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.ds-stat-card {
    background: #fff;
    padding: 24px;
    border: 1px solid var(--ds-border);
    border-radius: 4px;
}

.ds-stat-card h3 {
    margin: 0 0 8px;
    font-size: 13px;
    color: var(--ds-text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ds-stat-card .value {
    font-size: 36px;
    font-weight: 600;
    color: var(--ds-text);
    line-height: 1.2;
}

.ds-stat-card .sub {
    font-size: 14px;
    color: var(--ds-text-muted);
    margin-top: 4px;
}

.ds-stat-card .change {
    font-size: 13px;
    margin-top: 8px;
    font-weight: 500;
}

.ds-stat-card .change.positive { color: var(--ds-success); }
.ds-stat-card .change.negative { color: var(--ds-danger); }

.ds-card {
    background: #fff;
    border: 1px solid var(--ds-border);
    border-radius: 4px;
    margin-bottom: 24px;
    overflow: hidden;
}

.ds-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--ds-border);
}

.ds-card-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--ds-text);
}

.ds-card-body { padding: 20px; }
.ds-chart { min-height: 300px; }

.ds-tables {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 24px;
}

.ds-table {
    width: 100%;
    border-collapse: collapse;
}

.ds-table th,
.ds-table td {
    padding: 12px 20px;
    text-align: left;
    border-bottom: 1px solid var(--ds-border);
}

.ds-table th {
    font-weight: 600;
    color: var(--ds-text-muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f9f9f9;
}

.ds-table td {
    font-size: 14px;
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

.ds-table a:hover { text-decoration: underline; }
.ds-table tbody tr:last-child td { border-bottom: none; }
.ds-empty { color: var(--ds-text-muted); font-style: italic; }

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
    font-size: 14px;
    font-weight: 500;
    flex-shrink: 0;
}

.ds-bar-track {
    flex: 1;
    height: 24px;
    background: #f0f0f1;
    border-radius: 4px;
    overflow: hidden;
}

.ds-bar-fill {
    height: 100%;
    background: var(--ds-primary);
    border-radius: 4px;
    transition: width 0.3s;
}

.ds-bar-fill.mobile { background: #00a32a; }
.ds-bar-fill.tablet { background: #d63638; }

.ds-bar-value {
    width: 80px;
    text-align: right;
    font-size: 14px;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 782px) {
    #ds-dashboard { margin-left: -10px; }
    .ds-content { padding: 16px; }
    .ds-tables { grid-template-columns: 1fr; }
    .ds-stat-card .value { font-size: 28px; }
    .ds-tabs { overflow-x: auto; justify-content: flex-start; }
}
</style>

<div id="ds-dashboard">
    <div class="ds-header-bar">
        <div class="ds-header-bar-inner">
            <span class="ds-icon">📊</span>
            <h1 class="ds-title"><?php esc_html_e('Data Signals', 'data-signals'); ?></h1>
        </div>
    </div>
    
    <div class="ds-tabs">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?php echo esc_url(add_query_arg(['tab' => $key, 'view' => $range], $dashboard_url)); ?>" 
               class="<?php echo $tab === $key ? 'active' : ''; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="ds-content">
        <div class="ds-toolbar">
            <div class="ds-realtime">
                <span class="dot"></span>
                <span><?php printf(esc_html__('%d visitors in the last hour', 'data-signals'), $realtime); ?></span>
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
                    <option value="custom" <?php selected($range, 'custom'); ?>><?php esc_html_e('Custom', 'data-signals'); ?></option>
                </select>
                
                <?php if ($range === 'custom'): ?>
                    <input type="date" name="start_date" value="<?php echo esc_attr($date_start->format('Y-m-d')); ?>">
                    <span>—</span>
                    <input type="date" name="end_date" value="<?php echo esc_attr($date_end->format('Y-m-d')); ?>">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'data-signals'); ?></button>
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
            default:
                include DS_DIR . '/views/tabs/overview.php';
                break;
        }
        ?>
    </div>
</div>
