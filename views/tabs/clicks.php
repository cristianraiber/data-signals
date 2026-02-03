<?php
/**
 * Clicks tab content
 */
defined('ABSPATH') or exit;

$ct = $click_totals;
?>

<!-- Stats Cards -->
<div class="ds-stats-grid">
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Total Clicks', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($ct['total']['clicks']); ?></div>
        <div class="sub"><?php echo number_format_i18n($ct['total']['unique']); ?> <?php esc_html_e('unique', 'data-signals'); ?></div>
    </div>
    
    <div class="ds-stat-card">
        <h3>🔗 <?php esc_html_e('Outbound Links', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($ct['outbound']['clicks']); ?></div>
        <div class="sub"><?php esc_html_e('external link clicks', 'data-signals'); ?></div>
    </div>
    
    <div class="ds-stat-card">
        <h3>📥 <?php esc_html_e('Downloads', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($ct['download']['clicks']); ?></div>
        <div class="sub"><?php esc_html_e('file downloads', 'data-signals'); ?></div>
    </div>
    
    <div class="ds-stat-card">
        <h3>✉️ <?php esc_html_e('Contact Clicks', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($ct['mailto']['clicks'] + $ct['tel']['clicks']); ?></div>
        <div class="sub"><?php echo number_format_i18n($ct['mailto']['clicks']); ?> email, <?php echo number_format_i18n($ct['tel']['clicks']); ?> phone</div>
    </div>
</div>

<!-- Top Clicked Domains -->
<?php if (!empty($top_domains)): ?>
<div class="ds-card">
    <div class="ds-card-header">
        <h3>🌐 <?php esc_html_e('Top Clicked Domains', 'data-signals'); ?></h3>
    </div>
    <div class="ds-card-body">
        <div class="ds-bar-chart">
            <?php 
            $max_clicks = !empty($top_domains) ? max(array_column($top_domains, 'clicks')) : 1;
            foreach ($top_domains as $domain): 
                $pct = ($domain->clicks / $max_clicks) * 100;
            ?>
                <div class="ds-bar-row">
                    <span class="ds-bar-label" title="<?php echo esc_attr($domain->domain); ?>">
                        <?php echo esc_html(substr($domain->domain, 0, 20)); ?>
                    </span>
                    <div class="ds-bar-track">
                        <div class="ds-bar-fill" style="width: <?php echo $pct; ?>%"></div>
                    </div>
                    <span class="ds-bar-value"><?php echo number_format_i18n($domain->clicks); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="ds-tables">
    <!-- Outbound Links -->
    <div class="ds-card">
        <div class="ds-card-header">
            <h3>🔗 <?php esc_html_e('Outbound Link Clicks', 'data-signals'); ?></h3>
        </div>
        <table class="ds-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('URL', 'data-signals'); ?></th>
                    <th class="num"><?php esc_html_e('Clicks', 'data-signals'); ?></th>
                    <th class="num"><?php esc_html_e('Unique', 'data-signals'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($outbound_clicks)): ?>
                    <tr>
                        <td colspan="3" class="ds-empty">
                            <?php esc_html_e('No outbound link clicks yet. Clicks on external links will appear here.', 'data-signals'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($outbound_clicks as $click): ?>
                        <tr>
                            <td class="url" title="<?php echo esc_attr($click->target_url); ?>">
                                <a href="<?php echo esc_url($click->target_url); ?>" target="_blank" rel="noopener">
                                    <?php 
                                    $display_url = preg_replace('/^https?:\/\//', '', $click->target_url);
                                    echo esc_html(strlen($display_url) > 50 ? substr($display_url, 0, 47) . '...' : $display_url);
                                    ?>
                                </a>
                            </td>
                            <td class="num"><?php echo number_format_i18n($click->clicks); ?></td>
                            <td class="num"><?php echo number_format_i18n($click->unique_clicks); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Downloads -->
    <div class="ds-card">
        <div class="ds-card-header">
            <h3>📥 <?php esc_html_e('File Downloads', 'data-signals'); ?></h3>
        </div>
        <table class="ds-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('File', 'data-signals'); ?></th>
                    <th class="num"><?php esc_html_e('Downloads', 'data-signals'); ?></th>
                    <th class="num"><?php esc_html_e('Unique', 'data-signals'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($download_clicks)): ?>
                    <tr>
                        <td colspan="3" class="ds-empty">
                            <?php esc_html_e('No file downloads yet. PDF, ZIP, and other file downloads will appear here.', 'data-signals'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($download_clicks as $click): ?>
                        <tr>
                            <td class="url" title="<?php echo esc_attr($click->target_url); ?>">
                                <?php 
                                $filename = basename(parse_url($click->target_url, PHP_URL_PATH));
                                echo esc_html($filename ?: $click->target_url);
                                ?>
                            </td>
                            <td class="num"><?php echo number_format_i18n($click->clicks); ?></td>
                            <td class="num"><?php echo number_format_i18n($click->unique_clicks); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="ds-card" style="background: linear-gradient(135deg, #EEF2FF, #E0E7FF); border: 1px dashed var(--ds-primary);">
    <div class="ds-card-body">
        <h4 style="margin: 0 0 12px; font-size: 15px; color: var(--ds-primary);">
            💡 <?php esc_html_e('How Click Tracking Works', 'data-signals'); ?>
        </h4>
        <p style="margin: 0; color: var(--ds-text-muted); font-size: 14px; line-height: 1.6;">
            <?php esc_html_e('Click tracking is automatic. We track:', 'data-signals'); ?>
        </p>
        <ul style="margin: 12px 0 0; padding-left: 20px; color: var(--ds-text-muted); font-size: 14px; line-height: 1.8;">
            <li><strong><?php esc_html_e('Outbound links', 'data-signals'); ?></strong> — <?php esc_html_e('clicks on links to external websites', 'data-signals'); ?></li>
            <li><strong><?php esc_html_e('Downloads', 'data-signals'); ?></strong> — <?php esc_html_e('PDF, ZIP, DOC, and other file downloads', 'data-signals'); ?></li>
            <li><strong><?php esc_html_e('Email clicks', 'data-signals'); ?></strong> — <?php esc_html_e('mailto: links', 'data-signals'); ?></li>
            <li><strong><?php esc_html_e('Phone clicks', 'data-signals'); ?></strong> — <?php esc_html_e('tel: links', 'data-signals'); ?></li>
        </ul>
    </div>
</div>
