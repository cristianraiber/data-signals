<?php
/**
 * Geographic tab content
 */
defined('ABSPATH') or exit;

$total_visitors = max(1, $totals->visitors);
?>

<div class="ds-stats-grid">
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Countries', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n(count($countries)); ?></div>
        <div class="sub"><?php esc_html_e('Unique countries', 'data-signals'); ?></div>
    </div>
    
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Total Visitors', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($totals->visitors); ?></div>
        <div class="sub"><?php esc_html_e('With location data', 'data-signals'); ?></div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header">
        <h3><?php esc_html_e('Top Countries', 'data-signals'); ?></h3>
    </div>
    <table class="ds-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Country', 'data-signals'); ?></th>
                <th class="num"><?php esc_html_e('Visitors', 'data-signals'); ?></th>
                <th class="num"><?php esc_html_e('Pageviews', 'data-signals'); ?></th>
                <th class="num"><?php esc_html_e('Share', 'data-signals'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($countries)): ?>
                <tr>
                    <td colspan="4" class="ds-empty">
                        <?php esc_html_e('No geographic data yet. Location tracking uses IP geolocation.', 'data-signals'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($countries as $country): ?>
                    <tr>
                        <td>
                            <span style="margin-right: 6px;"><?php echo ds_flag($country->country_code); ?></span>
                            <?php echo esc_html($country->country_name ?: $country->country_code); ?>
                        </td>
                        <td class="num"><?php echo number_format_i18n($country->visitors); ?></td>
                        <td class="num"><?php echo number_format_i18n($country->pageviews); ?></td>
                        <td class="num"><?php echo ds_percent($country->visitors, $total_visitors); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($countries)): ?>
<div class="ds-card">
    <div class="ds-card-header">
        <h3><?php esc_html_e('Geographic Distribution', 'data-signals'); ?></h3>
    </div>
    <div class="ds-card-body">
        <div class="ds-bar-chart">
            <?php 
            $top5 = array_slice($countries, 0, 5);
            $max_visitors = !empty($top5) ? max(array_column($top5, 'visitors')) : 1;
            foreach ($top5 as $country): 
                $pct = ($country->visitors / $max_visitors) * 100;
            ?>
                <div class="ds-bar-row">
                    <span class="ds-bar-label">
                        <?php echo ds_flag($country->country_code); ?> 
                        <?php echo esc_html(substr($country->country_name ?: $country->country_code, 0, 12)); ?>
                    </span>
                    <div class="ds-bar-track">
                        <div class="ds-bar-fill" style="width: <?php echo esc_attr($pct); ?>%"></div>
                    </div>
                    <span class="ds-bar-value"><?php echo number_format_i18n($country->visitors); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
