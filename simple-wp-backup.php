<?php
/**
 * Plugin Name: Simple WP Backup
 * Plugin URI: https://github.com/fskilroy/simple-wp-backup
 * Description: A simple WordPress backup plugin that displays database tables and their information.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-wp-backup
 * Domain Path: /languages
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SIMPLE_WP_BACKUP_VERSION', '1.0.0');
define('SIMPLE_WP_BACKUP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIMPLE_WP_BACKUP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class SimpleWPBackup {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Add admin menu if user is admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            'Simple WP Backup - Database Tables',
            'WP Database Tables',
            'manage_options',
            'simple-wp-backup-tables',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>WordPress Database Tables</h1>
            <p>Here are all the database tables used by this WordPress site:</p>
            
            <?php
            $tables = $this->get_wordpress_tables();
            if (!empty($tables)) {
                echo '<div class="card">';
                echo '<h2>Database Tables (' . count($tables) . ' total)</h2>';
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>Table Name</th><th>Estimated Rows</th><th>Size</th></tr></thead>';
                echo '<tbody>';
                
                foreach ($tables as $table) {
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($table['name']) . '</strong></td>';
                    echo '<td>' . number_format($table['rows']) . '</td>';
                    echo '<td>' . $this->format_bytes($table['size']) . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
                
                // Show table categories
                $core_tables = $this->get_core_wordpress_tables();
                $plugin_tables = array_diff(array_column($tables, 'name'), $core_tables);
                
                echo '<div class="card" style="margin-top: 20px;">';
                echo '<h3>Table Categories</h3>';
                echo '<p><strong>Core WordPress Tables:</strong> ' . count($core_tables) . '</p>';
                echo '<ul>';
                foreach ($core_tables as $core_table) {
                    if (in_array($core_table, array_column($tables, 'name'))) {
                        echo '<li>' . esc_html($core_table) . '</li>';
                    }
                }
                echo '</ul>';
                
                if (!empty($plugin_tables)) {
                    echo '<p><strong>Plugin/Custom Tables:</strong> ' . count($plugin_tables) . '</p>';
                    echo '<ul>';
                    foreach ($plugin_tables as $plugin_table) {
                        echo '<li>' . esc_html($plugin_table) . '</li>';
                    }
                    echo '</ul>';
                }
                echo '</div>';
            } else {
                echo '<div class="notice notice-error"><p>Could not retrieve database tables.</p></div>';
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Get all WordPress database tables
     */
    private function get_wordpress_tables() {
        global $wpdb;
        
        $tables = array();
        
        // Get all tables in the database
        $results = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        
        if ($results) {
            foreach ($results as $row) {
                // Only include tables with the WordPress prefix
                if (strpos($row['Name'], $wpdb->prefix) === 0) {
                    $tables[] = array(
                        'name' => $row['Name'],
                        'rows' => intval($row['Rows']),
                        'size' => intval($row['Data_length']) + intval($row['Index_length'])
                    );
                }
            }
        }
        
        return $tables;
    }
    
    /**
     * Get core WordPress table names
     */
    private function get_core_wordpress_tables() {
        global $wpdb;
        
        return array(
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->comments,
            $wpdb->commentmeta,
            $wpdb->terms,
            $wpdb->termmeta,
            $wpdb->term_taxonomy,
            $wpdb->term_relationships,
            $wpdb->users,
            $wpdb->usermeta,
            $wpdb->options,
            $wpdb->links,
        );
    }
    
    /**
     * Format bytes to human readable format
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Plugin activation hook
     */
    public static function activate() {
        // Activation code would go here
        // For now, this does nothing
    }
    
    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        // Deactivation code would go here
        // For now, this does nothing
    }
}

// Initialize the plugin
new SimpleWPBackup();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('SimpleWPBackup', 'activate'));
register_deactivation_hook(__FILE__, array('SimpleWPBackup', 'deactivate'));
