<?php
/**
 * Logger Class
 *
 * Handles debug logging for the plugin
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger - Debug logging functionality
 */
class Logger {

    /**
     * Log levels
     */
    const LEVEL_ERROR   = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO    = 'info';
    const LEVEL_DEBUG   = 'debug';

    /**
     * Whether logging is enabled
     *
     * @var bool
     */
    private $enabled = false;

    /**
     * Log file path
     *
     * @var string
     */
    private $log_file = '';

    /**
     * Initialize logger
     */
    public function init() {
        // Check if debug logging is enabled
        $this->enabled = defined('WP_DEBUG') && WP_DEBUG && get_option('st_debug_mode', false);

        // Set log file path
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/simple-translator-debug.log';
    }

    /**
     * Log a message
     *
     * @param string $message Message to log
     * @param string $level   Log level (error, warning, info, debug)
     * @param array  $context Additional context data
     */
    public function log($message, $level = self::LEVEL_INFO, $context = array()) {
        if (!$this->enabled) {
            return;
        }

        // Format the log entry
        $entry = $this->format_entry($message, $level, $context);

        // Write to log file
        $this->write_to_file($entry);

        // Also log to error_log for critical errors
        if ($level === self::LEVEL_ERROR) {
            error_log('[Simple Translator] ' . $message);
        }
    }

    /**
     * Log an error
     *
     * @param string $message Message to log
     * @param array  $context Additional context data
     */
    public function error($message, $context = array()) {
        $this->log($message, self::LEVEL_ERROR, $context);
    }

    /**
     * Log a warning
     *
     * @param string $message Message to log
     * @param array  $context Additional context data
     */
    public function warning($message, $context = array()) {
        $this->log($message, self::LEVEL_WARNING, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Message to log
     * @param array  $context Additional context data
     */
    public function info($message, $context = array()) {
        $this->log($message, self::LEVEL_INFO, $context);
    }

    /**
     * Log a debug message
     *
     * @param string $message Message to log
     * @param array  $context Additional context data
     */
    public function debug($message, $context = array()) {
        $this->log($message, self::LEVEL_DEBUG, $context);
    }

    /**
     * Format log entry
     *
     * @param string $message Message to log
     * @param string $level   Log level
     * @param array  $context Additional context data
     * @return string Formatted log entry
     */
    private function format_entry($message, $level, $context) {
        $timestamp = current_time('Y-m-d H:i:s');
        $level_upper = strtoupper($level);

        $entry = "[{$timestamp}] [{$level_upper}] {$message}";

        // Add context if provided
        if (!empty($context)) {
            $entry .= ' | Context: ' . json_encode($context);
        }

        // Add memory usage for debug level
        if ($level === self::LEVEL_DEBUG) {
            $memory = round(memory_get_usage() / 1024 / 1024, 2);
            $entry .= " | Memory: {$memory}MB";
        }

        $entry .= "\n";

        return $entry;
    }

    /**
     * Write entry to log file
     *
     * @param string $entry Log entry to write
     */
    private function write_to_file($entry) {
        // Check if we can write to the log file
        if (!is_writable(dirname($this->log_file))) {
            return;
        }

        // Rotate log file if it's too large (> 5MB)
        if (file_exists($this->log_file) && filesize($this->log_file) > 5242880) {
            $this->rotate_log();
        }

        // Write to file
        file_put_contents($this->log_file, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rotate log file
     */
    private function rotate_log() {
        if (!file_exists($this->log_file)) {
            return;
        }

        // Keep last 3 rotations
        for ($i = 2; $i >= 0; $i--) {
            $old_file = $this->log_file . '.' . $i;
            $new_file = $this->log_file . '.' . ($i + 1);

            if (file_exists($old_file)) {
                if ($i === 2) {
                    // Delete oldest rotation
                    unlink($old_file);
                } else {
                    // Rename to next rotation
                    rename($old_file, $new_file);
                }
            }
        }

        // Move current log to .0
        rename($this->log_file, $this->log_file . '.0');
    }

    /**
     * Clear log file
     */
    public function clear_log() {
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }

        // Also clear rotations
        for ($i = 0; $i <= 3; $i++) {
            $rotation = $this->log_file . '.' . $i;
            if (file_exists($rotation)) {
                unlink($rotation);
            }
        }
    }

    /**
     * Get log contents
     *
     * @param int $lines Number of lines to retrieve (default: 100)
     * @return string Log contents
     */
    public function get_log($lines = 100) {
        if (!file_exists($this->log_file)) {
            return __('No log file found.', 'simple-translator');
        }

        // Read last N lines
        $file = new \SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $last_line = $file->key();

        $start_line = max(0, $last_line - $lines);
        $log_lines = array();

        $file->seek($start_line);
        while (!$file->eof()) {
            $log_lines[] = $file->current();
            $file->next();
        }

        return implode('', $log_lines);
    }

    /**
     * Get log file size
     *
     * @return string Formatted file size
     */
    public function get_log_size() {
        if (!file_exists($this->log_file)) {
            return '0 KB';
        }

        $size = filesize($this->log_file);
        $units = array('B', 'KB', 'MB', 'GB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;

        return number_format($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * Enable logging
     */
    public function enable() {
        $this->enabled = true;
        update_option('st_debug_mode', true);
    }

    /**
     * Disable logging
     */
    public function disable() {
        $this->enabled = false;
        update_option('st_debug_mode', false);
    }

    /**
     * Log query performance
     *
     * @param string $query_name Query name/description
     * @param float  $start_time Start time (microtime)
     */
    public function log_query_performance($query_name, $start_time) {
        if (!$this->enabled) {
            return;
        }

        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);

        $this->debug(
            "Query: {$query_name} completed in {$duration}ms",
            array(
                'query' => $query_name,
                'duration_ms' => $duration
            )
        );
    }

    /**
     * Log translation operation
     *
     * @param string $operation Operation type (create, update, delete)
     * @param int    $post_id   Post ID
     * @param string $lang      Language code
     * @param array  $context   Additional context
     */
    public function log_translation($operation, $post_id, $lang, $context = array()) {
        $message = sprintf(
            'Translation %s: Post ID %d, Language: %s',
            $operation,
            $post_id,
            $lang
        );

        $this->info($message, array_merge(
            array(
                'operation' => $operation,
                'post_id' => $post_id,
                'language' => $lang
            ),
            $context
        ));
    }
}
