<?php

namespace DataSignals;

class Script_Loader {
    
    /**
     * Enqueue the tracking script
     */
    public function enqueue(): void {
        if (is_request_excluded()) {
            return;
        }
        
        add_action('wp_enqueue_scripts', [$this, 'register_script'], 20);
        add_action('wp_head', [$this, 'print_tracking_data'], 1);
    }
    
    /**
     * Register the tracking script
     */
    public function register_script(): void {
        wp_enqueue_script(
            'data-signals-tracker',
            plugins_url('assets/js/tracker.js', DS_FILE),
            [],
            DS_VERSION,
            ['strategy' => 'defer', 'in_footer' => true]
        );
    }
    
    /**
     * Print tracking configuration in the head
     */
    public function print_tracking_data(): void {
        global $post;
        
        $data = [
            'url' => $this->get_collect_url(),
            'path' => $this->get_current_path(),
            'id' => $post->ID ?? 0,
        ];
        
        printf(
            '<script>window.dsConfig=%s;</script>' . PHP_EOL,
            wp_json_encode($data)
        );
    }
    
    /**
     * Get the collection endpoint URL
     */
    private function get_collect_url(): string {
        // TODO: Support optimized endpoint
        return add_query_arg('action', 'ds_collect', home_url('/'));
    }
    
    /**
     * Get the current page path
     */
    private function get_current_path(): string {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        $path = strtok($path, '?');
        
        // Ensure it starts with /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        
        return $path;
    }
}
