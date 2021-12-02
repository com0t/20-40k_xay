<?php

namespace FormVibes\Modules\Logs;

use FormVibes\Classes\Permissions;
use FormVibes\Plugin;
use FormVibes\Classes\Utils;

class Module {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ], 10, 1 );

		add_action( 'admin_menu', [ $this, 'admin_menu' ], 9 );
		add_action( 'wp_ajax_fv_event_logs', [ $this, 'get_event_logs_data' ], 10, 3 );
	}

	public function get_event_logs_data() {
		if ( ! wp_verify_nonce( $_POST['ajaxNonce'], 'fv_ajax_nonce' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		if (Utils::is_pro() && ! Permissions::check_permission( Permissions::$CAP_LOGS ) ) {
			die( 'Sorry, you are not allowed to do this action!' );
		}

		$params = (array) json_decode( stripslashes( sanitize_text_field( $_POST['params'] ) ) );

		$gmt_offset = get_option( 'gmt_offset' );
		$hours      = (int) $gmt_offset;
		$minutes    = ( $gmt_offset - floor( $gmt_offset ) ) * 60;
		if ( $hours >= 0 ) {
			$time_zone = $hours . ':' . $minutes;
		} else {
			$time_zone = $hours . ':' . $minutes;
		}
		$limit = '';
		if ( $params['page'] > 1 ) {
			$limit = ' limit ' . $params['pageSize'] * ( $params['page'] - 1 ) . ',' . $params['pageSize'];
		} else {
			$limit = ' limit ' . $params['pageSize'];
		}
		global $wpdb;

		$entry_query = "select @a:=@a+1 serial_number, l.id,l.user_id,u.user_login,event,description,DATE_FORMAT(ADDTIME(export_time_gmt,'" . $time_zone . "' ), '%Y/%m/%d %H:%i:%S') as export_time_gmt from {$wpdb->prefix}fv_logs l LEFT JOIN {$wpdb->prefix}users u on l.user_id=u.ID, (SELECT @a:= 0) AS a ORDER BY id desc" . $limit;

		$entry_result      = $wpdb->get_results( $entry_query, ARRAY_A );
		$entry_count_query = "select count(id) from {$wpdb->prefix}fv_logs l ORDER BY id desc";

		$entry_count_result = $wpdb->get_var( $entry_count_query );
		foreach ( $entry_result as $key => $value ) {
			$user_meta                    = get_user_meta( $value['user_id'] );
			$entry_result[ $key ]['user'] = $user_meta['first_name'][0] . ' ' . $user_meta['last_name'][0];
		}
		$results = [
			'count' => $entry_count_result,
			'data'  => $entry_result,
		];
		return wp_send_json( $results );
	}

	public function admin_scripts() {

		$screen = get_current_screen();

		if ( 'form-vibes_page_fv-logs' === $screen->id ) {
			wp_enqueue_script( 'analytics-js', WPV_FV__URL . 'assets/dist/eventLogs.js', [ 'wp-components' ], WPV_FV__VERSION, true );
			wp_enqueue_style( 'analytics-css', WPV_FV__URL . 'assets/dist/eventLogs.css', '', WPV_FV__VERSION );
		}
	}

	public function admin_menu() {

		$caps = Plugin::$capabilities->get_caps();

		add_submenu_page( 'fv-leads', 'Form Vibes Logs', 'Event Logs', $caps['fv_view_logs'], 'fv-logs', [ $this, 'render_root' ], 6 );
	}

	public function render_root() {

		$caps = Plugin::$capabilities->get_caps();

		if ( ! Plugin::$capabilities->check( $caps['fv_view_logs'] ) ) {
			return;
		}

		?>
		<div id="fv-logs" class="fv-logs">
			<div class="fv-wrapper">
				<div class="fv-data-wrapper">
					<div id="fv-event-log-wrapper" class="fv-event-log-wrapper">
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
