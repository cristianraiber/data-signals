<?php
/**
 * Events Tab - Custom Event Tracking
 */

use DataSignals\Event_Tracker;

defined('ABSPATH') or exit;

// Get event stats
$event_stats = Event_Tracker::get_stats($date_start, $date_end, 20);
$registered_events = Event_Tracker::get_registered_events();
$recent_events = Event_Tracker::get_recent(30);
$categories = Event_Tracker::get_categories();

$totals = $event_stats['totals'];
$top_events = $event_stats['top_events'];
$by_category = $event_stats['by_category'];
$daily = $event_stats['daily'];

// Build chart data
$chart_labels = [];
$chart_data = [];
$daily_by_date = [];
foreach ($daily as $row) {
    $daily_by_date[$row->date] = (int) $row->count;
}

$interval = $date_start->diff($date_end);
for ($i = 0; $i <= $interval->days; $i++) {
    $date = $date_start->modify("+{$i} days");
    $key = $date->format('Y-m-d');
    $chart_labels[] = $date->format('M j');
    $chart_data[] = $daily_by_date[$key] ?? 0;
}

$max_events = !empty($top_events) ? max(array_column($top_events, 'total_count')) : 1;
?>

<!-- Stats Cards -->
<div class="ds-stats-grid">
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Total Events', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($totals->total_events ?? 0); ?></div>
    </div>
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Unique Event Types', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($totals->unique_events ?? 0); ?></div>
    </div>
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Unique Visitors', 'data-signals'); ?></h3>
        <div class="value"><?php echo number_format_i18n($totals->unique_visitors ?? 0); ?></div>
    </div>
    <div class="ds-stat-card">
        <h3><?php esc_html_e('Registered Events', 'data-signals'); ?></h3>
        <div class="value"><?php echo count($registered_events); ?></div>
    </div>
</div>

<!-- Chart -->
<div class="ds-card">
    <div class="ds-card-header">
        <h3><?php esc_html_e('Events Over Time', 'data-signals'); ?></h3>
    </div>
    <div class="ds-card-body">
        <canvas id="ds-events-chart" class="ds-chart"></canvas>
    </div>
</div>

