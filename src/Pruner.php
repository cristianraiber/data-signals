<?php

namespace DataSignals;

class Pruner {
    
    /**
     * Run data pruning (called by daily cron)
     */
    public static function run(): void {
        global $wpdb;
        
        $settings = get_settings();
        $months = $settings['prune_data_after_months'] ?? 24;
        
        if ($months <= 0) {
            return; // Pruning disabled
        }
        
        $threshold = (new \DateTimeImmutable("-{$months} months", get_site_timezone()))->format('Y-m-d');
        
        // Prune site stats
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ds_site_stats WHERE date < %s",
            $threshold
        ));
        
        // Prune page stats
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ds_page_stats WHERE date < %s",
            $threshold
        ));
        
        // Prune referrer stats
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ds_referrer_stats WHERE date < %s",
            $threshold
        ));
        
        // Clean orphaned paths
        $wpdb->query(
            "DELETE p FROM {$wpdb->prefix}ds_paths p
             LEFT JOIN {$wpdb->prefix}ds_page_stats s ON s.path_id = p.id
             WHERE s.path_id IS NULL"
        );
        
        // Clean orphaned referrers
        $wpdb->query(
            "DELETE r FROM {$wpdb->prefix}ds_referrers r
             LEFT JOIN {$wpdb->prefix}ds_referrer_stats s ON s.referrer_id = r.id
             WHERE s.referrer_id IS NULL"
        );
    }
}
