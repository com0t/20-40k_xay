<?php
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
namespace FormVibes\Integrations;

use FormVibes\Classes\DbManager;
use FormVibes\Classes\ApiEndpoint;
use FormVibes\Classes\Utils;
use FormVibes\Integrations\Base;
use FormVibes\Classes\Settings;

class Cf7 extends Base {


	private static $instance = null;

	// array for skipping fields or unwanted data from the form data.
	protected $skip_fields       = [];
	public static $submission_id = '';

	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Cf7 constructor.
	 */
	public function __construct() {
		$this->plugin_name = 'cf7';

		$this->set_skip_fields();

		add_action( 'wpcf7_before_send_mail', [ $this, 'before_send_mail' ] );

		add_filter( 'fv_forms', [ $this, 'register_form' ] );

		add_filter( 'wpcf7_mail_components', [ $this, 'update_mail_content' ], 10, 3 );
	}

	public function register_form( $forms ) {
		$forms[ $this->plugin_name ] = 'Contact Form 7';
		return $forms;
	}

	protected function set_skip_fields() {
		// name of all fields which should not be stored in our database.
		$this->skip_fields = [ 'g-recaptcha-response', '_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post' ];
	}

	public function update_mail_content( $components, $current_form, $mail ) {

		$components['body']    = str_replace( '[fv-entry-id]', self::$submission_id, $components['body'] );
		$components['subject'] = str_replace( '[fv-entry-id]', self::$submission_id, $components['subject'] );

		return $components;
	}
	public function before_send_mail( $contact_form ) {
		$data = [];

		$submission = \WPCF7_Submission::get_instance();
		// getting all the fields or data from the form.
		$posted_data = $submission->get_posted_data();

		// File Upload

		$files = $submission->uploaded_files();

		$uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'form-vibes/cf7';
		if ( ! file_exists( $uploads_dir ) ) {
			wp_mkdir_p( $uploads_dir );
		}

		$cf7upload  = wp_upload_dir();
		$fv_dirname = $cf7upload['baseurl'] . '/form-vibes/cf7';

		foreach ( $files as $file_key => $file ) {
			$filetype = strrpos( $file[0], '.' );
			$filetype = substr( $file[0], $filetype );
			$filename = wp_rand( 1111111111, 9999999999 );
			$time_now = time();

			$posted_data[ $file_key ] = $fv_dirname . '/' . $time_now . '-' . $filename . $filetype;

			array_push( $uploaded_files, $time_now . '-' . $filename . $filetype );
			copy( $file[0], $uploads_dir . '/' . $time_now . '-' . $filename . $filetype );
		}

		// End File Upload Code

		// loop for skipping fields from the posted_data.
		foreach ( $posted_data as $key => $value ) {
			if ( in_array( $key, $this->skip_fields, true ) ) {
				// unset will destroy the skip's fields.
				unset( $posted_data[ $key ] );
			} elseif ( gettype( $value ) === 'array' ) {

				$posted_data[ $key ] = implode( ', ', $value );
			}
		}

		if ( $submission ) {

			$data['plugin_name']  = $this->plugin_name;
			$data['id']           = $contact_form->id();
			$data['captured']     = current_time( 'mysql', 0 );
			$data['captured_gmt'] = current_time( 'mysql', 1 );

			$data['title'] = $contact_form->title();
			$data['url']   = $submission->get_meta( 'url' );

			$posted_data['fv_plugin']  = $this->plugin_name;
			$posted_data['fv_form_id'] = $contact_form->id();

			$settings = get_option( 'fvSettings' );

			if ( array_key_exists( 'save_ip_address', $settings ) && true === $settings['save_ip_address'] ) {
				$posted_data['IP'] = $this->set_user_ip();
			}

			$data['posted_data'] = $posted_data;
		}
		self::$submission_id = $this->insert_enteries( $data );
	}

	public static function get_forms( $param ) {
		global $wpdb;

		$post_type = $param;

		$form_query = "select distinct form_id,form_plugin from {$wpdb->prefix}fv_enteries e WHERE form_plugin='cf7'";
		$form_res   = $wpdb->get_results( $wpdb->prepare( $form_query ), OBJECT_K );

		$inserted_forms = get_option( 'fv_forms' );

		$key   = 'cf7';
		$forms = [];
		foreach ( $form_res as $form_key => $form_value ) {
			if ( $form_res[ $form_key ]->form_plugin === $key ) {
				$forms[ $form_key ] = [
					'id'   => $form_key,
					'name' => null !== $inserted_forms[ $key ][ $form_key ]['name'] ? $inserted_forms[ $key ][ $form_key ]['name'] : $form_key,
				];
			}
		}
		return $forms;
	}
}
