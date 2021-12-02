<?php
// phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date
namespace FormVibes\Classes;

use Carbon\Carbon;
use Stripe\Util\Util;
use FormVibes\Classes\Settings;

class Utils {

	public static function make_params( $params ) {
		$temp = [
			'query_type' => '',
			'per_page'   => '',
			'page_num'   => '',
			'fromDate'   => '',
			'toDate'     => '',
			'plugin'     => '',
			'formid'     => '',
		];

		return array_merge( $temp, $params );
	}

	public static function show_disable_free_notice( $is_outer = false ) {
		$deactivate_url = wp_nonce_url( self_admin_url( 'plugins.php?action=deactivate&plugin=form-vibes/form-vibes.php' ), 'deactivate-plugin_form-vibes/form-vibes.php' );
		$classnames = '';

		if ( $is_outer ) {
			$classnames = 'fv-notice-outer';
		}

		?>
			<div class="notice notice-info is-dismissible <?php echo $classnames ?>">
				<p>
				From <b>1.4.0</b> onwards free version of plugin is not required if you have Pro version activated!
				<a href="<?php echo esc_url( $deactivate_url ); ?>" class="button-primary">Deactivate Plugin</a>
				</p>
			</div>
		<?php
	}

	public static function get_fv_logo_svg() {
		return '<svg
								width="30px"
								height="30px"
								viewBox="0 0 1340 1340"
								version="1.1"
							>
								<g
									id="Page-1"
									stroke="none"
									strokeWidth="1"
									fill="none"
									fillRule="evenodd"
								>
									<g
										id="Artboard"
										transform="translate(-534.000000, -2416.000000)"
										fillRule="nonzero"
									>
										<g
											id="g2950"
											transform="translate(533.017848, 2415.845322)"
										>
											<circle
												id="circle2932"
												fill="#FF6634"
												cx="670.8755"
												cy="670.048026"
												r="669.893348"
											/>
											<path
												d="M1151.33208,306.590013 L677.378555,1255.1191 C652.922932,1206.07005 596.398044,1092.25648 590.075594,1079.88578 L589.97149,1079.68286 L975.423414,306.590013 L1151.33208,306.590013 Z M589.883553,1079.51122 L589.97149,1079.68286 L589.940317,1079.74735 C589.355382,1078.52494 589.363884,1078.50163 589.883553,1079.51122 Z M847.757385,306.589865 L780.639908,441.206555 L447.47449,441.984865 L493.60549,534.507865 L755.139896,534.508386 L690.467151,664.221407 L558.27749,664.220865 L613.86395,775.707927 L526.108098,951.716924 L204.45949,306.589865 L847.757385,306.589865 Z"
												id="Combined-Shape"
												fill="#FFFFFF"
											/>
										</g>
									</g>
								</g>
							</svg>';
	}

