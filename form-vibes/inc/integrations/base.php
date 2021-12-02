<?php
// phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
namespace FormVibes\Integrations;

use FormVibes\Classes\Utils;
use FormVibes\Classes\Settings;

abstract class Base {


	protected $plugin_name;
	protected $ip;
	/**
	 * @param $args
	 */
	public function make_entry( $data ) {

		$args = [
			'post_type'   => 'fv_leads',
			'post_status' => 'publish',
		];

		// Insert Post
		$post_id = wp_insert_post( $args );

		// Add Meta Data
		$this->add_meta_entries( $post_id, $data );
	}

	// end

	private function add_meta_entries( $post_id, $data ) {
		foreach ( $data['posted_data'] as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	public function set_user_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			// check ip from share internet
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// to check ip is pass from proxy
			$temp_ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );

			$ip = $temp_ip[0];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return $ip;
	}

	public function insert_enteries( $enteries ) {

		// TODO :: Check exclude form

		$inserted_forms = get_option( 'fv_forms' );

		if ( false === $inserted_forms ) {
			$inserted_forms = [];
		}
		$forms = [];

		if ( array_key_exists( $enteries['plugin_name'], $inserted_forms ) ) {
			$forms = $inserted_forms[ $enteries['plugin_name'] ];

			$forms[ $enteries['id'] ] = [
				'id'   => $enteries['id'],
				'name' => $enteries['title'],
			];
		} else {
			$forms[ $enteries['id'] ] = [
				'id'   => $enteries['id'],
				'name' => $enteries['title'],
			];
		}
		$inserted_forms[ $enteries['plugin_name'] ] = $forms;

		update_option( 'fv_forms', $inserted_forms );

		global $wpdb;
		$entry_data = [
			'form_plugin'  => $enteries['plugin_name'],
			'form_id'      => $enteries['id'],
			'captured'     => $enteries['captured'],
			'captured_gmt' => $enteries['captured_gmt'],
			'url'          => $enteries['url'],
		];

		$settings = get_option( 'fvSettings' );
		$save_ua  = false;

		if ( $settings && array_key_exists( 'save_user_agent', $settings ) ) {
			$save_ua = $settings['save_user_agent'];
		}

		if ( $save_ua ) {
			$entry_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
			$enteries['user_agent']   = $_SERVER['HTTP_USER_AGENT'];
		} else {
			$entry_data['user_agent'] = '';
		}

		$wpdb->insert(
			$wpdb->prefix . 'fv_enteries',
			$entry_data
		);
		$insert_id = $wpdb->insert_id;

		if ( $insert_id !== 0 ) {
			$this->insert_fv_entry_meta( $insert_id, $enteries['posted_data'] );
			return $insert_id;
		}
	}

	public function insert_fv_entry_meta( $insert_id, $enteries ) {
		global $wpdb;

		foreach ( $enteries as $key => $value ) {
			$wpdb->insert(
				$wpdb->prefix . 'fv_entry_meta',
				[
					'data_id'    => $insert_id,
					'meta_key'   => $key,
					'meta_value' => $value,
				]
			);
		}
		$insert_id_meta = $wpdb->insert_id;
		if ( $insert_id_meta < 1 ) {
			write_log( '==============Entry Failed===============' );
		}
	}

	public static function is_save_ip_user_agent() {
		$settings = get_option( 'fvSettings' );

		$save_ip = false;
		$save_ua = false;

		if ( $settings && array_key_exists( 'save_ip_address', $settings ) ) {
			$save_ip = $settings['save_ip_address'];
		}
		if ( $settings && array_key_exists( 'save_user_agent', $settings ) ) {
			$save_ua = $settings['save_user_agent'];
		}

		return [
			$save_ip,
			$save_ua,
		];
	}

	public static function delete_entries( $ids ) {
		global $wpdb;
		$message           = [];
		$delete_row_query1 = "Delete from {$wpdb->prefix}fv_enteries where id IN (" . implode( ',', $ids ) . ')';
		$delete_row_query2 = "Delete from {$wpdb->prefix}fv_entry_meta where data_id IN (" . implode( ',', $ids ) . ')';

		$dl1 = $wpdb->query( $delete_row_query1 );

		$dl2 = $wpdb->query( $delete_row_query2 );

		if ( 0 === $dl1 || 0 === $dl2 ) {
			$message['status']  = 'failed';
			$message['message'] = 'Could not able to delete Entries';
		} else {
			$message['status']  = 'passed';
			$message['message'] = 'Entries Deleted';
		}

		wp_send_json( $message );
	}
}
