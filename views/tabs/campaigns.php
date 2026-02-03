<?php
/**
 * Campaigns tab content (UTM tracking)
 */
defined('ABSPATH') or exit;

$total_campaign_visitors = array_sum(array_column($campaigns, 'visitors')) ?: 1;
?>

<div class="ds-stats-grid">
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Campaigns', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n(count($campaigns)); ?></div>
        <div class="sub"><?php esc_html_e('Active campaigns', 'data-signals'); ?></div>
    </div>
    
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Sources', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n(count($sources)); ?></div>
        <div class="sub"><?php esc_html_e('Traffic sources', 'data-signals'); ?></div>
    </div>
    
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Campaign Visitors', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($total_campaign_visitors); ?></div>
        <div class="sub"><?php esc_html_e('Via UTM parameters', 'data-signals'); ?></div>
    </div>
</div>

<?php if (!empty($sources)): ?>
<div class="ds-card">
    <div class="ds-card-header">
        <h3><?php esc_html_e('Traffic Sources', 'data-signals'); ?></h3>
    </div>
    <div class="ds-card-body">
        <div class="ds-bar-chart">
            <?php 
            $max_source = !empty($sources) ? max(array_column($sources, 'visitors')) : 1;
            foreach ($sources as $source): 
                $pct = ($source->visitors / $max_source) * 100;
            ?>
                <div class="ds-bar-row">
                    <span class="ds-bar-label"><?php echo esc_html(ucfirst($source->source)); ?></span>
                    <div class="ds-bar-track">
                        <div class="ds-bar-fill" style="width: <?php echo $pct; ?>%"></div>
                    </div>
                    <span class="ds-bar-value"><?php echo number_format_i18n($source->visitors); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="ds-card">
    <div class="ds-card-header">
        <h3><?php esc_html_e('All Campaigns', 'data-signals'); ?></h3>
    </div>
    <table class="ds-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Source', 'data-signals'); ?></th>
                <th><?php esc_html_e('Medium', 'data-signals'); ?></th>
                <th><?php esc_html_e('Campaign', 'data-signals'); ?></th>
                <th class="num"><?php esc_html_e('Visitors', 'data-signals'); ?></th>
                <th class="num"><?php esc_html_e('Views', 'data-signals'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($campaigns)): ?>
                <tr>
                    <td colspan="5" class="ds-empty">
                        <?php esc_html_e('No campaign data yet. Use UTM parameters in your links.', 'data-signals'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($campaigns as $c): ?>
                    <tr>
                        <td><?php echo esc_html($c->utm_source ?: '–'); ?></td>
                        <td><?php echo esc_html($c->utm_medium ?: '–'); ?></td>
                        <td class="url" title="<?php echo esc_attr($c->utm_campaign); ?>">
                            <?php echo esc_html($c->utm_campaign ?: '–'); ?>
                        </td>
                        <td class="num"><?php echo number_format_i18n($c->visitors); ?></td>
                        <td class="num"><?php echo number_format_i18n($c->pageviews); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="ds-card ds-info-box" style="margin-top: 20px;">
    <h4><?php esc_html_e('How to use UTM tracking', 'data-signals'); ?></h4>
    <p><?php esc_html_e('Add UTM parameters to your links to track campaigns:', 'data-signals'); ?></p>
    <code style="display: block; padding: 10px; background: #fff; border: 1px solid var(--ds-border); border-radius: 3px; font-size: 12px; margin-top: 10px; overflow-x: auto;">
        https://yoursite.com/?utm_source=facebook&amp;utm_medium=social&amp;utm_campaign=summer-sale
    </code>
</div>
