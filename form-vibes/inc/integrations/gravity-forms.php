<?php

namespace FormVibes\Integrations;

use FormVibes\Classes\Utils;
use FormVibes\Integrations\Base;
use RGFormsModel;
use GFCommon;

class GravityForms extends Base {
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
		$this->plugin_name = 'gravity-forms';

		$this->set_skip_fields();

		add_filter( 'fv_forms', [ $this, 'register_plugin' ] );
		// calls after wp forms submit the form.
		add_action( 'gform_confirmation', [ $this, 'gravity_form' ], 10, 4 );
		add_filter( "formvibes/submissions/{$this->plugin_name}/columns", [ $this, 'prepare_columns' ], 10, 3 );
	}

	public function register_plugin( $forms ) {
		$forms[ $this->plugin_name ] = 'Gravity Forms';
		return $forms;
	}

	protected function set_skip_fields() {
		// name of all fields which should not be stored in our database.
		$this->skip_fields = [];
	}

	public function gravity_form( $message, $form, $lead ) {
		$form_name = $form['title'];
		$form_id   = $form['id'] . '_gravity-forms';
		// check if user wants to store/save the entry to db.
		$save_entry = true;

		$save_entry = apply_filters( 'formvibes/ninjaforms/save_record', $save_entry, $form );

		if ( ! $save_entry ) {
			return;
		}

		$data['plugin_name']  = $this->plugin_name;
		$data['id']           = $form_id;
		$data['captured']     = current_time( 'mysql', 0 );
		$data['captured_gmt'] = current_time( 'mysql', 1 );
		$data['title']        = $form_name;
		$data['url']          = $_SERVER['HTTP_REFERER'];
		$posted_data          = $this->prepare_posted_data( $form, $lead );

		$settings = get_option( 'fvSettings' );

		if ( array_key_exists( 'save_ip_address', $settings ) && true === $settings['save_ip_address'] ) {
			$posted_data['IP'] = $this->set_user_ip();
		}

		$data['fv_form_id']  = $form_id;
		$data['posted_data'] = $posted_data;

		self::$submission_id = $this->insert_enteries( $data );
	}

	private function prepare_posted_data( $form, $lead ) {
		$posted_data = [];
		$count       = 0;
		foreach ( $form['fields'] as $field ) {

			$value         = RGFormsModel::get_lead_field_value( $lead, $field );
			$display_value = GFCommon::get_lead_field_display( $field, $value, $lead['currency'], false, 'html' );
			$label         = GFCommon::get_label( $field );

			$key = str_replace( ' ', '_', $label ) . '_' . ( $count + 1 );

			$posted_data[ $key ] = wp_filter_nohtml_kses( $display_value );

			$count++;
		}
		return $posted_data;
	}

	public function prepare_columns( $cols, $columns, $form_id ) {

		foreach ( $cols as $key => $value ) {
			$colKey         = $value['colKey'];
			$alias_original = $value['alias'];
			$alias          = trim( str_replace( '_', ' ', preg_replace( '/\d+/u', '', $colKey ) ) );
			if ( $colKey === $alias_original ) {
				$cols[ $key ]['alias'] = $alias;
			}
		}

		return $cols;
	}
}
