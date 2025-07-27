<?php
/*
Plugin Name: Email Subscriber
Description: A simple plugin to collect email subscriptions with AJAX, email notifications, and export options.
Version: 1.1
Author: P Samiksha
*/

// Create table on plugin activation
function es_create_email_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'email_subscribers';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        subscribed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'es_create_email_table');

// Enqueue JS for AJAX
function es_enqueue_scripts() {
    wp_enqueue_script('es-ajax-script', plugin_dir_url(__FILE__) . 'es-ajax.js', ['jquery'], null, true);
    wp_localize_script('es-ajax-script', 'es_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('es_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'es_enqueue_scripts');

// AJAX Form Shortcode
function es_email_subscribe_form() {
    ob_start();
    ?>
    <form id="es-subscribe-form">
        <input type="email" name="es_email" id="es_email" placeholder="Your email" required>
        <input type="submit" value="Subscribe">
        <p id="es-message"></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('email_subscribe', 'es_email_subscribe_form');

// Handle AJAX Subscription
function es_handle_ajax_subscribe() {
    check_ajax_referer('es_nonce', 'nonce');

    if (!isset($_POST['email']) || !is_email($_POST['email'])) {
        wp_send_json_error('Invalid email.');
    }

    global $wpdb;
    $email = sanitize_email($_POST['email']);
    $table_name = $wpdb->prefix . 'email_subscribers';

    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email));
    if ($exists > 0) {
        wp_send_json_error('Already subscribed.');
    }

    $wpdb->insert($table_name, ['email' => $email]);

    // Send admin notification
    wp_mail(get_option('admin_email'), 'New Subscriber', 'Email: ' . $email);

    wp_send_json_success('Thanks for subscribing!');
}
add_action('wp_ajax_es_ajax_subscribe', 'es_handle_ajax_subscribe');
add_action('wp_ajax_nopriv_es_ajax_subscribe', 'es_handle_ajax_subscribe');

// Admin menu
function es_admin_menu() {
    add_menu_page(
        'Email Subscribers',
        'Email Subscribers',
        'manage_options',
        'email-subscriber',
        'es_admin_page',
        'dashicons-email-alt',
        25
    );
}
add_action('admin_menu', 'es_admin_menu');

// Admin page
function es_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'email_subscribers';
    $emails = $wpdb->get_results("SELECT * FROM $table_name");

    echo '<div class="wrap"><h1>Subscribed Emails</h1>';

    if ($emails) {
        echo '<table class="widefat"><thead><tr><th>ID</th><th>Email</th><th>Subscribed At</th></tr></thead><tbody>';
        foreach ($emails as $e) {
            echo '<tr><td>' . esc_html($e->id) . '</td><td>' . esc_html($e->email) . '</td><td>' . esc_html($e->subscribed_at) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<form method="post"><br><input type="submit" name="es_export_csv" class="button button-primary" value="Export CSV"></form>';
    } else {
        echo '<p>No subscribers yet.</p>';
    }

    echo '</div>';

    if (isset($_POST['es_export_csv'])) {
        es_export_to_csv($emails);
    }
}

// CSV Export
function es_export_to_csv($emails) {
    if (empty($emails)) return;

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="email_subscribers.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Email', 'Subscribed At']);

    foreach ($emails as $e) {
        fputcsv($output, [$e->id, $e->email, $e->subscribed_at]);
    }

    fclose($output);
    exit;
}
