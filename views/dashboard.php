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
.ds-wrap { max-width: 1400px; }
.ds-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
.ds-date-picker { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.ds-date-picker select, .ds-date-picker input[type="date"] { padding: 6px 10px; }
.ds-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.ds-stat-card { background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; }
.ds-stat-card h3 { margin: 0 0 10px; font-size: 14px; color: #646970; font-weight: 400; }
.ds-stat-card .value { font-size: 32px; font-weight: 600; color: #1d2327; }
.ds-stat-card .change { font-size: 13px; margin-top: 5px; }
.ds-stat-card .change.positive { color: #00a32a; }
.ds-stat-card .change.negative { color: #d63638; }
.ds-chart { background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; margin-bottom: 30px; min-height: 300px; }
.ds-tables { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; }
.ds-table-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; }
.ds-table-card h3 { margin: 0; padding: 15px 20px; border-bottom: 1px solid #c3c4c7; font-size: 14px; }
.ds-table-card table { width: 100%; border-collapse: collapse; }
.ds-table-card th, .ds-table-card td { padding: 10px 20px; text-align: left; border-bottom: 1px solid #f0f0f1; }
.ds-table-card th { font-weight: 600; color: #646970; font-size: 12px; text-transform: uppercase; }
.ds-table-card td { font-size: 14px; }
.ds-table-card td.num { text-align: right; }
.ds-table-card .url { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ds-realtime { display: flex; align-items: center; gap: 10px; background: #fff; padding: 10px 15px; border: 1px solid #c3c4c7; border-radius: 4px; }
.ds-realtime .dot { width: 8px; height: 8px; background: #00a32a; border-radius: 50%; animation: pulse 2s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
</style>

<div class="wrap ds-wrap">
    <div class="ds-header">
        <h1><?php esc_html_e('Analytics', 'data-signals'); ?></h1>
        
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
                <button type="submit" class="button"><?php esc_html_e('Apply', 'data-signals'); ?></button>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="ds-stats-grid">
        <div class="ds-stat-card">
            <h3><?php esc_html_e('Visitors', 'data-signals'); ?></h3>
            <div class="value"><?php echo number_format_i18n($totals->visitors); ?></div>
            <?php if ($visitors_change != 0): ?>
                <div class="change <?php echo $visitors_change > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo ($visitors_change > 0 ? '+' : '') . $visitors_change; ?>%
                </div>
            <?php endif; ?>
        </div>
        
        <div class="ds-stat-card">
            <h3><?php esc_html_e('Pageviews', 'data-signals'); ?></h3>
            <div class="value"><?php echo number_format_i18n($totals->pageviews); ?></div>
            <?php if ($pageviews_change != 0): ?>
                <div class="change <?php echo $pageviews_change > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo ($pageviews_change > 0 ? '+' : '') . $pageviews_change; ?>%
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
    
    <div class="ds-chart">
        <canvas id="ds-chart" height="250"></canvas>
    </div>
    
    <div class="ds-tables">
        <div class="ds-table-card">
            <h3><?php esc_html_e('Top Pages', 'data-signals'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Page', 'data-signals'); ?></th>
                        <th class="num"><?php esc_html_e('Visitors', 'data-signals'); ?></th>
                        <th class="num"><?php esc_html_e('Views', 'data-signals'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pages)): ?>
                        <tr><td colspan="3"><?php esc_html_e('No data yet.', 'data-signals'); ?></td></tr>
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
        
        <div class="ds-table-card">
            <h3><?php esc_html_e('Top Referrers', 'data-signals'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Source', 'data-signals'); ?></th>
                        <th class="num"><?php esc_html_e('Visitors', 'data-signals'); ?></th>
                        <th class="num"><?php esc_html_e('Views', 'data-signals'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($referrers)): ?>
                        <tr><td colspan="3"><?php esc_html_e('No referrer data yet.', 'data-signals'); ?></td></tr>
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
                    backgroundColor: 'rgba(0, 124, 186, 0.8)',
                    yAxisID: 'y'
                },
                {
                    label: '<?php esc_html_e('Pageviews', 'data-signals'); ?>',
                    data: data.map(function(d) { return d.pageviews; }),
                    backgroundColor: 'rgba(0, 163, 42, 0.6)',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { type: 'linear', position: 'left', beginAtZero: true },
                y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } }
            },
            plugins: {
                legend: { position: 'top' }
            }
        }
    });
})();
</script>
