<?php
/**
 * HaLong Tour Theme - Functions
 *
 * Loads all backend classes and configures WordPress hooks.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ===========================
// AUTOLOAD BACKEND CLASSES
// ===========================
require_once get_theme_file_path('inc/class-audit-log.php');
require_once get_theme_file_path('inc/class-theme-settings.php');
require_once get_theme_file_path('inc/class-tour-cpt.php');
require_once get_theme_file_path('inc/class-review-cpt.php');
require_once get_theme_file_path('inc/class-booking-cpt.php');
require_once get_theme_file_path('inc/class-ajax-handler.php');
require_once get_theme_file_path('inc/class-email-sender.php');

// ===========================
// INSTANTIATE CLASSES
// ===========================
new Halong_Audit_Log();
new Halong_Theme_Settings();
new Halong_Tour_CPT();
new Halong_Review_CPT();
new Halong_Booking_CPT();
new Halong_Ajax_Handler();
new Halong_Email_Sender();

// ===========================
// THEME SETUP
// ===========================
add_action('after_setup_theme', 'halong_theme_setup');
function halong_theme_setup()
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo');
    add_theme_support('elementor');
    load_theme_textdomain('halong-tour', get_template_directory() . '/languages');
}

// ===========================
// ENQUEUE SCRIPTS & STYLES
// ===========================
add_action('wp_enqueue_scripts', 'halong_enqueue_assets');
function halong_enqueue_assets()
{
    // Only load booking assets on tour single page and payment page
    if (!is_singular('tour') && !is_page('payment')) {
        return;
    }

    $ver = '1.0.0';

    // External CDN assets (Tailwind + Phosphor Icons)
    wp_enqueue_script(
        'phosphor-icons',
        'https://unpkg.com/@phosphor-icons/web',
        [],
        null,
        false
    );
    wp_enqueue_script(
        'tailwindcss',
        'https://cdn.tailwindcss.com',
        [],
        null,
        false
    );

    // Custom booking CSS
    wp_enqueue_style(
        'halong-booking-app',
        get_theme_file_uri('assets/css/booking-app.css'),
        [],
        $ver
    );

    // Custom booking JS
    wp_enqueue_script(
        'halong-booking-app',
        get_theme_file_uri('assets/js/booking-app.js'),
        [],
        $ver,
        true  // Load in footer
    );

    // Localize script: inject PHP config into JS as BookingConfig
    global $post;
    $post_id = $post ? $post->ID : 0;

    // Get time slots from SCF
    $time_slots = Halong_Tour_CPT::get_time_slots($post_id);

    // Get prices
    $adult_price = (int) get_field('halong_adult_price', $post_id);
    if (!$adult_price)
        $adult_price = 450000;
    $child_price = (int) get_field('halong_child_price', $post_id);
    if (!$child_price)
        $child_price = 225000;

    // Get max guests
    $max_guests = (int) get_field('halong_tour_max_guests', $post_id);
    if (!$max_guests)
        $max_guests = 15;

    // Bank info for VietQR
    $bank_bin = get_field('halong_bank_bin', 'option') ?: '970422';
    $bank_account = get_field('halong_bank_account', 'option') ?: '';
    $bank_name = get_field('halong_bank_name', 'option') ?: 'HALONG RUM';

    // Feature flags
    $children_enabled = (bool) get_field('halong_enable_children', 'option');
    $qr_enabled = get_field('halong_enable_qr', 'option') !== false ? true : false;
    $email_enabled = get_field('halong_enable_email', 'option') !== false ? true : false;
    $booking_enabled = get_field('halong_enable_booking', 'option') !== false ? true : false;

    // Age verify redirect
    $age_redirect = get_field('halong_age_verify_redirect', 'option') ?: 'https://halongrum.com';

    wp_localize_script('halong-booking-app', 'BookingConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('halong_booking_nonce'),
        'tourId' => $post_id,
        'price' => [
            'adult' => $adult_price,
            'child' => $child_price,
        ],
        'timeSlots' => $time_slots,
        'tourMaxGuests' => $max_guests,
        'childrenEnabled' => $children_enabled,
        'features' => [
            'qr_enabled' => $qr_enabled,
            'email_enabled' => $email_enabled,
            'booking_enabled' => $booking_enabled,
        ],
        'bank' => [
            'bin' => $bank_bin,
            'account' => $bank_account,
            'name' => $bank_name,
        ],
        'ageVerifyRedirect' => $age_redirect,
    ]);

    // Inline Tailwind config (must be inline, not external, for Tailwind CDN to work)
    $tailwind_config = <<<JS
tailwind.config = {
    theme: {
        extend: {
            colors: {
                'brand-green': '#395327',
                'brand-accent': '#C6A96B',
                'brand-black': '#0B0C09',
                'brand-section': '#1E2B1A',
                'brand-cream': '#F0EBE1',
                'brand-body': '#b0c8a0',
            },
            fontFamily: {
                'serif': ['"Playfair Display"', 'serif'],
                'sans': ['"Inter"', 'sans-serif'],
            },
            letterSpacing: {
                'h2': '0.08em',
                'label': '0.1em',
            }
        }
    }
};
JS;
    wp_add_inline_script('tailwindcss', $tailwind_config);

    // Google Fonts inline
    wp_enqueue_style(
        'halong-google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&family=Playfair+Display:wght@400;500&display=swap',
        [],
        null
    );
}

// ===========================
// ASSET STRIPPING for tour single template
// Remove unnecessary WP CSS/JS to keep page fast
// ===========================
add_action('template_redirect', 'halong_strip_unnecessary_assets');
function halong_strip_unnecessary_assets()
{
    if (!is_singular('tour')) {
        return;
    }

    // Remove admin bar
    add_filter('show_admin_bar', '__return_false');

    // Remove WP block styles
    add_action('wp_enqueue_scripts', function () {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('global-styles');
        wp_dequeue_style('classic-theme-styles');
    }, 100);
}

// ===========================
// DISABLE BLOCK EDITOR for tour CPT
// Elementor + SCF work better without Gutenberg
// ===========================
add_filter('use_block_editor_for_post_type', 'halong_disable_gutenberg_for_tours', 10, 2);
function halong_disable_gutenberg_for_tours($use, $post_type)
{
    if (in_array($post_type, ['tour', 'tour_booking', 'tour_review'], true)) {
        return false;
    }
    return $use;
}

// ===========================
// WP-CRON: Schedule booking expiry job
// ===========================
add_action('wp', 'halong_setup_cron_jobs');
function halong_setup_cron_jobs()
{
    if (!wp_next_scheduled('halong_expire_bookings_cron')) {
        wp_schedule_event(time(), 'hourly', 'halong_expire_bookings_cron');
    }
}

// Activation hook: create DB tables and schedule cron
register_activation_hook(__FILE__, 'halong_theme_activate');
function halong_theme_activate()
{
    Halong_Audit_Log::create_tables();
    if (!wp_next_scheduled('halong_expire_bookings_cron')) {
        wp_schedule_event(time(), 'hourly', 'halong_expire_bookings_cron');
    }
    // Flush rewrite rules after CPT registration
    flush_rewrite_rules();
}

// Deactivation: clear cron
register_deactivation_hook(__FILE__, 'halong_theme_deactivate');
function halong_theme_deactivate()
{
    wp_clear_scheduled_hook('halong_expire_bookings_cron');
}

// ===========================
// BOOKING STATUS CHANGE HOOK
// Track changes from admin booking edit page
// ===========================
add_action('save_post_tour_booking', 'halong_track_status_change', 20, 3);
function halong_track_status_change($post_id, $post, $update)
{
    if (!$update)
        return;
    if (wp_is_post_revision($post_id))
        return;

    $new_status = get_post_meta($post_id, 'booking_status', true);
    $old_status = get_post_meta($post_id, '_booking_status_prev', true);

    if ($new_status && $new_status !== $old_status) {
        update_post_meta($post_id, '_booking_status_prev', $new_status);
        if (class_exists('Halong_Audit_Log')) {
            Halong_Audit_Log::log($post_id, 'status_changed', $old_status, $new_status);
        }
        if ('confirmed' === $new_status) {
            do_action('halong_booking_confirmed', $post_id);
        }
    }
}

// ===========================
// SEO: Expose SCF fields to Yoast/RankMath
// ===========================
add_filter('wpseo_opengraph_image', 'halong_seo_og_image');
function halong_seo_og_image($img)
{
    if (is_singular('tour')) {
        $hero = get_field('halong_hero_image');
        if ($hero)
            return $hero;
    }
    return $img;
}

add_filter('body_class', function ($classes) {
    $classes[] = 'bg-brand-black';
    $classes[] = 'text-brand-body';
    return $classes;
});

// ===========================
// AUTOMATICALLY CREATE/RENAME PAYMENT PAGE TO PREVENT 404
// ===========================
add_action('init', 'halong_ensure_payment_page');
function halong_ensure_payment_page() {
    // 1. Check if 'payment' page already exists
    $payment_page = get_page_by_path('payment');
    if ($payment_page) {
        return; // Page exists, do nothing
    }

    // 2. Check if the old 'thanh-toan' page exists, if so, rename its slug to 'payment'
    $thanh_toan_page = get_page_by_path('thanh-toan');
    if ($thanh_toan_page) {
        wp_update_post(array(
            'ID'         => $thanh_toan_page->ID,
            'post_name'  => 'payment',
            'post_title' => 'Thanh toán',
        ));
        update_post_meta($thanh_toan_page->ID, '_wp_page_template', 'page-payment.php');
        return;
    }

    // 3. Otherwise, create a new 'payment' page
    $page_id = wp_insert_post(array(
        'post_title'   => 'Thanh toán',
        'post_content' => '',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_name'    => 'payment',
    ));
    if ($page_id) {
        update_post_meta($page_id, '_wp_page_template', 'page-payment.php');
    }
}

// ===========================
// VIETQR BANKS API HELPERS
// ===========================
function halong_get_vietqr_banks() {
    $transient_key = 'halong_vietqr_banks_cache';
    $banks = get_transient($transient_key);
    if (false === $banks) {
        $response = wp_remote_get('https://api.vietqr.io/v2/banks');
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $json = json_decode($body, true);
            if (isset($json['data']) && is_array($json['data'])) {
                $banks = $json['data'];
                set_transient($transient_key, $banks, WEEK_IN_SECONDS);
            }
        }
    }
    return $banks ?: array();
}

function halong_get_bank_details_by_bin($bin) {
    if (empty($bin)) {
        return null;
    }
    $banks = halong_get_vietqr_banks();
    foreach ($banks as $bank) {
        if (isset($bank['bin']) && $bank['bin'] === $bin) {
            return $bank;
        }
    }
    return null;
}

// Populate the Bank BIN text field as a beautiful select dropdown dynamically loaded from VietQR
add_filter('acf/load_field/name=halong_bank_bin', 'halong_load_bank_bin_field_choices');
function halong_load_bank_bin_field_choices($field) {
    $field['type'] = 'select';
    $field['label'] = 'Ngân hàng';
    $field['instructions'] = 'Chọn ngân hàng chuyển khoản để hiển thị thông tin và tạo mã QR.';
    $field['ui'] = 1;
    $field['ajax'] = 0;
    
    $banks = halong_get_vietqr_banks();
    $choices = array();
    
    foreach ($banks as $bank) {
        $bin = isset($bank['bin']) ? $bank['bin'] : '';
        $short_name = isset($bank['shortName']) ? $bank['shortName'] : '';
        $name = isset($bank['name']) ? $bank['name'] : '';
        
        if (!empty($bin)) {
            $choices[$bin] = $short_name . ' - ' . $name;
        }
    }
    
    $field['choices'] = $choices;
    return $field;
}