	public static function set_export_reason( $description ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'fv_logs',
			[
				'user_id'         => get_current_user_id(),
				'event'           => 'export',
				'description'     => sanitize_text_field( $description ),
				'export_time'     => current_time( 'mysql', 0 ),
				'export_time_gmt' => current_time( 'mysql', 1 ),
			]
		);
	}

	public static function dashes_to_camel_case( $string, $capitalize_first_character = true ) {
		$str = str_replace( '-', '', ucwords( $string, '-' ) );
		if ( ! $capitalize_first_character ) {
			$str = lcfirst( $str );
		}
		return $str;
	}

	public static function prepare_forms_data() {
		global $wpdb;
		$forms                = [];
		$data                 = [];
		$data['forms_plugin'] = apply_filters( 'fv_forms', $forms );

		$settings   = get_option( 'fvSettings' );
		$debug_mode = false;

		if ( $settings && array_key_exists( 'debug_mode', $settings ) ) {
			$debug_mode = $settings['debug_mode'];
		}

		$form_res = $wpdb->get_results( "select distinct form_id,form_plugin from {$wpdb->prefix}fv_enteries e", OBJECT_K );

		$inserted_forms = get_option( 'fv_forms' );

		$plugin_forms = [];

		foreach ( $data['forms_plugin'] as $key => $value ) {
			$res = [];

			if ( 'caldera' === $key ) {
				$class = '\FormVibes\Integrations\\' . ucfirst( $key );

				$res = $class::get_forms( $key );
			} else {
				foreach ( $form_res as $form_key => $form_value ) {

					if ( array_key_exists( $key, $inserted_forms ) && array_key_exists( $form_key, $inserted_forms[ $key ] ) ) {
						$name = $inserted_forms[ $key ][ $form_key ]['name'];
					} else {
						$name = $form_key;
					}
					if ( $form_res[ $form_key ]->form_plugin === $key ) {
						$res[ $form_key ] = [
							'id'   => $form_key,
							'name' => $name,
						];
					}
				}
			}

			if ( null !== $res ) {
				$plugin_forms[ $key ] = $res;
			}
		}

		// sort the forms as per their names
		foreach ( $plugin_forms as $f_key => $form ) {
			$forms_name = [];

			foreach ( $form as $key => $row ) {
				$forms_name[ $key ] = $row['name'];
			}

			array_multisort( $forms_name, SORT_ASC, $form );

			$plugin_forms[ $f_key ] = (object) $form;

		}

		return apply_filters( 'formvibes/all_forms', $plugin_forms );
	}

	public static function get_fv_keys() {
		$temp = get_option( 'fv-keys' );

		if ( '' === $temp || false === $temp ) {
			return [];
		}
		$fv_keys = [];
		foreach ( $temp as $key => $value ) {
			foreach ( $value as $val_key => $val_val ) {
				$val_val                             = (object) $val_val;
				$fv_keys[ $key ][ $val_val->colKey ] = $val_val;
			}
		}
		return $fv_keys;
	}

	public static function get_plugin_key_by_name( $name ) {
		if ( 'Contact Form 7' === $name ) {
			return 'cf7';
		} elseif ( 'Elementor Forms' === $name ) {
			return 'elementor';
		} elseif ( 'Beaver Builder' === $name ) {
			return 'beaverBuilder';
		} elseif ( 'WP Forms' === $name ) {
			return 'wp-forms';
		} elseif ( 'Caldera' === $name ) {
			return 'caldera';
		} elseif ( 'Ninja Forms' === $name ) {
			return 'Ninja-Forms';
		} elseif ( 'Gravity Forms' === $name ) {
			return 'gravity-forms';
		}

		return $name;
	}

	public static function get_plugin_name_by_key( $key ) {
		if ( 'cf7' === $key ) {
			return 'Contact Form 7';
		} elseif ( 'elementor' === $key ) {
			return 'Elementor Forms';
		} elseif ( 'beaverBuilder' === $key ) {
			return 'Beaver Builder';
		} elseif ( 'wp-forms' === $key ) {
			return 'WP Forms';
		} elseif ( 'caldera' === $key ) {
			return 'Caldera';
		} elseif ( 'Ninja-Forms' === $key ) {
			return 'Ninja Forms';
		} elseif ( 'gravity-forms' === $key ) {
			return 'Gravity Forms';
		}

		return $key;
	}

	public static function get_query_dates( $query_type, $param ) {

		$gmt_offset = get_option( 'gmt_offset' );
		$hours      = (int) $gmt_offset;
		$minutes    = ( $gmt_offset - floor( $gmt_offset ) ) * 60;

		if ( $hours >= 0 ) {
			$time_zone = '+' . $hours . ':' . $minutes;
		} else {
			$time_zone = $hours . ':' . $minutes;
		}

		if ( 'Custom' !== $query_type ) {
			$dates     = self::get_date_interval( $query_type, $time_zone );
			$from_date = $dates['fromDate'];
			$to_date   = $dates['endDate'];

			if ( $query_type === 'All_Time' ) {
				$from_date = new Carbon( '2019-05-29', $time_zone );
				$to_date   = Carbon::now( $time_zone );
			}
		} else {
			$tz        = new \DateTimeZone( $time_zone );
			$from_date = new \DateTime( $param['fromDate'] );
			$from_date->setTimezone( $tz );
			$to_date = new \DateTime( $param['toDate'] );
			$to_date->setTimezone( $tz );
		}

		return [ $from_date, $to_date ];
	}

	public static function is_array_associative( array $array ) {
		reset( $array );
		return ! is_int( key( $array ) );
	}

	public static function get_date_interval( $query_type, $time_zone ) {
		$dates = [];
		switch ( $query_type ) {
			case 'Today':
				$dates['fromDate'] = Carbon::now( $time_zone );
				$dates['endDate']  = Carbon::now( $time_zone );

				return $dates;

			case 'Yesterday':
				$dates['fromDate'] = Carbon::now( $time_zone )->subDay();
				$dates['endDate']  = Carbon::now( $time_zone )->subDay();

				return $dates;

			case 'Last_7_Days':
				$dates['fromDate'] = Carbon::now( $time_zone )->subDays( 6 );
				$dates['endDate']  = Carbon::now( $time_zone );

				return $dates;

			case 'This_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::MONDAY );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SUNDAY );
				} else {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::SUNDAY );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SATURDAY );
				}
				$dates['fromDate'] = $staticstart;
				$dates['endDate']  = $staticfinish;
				return $dates;

			case 'Last_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::MONDAY )->subDays( 7 );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SUNDAY )->subDays( 7 );
				} else {
					$staticstart  = Carbon::now( $time_zone )->startOfWeek( Carbon::SUNDAY )->subDays( 7 );
					$staticfinish = Carbon::now( $time_zone )->endOfWeek( Carbon::SATURDAY )->subDays( 7 );
				}

				$dates['fromDate'] = $staticstart;
				$dates['endDate']  = $staticfinish;

				return $dates;

			case 'Last_30_Days':
				$dates['fromDate'] = Carbon::now( $time_zone )->subDays( 29 );
				$dates['endDate']  = Carbon::now( $time_zone );

				return $dates;

			case 'This_Month':
				$dates['fromDate'] = Carbon::now( $time_zone )->startOfMonth();
				$dates['endDate']  = Carbon::now( $time_zone )->endOfMonth();

				return $dates;

			case 'Last_Month':
				$dates['fromDate'] = Carbon::now( $time_zone )->subMonth()->startOfMonth();
				$dates['endDate']  = Carbon::now( $time_zone )->subMonth()->endOfMonth();

				return $dates;

			case 'This_Quarter':
				$dates['fromDate'] = Carbon::now( $time_zone )->startOfQuarter();
				$dates['endDate']  = Carbon::now( $time_zone )->endOfQuarter();

				return $dates;

			case 'Last_Quarter':
				$dates['fromDate'] = Carbon::now( $time_zone )->subMonths( 3 )->startOfQuarter();
				$dates['endDate']  = Carbon::now( $time_zone )->subMonths( 3 )->endOfQuarter();

				return $dates;

			case 'This_Year':
				$dates['fromDate'] = Carbon::now( $time_zone )->startOfYear();
				$dates['endDate']  = Carbon::now( $time_zone )->endOfYear();

				return $dates;

			case 'Last_Year':
				$dates['fromDate'] = Carbon::now( $time_zone )->subMonths( 12 )->startOfYear();
				$dates['endDate']  = Carbon::now( $time_zone )->subMonths( 12 )->endOfYear();

				return $dates;
		}
	}
	public static function get_dates( $query_type ) {
		$dates = [];
		switch ( $query_type ) {
			case 'Today':
				$dates['fromDate'] = date( 'Y-m-d H:i:s' );
				$dates['endDate']  = date( 'Y-m-d H:i:s' );

				return $dates;

			case 'Yesterday':
				$dates['fromDate'] = date( 'Y-m-d H:i:s', strtotime( '-1 days' ) );
				$dates['endDate']  = date( 'Y-m-d H:i:s', strtotime( '-1 days' ) );

				return $dates;

			case 'Last_7_Days':
				$dates['fromDate'] = date( 'Y-m-d H:i:s', strtotime( '-6 days' ) );
				$dates['endDate']  = date( 'Y-m-d H:i:s' );

				return $dates;

			case 'This_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					if ( 'Mon' !== date( 'D' ) ) {
						$staticstart = date( 'Y-m-d', strtotime( 'last Monday' ) );
					} else {
						$staticstart = date( 'Y-m-d' );
					}

					if ( 'Sat' !== date( 'D' ) ) {
						$staticfinish = date( 'Y-m-d', strtotime( 'next Sunday' ) );
					} else {

						$staticfinish = date( 'Y-m-d' );
					}
				} else {
					if ( 'Sun' !== date( 'D' ) ) {
						$staticstart = date( 'Y-m-d', strtotime( 'last Sunday' ) );
					} else {
						$staticstart = date( 'Y-m-d' );
					}

					if ( 'Sat' !== date( 'D' ) ) {
						$staticfinish = date( 'Y-m-d', strtotime( 'next Saturday' ) );
					} else {

						$staticfinish = date( 'Y-m-d' );
					}
				}
				$dates['fromDate'] = $staticstart;
				$dates['endDate']  = $staticfinish;
				return $dates;

			case 'Last_Week':
				$start_week = get_option( 'start_of_week' );
				if ( 0 !== $start_week ) {
					$previous_week = strtotime( '-1 week +1 day' );
					$start_week    = strtotime( 'last monday midnight', $previous_week );
					$end_week      = strtotime( 'next sunday', $start_week );
				} else {
					$previous_week = strtotime( '-1 week +1 day' );
					$start_week    = strtotime( 'last sunday midnight', $previous_week );
					$end_week      = strtotime( 'next saturday', $start_week );
				}
				$start_week = date( 'Y-m-d', $start_week );
				$end_week   = date( 'Y-m-d', $end_week );

				$dates['fromDate'] = $start_week;
				$dates['endDate']  = $end_week;

				return $dates;

			case 'Last_30_Days':
				$dates['fromDate'] = date( 'Y-m-d h:m:s', strtotime( '-29 days' ) );
				$dates['endDate']  = date( 'Y-m-d h:m:s' );

				return $dates;

			case 'This_Month':
				$dates['fromDate'] = date( 'Y-m-01' );
				$dates['endDate']  = date( 'Y-m-t' );

				return $dates;

			case 'Last_Month':
				$dates['fromDate'] = date( 'Y-m-01', strtotime( 'first day of last month' ) );
				$dates['endDate']  = date( 'Y-m-t', strtotime( 'last day of last month' ) );

				return $dates;

			case 'This_Quarter':
				$current_month = date( 'm' );
				$current_year  = date( 'Y' );
				if ( $current_month >= 1 && $current_month <= 3 ) {
					$start_date = strtotime( '1-January-' . $current_year );  // timestamp or 1-Januray 12:00:00 AM
					$end_date   = strtotime( '31-March-' . $current_year );  // timestamp or 1-April 12:00:00 AM means end of 31 March
				} elseif ( $current_month >= 4 && $current_month <= 6 ) {
					$start_date = strtotime( '1-April-' . $current_year );  // timestamp or 1-April 12:00:00 AM
					$end_date   = strtotime( '30-June-' . $current_year );  // timestamp or 1-July 12:00:00 AM means end of 30 June
				} elseif ( $current_month >= 7 && $current_month <= 9 ) {
					$start_date = strtotime( '1-July-' . $current_year );  // timestamp or 1-July 12:00:00 AM
					$end_date   = strtotime( '30-September-' . $current_year );  // timestamp or 1-October 12:00:00 AM means end of 30 September
				} elseif ( $current_month >= 10 && $current_month <= 12 ) {
					$start_date = strtotime( '1-October-' . $current_year );  // timestamp or 1-October 12:00:00 AM
					$end_date   = strtotime( '31-December-' . ( $current_year ) );  // timestamp or 1-January Next year 12:00:00 AM means end of 31 December this year
				}

				$dates['fromDate'] = date( 'Y-m-d', $start_date );
				$dates['endDate']  = date( 'Y-m-d', $end_date );
				return $dates;

			case 'Last_Quarter':
				$current_month = date( 'm' );
				$current_year  = date( 'Y' );

				if ( $current_month >= 1 && $current_month <= 3 ) {
					$start_date = strtotime( '1-October-' . ( $current_year - 1 ) );  // timestamp or 1-October Last Year 12:00:00 AM
					$end_date   = strtotime( '31-December-' . ( $current_year - 1 ) );  // // timestamp or 1-January  12:00:00 AM means end of 31 December Last year
				} elseif ( $current_month >= 4 && $current_month <= 6 ) {
					$start_date = strtotime( '1-January-' . $current_year );  // timestamp or 1-Januray 12:00:00 AM
					$end_date   = strtotime( '31-March-' . $current_year );  // timestamp or 1-April 12:00:00 AM means end of 31 March
				} elseif ( $current_month >= 7 && $current_month <= 9 ) {
					$start_date = strtotime( '1-April-' . $current_year );  // timestamp or 1-April 12:00:00 AM
					$end_date   = strtotime( '30-June-' . $current_year );  // timestamp or 1-July 12:00:00 AM means end of 30 June
				} elseif ( $current_month >= 10 && $current_month <= 12 ) {
					$start_date = strtotime( '1-July-' . $current_year );  // timestamp or 1-July 12:00:00 AM
					$end_date   = strtotime( '30-September-' . $current_year );  // timestamp or 1-October 12:00:00 AM means end of 30 September
				}
				$dates['fromDate'] = date( 'Y-m-d', $start_date );
				$dates['endDate']  = date( 'Y-m-d', $end_date );
				return $dates;

			case 'This_Year':
				$dates['fromDate'] = date( 'Y-01-01' );
				$dates['endDate']  = date( 'Y-12-t' );

				return $dates;

			case 'Last_Year':
				$dates['fromDate'] = date( 'Y-01-01', strtotime( '-1 year' ) );
				$dates['endDate']  = date( 'Y-12-t', strtotime( '-1 year' ) );

				return $dates;
		}
	}

	public static function get_first_plugin_form() {
		$forms   = [];
		$plugins = apply_filters( 'fv_forms', $forms );

		$class = '\FormVibes\Integrations\\' . ucfirst( array_keys( $plugins )[0] );

		$plugin_forms = $class::get_forms( array_keys( $plugins )[0] );
		$plugin       = array_keys( $plugins )[0];

		$data = [
			'formName'       => $plugin_forms,
			'selectedPlugin' => $plugin,
			'selectedForm'   => array_keys( $plugin_forms )[0],
		];

		return $data;
	}

	public static function get_form_name_by_id( $id ) {
		$all_forms = self::prepare_forms_data()['allForms'];
		$form_name = '';
		foreach ( $all_forms as $key => $value ) {
			$options = $value['options'];
			foreach ( $options as $op_key => $op_value ) {
				// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				if ( $op_value['value'] == $id ) {
					$form_name = $op_value['formName'];
				}
			}
		}
		return $form_name;
	}

	private static function prepare_columns_for_all_forms( $original_columns,
	$saved_columns_option ) {

		$original_columns = array_unique( $original_columns );

		$columns     = [];
		$column_keys = [];

		// taking all saved column and saving into a variable.
		if ( $saved_columns_option && array_key_exists( 'fv__all_forms_fv__all_forms', $saved_columns_option ) ) {
			foreach ( $saved_columns_option as $values ) {
				foreach ( $values as $value ) {
					$col_key = $value->colKey;
					if ( in_array( $col_key, $original_columns, true ) && ! in_array( $col_key, $column_keys, true ) ) {
						$columns[ $col_key ] = $value;
					}
				}
			}

			$all_forms_cols = $saved_columns_option['fv__all_forms_fv__all_forms'];
			foreach ( $all_forms_cols as $values ) {
				$columns[ $values['colKey'] ] = $values;
			}
		}

		$cols = [];

		foreach ( $original_columns as $value ) {
			if ( array_key_exists( $value, $columns ) ) {
				$cols[ $value ] = $columns[ $value ];
			} else {
				$alias              = self::change_alias( $value );
					$cols[ $value ] = [
						'colKey'  => $value,
						'alias'   => $alias,
						'visible' => 1,
					];
			}
		}

		$columns = [];

		foreach ( $cols as $col ) {
			$columns[] = $col;
		}

		return $columns;
	}

	private static function form_columns_already_saved( $original_columns, $form_id, $plugin_name, $is_all_forms ) {
		$saved_columns_option = get_option( 'fv-keys' );

		if ( $is_all_forms ) {
			return self::prepare_columns_for_all_forms(
				$original_columns,
				$saved_columns_option
			);
		}

		$saved_columns_key = $plugin_name . '_' . $form_id;
		$settings          = Settings::instance();
		$save_ip           = $settings->get_setting_value_by_key( 'save_ip_address' );
		$save_ua           = $settings->get_setting_value_by_key( 'save_user_agent' );

		if ( $saved_columns_option && array_key_exists( $saved_columns_key, $saved_columns_option ) && $saved_columns_option[ $saved_columns_key ] ) {

			$saved_columns = $saved_columns_option[ $saved_columns_key ];

			foreach ( $original_columns as $column ) {
				$key   = array_search( $column, array_column( $saved_columns, 'colKey' ), true );
				$alias = $column;

				// if newly added column is not in the saved columns then we push it.
				if ( false === $key ) {
					$saved_columns[] = [
						'alias'   => $alias,
						'colKey'  => $column,
						'visible' => true,
					];
				}
			}

			$cols = [];

			foreach ( $saved_columns as $values ) {
				$values                    = (array) $values;
				$cols[ $values['colKey'] ] = $values;
			}

			// check this if we want to hide them if save user agent is set false from settings
			if ( ! $save_ip && false ) {
				unset( $cols['IP'] );
			}
			if ( ! $save_ua && false ) {
				unset( $cols['user_agent'] );
			}

			$cols = apply_filters( "formvibes/submissions/{$plugin_name}/columns", $cols, $original_columns, $form_id );

			return array_values( $cols );
		}
		return false;
	}

	public static function prepare_table_columns( $columns, $plugin_name, $form_id, $is_all_forms ) {

		// check if column value contains null,false or empty.
		$columns = array_filter(
			$columns,
			function( $column ) {
				return ( $column !== null && $column !== false && $column !== '' );
			}
		);

		// remove the fv-notes if exist from the columns because we don't want to show it in the table.
		$key = array_search( 'fv-notes', $columns, true );
		if ( ( $key ) !== false ) {
			unset( $columns[ $key ] );
		}

		$already_saved_columns_data = self::form_columns_already_saved( $columns, $form_id, $plugin_name, $is_all_forms );

		// if columns are saved in db.
		if ( $already_saved_columns_data ) {
			return $already_saved_columns_data;
		}

		$cols = [];

		// default columns
		foreach ( $columns as $column ) {

			$alias = self::change_alias( $column );

			$cols[ $column ] = [
				'colKey'  => $column,
				'alias'   => $alias,
				'visible' => true,
			];
		}

		$cols = apply_filters( "formvibes/submissions/{$plugin_name}/columns", $cols, $columns, $form_id );

		return array_values( $cols );
	}

	private static function change_alias( $key ) {
		$alias = $key;
		if ( $key === 'captured' || $key === 'datestamp' ) {
				$alias = 'Submission Date';
		}

		if ( $key === 'user_agent' ) {
			$alias = 'User Agent';
		}

		if ( $key === 'form_name' ) {
			$alias = 'Form Name';
		}
		if ( $key === 'form_plugin' ) {
			$alias = 'Plugin Name';
		}

		return $alias;
	}

	public static function admin_sidebar1() {
		echo ''
		?>
		<div class="fv-sidebar">
			<div class="fv-sidebar-wrapper">
				<div class="fv-sidebar-box" style="display: none">
					<div class="fv-sidebar-inner">
						<div class="fv-free-version">
							<h4 class="fv_title">Form Vibes:</h4>
							<span class="fv_version"><?php echo esc_html( WPV_FV__VERSION ); ?></span>
						</div>
					<?php
					$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );
					if ( $is_pro_activated ) {
						?>
							<div class="fv-pro-version">
								<h4>Form Vibes Pro:</h4>
								<span><?php echo esc_html( WPV_PRO_FV__VERSION ); ?></span>
							</div>
							<?php
					}
					?>
					</div>
				</div>
				<div class="fv-sidebar-box">
					<h3>Need Help?</h3>
					<div class="fv-sidebar-inner">
						<ul>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-getting-started/">Getting Started</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-view-submitted-data/">View Submitted Data</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-export-form-data-to-csv/">Export to CSV</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-data-analytics/">View Data Analytics</a></li>
							<li><a target="_blank" href="https://wpvibes.link/go/fv-add-dashboard-widget/">Add Dashboard Widgets</a></li>
						</ul><a target="_blank" href="https://wpvibes.link/go/fv-all-docs/"><b>View All Documentation <i class="dashicons dashicons-arrow-right"></i></b></a><br><a target="_blank" href="https://wpvibes.link/go/form-vibes-support/"><b>Get Support <i class="dashicons dashicons-arrow-right"></i></b></a>
					</div>
				</div>
			</div>

		</div>
			<?php
	}


	// FILTERS STARTS

	// submission status
	public static function get_fv_status() {
		$status = [
			[
				'key'       => 'read',
				'value'     => 'Read',
				'textColor' => '#065F46',
				'bgColor'   => '#D1FAE5',
			],
			[
				'key'       => 'unread',
				'value'     => 'Unread',
				'textColor' => '#1E40AF',
				'bgColor'   => '#DBEAFE',
			],
			[
				'key'       => 'spam',
				'value'     => 'Spam',
				'textColor' => '#991B1B',
				'bgColor'   => '#FEE2E2',
			],
		];

		return apply_filters( 'formvibes/submission/status', $status );
	}

	// submission filter operators

	public static function get_operators() {
		$operators = [
			[
				'key'      => 'equal',
				'value'    => 'Equal',
				'operator' => '=',
			],
			[
				'key'      => 'not_equal',
				'value'    => 'Not Equal',
				'operator' => '!=',
			],
			[
				'key'      => 'contain',
				'value'    => 'Contain',
				'operator' => 'LIKE',
			],
			[
				'key'      => 'not_contain',
				'value'    => 'Not Contain',
				'operator' => 'NOT LIKE',
			],
		];
		return apply_filters( 'formvibes/submission/filter/operators', $operators );
	}

	// public static function get_compare_value( $operator ) {
	// 	switch ( $operator ) {
	// 		case 'equal':
	// 			return '=';
	// 		case 'not_equal':
	// 			return '!=';
	// 		case 'contain':
	// 			return 'LIKE';
	// 		case 'not_contain':
	// 			return 'NOT LIKE';
	// 		default:
	// 			return '=';
	// 	}
	// }

	public static function check_operator_for_backward_compatibility( $operator ) {
		switch ( $operator ) {
			case 'equal':
				return '=';
			case 'not_equal':
				return '!=';
			case 'contain':
				return 'LIKE';
			case 'not_contain':
				return 'NOT LIKE';
			default:
				return $operator;
		}
	}

	// entry table columns
	public static function get_entry_table_fields() {
		$entry_table_fields = [
			'url',
			'user_agent',
			'fv_status',
			'captured',
			'form_id',
			'form_name',
			'form_plugin',
		];

		return apply_filters( 'formvibes/entry_table_fields', $entry_table_fields );
	}

	public static function get_table_size_limits() {
		$limits = [
			[
				'key'   => '5',
				'value' => 5,
			],
			[
				'key'   => '10',
				'value' => 10,
			],
			[
				'key'   => '15',
				'value' => 15,
			],
			[
				'key'   => '20',
				'value' => 20,
			],
			[
				'key'   => '30',
				'value' => 30,
			],
			[
				'key'   => '40',
				'value' => 40,
			],
			[
				'key'   => '50',
				'value' => 50,
			],
			[
				'key'   => '100',
				'value' => 100,
			],
		];
		return apply_filters( 'formvibes/submission/table/limits', $limits );
	}

	public static function get_global_settings() {
		$global_settings = [
                'ajax_url'                     => admin_url( 'admin-ajax.php' ),
                'rest_url'                     => get_rest_url(),
                'nonce'                        => wp_create_nonce( 'wp_rest' ),
                'ajax_nonce'                   => wp_create_nonce( 'fv_ajax_nonce' ),
                'forms'                        => Utils::prepare_forms_data(),
                'fv_dashboard_widget_settings' => get_option( 'fv_dashboard_widget_settings' ),
                'entry_table_fields'           => Utils::get_entry_table_fields(),
                'saved_columns'                => Utils::get_fv_keys(),
                'plugins'                      => apply_filters( 'fv_forms', [] ),
                'title'                        => 'Form Vibes',
                'version'                      => WPV_FV__VERSION,
                'logo'                         => Utils::get_fv_logo_svg(),
                'quick_export_limit'           => 1000,
            ];
            return apply_filters( 'formvibes/global/settings', $global_settings );
	}

	public static function is_pro() {
		$global_settings = Utils::get_global_settings();
		$is_pro = false;

		if( wpv_fv()->can_use_premium_code__premium_only() && array_key_exists('is_pro', $global_settings) && $global_settings['is_pro'] == 1 ) {
			$is_pro = true;
		}

		return $is_pro;
	}

	// FILTERS ENDS
}
