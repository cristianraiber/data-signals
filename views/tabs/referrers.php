<?php
/**
 * Referrers tab content
 */
defined('ABSPATH') or exit;

$total_visitors = max(1, $totals->visitors);
$total_referred = array_sum(array_column($referrers, 'visitors')) ?: 0;
?>

<div class="ds-stats-grid">
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Referrer Sources', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n(count($referrers)); ?></div>
        <div class="sub"><?php esc_html_e('Unique referrers', 'data-signals'); ?></div>
    </div>
    
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Referred Visitors', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($total_referred); ?></div>
        <div class="sub"><?php echo ds_percent($total_referred, $total_visitors); ?> of total traffic</div>
    </div>
    
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Direct Traffic', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n(max(0, $total_visitors - $total_referred)); ?></div>
        <div class="sub"><?php esc_html_e('No referrer', 'data-signals'); ?></div>
    </div>
</div>

<?php if (!empty($referrers)): ?>
<div class="ds-card">
    <div class="ds-card-header">
        <h3><?php esc_html_e('Top Referrers', 'data-signals'); ?></h3>
    </div>
    <div class="ds-card-body">
        <div class="ds-bar-chart">
            <?php 
            $top5 = array_slice($referrers, 0, 5);
            $max_ref = !empty($top5) ? max(array_column($top5, 'visitors')) : 1;
            foreach ($top5 as $ref): 
                $pct = ($ref->visitors / $max_ref) * 100;
                $label = preg_replace('/^https?:\/\//', '', $ref->url);
                $label = strlen($label) > 20 ? substr($label, 0, 17) . '...' : $label;
            ?>
                <div class="ds-bar-row">
                    <span class="ds-bar-label" title="<?php echo esc_attr($ref->url); ?>">
                        <?php echo esc_html($label); ?>
                    </span>
                    <div class="ds-bar-track">
                        <div class="ds-bar-fill" style="width: <?php echo esc_attr($pct); ?>%"></div>
                    </div>
                    <span class="ds-bar-value"><?php echo number_format_i18n($ref->visitors); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="ds-card">
    <div class="ds-card-header">
        <h3><?php esc_html_e('All Referrers', 'data-signals'); ?></h3>
    </div>
    <table class="ds-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Referrer URL', 'data-signals'); ?></th>
                <th class="num"><?php esc_html_e('Visitors', 'data-signals'); ?></th>
                <th class="num"><?php esc_html_e('Pageviews', 'data-signals'); ?></th>
                <th class="num"><?php esc_html_e('Share', 'data-signals'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($referrers)): ?>
                <tr>
                    <td colspan="4" class="ds-empty">
                        <?php esc_html_e('No referrer data yet.', 'data-signals'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($referrers as $referrer): ?>
                    <tr>
                        <td class="url" title="<?php echo esc_attr($referrer->url); ?>">
                            <a href="https://<?php echo esc_attr($referrer->url); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($referrer->url); ?>
                            </a>
                        </td>
                        <td class="num"><?php echo number_format_i18n($referrer->visitors); ?></td>
                        <td class="num"><?php echo number_format_i18n($referrer->pageviews); ?></td>
                        <td class="num"><?php echo ds_percent($referrer->visitors, $total_visitors); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
