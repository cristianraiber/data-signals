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
        
        // Get stats
        $totals = $this->stats->get_totals($start_str, $end_str);
        
        // Comparison period
        $days_diff = $date_end->diff($date_start)->days + 1;
        $prev_end = $date_start->modify('-1 day');
        $prev_start = $prev_end->modify("-{$days_diff} days");
        $prev_totals = $this->stats->get_totals($prev_start->format('Y-m-d'), $prev_end->format('Y-m-d'));
        
        // Chart data
        $group = $date_end->diff($date_start)->days >= 90 ? 'month' : 'day';
        $chart_data = $this->stats->get_stats($start_str, $end_str, $group);
        
        // Top pages
        $pages_offset = absint($_GET['pages_offset'] ?? 0);
        $pages = $this->stats->get_pages($start_str, $end_str, $pages_offset, $this->items_per_page);
        $pages_count = $this->stats->count_pages($start_str, $end_str);
        
        // Top referrers
        $referrers_offset = absint($_GET['referrers_offset'] ?? 0);
        $referrers = $this->stats->get_referrers($start_str, $end_str, $referrers_offset, $this->items_per_page);
        $referrers_count = $this->stats->count_referrers($start_str, $end_str);
        
        // Realtime
        $realtime = get_realtime_pageview_count('-1 hour');
        
        // Date presets
        $presets = $this->get_date_presets();
        
        require DS_DIR . '/views/dashboard.php';
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
