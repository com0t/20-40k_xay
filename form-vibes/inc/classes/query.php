<?php
namespace FormVibes\Classes;

class FV_Query {

	private $entry_table_name      = '';
	private $entry_meta_table_name = '';
	private $entry_table_alias     = 'entry';
	private $submission_date_key   = 'captured';
	private $join_key              = 'data_id';
	private $result                = [
		'error'                  => false,
		'message'                => '',
		'data'                   => [],
		'total_submission_count' => '',
		'status'                 => '',
	];

	public function __construct( $query = '' ) {
		if ( ! empty( $query ) ) {
			$this->query( $query );
		}
	}

	public function get_result() {
		return $this->result;
	}

	private function query( $query ) {
		global $wpdb;
		$default_query_arr           = $this->init();
		$query_arr                   = wp_parse_args( $query, $default_query_arr );
		$this->entry_table_name      = "{$wpdb->prefix}fv_enteries";
		$this->entry_meta_table_name = "{$wpdb->prefix}fv_entry_meta";
		$this->plugin                = $query_arr['plugin'];
		$meta_key_key                = 'meta_key';
		$meta_value_key              = 'meta_value';

		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $this->plugin == 'caldera' ) {
			$this->entry_table_name      = "{$wpdb->prefix}cf_form_entries";
			$this->entry_meta_table_name = "{$wpdb->prefix}cf_form_entry_values";
			$this->join_key              = 'entry_id';
			$meta_key_key                = 'slug';
			$meta_value_key              = 'value';
		}

