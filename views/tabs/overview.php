<?php
/**
 * Overview tab content
 */
defined('ABSPATH') or exit;

// Calculate percentage changes
$visitors_change = $prev_totals->visitors > 0 
    ? round((($totals->visitors - $prev_totals->visitors) / $prev_totals->visitors) * 100, 1) 
    : 0;
$pageviews_change = $prev_totals->pageviews > 0 
    ? round((($totals->pageviews - $prev_totals->pageviews) / $prev_totals->pageviews) * 100, 1) 
    : 0;
?>

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
                x: { grid: { display: false } },
                y: { type: 'linear', position: 'left', beginAtZero: true, grid: { color: '#e2e4e7' } },
                y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } }
            },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, padding: 20 } }
            }
        }
    });
})();
</script>
