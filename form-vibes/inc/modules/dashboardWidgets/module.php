<?php

namespace FormVibes\Modules\DashboardWidgets;

use FormVibes\Plugin;
use FormVibes\Classes\Settings;
use FormVibes\Classes\Capabilities;

class Module {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {

		add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widgets' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ], 10, 1 );
	}

	public function add_dashboard_widgets() {

		$settings         = new Settings();
		$dashboard_widget = $settings->get_setting_value_by_key( 'dashboard_widget' );

		if ( ! $dashboard_widget ) {
			return;
		}

		$caps = Capabilities::instance()->get_caps();

		if ( ! Capabilities::check( $caps['fv_analytics'] ) ) {
			return;
		}

		add_meta_box( 'form_vibes_widget-0', 'Form Vibes Analytics', [ $this, 'dashboard_widget' ], null, 'normal', 'high', 0 );
	}

	public function dashboard_widget( $vars, $i ) {
		echo '<div name="dashboard-widget" id="fv-dashboard-widgets-' . esc_html( $i['args'] ) . '">
				</div>';
	}

	public function admin_scripts() {

		$screen = get_current_screen();

		if ( 'dashboard' === $screen->id ) {
			wp_enqueue_script( 'dashboard-select-form-js', WPV_FV__URL . 'assets/script/add-dashboard-widget-gear-icon.js', [], WPV_FV__VERSION, true );
			wp_enqueue_script( 'dashboard-js', WPV_FV__URL . 'assets/dist/dashboardWidget.js', [ 'wp-components' ], WPV_FV__VERSION, true );
			wp_enqueue_script( 'script-js', WPV_FV__URL . 'assets/script/index.js', '', WPV_FV__VERSION, true );
			wp_enqueue_style( 'dashboard-css', WPV_FV__URL . 'assets/dist/dashboardWidget.css', '', WPV_FV__VERSION );
		}
	}


}
