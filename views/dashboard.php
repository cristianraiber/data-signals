<?php
/**
 * Dashboard view template
 * 
 * @var object $totals
 * @var object $prev_totals
 * @var array $chart_data
 * @var array $pages
 * @var int $pages_count
 * @var array $referrers
 * @var int $referrers_count
 * @var int $realtime
 * @var array $presets
 * @var string $range
 * @var \DateTimeImmutable $date_start
 * @var \DateTimeImmutable $date_end
 */

defined('ABSPATH') or exit;

$date_format = get_option('date_format', 'Y-m-d');
$dashboard_url = admin_url('admin.php?page=data-signals');

// Calculate percentage changes
$visitors_change = $prev_totals->visitors > 0 
    ? round((($totals->visitors - $prev_totals->visitors) / $prev_totals->visitors) * 100, 1) 
    : 0;
$pageviews_change = $prev_totals->pageviews > 0 
    ? round((($totals->pageviews - $prev_totals->pageviews) / $prev_totals->pageviews) * 100, 1) 
    : 0;
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

.ds-header-bar .ds-icon {
    font-size: 28px;
}

.ds-header-bar .ds-title {
    font-size: 24px;
    font-weight: 600;
    color: var(--ds-text);
    margin: 0;
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

@keyframes ds-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.ds-date-picker {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.ds-date-picker select {
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
    min-height: 40px;
    background: #fff;
}

.ds-date-picker select:focus {
    border-color: var(--ds-primary);
    box-shadow: 0 0 0 1px var(--ds-primary);
    outline: none;
}

.ds-date-picker input[type="date"] {
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
    min-height: 40px;
}

.ds-date-picker .button {
    min-height: 40px;
    padding: 0 16px;
}

.ds-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

.ds-stat-card .change {
    font-size: 13px;
    margin-top: 8px;
    font-weight: 500;
}

.ds-stat-card .change.positive {
    color: var(--ds-success);
}

.ds-stat-card .change.negative {
    color: var(--ds-danger);
}

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

.ds-card-body {
    padding: 20px;
}

.ds-chart {
    min-height: 300px;
}

.ds-tables {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
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

.ds-table a:hover {
    text-decoration: underline;
}

.ds-table tbody tr:last-child td {
    border-bottom: none;
}

.ds-empty {
    color: var(--ds-text-muted);
    font-style: italic;
}

@media (max-width: 782px) {
    #ds-dashboard {
        margin-left: -10px;
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
}
</style>

<div id="ds-dashboard">
    <div class="ds-header-bar">
        <div class="ds-header-bar-inner">
            <span class="ds-icon">📊</span>
            <h1 class="ds-title"><?php esc_html_e('Data Signals', 'data-signals'); ?></h1>
        </div>
    </div>
    
    <div class="ds-content">
        <div class="ds-toolbar">
            <div class="ds-realtime">
                <span class="dot"></span>
                <span><?php printf(esc_html__('%d visitors in the last hour', 'data-signals'), $realtime); ?></span>
            </div>
            
            <form method="get" class="ds-date-picker">
                <input type="hidden" name="page" value="data-signals">
                
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
        
        <div class="ds-stats-grid">
            <div class="ds-stat-card">
                <h3><?php esc_html_e('Visitors', 'data-signals'); ?></h3>
                <div class="value"><?php echo number_format_i18n($totals->visitors); ?></div>
                <?php if ($visitors_change != 0): ?>
                    <div class="change <?php echo $visitors_change > 0 ? 'positive' : 'negative'; ?>">
                        <?php echo ($visitors_change > 0 ? '↑ +' : '↓ ') . $visitors_change; ?>% vs previous period
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="ds-stat-card">
                <h3><?php esc_html_e('Pageviews', 'data-signals'); ?></h3>
                <div class="value"><?php echo number_format_i18n($totals->pageviews); ?></div>
                <?php if ($pageviews_change != 0): ?>
                    <div class="change <?php echo $pageviews_change > 0 ? 'positive' : 'negative'; ?>">
                        <?php echo ($pageviews_change > 0 ? '↑ +' : '↓ ') . $pageviews_change; ?>% vs previous period
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="ds-stat-card">
                <h3><?php esc_html_e('Pages per Visit', 'data-signals'); ?></h3>
                <div class="value">
                    <?php echo $totals->visitors > 0 ? number_format_i18n($totals->pageviews / $totals->visitors, 2) : '0'; ?>
                </div>
            </div>
        </div>
        
        <div class="ds-card">
            <div class="ds-card-header">
                <h3><?php esc_html_e('Traffic Overview', 'data-signals'); ?></h3>
            </div>
            <div class="ds-card-body ds-chart">
                <canvas id="ds-chart" height="280"></canvas>
            </div>
        </div>
        
        <div class="ds-tables">
            <div class="ds-card">
                <div class="ds-card-header">
                    <h3><?php esc_html_e('Top Pages', 'data-signals'); ?></h3>
                </div>
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Page', 'data-signals'); ?></th>
                            <th class="num"><?php esc_html_e('Visitors', 'data-signals'); ?></th>
                            <th class="num"><?php esc_html_e('Views', 'data-signals'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pages)): ?>
                            <tr><td colspan="3" class="ds-empty"><?php esc_html_e('No data yet.', 'data-signals'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($pages as $page): ?>
                                <tr>
                                    <td class="url" title="<?php echo esc_attr($page->path); ?>">
                                        <a href="<?php echo esc_url($page->url); ?>" target="_blank">
                                            <?php echo esc_html($page->title ?: $page->path); ?>
                                        </a>
                                    </td>
                                    <td class="num"><?php echo number_format_i18n($page->visitors); ?></td>
                                    <td class="num"><?php echo number_format_i18n($page->pageviews); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="ds-card">
                <div class="ds-card-header">
                    <h3><?php esc_html_e('Top Referrers', 'data-signals'); ?></h3>
                </div>
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Source', 'data-signals'); ?></th>
                            <th class="num"><?php esc_html_e('Visitors', 'data-signals'); ?></th>
                            <th class="num"><?php esc_html_e('Views', 'data-signals'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($referrers)): ?>
                            <tr><td colspan="3" class="ds-empty"><?php esc_html_e('No referrer data yet.', 'data-signals'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($referrers as $referrer): ?>
                                <tr>
                                    <td class="url" title="<?php echo esc_attr($referrer->url); ?>">
                                        <?php echo esc_html($referrer->url); ?>
                                    </td>
                                    <td class="num"><?php echo number_format_i18n($referrer->visitors); ?></td>
                                    <td class="num"><?php echo number_format_i18n($referrer->pageviews); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    var ctx = document.getElementById('ds-chart').getContext('2d');
    var data = <?php echo wp_json_encode($chart_data); ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(function(d) { return d.date; }),
            datasets: [
                {
                    label: '<?php esc_html_e('Visitors', 'data-signals'); ?>',
                    data: data.map(function(d) { return d.visitors; }),
                    backgroundColor: 'rgba(56, 88, 233, 0.85)',
                    borderRadius: 3,
                    yAxisID: 'y'
                },
                {
                    label: '<?php esc_html_e('Pageviews', 'data-signals'); ?>',
                    data: data.map(function(d) { return d.pageviews; }),
                    backgroundColor: 'rgba(0, 163, 42, 0.65)',
                    borderRadius: 3,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: {
                    grid: { display: false }
                },
                y: { 
                    type: 'linear', 
                    position: 'left', 
                    beginAtZero: true,
                    grid: { color: '#e2e4e7' }
                },
                y1: { 
                    type: 'linear', 
                    position: 'right', 
                    beginAtZero: true, 
                    grid: { drawOnChartArea: false }
                }
            },
            plugins: {
                legend: { 
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            }
        }
    });
})();
</script>
