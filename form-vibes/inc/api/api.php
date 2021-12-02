<?php

namespace FormVibes\API;

/** Requiere the JWT library. */

use Exception;
use \Firebase\JWT\JWT;
use FormVibes\Classes\FV_Columns;
use FormVibes\Classes\FV_Query;
use FormVibes\Classes\Permissions;
use FormVibes\Classes\Utils;
use FormVibes\Pro\Modules\Notes\Module as Notes;
use WP_Error;

class FV_API {
	private $plugin_name;
	private $version;
	private $namespace;
	private $jwt_error             = null;
	private $entry_table_name      = '';
	private $entry_meta_table_name = '';


	public function __construct( $plugin_name, $version ) {
		global $wpdb;
		$this->plugin_name           = $plugin_name;
		$this->version               = $version;
		$this->namespace             = $this->plugin_name . '/v' . intval( $this->version );
		$this->entry_table_name      = $wpdb->prefix . 'fv_enteries';
		$this->entry_meta_table_name = $wpdb->prefix . 'fv_entry_meta';
	}

	public function add_submissions_api_routes() {
		// VERIFY

		register_rest_route(
			$this->namespace,
			'token',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'generate_token' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			$this->namespace,
			'token/validate',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'validate_token' ],
				'permission_callback' => '__return_true',
			]
		);

		// GET

		register_rest_route(
			$this->namespace,
			'submissions',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_submissions' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);
		register_rest_route(
			$this->namespace,
			'columns',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_columns' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);
		register_rest_route(
			$this->namespace,
			'forms',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_forms' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);
		register_rest_route(
			$this->namespace,
			'status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);
		register_rest_route(
			$this->namespace,
			'permissions',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_permissions' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);
		register_rest_route(
			$this->namespace,
			'site_details',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_site_details' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);

		// POST

		register_rest_route(
			$this->namespace,
			'submission/(?P<id>\d+)/note',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'add_note' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);

		// PUT

		register_rest_route(
			$this->namespace,
			'submission/(?P<id>\d+)',
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'update_submission' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);
		register_rest_route(
			$this->namespace,
			'submission/status',
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'update_status' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);
		register_rest_route(
			$this->namespace,
			'submission/(?P<id>\d+)/note/(?P<noteid>\d+)',
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'update_note' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);

		// DELETE

		register_rest_route(
			$this->namespace,
			'submissions',
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_submissions' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);
		register_rest_route(
			$this->namespace,
			'submissions/(?P<id>\d+)/notes',
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_notes' ],
				'permission_callback' => function () {
					return $this->validate_token();
				},
			]
		);
	}


	// GET
	public function get_submissions( $request ) {
		$limit        = $request['limit'] ?: 20;
		$form_id      = $request['form_id'];
		$plugin       = $request['plugin'];
		$from_date    = $request['from_date'] ?: '2019-05-29';
		$to_date      = $request['to_date'] ?: date( 'Y-m-d', time() );
		$current_page = $request['current_page'] ?: 1;

		$user_id = $request['user_id'];
		if ( ! $user_id ) {
			return $this->throw_bad_request_error( 'User ID is missing' );
		}

		if ( ! Permissions::check_permission( Permissions::$CAP_SUBMISSIONS, $user_id ) ) {
			return $this->throw_permission_error( 'You are not allowed to add notes' );
		}

		$params = [
			'limit'        => $limit,
			'form_id'      => $form_id,
			'plugin'       => $plugin,
			'from_date'    => $from_date,
			'to_date'      => $to_date,
			'current_page' => $current_page,
		];

		$fv_query = new FV_Query( $params );
		$result   = $fv_query->get_result();

		return $result;
	}

	public function get_site_details( $request ) {
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		$image          = wp_get_attachment_image_src( $custom_logo_id, 'full' );

		return [
			'site_name' => get_bloginfo( 'name' ),
			'logo_url'  => $image[0],
		];
	}

	public function get_columns( $request ) {
		$params['form_id'] = $request['form_id'];
		$params['plugin']  = $request['plugin'];

		if ( ! $params['form_id'] || ! $params['plugin'] ) {
			return $this->throw_bad_request_error( 'Form ID or Plugin missing' );
		}

		$columns_obj                = new FV_Columns( $params );
		$cols                       = $columns_obj->get_columns();
		$result['columns']          = $cols['columns'];
		$result['original_columns'] = $cols['original_columns'];

		return $result;
	}

	public function get_forms( $request ) {
		return Utils::prepare_forms_data();
	}

	public function get_permissions( $request ) {
		$user_id = $request['user_id'];

		if ( ! $user_id ) {
			return $this->throw_bad_request_error( 'User ID is missing' );
		}

		$permissions       = [];
		$permissions_names = Permissions::get_permissions();

		foreach ( $permissions_names as $name ) {
			$permissions[ $name ] = Permissions::check_permission( $name, $user_id );
		}

		return $permissions;
	}

	public function get_status( $request ) {
		return (object) Utils::get_fv_status();
	}

	// POST

	public function add_note( $request ) {
		global $wpdb;
		$body = (array) $request->get_params();

		$submission_id = ! array_key_exists( 'id', $body ) ? '' : $body['id'];
		if ( ! $submission_id ) {
			return $this->throw_bad_request_error( 'Submission id is missing' );
		}

		$author_id = ! array_key_exists( 'author_id', $body ) ? '' : $body['author_id'];
		if ( ! $author_id ) {
			return $this->throw_bad_request_error( 'Author id is missing' );
		}

		if ( ! Permissions::check_permission( Permissions::$CAP_ADD_NOTES, $author_id ) ) {
			return $this->throw_permission_error( 'You are not allowed to add notes' );
		}

		$note = ! array_key_exists( 'note', $body ) ? '' : $body['note'];
		if ( ! $note ) {
			return $this->throw_bad_request_error( 'Note can not be empty' );
		}

		$author_obj   = get_user_by( 'id', $author_id );
		$note_content = [
			[
				'note'        => $note,
				'author_id'   => $author_id,
				'date'        => current_time( 'mysql' ),
				'author_mail' => $author_obj->data->user_email,
			],
		];
		$data         = $note_content;
		$is_saved     = false;

		$get_qry_data = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$this->entry_meta_table_name} WHERE data_id = %s AND meta_key = 'fv-notes'", $submission_id ), OBJECT );

		if ( count( $get_qry_data ) <= 0 ) {
			// make a notes field if not available.
			$is_saved = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$this->entry_meta_table_name} (data_id,meta_key,meta_value) VALUES (%d,%s,%s)",
					$submission_id,
					'fv-notes',
					wp_json_encode( $data )
				)
			);
		} else {
			$json_data = $get_qry_data;
			$data      = (array) json_decode( $json_data[0]->meta_value );
			array_push( $data, $note_content[0] );

			$is_saved = $wpdb->update(
				$this->entry_meta_table_name,
				[ 'meta_value' => wp_json_encode( $data ) ],
				[
					'meta_key' => 'fv-notes',
					'data_id'  => $submission_id,
				]
			);
		}

		$note_content[0]['author_name'] = $author_obj->data->user_login;
		$note_content[0]['is_me']       = true;

		if ( $is_saved !== 0 ) {
			return [
				'is_error' => false,
				'message'  => 'The note has been saved successfully!',
				'data'     => $note_content[0],
			];
		}

		return [
			'is_error' => true,
			'message'  => 'Could not able to save note!',
			'notes'    => null,
		];
	}


	// PUT

	public function update_submission( $request ) {
		$body = (array) $request->get_params();

		$submission_id = ! array_key_exists( 'id', $body ) ? '' : $body['id'];
		if ( ! $submission_id ) {
			return $this->throw_bad_request_error( 'Submission id is missing' );
		}

		$user_id = ! array_key_exists( 'user_id', $body ) ? '' : $body['user_id'];
		if ( ! $user_id ) {
			return $this->throw_bad_request_error( 'User id is missing' );
		}

		if ( ! Permissions::check_permission( Permissions::$CAP_EDIT, $user_id ) ) {
			return $this->throw_permission_error( 'You are not allowed to edit the submission' );
		}

		global $wpdb;

		$column_name = ! array_key_exists( 'column_name', $body ) ? '' : $body['column_name'];
		if ( ! $column_name ) {
			return $this->throw_bad_request_error( 'Column name is missing' );
		}

		$value = ! array_key_exists( 'value', $body ) ? '' : $body['value'];
		if ( ! $value ) {
			return $this->throw_bad_request_error( 'Value is missing or can not be empty' );
		}

		$prev_value = ! array_key_exists( 'prev_value', $body ) ? '' : $body['prev_value'];
		if ( ! $prev_value ) {
			return $this->throw_bad_request_error( 'Previous value is missing or can not be empty' );
		}

		$form_id = ! array_key_exists( 'form_id', $body ) ? '' : $body['form_id'];
		$plugin  = ! array_key_exists( 'plugin', $body ) ? '' : $body['plugin'];
		if ( ! $form_id || ! $plugin ) {
			return $this->throw_bad_request_error( 'Form id or plugin is missing' );
		}

		$entry_table_fields = Utils::get_entry_table_fields();

		if ( $value === $prev_value ) {
			return $this->throw_bad_request_error( 'Both value can not be same' );
		}

		if ( in_array( $column_name, $entry_table_fields, true ) ) {
			$no_of_affected_rows = $wpdb->update(
				$this->entry_table_name,
				[ $column_name => $value ],
				[
					'form_id'     => $form_id,
					'form_plugin' => $plugin,
					'id'          => $submission_id,
				]
			);
		} else {
			$no_of_affected_rows = $wpdb->update(
				$this->entry_meta_table_name,
				[ 'meta_value' => $value ],
				[
					'meta_key'   => $column_name,
					'meta_value' => $prev_value,
					'data_id'    => $submission_id,
				]
			);
		}

		if ( $no_of_affected_rows === false ) {
			return $this->throw_server_error( 'Error white updating submission data' );
		}

		$logs_data = [
			'entry_id'   => $submission_id,
			'field_name' => $column_name,
			'value'      => [
				'prev_value' => $prev_value,
				'next_value' => $value,
			],
		];
		$this->set_edit_log( $logs_data );

		return [
			'no_of_affected_rows' => $no_of_affected_rows,
			'message'             => 'Entry Updated Successfully!',
		];
	}


	public function update_status( $request ) {
		$body = (array) $request->get_params();

		$submission_ids = ! array_key_exists( 'submissions_ids', $body ) ? '' : $body['submissions_ids'];
		if ( ! $submission_ids ) {
			return $this->throw_bad_request_error( 'Submissions id is missing' );
		}

		if ( count( $submission_ids ) > 100 ) {
			return $this->throw_bad_request_error( 'Length should be less than 100' );
		}

		$user_id = ! array_key_exists( 'user_id', $body ) ? '' : $body['user_id'];
		if ( ! $user_id ) {
			return $this->throw_bad_request_error( 'User id is missing' );
		}

		if ( ! Permissions::check_permission( Permissions::$CAP_CHANGE_STATUS, $user_id ) ) {
			return $this->throw_permission_error( 'You are not allowed to update the status' );
		}

		global $wpdb;

		$status = ! array_key_exists( 'status', $body ) ? '' : $body['status'];
		if ( ! $status ) {
			return $this->throw_bad_request_error( 'Status is missing' );
		}

		$is_saved = $wpdb->query(
			"UPDATE {$this->entry_table_name} SET fv_status = '{$status}' WHERE id IN (" .
					implode( ',', $submission_ids ) . ')'
		);

		if ( ! $is_saved ) {
			return $this->throw_server_error( 'Failed to update status' );

		}

		return [
			'message' => 'Status updated successfully!',
		];
	}

	public function update_note( $request ) {
		$body = (array) $request->get_params();
		global $wpdb;

		$submission_id = ! array_key_exists( 'id', $body ) ? '' : $body['id'];
		if ( ! $submission_id ) {
			return $this->throw_bad_request_error( 'Submission id is missing' );
		}

		$note_id = ! array_key_exists( 'noteid', $body ) ? '' : (int) $body['noteid'];
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( ! $note_id && $note_id != 0 ) {
			return $this->throw_bad_request_error( 'Note id is missing' );
		}

		$author_id = ! array_key_exists( 'author_id', $body ) ? '' : $body['author_id'];
		if ( ! $author_id ) {
			return $this->throw_bad_request_error( 'Author id is missing' );
		}

		if ( ! Permissions::check_permission( Permissions::$CAP_CHANGE_NOTES, $author_id ) ) {
			return $this->throw_permission_error( 'You are not allowed to update the notes' );
		}

		$note = $body['note'];
		$note = ! array_key_exists( 'note', $body ) ? '' : $body['note'];
		if ( ! $note ) {
			return $this->throw_bad_request_error( 'Note is either empty or missing' );
		}

		$note_content = [
			[
				'note'       => $note,
				'author_id'  => $author_id,
				'updated_at' => current_time( 'mysql' ),
			],
		];
		$data         = $note_content;
		$is_saved     = false;

		$json_data = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$this->entry_meta_table_name} WHERE data_id = %s AND meta_key = 'fv-notes'", $submission_id ), OBJECT );

		$data = (array) json_decode( $json_data[0]->meta_value );
		if ( ! $data || $note_id < 0 || $note_id >= count( $data ) ) {
			return $this->throw_bad_request_error( 'Note id not found' );
		}

		$updated_note = $data[ $note_id ];
		$updated_note = array_merge( (array) $data[ $note_id ], $note_content[0] );

		$data[ $note_id ] = $updated_note;

		$is_saved = $wpdb->update(
			$this->entry_meta_table_name,
			[ 'meta_value' => wp_json_encode( $data ) ],
			[
				'meta_key' => 'fv-notes',
				'data_id'  => $submission_id,
			]
		);

		if ( $is_saved ) {
			return [
				'message' => 'Note updated successfully!',
			];
		}

		return [
			'message' => 'Failed to update note',
		];
	}

	// DELETE
	public function delete_submissions( $request ) {
		$body = $request->get_params();

		$user_id = ! array_key_exists( 'user_id', $body ) ? '' : $body['user_id'];
		if ( ! $user_id ) {
			return $this->throw_bad_request_error( 'User id missing' );
		}

		if ( ! Permissions::check_permission( Permissions::$CAP_DELETE, $user_id ) ) {
			return $this->throw_permission_error( 'You are not allowed to delete submissions' );
		}

		global $wpdb;
		$submission_ids = ! array_key_exists( 'submissions_ids', $body ) ? '' : $body['submissions_ids'];
		if ( ! $submission_ids ) {
			return $this->throw_bad_request_error( 'Submissions id missing' );
		}

		$delete_row_query1 = "Delete from {$wpdb->prefix}fv_enteries where id IN (" . implode( ',', $submission_ids ) . ')';
		$delete_row_query2 = "Delete from {$wpdb->prefix}fv_entry_meta where data_id IN (" . implode( ',', $submission_ids ) . ')';

		$dl1 = $wpdb->query( $delete_row_query1 );

		$dl2 = $wpdb->query( $delete_row_query2 );

		if ( 0 === $dl1 || 0 === $dl2 ) {
			return [
				'message' => 'Could not able to delete Entries',
			];
		} else {
			return [
				'message' => 'Submissions Deleted',
			];
		}
	}

	public function delete_notes( $request ) {
		global $wpdb;
		$body = $request->get_params();

		$submission_id = ! array_key_exists( 'id', $body ) ? '' : $body['id'];
		if ( ! $submission_id ) {
			return rest_ensure_response( $this->throw_bad_request_error( 'Submission id is missing' ) );
		}

		$user_id = ! array_key_exists( 'user_id', $body ) ? '' : $body['user_id'];
		if ( ! $user_id ) {
			return rest_ensure_response( $this->throw_bad_request_error( 'User id is missing' ) );
		}

		if ( ! Permissions::check_permission( Permissions::$CAP_DELETE_NOTES, $user_id ) ) {
			return rest_ensure_response( $this->throw_permission_error( 'You are not allowed to delete notes' ) );
		}

		$note_ids = ! array_key_exists( 'note_ids', $body ) ? '' : $body['note_ids'];

		if ( ! $note_ids || count( $note_ids ) <= 0 ) {
			return rest_ensure_response( $this->throw_bad_request_error( 'Note id is missing' ) );
		}

		$is_saved = false;

		$json_data = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$this->entry_meta_table_name} WHERE data_id = %s AND meta_key = 'fv-notes'", $submission_id ), OBJECT );

		$data = (array) json_decode( $json_data[0]->meta_value );
		$t    = (array) json_decode( $json_data[0]->meta_value );

		$found_note_ids = [];

		foreach ( $note_ids as $note_id ) {
			if ( ! $data || $note_id < 0 || $note_id >= count( $data ) ) {
				continue;
			}

			$found_note_ids[] = $note_id;
		}

		$temp = $data;

		foreach ( $found_note_ids as $id ) {
			unset( $temp[ $id ] );
			$data = array_values( $temp );
		}

		$is_saved = $wpdb->update(
			$this->entry_meta_table_name,
			[ 'meta_value' => wp_json_encode( $data ) ],
			[
				'meta_key' => 'fv-notes',
				'data_id'  => $submission_id,
			]
		);

		if ( ! $is_saved ) {
			return rest_ensure_response(
				[
					'message' => 'Failed to delete note',
				]
			);
		}

		return rest_ensure_response(
			[
				'message' => 'Note deleted successfully',
			]
		);
	}

	// OTHERS
	private function set_edit_log( $logs_data ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'fv_logs',
			[
				'user_id'         => get_current_user_id(),
				'event'           => 'edit-data',
				'description'     => sanitize_text_field( wp_json_encode( $logs_data ) ),
				'export_time'     => current_time( 'mysql', 0 ),
				'export_time_gmt' => current_time( 'mysql', 1 ),
			]
		);
	}

	// AUTH
	public function add_cors_support() {
		$enable_cors = defined( 'FV_JWT_AUTH_CORS_ENABLE' ) ? FV_AUTH_CORS_ENABLE : false;
		if ( $enable_cors || true ) {
			$headers = apply_filters( 'jwt_auth_cors_allow_headers', 'Access-Control-Allow-Headers, Content-Type, Authorization' );
			header( sprintf( 'Access-Control-Allow-Headers: %s', $headers ) );
		}
	}

	public function generate_token( $request ) {

		$secret_key = defined( 'FV_JWT_AUTH_SECRET_KEY' ) ? FV_JWT_AUTH_SECRET_KEY : 'my-secret-key';
		$username   = $request->get_param( 'username' );
		$password   = $request->get_param( 'password' );

		/** First thing, check the secret key if not exist return a error*/
		if ( ! $secret_key ) {
			return $this->throw_forbidden_error( 'JWT is not configured properly, please contact the admin' );
		}
		/** Try to authenticate the user with the passed credentials*/
		$user = wp_authenticate( $username, $password );

		/** If the authentication fails return a error*/
		if ( is_wp_error( $user ) ) {
			$error_code = $user->get_error_code();
			return $this->throw_forbidden_error( 'Username or password wrong.' );
		}

		/** Valid credentials, the user exists create the according Token */
		$issuedAt  = time();
		$notBefore = apply_filters( 'fv_jwt_auth_not_before', $issuedAt, $issuedAt );
		$expire    = apply_filters( 'fv_jwt_auth_expire', $issuedAt + ( DAY_IN_SECONDS * 7 ), $issuedAt );

		$token = [
			'iss'  => get_bloginfo( 'url' ),
			'iat'  => $issuedAt,
			'nbf'  => $notBefore,
			'exp'  => $expire,
			'data' => [
				'user' => [
					'id' => $user->data->ID,
				],
			],
		];

		/** Let the user modify the token data before the sign. */
		$token = JWT::encode( apply_filters( 'fv_jwt_auth_token_before_sign', $token, $user ), $secret_key );

		/** The token is signed, now create the object with no sensible user data to the client*/
		$data = [
			'token'             => $token,
			'user_email'        => $user->data->user_email,
			'user_nicename'     => $user->data->user_nicename,
			'user_display_name' => $user->data->display_name,
			'id'                => $user->data->ID,
		];

		/** Let the user modify the data before send it back */
		return apply_filters( 'fv_jwt_auth_token_before_dispatch', $data, $user );
	}

	public function determine_current_user( $user ) {
		/**
		 * This hook only should run on the REST API requests to determine
		 * if the user in the Token (if any) is valid, for any other
		 * normal call ex. wp-admin/.* return the user.
		 *
		 * @since 1.2.3
		 */
		$rest_api_slug = rest_get_url_prefix();
		$valid_api_uri = strpos( $_SERVER['REQUEST_URI'], $rest_api_slug );
		if ( ! $valid_api_uri ) {
			return $user;
		}

		/*
		 * if the request URI is for validate the token don't do anything,
		 * this avoid double calls to the validate_token function.
		 */
		$validate_uri = strpos( $_SERVER['REQUEST_URI'], 'token/validate' );
		if ( $validate_uri > 0 ) {
			return $user;
		}

		$token = $this->validate_token( false );

		if ( is_wp_error( $token ) ) {
			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			if ( $token->get_error_code() != 'fv_jwt_auth_no_auth_header' ) {
				/** If there is a error, store it to show it after see rest_pre_dispatch */
				$this->jwt_error = $token;
				return $user;
			} else {
				return $user;
			}
		}
		/** Everything is ok, return the user ID stored in the token*/
		return $token->data->user->id;
	}

	public function validate_token( $output = true ) {
		/*
		 * Looking for the HTTP_AUTHORIZATION header, if not present just
		 * return the user.
		 */
		$auth = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : false;

		/* Double check for different auth header string (server dependent) */
		if ( ! $auth ) {
			$auth = isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : false;
		}

		if ( ! $auth ) {
			return new WP_Error(
				'fv_jwt_auth_no_auth_header',
				'Authorization header not found.',
				[
					'status' => 403,
				]
			);
		}

		/*
		 * The HTTP_AUTHORIZATION is present verify the format
		 * if the format is wrong return the user.
		 */
		list($token) = sscanf( $auth, 'Bearer %s' );
		if ( ! $token ) {
			return $this->throw_forbidden_error( 'Authorization header malformed.' );
		}

		/** Get the Secret Key */
		$secret_key = defined( 'FV_JWT_AUTH_SECRET_KEY' ) ? FV_JWT_AUTH_SECRET_KEY : 'my-secret-key';

		if ( ! $secret_key ) {
			return $this->throw_forbidden_error( 'JWT is not configured properly, please contact the admin.' );
		}

		/** Try to decode the token */
		try {
			$token = JWT::decode( $token, $secret_key, [ 'HS256' ] );
			/** The Token is decoded now validate the iss */
			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			if ( $token->iss != get_bloginfo( 'url' ) ) {
				/** The iss do not match, return error */
				return $this->throw_forbidden_error( 'The iss do not match with this server.' );
			}
			/** So far so good, validate the user id in the token */
			if ( ! isset( $token->data->user->id ) ) {
				/** No user id in the token, abort!! */
				return $this->throw_forbidden_error( 'User ID not found in the token.' );
			}
			/** Everything looks good return the decoded token if the $output is false */
			if ( ! $output ) {
				return $token;
			}
			/** If the output is true return an answer to the request to show it */
			return [
				'code' => 'fv_jwt_auth_valid_token',
				'data' => [
					'status' => 200,
				],
			];
		} catch ( Exception $e ) {
			/** Something is wrong trying to decode the token, send back the error */
			return $this->throw_forbidden_error( $e->getMessage() );
		}
	}

	public function rest_pre_dispatch( $request ) {
		if ( is_wp_error( $this->jwt_error ) ) {
			return $this->jwt_error;
		}
		return $request;
	}

	private function throw_bad_request_error( $message = null ) {
		return new WP_Error(
			'400',
			$message ?: '400 Error'
		);
	}

	private function throw_permission_error( $message = null ) {
		return new WP_Error(
			'401',
			$message ?: 'Unauthorized Error'
		);
	}

	private function throw_server_error( $message = null ) {
		return new WP_Error(
			'500',
			$message ?: 'Server Error'
		);
	}

	private function throw_forbidden_error( $message = null ) {
		return new WP_Error(
			'403',
			$message ?: 'Forbidden Error'
		);
	}
}