		if ( ! array_key_exists( 'form_id', $query_arr ) || ! array_key_exists( 'plugin', $query_arr ) || $this->plugin === '' || $query_arr['form_id'] === '' ) {
			$this->result['error']   = true;
			$this->result['message'] = 'Either form id or plugin missing!';
		} else {
			$entry_query_str             = $this->prepare_query( $query_arr );
			$total_entry_count_query_str = $this->prepare_total_entry_count_query( $query_arr );
			$entry_data                  = $wpdb->get_results( $entry_query_str, ARRAY_A );
			$entry_count                 = $wpdb->get_var( $total_entry_count_query_str );

			if ( count( $entry_data ) > 0 ) {
				$data_ids = [];
				$entries  = [];
				foreach ( $entry_data as $entry ) {
					array_push( $data_ids, $entry['id'] );
					$entries[ $entry['id'] ] = $entry;
				}

				$entry_meta_query = $this->prepare_entry_meta_query( $data_ids );
				$entry_meta_data  = $wpdb->get_results( $entry_meta_query, ARRAY_A );

				foreach ( $entry_meta_data as $meta ) {
					$entries[ $meta[ $this->join_key ] ][ $meta[ $meta_key_key ] ] = $meta[ $meta_value_key ];

					if ( $meta[ $meta_key_key ] === 'fv-notes' ) {
						$new_notes_data = $this->update_notes_meta_data( $meta[ $meta_value_key ] );
						// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
						$entries[ $meta[ $this->join_key ] ]['fv-notes'] = json_encode( $new_notes_data );
					}
				}

				$this->result['error']                  = false;
				$this->result['message']                = '';
				$this->result['data']                   = array_values( $entries );
				$this->result['total_submission_count'] = (int) $entry_count;
				$this->result['status']                 = 200;

				if ( array_key_exists( 'data_return_type', $query_arr ) && $query_arr['data_return_type'] === 'with-column-keys' ) {
					$this->result['data'] = $entries;
				}
			}
		}
	}

	private function update_notes_meta_data( $notes ) {
		$notes = json_decode( $notes );

		foreach ( $notes as $key => $note ) {
			$user_data       = get_userdata( $note->author_id )->data;
			$username        = $user_data->user_login;
			$current_user_id = get_current_user_id();
			$is_me           = true;
			if ( $current_user_id !== (int) $note->author_id ) {
				$is_me = false;
			}
			$notes[ $key ]->author_name = $username;
			$notes[ $key ]->is_me       = $is_me;
		}

		return $notes;
	}

	private function prepare_total_entry_count_query( $query_arr ) {
		return "SELECT COUNT(distinct(e1.{$this->join_key})) FROM {$this->entry_table_name} as entry " . implode( ' ', $this->prepare_joins( $query_arr ) ) . implode( ' AND ', $this->prepare_where( $query_arr ) );
	}

	private function prepare_entry_meta_query( $data_ids ) {
		$fields = [
			'meta_key',
			'meta_value',
			'data_id',
		];
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $this->plugin == 'caldera' ) {
			$fields = [
				'slug',
				'value',
				$this->join_key,
			];
		}

		$fields = implode( ',', $fields );
		$q[]    = "SELECT {$fields} FROM {$this->entry_meta_table_name} where {$this->join_key} IN (" . implode( ',', $data_ids ) . ')';
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $this->plugin != 'caldera' ) {
			$q[] = "AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
		}

		return implode( ' ', $q );
	}

	private function prepare_query( $query_arr ) {
		$cols = $this->get_entry_table_query_cols( $query_arr );
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $this->plugin == 'caldera' ) {
			$cols                      = [ 'datestamp', 'id', 'form_id', 'user_id', 'status' ];
			$this->submission_date_key = 'datestamp';
		}

		$cols[0] = $this->entry_table_alias . '.' . $cols[0];

		$entry_query_arr[] = ' SELECT distinct ' . implode( ', ' . $this->entry_table_alias . '.', $cols ) . " FROM {$this->entry_table_name} as {$this->entry_table_alias} ";

		// joins
		$joins[] = implode( ' ', $this->prepare_joins( $query_arr ) );

		// merge joins into entry array
		$entry_query_arr = array_merge( $entry_query_arr, $joins );

		// add where query into entry query array
		$entry_query_arr[] = implode( ' AND ', $this->prepare_where( $query_arr ) );

		// order by
		if ( array_key_exists( 'order_by', $query_arr ) && $query_arr['order_by'] !== '' ) {
			$entry_query_arr[] = " order by {$this->entry_table_alias}.{$this->submission_date_key} {$query_arr['order_by']} ";
		} else {
			$entry_query_arr[] = " order by {$this->entry_table_alias}.{$this->submission_date_key} desc ";
		}

		// limit
		if ( array_key_exists( 'limit', $query_arr ) && $query_arr['limit'] !== '' ) {
			$limit        = $query_arr['limit'];
			$current_page = $query_arr['current_page'];

			if ( $current_page > 1 ) {
				// offset
				$offset            = ( $current_page - 1 ) * $limit;
				$entry_query_arr[] = " LIMIT {$offset},{$limit}";
			} else {
				$entry_query_arr[] = " LIMIT {$limit} ";
			}
		} else {
			$entry_query_arr[] = ' LIMIT 20 ';
		}

		$entry_query_arr = apply_filters( 'fv_entry_query_arr', $entry_query_arr );

		// prepare query from array
		return implode( ' ', $entry_query_arr );
	}

	private function prepare_entry_query( $query_arr ) {
		$query_arr = $query_arr['entry_query'];
		$relation  = $query_arr['relation'];
		unset( $query_arr['relation'] );
		$vars = [];

		foreach ( $query_arr as $key => $values ) {
			$column  = $values['column'];
			$compare = $values['compare'];
			$value   = trim( $values['value'] );

			$vars[] = " {$this->entry_table_alias}.{$column} {$compare} '{$value}' ";
		}

		return implode( " {$relation} ", $vars );
	}

	private function prepare_meta_query( $query_arr ) {
		$entry_fields   = Utils::get_entry_table_fields();
		$meta_query_arr = (array) $query_arr['meta_query'];
		$relation       = $meta_query_arr['relation'];
		unset( $meta_query_arr['relation'] );
		$vars = [];

		if ( count( $meta_query_arr ) <= 0 ) {
			return '';
		}

		$meta_key_key   = 'meta_key';
		$meta_value_key = 'meta_value';
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $this->plugin == 'caldera' ) {
			$meta_key_key   = 'slug';
			$meta_value_key = 'value';
		}

		foreach ( $meta_query_arr as $key => $values ) {
			$values      = (array) $values;
			$table_alias = 'e';
			$meta_key    = $values['meta_key'];
			$meta_value  = trim( $values['meta_value'] );
			$compare     = $values['compare'];
			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			if ( 'OR' == $relation ) {
				$key = 0;
			}

			if ( in_array( $values['meta_key'], $entry_fields, true ) ) {
				$table_alias = 'entry';
			}

			if ( $table_alias === 'e' ) {
				$value  = $this->check_like_operator( $compare, $meta_value );
				$k      = $key + 1;
				if ( empty($meta_value) ) {
					$vars[] = " ( e{$k}.{$meta_key_key} = '{$meta_key}' AND e{$k}.{$meta_value_key} is null or e{$k}.{$meta_key_key} = '' )";
				} else {
					$vars[] = " ( e{$k}.{$meta_key_key} = '{$meta_key}' AND e{$k}.{$meta_value_key} {$compare} {$value} )";
				}
			} else {
				$value  = $this->check_like_operator( $compare, $meta_value );
				if ( empty($meta_value) ) {
					$vars[] = " ( entry.{$meta_key} is null or entry.{$meta_key} = '' )";
				} else {
					$vars[] = " ( entry.{$meta_key} {$compare} {$value}  )";
				}
			}
		}
		
		return implode( " {$relation} ", $vars );
	}

	private function check_like_operator( $operator, $value ) {
		if ( $operator === 'LIKE' || $operator === 'NOT LIKE' ) {
			return "'%{$value}%'";
		}
		return "'{$value}'";
	}

	private function prepare_joins( $query_arr ) {

		$joins[]                 = " INNER JOIN {$this->entry_meta_table_name} as e1 ON ({$this->entry_table_alias}.id = e1.{$this->join_key}) ";
		$query_arr['meta_query'] = (array) $query_arr['meta_query'];

		if ( array_key_exists( 'meta_query', $query_arr ) && $query_arr['meta_query'] !== '' && count( $query_arr['meta_query'] ) > 0 ) {
			$meta_query_arr = (array) $query_arr['meta_query'];
			$condition      = 'data_id';
			$relation       = $meta_query_arr['relation'];
			unset( $meta_query_arr['relation'] );

			if ( $relation !== 'OR' && count( $meta_query_arr ) > 0 ) {
				$joins = [];
				foreach ( $meta_query_arr as $key => $values ) {
					$joins[] = 'INNER JOIN ' . $this->entry_meta_table_name . ' as e' . ( $key + 1 ) . ' ON (entry.id = e' . ( $key + 1 ) . '.' . $condition . ' ) ';
				}
			}
		}

		return apply_filters( 'fv_entry_query_joins', $joins );
	}

	private function get_entry_table_query_cols( $query_arr ) {

		$cols = [
			'id',
			'fv_status',
			'url',
			'form_id',
			'form_plugin',
			'captured',
			'user_agent'
		];

		return apply_filters( 'fv_entry_table_query_cols', $cols );
	}

	private function prepare_where( $query_arr ) {
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $this->plugin == 'caldera' ) {
			$where[] = 'WHERE 1=1';
		} else {
			// form and plugin
			$where[] = " WHERE {$this->entry_table_alias}.form_plugin = '{$query_arr['plugin']}' AND {$this->entry_table_alias}.form_id = '{$query_arr['form_id']}' ";
		}

		// entry query query
		if ( array_key_exists( 'entry_query', $query_arr ) && count( $query_arr['entry_query'] ) > 0 ) {
			$where[] = ' ( ' . $this->prepare_entry_query( $query_arr ) . ' ) ';
		}

		$query_arr['meta_query'] = (array) $query_arr['meta_query'];

		// meta query
		if ( array_key_exists( 'meta_query', $query_arr ) && $query_arr['meta_query'] !== '' && count( $query_arr['meta_query'] ) > 0 ) {
			$meta_query = $this->prepare_meta_query( $query_arr );
			if ( $meta_query !== '' ) {
				$where[] = ' ( ' . $this->prepare_meta_query( $query_arr ) . ' ) ';
			}
		}

		// status
		if ( array_key_exists( 'status', $query_arr ) && count( $query_arr['status'] ) > 0 ) {
			// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			if ( in_array( 'unread', $query_arr['status'] ) ) {
				$query_arr['status'][] = 'undefined';
			}
			$where[] = "  fv_status IN ('" . implode( "', '", $query_arr['status'] ) . "') ";
		}

		// date
		if ( array_key_exists( 'from_date', $query_arr ) && array_key_exists( 'to_date', $query_arr ) && $query_arr['from_date'] !== '' && $query_arr['to_date'] !== '' ) {
			$where[] = "  DATE_FORMAT({$this->entry_table_alias}.{$this->submission_date_key},GET_FORMAT(DATE,'JIS')) >= '" . $query_arr['from_date'] . "' ";
			$where[] = "  DATE_FORMAT({$this->entry_table_alias}.{$this->submission_date_key},GET_FORMAT(DATE,'JIS')) <= '" . $query_arr['to_date'] . "' ";
		}

		return apply_filters( 'fv_entry_query_where', $where );
	}

	private function init() {
		$default_query_arr = [
			'limit'            => 20,
			'plugin'           => '',
			'form_id'          => '',
			'from_date'        => '',
			'to_date'          => '',
			'order_by'         => 'desc',
			'status'           => [],
			'entry_query'      => [],
			'meta_query'       => [],
			'current_page'     => 1,
			'meta_key_exclude' => [],
			'data_return_type' => 'array',
		];
		return apply_filters( 'fv_default_query_arr', $default_query_arr );
	}
}