<div class="ds-tables">
    <!-- Top Events -->
    <div class="ds-card">
        <div class="ds-card-header">
            <h3><?php esc_html_e('Top Events', 'data-signals'); ?></h3>
        </div>
        <?php if (!empty($top_events)): ?>
            <table class="ds-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Event', 'data-signals'); ?></th>
                        <th><?php esc_html_e('Category', 'data-signals'); ?></th>
                        <th class="num"><?php esc_html_e('Count', 'data-signals'); ?></th>
                        <th class="num"><?php esc_html_e('Visitors', 'data-signals'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_events as $event): 
                        $def = $registered_events[$event->event_name] ?? null;
                        $label = $def ? $def['label'] : ucwords(str_replace('_', ' ', $event->event_name));
                        $cat_label = $categories[$event->event_category] ?? ucfirst($event->event_category);
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($label); ?></strong>
                                <br><small style="color: var(--ds-text-muted);"><?php echo esc_html($event->event_name); ?></small>
                            </td>
                            <td>
                                <span style="background: var(--ds-bg); padding: 2px 8px; border-radius: 3px; font-size: 11px;">
                                    <?php echo esc_html($cat_label); ?>
                                </span>
                            </td>
                            <td class="num"><?php echo number_format_i18n($event->total_count); ?></td>
                            <td class="num"><?php echo number_format_i18n($event->unique_visitors); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ds-card-body">
                <p class="ds-empty"><?php esc_html_e('No events tracked yet.', 'data-signals'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Events by Category -->
    <div class="ds-card">
        <div class="ds-card-header">
            <h3><?php esc_html_e('Events by Category', 'data-signals'); ?></h3>
        </div>
        <?php if (!empty($by_category)): ?>
            <div class="ds-card-body">
                <div class="ds-bar-chart">
                    <?php 
                    $max_cat = max(array_column($by_category, 'total_count'));
                    foreach ($by_category as $cat): 
                        $cat_label = $categories[$cat->event_category] ?? ucfirst($cat->event_category);
                        $pct = $max_cat > 0 ? ($cat->total_count / $max_cat) * 100 : 0;
                    ?>
                        <div class="ds-bar-row">
                            <span class="ds-bar-label"><?php echo esc_html($cat_label); ?></span>
                            <div class="ds-bar-track">
                                <div class="ds-bar-fill" style="width: <?php echo esc_attr($pct); ?>%"></div>
                            </div>
                            <span class="ds-bar-value"><?php echo number_format_i18n($cat->total_count); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="ds-card-body">
                <p class="ds-empty"><?php esc_html_e('No events tracked yet.', 'data-signals'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Events Feed -->
<div class="ds-card" style="margin-top: 20px;">
    <div class="ds-card-header">
        <h3><?php esc_html_e('Recent Events', 'data-signals'); ?></h3>
    </div>
    <?php if (!empty($recent_events)): ?>
        <table class="ds-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Time', 'data-signals'); ?></th>
                    <th><?php esc_html_e('Event', 'data-signals'); ?></th>
                    <th><?php esc_html_e('Page', 'data-signals'); ?></th>
                    <th><?php esc_html_e('Properties', 'data-signals'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_events as $event):
                    $def = $registered_events[$event->event_name] ?? null;
                    $label = $def ? $def['label'] : ucwords(str_replace('_', ' ', $event->event_name));
                    $data = $event->event_data ? json_decode($event->event_data, true) : [];
                    $time = new DateTime($event->created_at, wp_timezone());
                ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <?php echo esc_html($time->format('M j, g:i a')); ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($label); ?></strong>
                        </td>
                        <td class="url">
                            <?php if ($event->page_url): ?>
                                <?php echo esc_html(wp_parse_url($event->page_url, PHP_URL_PATH) ?: '/'); ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($data)): ?>
                                <code style="font-size: 11px; background: var(--ds-bg); padding: 2px 6px; border-radius: 3px;">
                                    <?php 
                                    $props = [];
                                    foreach (array_slice($data, 0, 3) as $k => $v) {
                                        $props[] = $k . '=' . (is_string($v) ? $v : wp_json_encode($v));
                                    }
                                    echo esc_html(implode(', ', $props));
                                    if (count($data) > 3) echo '...';
                                    ?>
                                </code>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="ds-card-body">
            <p class="ds-empty"><?php esc_html_e('No events tracked yet.', 'data-signals'); ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Developer Info -->
<div class="ds-card" style="margin-top: 20px;">
    <div class="ds-card-header">
        <h3><?php esc_html_e('Registered Event Types', 'data-signals'); ?></h3>
    </div>
    <?php if (!empty($registered_events)): ?>
        <table class="ds-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Event Name', 'data-signals'); ?></th>
                    <th><?php esc_html_e('Label', 'data-signals'); ?></th>
                    <th><?php esc_html_e('Category', 'data-signals'); ?></th>
                    <th><?php esc_html_e('Plugin', 'data-signals'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registered_events as $name => $event):
                    $cat_label = $categories[$event['category']] ?? ucfirst($event['category']);
                ?>
                    <tr>
                        <td><code><?php echo esc_html($name); ?></code></td>
                        <td><?php echo esc_html($event['label']); ?></td>
                        <td>
                            <span style="background: var(--ds-bg); padding: 2px 8px; border-radius: 3px; font-size: 11px;">
                                <?php echo esc_html($cat_label); ?>
                            </span>
                        </td>
                        <td><?php echo $event['plugin'] ? esc_html($event['plugin']) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="ds-card-body">
            <div class="ds-info-box">
                <h4><?php esc_html_e('No Custom Events Registered', 'data-signals'); ?></h4>
                <p><?php esc_html_e('Third-party plugins can register custom events using the Data Signals API:', 'data-signals'); ?></p>
                <pre style="background: #1d2327; color: #fff; padding: 12px; border-radius: 4px; overflow-x: auto; margin-top: 12px; font-size: 12px;"><code>// Register an event type
add_action('data_signals_register_events', function() {
    ds_register_event('form_submitted', [
        'label'    => 'Form Submitted',
        'category' => 'form',
        'plugin'   => 'my-plugin'
    ]);
});

// Track from PHP
ds_track_event('form_submitted', ['form_id' => 123]);

// Track from JavaScript
dataSignals.track('button_click', { button_id: 'cta' });</code></pre>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('ds-events-chart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo wp_json_encode($chart_labels); ?>,
            datasets: [{
                label: '<?php esc_attr_e('Events', 'data-signals'); ?>',
                data: <?php echo wp_json_encode($chart_data); ?>,
                backgroundColor: 'rgba(34, 113, 177, 0.8)',
                borderRadius: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
});
</script>
