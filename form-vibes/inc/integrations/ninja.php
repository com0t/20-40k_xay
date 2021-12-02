<?php
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
namespace FormVibes\Integrations;

use FormVibes\Classes\Utils;
use FormVibes\Integrations\Base;

class NinjaForms extends Base {
	private static $instance     = null;
	public static $forms         = [];
	public static $submission_id = '';

	// array for skipping fields or unwanted data from the form data.
	protected $skip_fields = [];

	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Ninja Forms constructor.
	 */
	public function __construct() {
		$this->plugin_name = 'Ninja-Forms';

		$this->set_skip_fields();

		add_filter( 'fv_forms', [ $this, 'register_plugin' ] );
		// calls after ninja forms submit the form.
		add_action( 'ninja_forms_after_submission', [ $this, 'ninja_forms_after_submission' ] );
		add_filter( "formvibes/submissions/{$this->plugin_name}/columns", [ $this, 'prepare_columns' ], 10, 3 );
	}

	public function register_plugin( $forms ) {
		$forms[ $this->plugin_name ] = 'Ninja Forms';
		return $forms;
	}

	protected function set_skip_fields() {
		// name of all fields which should not be stored in our database.
		$this->skip_fields = [];
	}

	public function ninja_forms_after_submission( $data ) {

		$form_id   = $data['form_id'];
		$form_name = $data['settings']['title'];
		// check if user wants to store/save the entry to db.
		$save_entry = true;

		$save_entry = apply_filters( 'formvibes/ninjaforms/save_record', $save_entry, $data );

		if ( ! $save_entry ) {
			return;
		}

		$form_data['plugin_name']  = $this->plugin_name;
		$form_data['id']           = $form_id;
		$form_data['captured']     = current_time( 'mysql', 0 );
		$form_data['captured_gmt'] = current_time( 'mysql', 1 );
		$form_data['title']        = $form_name;
		$form_data['url']          = $_SERVER['HTTP_REFERER'];
		$posted_data               = $this->prepare_posted_data( $data['fields'] );

		$settings = get_option( 'fvSettings' );

		if ( array_key_exists( 'save_ip_address', $settings ) && true === $settings['save_ip_address'] ) {
			$posted_data['IP'] = $this->set_user_ip();
		}

		$form_data['fv_form_id']  = $form_id;
		$form_data['posted_data'] = $posted_data;
		self::$submission_id      = $this->insert_enteries( $form_data );
	}

	private function prepare_posted_data( $data ) {
		$posted_data = [];

		foreach ( $data as $key => $values ) {
			$value_key = $values['key'];
			$value     = $values['value'];
			$type      = $values['type'];

			$posted_data[ $value_key ] = $value;

			if ( $type === 'listcheckbox' || $type === 'listimage' || $type === 'listmultiselect' || $type === 'file_upload' ) {
				if ( $value ) {
					$posted_data[ $value_key ] = implode( ', ', $value );
				}
			}
		}

		return $posted_data;
	}

	public function prepare_columns( $cols, $columns, $form_id ) {
		// get fields data from Ninja Form.
		$fields = Ninja_Forms()->form( $form_id )->get_fields();

		foreach ( $fields as $field ) {
			$settings = ( is_object( $field ) ) ? $field->get_settings() : $field['settings'];
			$label    = ( is_object( $settings ) ) ? $settings->label : $settings['label'];
			$key      = ( is_object( $settings ) ) ? $settings->key : $settings['key'];
			// if alias is as same as key
			if ( $cols[ $key ]['alias'] === $key ) {
				$cols[ $key ]['alias'] = $label;
			}
		}

		return $cols;
	}
}
