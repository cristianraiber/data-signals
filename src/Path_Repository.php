<?php

namespace DataSignals;

class Path_Repository {
    
    /**
     * Get or create path IDs
     */
    public static function upsert(array $paths): array {
        global $wpdb;
        
        if (empty($paths)) {
            return [];
        }
        
        $paths = array_unique($paths);
        $table = $wpdb->prefix . 'ds_paths';
        
        // Get existing paths
        $placeholders = implode(',', array_fill(0, count($paths), '%s'));
        $existing = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, path FROM {$table} WHERE path IN ({$placeholders})",
                $paths
            ),
            OBJECT_K
        );
        
        $result = [];
        $to_insert = [];
        
        foreach ($paths as $path) {
            $found = false;
            foreach ($existing as $row) {
                if ($row->path === $path) {
                    $result[$path] = (int) $row->id;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $to_insert[] = $path;
            }
        }
        
        // Insert new paths
        foreach ($to_insert as $path) {
            $wpdb->insert($table, ['path' => $path], ['%s']);
            $result[$path] = (int) $wpdb->insert_id;
        }
        
        return $result;
    }
    
    /**
     * Get path by ID
     */
    public static function get(int $id): ?string {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT path FROM {$wpdb->prefix}ds_paths WHERE id = %d",
            $id
        ));
    }
}
