<?php
/**
 * Devices tab content
 */
defined('ABSPATH') or exit;

$dt = $device_totals;
$total = max(1, $dt->total);
?>

<div class="ds-stats-grid">
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Desktop', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($dt->desktop); ?></div>
        <div class="sub"><?php echo ds_percent($dt->desktop, $total); ?> of visitors</div>
    </div>
    
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Mobile', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($dt->mobile); ?></div>
        <div class="sub"><?php echo ds_percent($dt->mobile, $total); ?> of visitors</div>
    </div>
    
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Tablet', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($dt->tablet); ?></div>
        <div class="sub"><?php echo ds_percent($dt->tablet, $total); ?> of visitors</div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header">
        <h3><?php esc_html_e('Device Types', 'data-signals'); ?></h3>
    </div>
    <div class="ds-card-body">
        <div class="ds-bar-chart">
            <div class="ds-bar-row">
                <span class="ds-bar-label"><?php esc_html_e('Desktop', 'data-signals'); ?></span>
                <div class="ds-bar-track">
                    <div class="ds-bar-fill" style="width: <?php echo ds_percent($dt->desktop, $total); ?>"></div>
                </div>
                <span class="ds-bar-value"><?php echo number_format_i18n($dt->desktop); ?></span>
            </div>
            <div class="ds-bar-row">
                <span class="ds-bar-label"><?php esc_html_e('Mobile', 'data-signals'); ?></span>
                <div class="ds-bar-track">
                    <div class="ds-bar-fill secondary" style="width: <?php echo ds_percent($dt->mobile, $total); ?>"></div>
                </div>
                <span class="ds-bar-value"><?php echo number_format_i18n($dt->mobile); ?></span>
            </div>
            <div class="ds-bar-row">
                <span class="ds-bar-label"><?php esc_html_e('Tablet', 'data-signals'); ?></span>
                <div class="ds-bar-track">
                    <div class="ds-bar-fill tertiary" style="width: <?php echo ds_percent($dt->tablet, $total); ?>"></div>
                </div>
                <span class="ds-bar-value"><?php echo number_format_i18n($dt->tablet); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="ds-tables">
    <div class="ds-card">
        <div class="ds-card-header">
            <h3><?php esc_html_e('Browsers', 'data-signals'); ?></h3>
        </div>
        <table class="ds-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Browser', 'data-signals'); ?></th>
                    <th class="num"><?php esc_html_e('Visitors', 'data-signals'); ?></th>
                    <th class="num"><?php esc_html_e('Share', 'data-signals'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($browsers)): ?>
                    <tr><td colspan="3" class="ds-empty"><?php esc_html_e('No browser data yet.', 'data-signals'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($browsers as $b): ?>
                        <tr>
                            <td><?php echo esc_html($b->label ?: 'Unknown'); ?></td>
                            <td class="num"><?php echo number_format_i18n($b->visitors); ?></td>
                            <td class="num"><?php echo ds_percent($b->visitors, $total); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="ds-card">
        <div class="ds-card-header">
            <h3><?php esc_html_e('Operating Systems', 'data-signals'); ?></h3>
        </div>
        <table class="ds-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('OS', 'data-signals'); ?></th>
                    <th class="num"><?php esc_html_e('Visitors', 'data-signals'); ?></th>
                    <th class="num"><?php esc_html_e('Share', 'data-signals'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($os_list)): ?>
                    <tr><td colspan="3" class="ds-empty"><?php esc_html_e('No OS data yet.', 'data-signals'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($os_list as $os): ?>
                        <tr>
                            <td><?php echo esc_html($os->label ?: 'Unknown'); ?></td>
                            <td class="num"><?php echo number_format_i18n($os->visitors); ?></td>
                            <td class="num"><?php echo ds_percent($os->visitors, $total); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
