<?php
/**
 * PSR-4 Autoloader for DataSignals namespace
 */

spl_autoload_register(function ($class) {
    // Only handle DataSignals namespace
    if (!str_starts_with($class, 'DataSignals\\')) {
        return;
    }
    
    // Convert namespace to file path
    $relative_class = substr($class, strlen('DataSignals\\'));
    $file = DS_DIR . '/src/' . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
