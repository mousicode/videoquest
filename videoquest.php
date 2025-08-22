<?php
/*
Plugin Name: Video Quest Final Fixed
Description: ویدیو + آزمون + نظرسنجی مرحله‌ای + Progress Bar (نسخه کامل با فایل‌های شامل)
Version: 6.0
Author: ChatGPT
*/
if ( ! defined( 'ABSPATH' ) ) exit;
define('VQ_PATH', plugin_dir_path(__FILE__));
define('VQ_URL', plugin_dir_url(__FILE__));
require_once VQ_PATH . 'shortcodes.php';
require_once VQ_PATH . 'includes/admin.php';
require_once VQ_PATH . 'includes/ajax.php';
function vq_enqueue_assets() {
    wp_enqueue_style('vq-style', VQ_URL . 'assets/style.css', array(), '6.0');
    wp_enqueue_script('vq-script', VQ_URL . 'assets/script.js', array('jquery'), '6.0', true);
    wp_localize_script('vq-script', 'vqAjax', array(
        'ajaxUrl'  => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('vq_nonce'),
        'loggedIn' => is_user_logged_in(),
    ));
}
add_action('wp_enqueue_scripts', 'vq_enqueue_assets');
function vq_register_post_type() {
    register_post_type('vq_video', array(
        'labels' => array('name' => 'ویدیوها','singular_name'=>'ویدیو'),
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-video-alt3',
        'supports' => array('title','editor','thumbnail')
    ));
    register_taxonomy('vq_category','vq_video',array('labels'=>array('name'=>'دسته‌بندی ویدیو'),'hierarchical'=>true,'show_admin_column'=>true));
}
add_action('init','vq_register_post_type');
