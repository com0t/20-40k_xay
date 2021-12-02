<?php

namespace FormVibes\Modules\Submissions;

use FormVibes\Classes\FV_Columns;
use FormVibes\Classes\FV_Query;
use FormVibes\Classes\Permissions;
use FormVibes\Classes\Utils;
use FormVibes\Plugin;
use FormVibes\Integrations\Base;

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

		// ajax
		add_action( 'wp_ajax_fv_get_submissions', [ $this, 'get_submissions' ], 10, 3 );
		add_action( 'wp_ajax_fv_delete_submissions', [ $this, 'delete_submissions' ], 10, 3 );
		add_action( 'wp_ajax_fv_get_columns', [ $this, 'get_columns' ], 10 );
	}

	// TODO:: consider this to add into FV_Columns class
	public function get_columns() {
		if ( ! wp_verify_nonce( $_POST['ajaxNonce'], 'fv_ajax_nonce' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		$params  = (array) json_decode( stripslashes( sanitize_text_field( $_POST['params'] ) ) );
		$plugin  = $params['plugin'];
		$form_id = $params['formId'];

		$fv_columns_obj = new FV_Columns(
			[
				'plugin'  => $plugin,
				'form_id' => $form_id,
			]
		);

		$all_cols = $fv_columns_obj->get_columns();
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $plugin != 'caldera' ) {
			$all_cols['columns'][]          = [
				'colKey'  => 'fv_status',
				'alias'   => 'Status',
				'visible' => true,
			];
			$all_cols['original_columns'][] = 'fv_status';
		}

		foreach ( $all_cols['columns'] as $key => $values ) {
			$all_cols['columns'][ $key ]['visible'] = true;
		}

		return wp_send_json( $all_cols );
	}

	public function delete_submissions() {
		if ( ! wp_verify_nonce( $_POST['ajaxNonce'], 'fv_ajax_nonce' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		if (Utils::is_pro() && ! Permissions::check_permission( Permissions::$CAP_DELETE ) ) {
			die( 'Sorry, you are not allowed to do this action!' );
		}

		$params = (array) json_decode( stripslashes( sanitize_text_field( $_POST['params'] ) ) );
		Base::delete_entries( $params['ids'] );
	}

	public function get_submissions( $params ) {

		if ( ! wp_verify_nonce( $_POST['ajaxNonce'], 'fv_ajax_nonce' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		if (Utils::is_pro() && ! Permissions::check_permission( Permissions::$CAP_SUBMISSIONS ) ) {
			die( 'Sorry, you are not allowed to do this action!' );
		}

		$params = (array) json_decode( stripslashes( sanitize_text_field( $_POST['params'] ) ) );

		$fv_query          = new FV_Query( $params );
		$result            = $fv_query->get_result();
		$result['columns'] = [];

		if ( count( array_keys( $result['data'] ) ) > 0 || true ) {
			$columns_obj                = new FV_Columns( $params );
			$cols                       = $columns_obj->get_columns();
			$result['columns']          = $cols['columns'];
			$result['original_columns'] = $cols['original_columns'];
		}

		wp_send_json( $result );
	}

	public function admin_scripts() {

		$screen = get_current_screen();
		if ( $screen->id === 'toplevel_page_fv-leads' ) {
			wp_enqueue_script( 'submissions-js', WPV_FV__URL . 'assets/dist/submissions.js', [ 'wp-components' ], WPV_FV__VERSION, true );
			wp_enqueue_style( 'fv-submission-css', WPV_FV__URL . 'assets/dist/submissions.css', '', WPV_FV__VERSION );
		}
	}

	public function admin_menu() {

		$caps = Plugin::$capabilities->get_caps();

		add_menu_page( 'Form Vibes Leads', 'Form Vibes', $caps['fv_leads'], 'fv-leads', [ $this, 'render_root' ], 'dashicons-analytics', 30 );
		add_submenu_page( 'fv-leads', 'Form Vibes Submissions', 'Submissions', $caps['fv_leads'], 'fv-leads', [ $this, 'render_root' ], 1 );
	}

	public function render_root() {

		$caps = Plugin::$capabilities->get_caps();

		if ( ! Plugin::$capabilities->check( $caps['fv_leads'] ) ) {
			return;
		}
		?>
		<div id="fv-submissions">

		</div>
		<?php
	}
}
