<?php
namespace FormVibes\Classes;

class FV_Columns {
	private $columns = [
		'columns'          => [],
		'original_columns' => [],
	];

	public function __construct( $params = '' ) {
		if ( ! empty( $params ) ) {
			$this->columns( $params );
		}
	}

	private function columns( $params ) {
		global $wpdb;
		$distinct_cols_query = "select distinct BINARY(meta_key) from {$wpdb->prefix}fv_entry_meta em join {$wpdb->prefix}fv_enteries e on em.data_id=e.id where form_id='" . $params['form_id'] . "' AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $params['plugin'] == 'caldera' ) {
			$distinct_cols_query = "select distinct BINARY(slug) from {$wpdb->prefix}cf_form_entry_values em join {$wpdb->prefix}cf_form_entries e on em.entry_id=e.id AND e.form_id ='" . $params['form_id'] . "'";
		}

		$columns = $wpdb->get_col( $distinct_cols_query );
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $params['plugin'] != 'caldera' ) {
			array_push( $columns, 'captured' );
			array_push( $columns, 'url' );
			array_push( $columns, 'user_agent' );
		} else {
			array_push( $columns, 'datestamp' );
		}

		$original_columns = $columns;
		$columns          = Utils::prepare_table_columns( $columns, $params['plugin'], $params['form_id'], false );

		$this->columns['columns']          = $columns;
		$this->columns['original_columns'] = $original_columns;
	}

	public function get_columns() {
		return $this->columns;
	}
}
