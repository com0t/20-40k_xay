<?php

/*
Plugin Name: Form Vibes
Plugin URI: https://formvibes.com
Description: Lead Management and Graphical Reports for Elementor Pro, Contact Form 7 & Caldera form submissions.
Author: WPVibes
Version: 1.4.1
Author URI: https://wpvibes.com/
Text Domain: wpv-fv
License: GPLv2
*/

if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly
}


if ( !defined( 'WPV_FV__PATH' ) ) {
    define( 'WPV_FV__VERSION', '1.4.1' );
    // recommended pro version for free
    // define( 'WPV_FV__PRO_RECOMMENDED_VERSION', '0.5' );
    define( 'WPV_FV__URL', plugins_url( '/', __FILE__ ) );
    define( 'WPV_FV__PATH', plugin_dir_path( __FILE__ ) );
    define( 'WPV_FV_PLUGIN_BASE', plugin_basename( __FILE__ ) );
    
    if ( !defined( 'WPV_PRO_FV_VERSION' ) ) {
        // maintain
        define( 'WPV_PRO_FV_VERSION', '1.4.1' );
        define( "WPV_FV_MIN_VERSION", '1.3.6' );
    }

}


if ( !function_exists( 'wpv_fv' ) ) {
    // Create a helper function for easy SDK access.
    function wpv_fv()
    {
        global  $wpv_fv ;
        
        if ( !isset( $wpv_fv ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';
            $wpv_fv = fs_dynamic_init( array(
                'id'             => '4666',
                'slug'           => 'form-vibes',
                'premium_slug'   => 'form-vibes-pro',
                'type'           => 'plugin',
                'public_key'     => 'pk_321780b7f1d1ee45009cf6da38431',
                'is_premium'     => false,
                'premium_suffix' => 'Pro',
                'has_addons'     => false,
                'has_paid_plans' => true,
                'menu'           => array(
                'slug'       => 'fv-leads',
                'first-path' => 'admin.php?page=fv-db-settings',
                'support'    => false,
            ),
                'is_live'        => true,
            ) );
        }
        
        return $wpv_fv;
    }
    
    // Init Freemius.
    wpv_fv();
    // Signal that SDK was initiated.
    do_action( 'wpv_fv_loaded' );
}

add_action( 'plugins_loaded', function () {
    require_once WPV_FV__PATH . '/vendor/autoload.php';
    require_once WPV_FV__PATH . '/inc/bootstrap.php';
    FormVibes\Classes\DbTables::fv_plugin_activated();
} );