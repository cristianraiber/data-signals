<?php

namespace DataSignals;

class Referrer_Repository {
    
    /**
     * Get or create referrer IDs
     */
    public static function upsert(array $urls): array {
        global $wpdb;
        
        if (empty($urls)) {
            return [];
        }
        
        $urls = array_unique($urls);
        $table = $wpdb->prefix . 'ds_referrers';
        
        // Get existing referrers
        $placeholders = implode(',', array_fill(0, count($urls), '%s'));
        $existing = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, url FROM {$table} WHERE url IN ({$placeholders})",
                $urls
            ),
            OBJECT_K
        );
        
        $result = [];
        $to_insert = [];
        
        foreach ($urls as $url) {
            $found = false;
            foreach ($existing as $row) {
                if ($row->url === $url) {
                    $result[$url] = (int) $row->id;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $to_insert[] = $url;
            }
        }
        
        // Insert new referrers
        foreach ($to_insert as $url) {
            $wpdb->insert($table, ['url' => $url], ['%s']);
            $result[$url] = (int) $wpdb->insert_id;
        }
        
        return $result;
    }
    
    /**
     * Get URL by ID
     */
    public static function get(int $id): ?string {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT url FROM {$wpdb->prefix}ds_referrers WHERE id = %d",
            $id
        ));
    }
}
