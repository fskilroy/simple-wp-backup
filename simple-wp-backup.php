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
            // Add AJAX handlers for backup
            add_action('wp_ajax_simple_wp_backup_database', array($this, 'backup_database'));
        }
        
        // Add item to admin bar for all logged-in users who can manage options
        add_action('admin_bar_menu', array($this, 'add_admin_bar_item'), 100);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'WP Simple Backup',
            'WP Simple Backup',
            'manage_options',
            'simple-wp-backup',
            array($this, 'admin_page'),
            'dashicons-database',
            30
        );
        
        // Add submenu page for database tables
        add_submenu_page(
            'simple-wp-backup',
            'Database Tables',
            'Database Tables',
            'manage_options',
            'simple-wp-backup-tables',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Add item to admin bar
     */
    public function add_admin_bar_item($wp_admin_bar) {
        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_node(array(
            'id'    => 'simple-wp-backup',
            'title' => 'WP Simple Backup',
            'href'  => admin_url('admin.php?page=simple-wp-backup'),
            'meta'  => array(
                'title' => 'View WordPress Database Tables'
            )
        ));
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
                
                // Add backup section
                echo '<div class="card" style="margin-top: 20px;">';
                echo '<h3>Database Backup</h3>';
                echo '<p>Create a complete backup of all WordPress database tables.</p>';
                echo '<button id="backup-database-btn" class="button button-primary">Backup Database</button>';
                echo '<div id="backup-status" style="margin-top: 10px;"></div>';
                echo '</div>';
                
            } else {
                echo '<div class="notice notice-error"><p>Could not retrieve database tables.</p></div>';
            }
            ?>
            
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#backup-database-btn').click(function() {
                    var button = $(this);
                    var status = $('#backup-status');
                    
                    button.prop('disabled', true).text('Creating Backup...');
                    status.html('<div class="notice notice-info"><p>Creating database backup, please wait...</p></div>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'simple_wp_backup_database',
                            nonce: '<?php echo wp_create_nonce('simple_wp_backup_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                status.html('<div class="notice notice-success"><p>Backup created successfully! <a href="' + response.data.download_url + '" class="button">Download Backup</a></p></div>');
                            } else {
                                status.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                            }
                        },
                        error: function() {
                            status.html('<div class="notice notice-error"><p>An error occurred while creating the backup.</p></div>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text('Backup Database');
                        }
                    });
                });
            });
            </script>
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
     * Handle database backup AJAX request
     */
    public function backup_database() {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'simple_wp_backup_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            global $wpdb;
            
            // Create backup directory if it doesn't exist
            $upload_dir = wp_upload_dir();
            $backup_dir = $upload_dir['basedir'] . '/wp-simple-backup/';
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }
            
            // Generate filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $sql_filename = 'database_backup_' . $timestamp . '.sql';
            $sql_filepath = $backup_dir . $sql_filename;
            
            // Get all WordPress tables
            $tables = $this->get_wordpress_tables();
            
            if (empty($tables)) {
                wp_send_json_error('No tables found to backup');
            }
            
            // Create SQL dump
            $sql_content = "-- WordPress Database Backup\n";
            $sql_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
            $sql_content .= "-- Site URL: " . get_site_url() . "\n\n";
            
            foreach ($tables as $table) {
                $table_name = $table['name'];
                
                // Add DROP TABLE statement
                $sql_content .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
                
                // Get CREATE TABLE statement
                $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_N);
                if ($create_table) {
                    $sql_content .= $create_table[1] . ";\n\n";
                }
                
                // Get table data
                $rows = $wpdb->get_results("SELECT * FROM `{$table_name}`", ARRAY_A);
                if ($rows) {
                    $sql_content .= "INSERT INTO `{$table_name}` VALUES\n";
                    $values = array();
                    
                    foreach ($rows as $row) {
                        $escaped_values = array();
                        foreach ($row as $value) {
                            if ($value === null) {
                                $escaped_values[] = 'NULL';
                            } else {
                                $escaped_values[] = "'" . $wpdb->_real_escape($value) . "'";
                            }
                        }
                        $values[] = '(' . implode(', ', $escaped_values) . ')';
                    }
                    
                    $sql_content .= implode(",\n", $values) . ";\n\n";
                }
            }
            
            // Write SQL file
            if (file_put_contents($sql_filepath, $sql_content) === false) {
                wp_send_json_error('Failed to create SQL backup file');
            }
            
            // Generate download URL
            $download_url = $upload_dir['baseurl'] . '/wp-simple-backup/' . $sql_filename;
            
            wp_send_json_success(array(
                'message' => 'Database backup created successfully',
                'filename' => $sql_filename,
                'download_url' => $download_url
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Backup failed: ' . $e->getMessage());
        }
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
