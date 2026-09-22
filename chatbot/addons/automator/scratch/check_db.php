<?php
require_once '../../../wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'wpbot_automator_email_templates';
echo "Checking table: $table\n";
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
if ($exists) {
    echo "Table EXISTS\n";
    $columns = $wpdb->get_results("DESCRIBE $table");
    foreach ($columns as $col) {
        echo "Field: {$col->Field}, Type: {$col->Type}\n";
    }
} else {
    echo "Table DOES NOT EXIST\n";
}
