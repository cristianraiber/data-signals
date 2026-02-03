<?php

namespace DataSignals;

class Plugin {
    
    public static function activate(): void {
        // Run migrations
        (new Migrations())->run();
        
        // Setup cron jobs
        if (!wp_next_scheduled('ds_aggregate_stats')) {
            wp_schedule_event(time(), 'ds_every_minute', 'ds_aggregate_stats');
        }
        
        if (!wp_next_scheduled('ds_prune_data')) {
            wp_schedule_event(time(), 'daily', 'ds_prune_data');
        }
        
        if (!wp_next_scheduled('ds_rotate_seed')) {
            wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'ds_rotate_seed');
        }
        
        // Create upload directory
        self::create_upload_dir();
        
        // Setup capabilities
        self::setup_capabilities();
        
        // Initialize fingerprint seed
        Fingerprinter::init_seed();
        
        // Initialize GeoLite2 updater cron
        GeoLite2_Updater::init();
        
        update_option('ds_version', DS_VERSION);
    }
    
    public static function deactivate(): void {
        wp_clear_scheduled_hook('ds_aggregate_stats');
        wp_clear_scheduled_hook('ds_prune_data');
        wp_clear_scheduled_hook('ds_rotate_seed');
        GeoLite2_Updater::deactivate();
    }
    
    private static function create_upload_dir(): void {
        $dir = get_upload_dir();
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Create .htaccess to prevent direct access
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = <<<HTACCESS
<IfModule !authz_core_module>
Order deny,allow
Deny from all
</IfModule>
<IfModule authz_core_module>
Require all denied
</IfModule>
HTACCESS;
            file_put_contents($htaccess, $content);
        }
        
        // Create index.html
        file_put_contents($dir . '/index.html', '');
        
        // Create sessions directory
        $sessions_dir = $dir . '/sessions';
        if (!is_dir($sessions_dir)) {
            mkdir($sessions_dir, 0755, true);
        }
    }
    
    private static function setup_capabilities(): void {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('view_data_signals');
            $role->add_cap('manage_data_signals');
        }
    }
}
