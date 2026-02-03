<?php

namespace DataSignals;

class Migrations {
    
    private string $option_key = 'ds_db_version';
    private string $migrations_dir;
    
    public function __construct() {
        $this->migrations_dir = DS_DIR . '/migrations';
    }
    
    public function run(): void {
        $current_version = get_option($this->option_key, '0.0.0');
        $migrations = $this->get_migrations();
        
        foreach ($migrations as $version => $file) {
            if (version_compare($version, $current_version, '>')) {
                require $file;
                update_option($this->option_key, $version);
            }
        }
    }
    
    private function get_migrations(): array {
        $migrations = [];
        
        if (!is_dir($this->migrations_dir)) {
            return $migrations;
        }
        
        $files = scandir($this->migrations_dir);
        
        foreach ($files as $file) {
            if (!str_ends_with($file, '.php')) {
                continue;
            }
            
            // Extract version from filename (e.g., "2.0.0-initial-schema.php")
            if (preg_match('/^(\d+\.\d+\.\d+)-/', $file, $matches)) {
                $version = $matches[1];
                $migrations[$version] = $this->migrations_dir . '/' . $file;
            }
        }
        
        uksort($migrations, 'version_compare');
        
        return $migrations;
    }
}
