<?php

namespace DataSignals;

use DateTimeImmutable;

class Dashboard {
    
    private Stats $stats;
    private int $items_per_page = 20;
    
    public function __construct() {
        $this->stats = new Stats();
    }
    
    public function show(): void {
        $settings = get_settings();
        $timezone = get_site_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        
        // Determine current tab
        $tab = $_GET['tab'] ?? 'overview';
        $valid_tabs = ['overview', 'devices', 'geographic', 'campaigns', 'referrers', 'clicks'];
        if (!in_array($tab, $valid_tabs)) {
            $tab = 'overview';
        }
        
        // Determine date range
        $range = $_GET['view'] ?? $settings['default_view'];
        
        if (isset($_GET['start_date'], $_GET['end_date'])) {
            $range = 'custom';
            try {
                $date_start = new DateTimeImmutable($_GET['start_date'], $timezone);
                $date_end = new DateTimeImmutable($_GET['end_date'], $timezone);
            } catch (\Exception $e) {
                [$date_start, $date_end] = $this->get_dates_for_range($now, 'last_28_days');
            }
        } else {
            [$date_start, $date_end] = $this->get_dates_for_range($now, $range);
        }
        
        $start_str = $date_start->format('Y-m-d');
        $end_str = $date_end->format('Y-m-d');
        
        // Comparison period
        $days_diff = $date_end->diff($date_start)->days + 1;
        $prev_end = $date_start->modify('-1 day');
        $prev_start = $prev_end->modify("-{$days_diff} days");
        
        // Get common data
        $presets = $this->get_date_presets();
        $realtime = get_realtime_pageview_count('-1 hour');
        $tabs = $this->get_tabs();
        
        // Tab-specific data
        $view_data = [
            'tab' => $tab,
            'tabs' => $tabs,
            'presets' => $presets,
            'range' => $range,
            'date_start' => $date_start,
            'date_end' => $date_end,
            'start_str' => $start_str,
            'end_str' => $end_str,
            'realtime' => $realtime,
        ];
        
        switch ($tab) {
            case 'devices':
                $view_data['device_totals'] = $this->stats->get_device_totals($start_str, $end_str);
                $view_data['browsers'] = $this->stats->get_devices($start_str, $end_str, 'browser', 10);
                $view_data['os_list'] = $this->stats->get_devices($start_str, $end_str, 'os', 10);
                break;
                
            case 'geographic':
                $view_data['countries'] = $this->stats->get_countries($start_str, $end_str, 20);
                $view_data['totals'] = $this->stats->get_totals($start_str, $end_str);
                break;
                
            case 'campaigns':
                $view_data['campaigns'] = $this->stats->get_campaigns($start_str, $end_str, 20);
                $view_data['sources'] = $this->stats->get_campaign_sources($start_str, $end_str, 10);
                break;
                
            case 'referrers':
                $view_data['referrers'] = $this->stats->get_referrers($start_str, $end_str, 0, 20);
                $view_data['totals'] = $this->stats->get_totals($start_str, $end_str);
                break;
                
            case 'clicks':
                $view_data['click_totals'] = $this->stats->get_click_totals($start_str, $end_str);
                $view_data['outbound_clicks'] = $this->stats->get_clicks($start_str, $end_str, 'outbound', 15);
                $view_data['download_clicks'] = $this->stats->get_clicks($start_str, $end_str, 'download', 10);
                $view_data['top_domains'] = $this->stats->get_top_domains($start_str, $end_str, 10);
                break;
                
            case 'overview':
            default:
                $view_data['totals'] = $this->stats->get_totals($start_str, $end_str);
                $view_data['prev_totals'] = $this->stats->get_totals(
                    $prev_start->format('Y-m-d'), 
                    $prev_end->format('Y-m-d')
                );
                $group = $date_end->diff($date_start)->days >= 90 ? 'month' : 'day';
                $view_data['chart_data'] = $this->stats->get_stats($start_str, $end_str, $group);
                $view_data['pages'] = $this->stats->get_pages($start_str, $end_str, 0, 10);
                $view_data['referrers'] = $this->stats->get_referrers($start_str, $end_str, 0, 10);
                break;
        }
        
        extract($view_data);
        require DS_DIR . '/views/dashboard.php';
    }
    
    public function get_tabs(): array {
        return [
            'overview'   => __('Overview', 'data-signals'),
            'devices'    => __('Devices', 'data-signals'),
            'geographic' => __('Geographic', 'data-signals'),
            'campaigns'  => __('Campaigns', 'data-signals'),
            'referrers'  => __('Referrers', 'data-signals'),
            'clicks'     => __('Clicks', 'data-signals'),
        ];
    }
    
    public function get_date_presets(): array {
        return [
            'today' => __('Today', 'data-signals'),
            'yesterday' => __('Yesterday', 'data-signals'),
            'this_week' => __('This week', 'data-signals'),
            'last_week' => __('Last week', 'data-signals'),
            'last_14_days' => __('Last 14 days', 'data-signals'),
            'last_28_days' => __('Last 28 days', 'data-signals'),
            'this_month' => __('This month', 'data-signals'),
            'last_month' => __('Last month', 'data-signals'),
            'this_year' => __('This year', 'data-signals'),
            'last_year' => __('Last year', 'data-signals'),
        ];
    }
    
    private function get_dates_for_range(DateTimeImmutable $now, string $key): array {
        $week_starts_on = (int) get_option('start_of_week', 0);
        
        switch ($key) {
            case 'today':
                return [$now->setTime(0, 0), $now->setTime(23, 59, 59)];
                
            case 'yesterday':
                $yesterday = $now->modify('-1 day');
                return [$yesterday->setTime(0, 0), $yesterday->setTime(23, 59, 59)];
                
            case 'this_week':
                $start = $this->get_start_of_week($now, $week_starts_on);
                return [$start, $start->modify('+6 days')->setTime(23, 59, 59)];
                
            case 'last_week':
                $start = $this->get_start_of_week($now, $week_starts_on)->modify('-7 days');
                return [$start, $start->modify('+6 days')->setTime(23, 59, 59)];
                
            case 'last_14_days':
                return [$now->modify('-13 days')->setTime(0, 0), $now->setTime(23, 59, 59)];
                
            case 'last_28_days':
            default:
                return [$now->modify('-27 days')->setTime(0, 0), $now->setTime(23, 59, 59)];
                
            case 'this_month':
                return [
                    $now->modify('first day of this month')->setTime(0, 0),
                    $now->modify('last day of this month')->setTime(23, 59, 59)
                ];
                
            case 'last_month':
                return [
                    $now->modify('first day of last month')->setTime(0, 0),
                    $now->modify('last day of last month')->setTime(23, 59, 59)
                ];
                
            case 'this_year':
                return [
                    $now->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0),
                    $now->setDate((int) $now->format('Y'), 12, 31)->setTime(23, 59, 59)
                ];
                
            case 'last_year':
                $year = (int) $now->format('Y') - 1;
                return [
                    $now->setDate($year, 1, 1)->setTime(0, 0),
                    $now->setDate($year, 12, 31)->setTime(23, 59, 59)
                ];
        }
    }
    
    private function get_start_of_week(DateTimeImmutable $date, int $week_starts_on): DateTimeImmutable {
        $day_of_week = (int) $date->format('w');
        $diff = ($day_of_week - $week_starts_on + 7) % 7;
        return $date->modify("-{$diff} days")->setTime(0, 0);
    }
}
