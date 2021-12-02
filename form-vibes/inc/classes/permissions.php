<?php

namespace FormVibes\Classes;

class Permissions {

	public static $CAP_EDIT           = 'edit_fv_submissions';
	public static $CAP_DELETE         = 'delete_fv_submissions';
	public static $CAP_EXPORT         = 'export_fv_submissions';
	public static $CAP_ADD_NOTES      = 'add_fv_notes';
	public static $CAP_DELETE_NOTES   = 'delete_fv_note';
	public static $CAP_VIEW_NOTES     = 'view_fv_note';
	public static $CAP_CHANGE_NOTES   = 'change_fv_note';
	public static $CAP_VIEW_STATUS    = 'view_fv_status';
	public static $CAP_CHANGE_STATUS  = 'change_fv_status';
	public static $CAP_LOGS           = 'view_fv_logs';
	public static $CAP_SUBMISSIONS    = 'view_fv_submissions';
	public static $CAP_ANALYTICS      = 'view_fv_analytics';
	public static $CAP_DATA_PROFILE   = 'manage_fv_data_profiles';
	public static $CAP_EXPORT_PROFILE = 'manage_fv_export_profiles';

	public static function check_permission( $permission, $user_id = null ) {
		$user = wp_get_current_user();

		if ( ! $user_id ) {
			$user_id = $user->ID;
		}

		$can = user_can( $user_id, $permission );

		if ( user_can( $user_id, 'administrator' ) ) {
			// user is admin
			return true;
		}

		if ( $can ) {
			return true;
		}

		return false;
	}

	public static function get_permissions() {
		return apply_filters(
			'formvibes/permissions',
			[
				'edit_fv_submissions',
				'delete_fv_submissions',
				'export_fv_submissions',
				'add_fv_notes',
				'delete_fv_note',
				'view_fv_note',
				'change_fv_note',
				'view_fv_status',
				'change_fv_status',
				'view_fv_logs',
				'view_fv_submissions',
				'view_fv_analytics',
				'manage_fv_data_profiles',
				'manage_fv_export_profiles',
			]
		);
	}

	public static function is_admin() {

		if ( current_user_can( 'manage_options' ) ) {
			// user is admin
			return true;
		}

		return false;
	}
}

