<?php

namespace FormVibes\Modules\Analytics;

use FormVibes\Classes\Permissions;
use FormVibes\Plugin;
use FormVibes\Classes\Utils;
use FormVibes\Integrations\Base;
use FormVibes\Integrations\Caldera;

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
		add_action( 'wp_ajax_fv_get_analytics_data', [ $this, 'get_analytics_data' ], 10, 3 );
	}

	public function get_analytics_data() {
		if ( ! wp_verify_nonce( $_POST['ajaxNonce'], 'fv_ajax_nonce' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		if (Utils::is_pro() && ! Permissions::check_permission( Permissions::$CAP_ANALYTICS ) ) {
			die( 'Sorry, you are not allowed to do this action!' );
		}

		$params = (array) json_decode( stripslashes( sanitize_text_field( $_POST['params'] ) ) );

		$params = Utils::make_params( $params );
		$plugin = $params['plugin'];
		$data   = [];

		$dates              = Utils::get_query_dates( $params['query_type'], $params );
		$params['fromDate'] = $dates[0]->format( 'Y-m-d' );
		$params['toDate']   = $dates[1]->format( 'Y-m-d' );
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $plugin == 'caldera' ) {
			$caldera = new Caldera();
			$data    = $caldera->get_analytics( $params );
		} else {
			$data = $this->get_data( $params );
		}

		wp_send_json( $data );
	}

	private function get_data( $params ) {

		$filter_type = $params['filter_type'];
		$plugin_name = $params['plugin'];
		$from_date   = $params['fromDate'];
		$to_date     = $params['toDate'];
		$filter      = '';
		$formid      = $params['formid'];
		$label       = '';
		$query_param = '';
		$query_type  = $params['query_type'];

		if ( 'day' === $filter_type ) {
			$default_data = $this->get_dates_from_range( $from_date, $to_date );
			$filter       = '%j';
			$label        = "MAKEDATE(DATE_FORMAT(`captured`, '%Y'), DATE_FORMAT(`captured`, '%j'))";
		} elseif ( 'month' === $filter_type ) {
			$default_data = $this->get_month_range( $from_date, $to_date );
			$filter       = '%b';
			$label        = "concat(DATE_FORMAT(`captured`, '%b'),'(',DATE_FORMAT(`captured`, '%y'),')')";
		} else {
			$default_data = $this->get_date_range_ror_all_weeks( $from_date, $to_date );

			$start_week = get_option( 'start_of_week' );

			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			if ( 0 == $start_week ) {
				$filter      = '%U';
				$day_start   = 'Sunday';
				$week_number = '';
			} else {
				$filter      = '%u';
				$day_start   = 'Monday';
				$week_number = '';
			}
			$label = "STR_TO_DATE(CONCAT(DATE_FORMAT(`captured`, '%Y'),' ', DATE_FORMAT(`captured`, '" . $filter . "')" . $week_number . ",' ', '" . $day_start . "'), '%X %V %W')";
		}
		if ( '%b' === $filter ) {
			$orderby = '%m';
		} else {
			$orderby = $filter;
		}

		global $wpdb;
		$param_where = [];

		$param_where[] = "form_plugin='" . $plugin_name . "'";
		$param_where[] = "form_id='" . $formid . "'";

		if ( array_key_exists( 'is_all_forms', $params ) && $params['is_all_forms'] ) {
			$param_where = [];
		}

		if ( $query_type !== 'All_Time' ) {
			$param_where[] = "DATE_FORMAT(`captured`,GET_FORMAT(DATE,'JIS')) >= '" . $from_date . "'";
			$param_where[] = "DATE_FORMAT(`captured`,GET_FORMAT(DATE,'JIS')) <= '" . $to_date . "'";
		}

		if ( count( $param_where ) > 0 ) {
			$query_param = ' Where ' . implode( ' and ', $param_where );
		}

		$data_query = 'SELECT ' . $label . " as Label,CONCAT(DATE_FORMAT(`captured`, '" . $filter . "'),'(',DATE_FORMAT(`captured`, '%y'),')') as week, count(*) as count,CONCAT(DATE_FORMAT(`captured`, '%y'),'-',DATE_FORMAT(`captured`, '" . $orderby . "')) as ordering from {$wpdb->prefix}fv_enteries " . $query_param . " GROUP BY DATE_FORMAT(`captured`, '" . $orderby . "'),ordering ORDER BY ordering";
		$res        = [];

		$res['data'] = $wpdb->get_results( $data_query, OBJECT_K );

		if ( count( (array) $res['data'] ) > 0 ) {
			$key = array_keys( $res['data'] )[0];
			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			if ( null == $res['data'][ $key ]->Label || '' == $res['data'][ $key ]->Label ) {
				$abc                                   = [];
				$abc[ array_keys( $default_data )[0] ] = (object) $res['data'][''];
				$res['data']                           = $abc + $res['data'];
				$res['data'][ array_keys( $default_data )[0] ]->Label = array_keys( $default_data )[0];
				unset( $res['data'][''] );
			}
		}

		$data = array_replace( $default_data, $res['data'] );

		if ( array_key_exists( 'dashboard_data', $params ) && $params['dashboard_data'] ) {
			$dashboard_data         = $this->prepare_data_for_dashboard_widget( $params, $res );
			$data['dashboard_data'] = $dashboard_data;
		}
		return $data;
	}

	private function prepare_data_for_dashboard_widget( $params, $res ) {
		$all_forms      = [];
		$dashboard_data = [];
		$count          = count( $params['allForms'] );

		for ( $i = 0; $i < $count; ++$i ) {
			$plugin       = $params['allForms'][ $i ]->label;
			$option_count = count( $params['allForms'][ $i ]->options );
			for ( $j = 0; $j < $option_count; ++$j ) {
				$id               = $params['allForms'][ $i ]->options[ $j ]->value;
				$form_name        = $params['allForms'][ $i ]->options[ $j ]->label;
				$all_forms[ $id ] = [
					'id'       => $id,
					'plugin'   => $plugin,
					'formName' => $form_name,
				];
			}
		}
		if ( 'Last_7_Days' === $params['query_type'] || 'This_Week' === $params['query_type'] ) {
			$pre_from_date = date( 'Y-m-d', strtotime( $params['fromDate'] . '-7 days' ) );
			$pre_to_date   = date( 'Y-m-d', strtotime( $params['fromDate'] . '-1 days' ) );
		} elseif ( 'Last_30_Days' === $params['query_type'] ) {
			$pre_from_date = date( 'Y-m-d', strtotime( $params['fromDate'] . '-30 days' ) );
			$pre_to_date   = date( 'Y-m-d', strtotime( $params['fromDate'] . '-1 days' ) );
		} else {
			$pre_from_date = date( 'Y-m-01', strtotime( 'first day of last month' ) );
			$pre_to_date   = date( 'Y-m-t', strtotime( 'last day of last month' ) );
		}
		global $wpdb;
		$pre_param  = " where form_id='" . $params['formid'] . "' and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $pre_from_date . "'";
		$pre_param .= " and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $pre_to_date . "'";
		$qry        = "SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries " . $pre_param;

		$pre_data_count = $wpdb->get_var( $qry );
		foreach ( $all_forms as $form_key => $form_value ) {
			if ( 'Caldera' === $form_value['plugin'] || 'caldera' === $form_value['plugin'] ) {
				$param  = " where form_id='" . $form_key . "' and DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) >= '" . $params['fromDate'] . "'";
				$param .= " and DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
				$qry    = "SELECT COUNT(*) FROM {$wpdb->prefix}cf_form_entries " . $param;

				$data_count = $wpdb->get_var( $qry );

				$dashboard_data['allFormsDataCount'][ $form_key ] = [
					'plugin'   => $form_value['plugin'],
					'count'    => $data_count,
					'formName' => $form_value['formName'],
				];
			} else {
				$param  = " where form_id='" . $form_key . "' and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $params['fromDate'] . "'";
				$param .= " and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
				$qry    = "SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries " . $param;

				$data_count = $wpdb->get_var( $qry );

				$dashboard_data['allFormsDataCount'][ $form_key ] = [
					'plugin'   => $form_value['plugin'],
					'count'    => $data_count,
					'formName' => $form_value['formName'],
				];
			}
		}
		$total_entries = 0;

		foreach ( $res['data'] as $key => $val ) {
			$total_entries += $val->count;
		}

		$dashboard_widget_setting               = [];
		$dashboard_widget_setting['query_type'] = $params['query_type'];
		$dashboard_widget_setting['plugin']     = $params['plugin'];
		$dashboard_widget_setting['formid']     = $params['formid'];
		update_option( 'fv_dashboard_widget_settings', $dashboard_widget_setting );
		$dashboard_data['totalEntries']               = $total_entries;
		$dashboard_data['previousDateRangeDataCount'] = (int) $pre_data_count;
		return $dashboard_data;
	}

	private function get_month_range( $start_date, $end_date ) {
		if ( '' === $start_date && '' === $end_date ) {
			return [];
		}
		$start = new \DateTime( $start_date );
		$start->modify( 'first day of this month' );
		$end = new \DateTime( $end_date );
		$end->modify( 'first day of next month' );
		$interval = \DateInterval::createFromDateString( '1 month' );
		$period   = new \DatePeriod( $start, $interval, $end );

		$months = [];
		foreach ( $period as $dt ) {
			$months[ $dt->format( 'M' ) . '(' . $dt->format( 'y' ) . ')' ] = (object) [
				'Label'    => $dt->format( 'M' ) . '(' . $dt->format( 'y' ) . ')',
				'week'     => '',
				'count'    => 0,
				'ordering' => '',
			];
		}

		return $months;
	}
	private function get_date_range_ror_all_weeks( $start, $end ) {
		if ( '' === $start && '' === $end ) {
			return [];
		}
		$fweek = $this->get_date_range_for_week( $start );
		$lweek = $this->get_date_range_for_week( $end );

		$week_dates = [];

		$start_week = get_option( 'start_of_week' );
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( 0 == $start_week ) {
			while ( $fweek['saturday'] < $lweek['saturday'] ) {
				$week_dates[ $fweek['sunday'] ] = (object) [
					'Label'    => $fweek['sunday'],
					'week'     => '',
					'count'    => 0,
					'ordering' => '',
				];

				$date = new \DateTime( $fweek['saturday'] );
				$date->modify( 'next day' );

				$fweek = $this->get_date_range_for_week( $date->format( 'Y-m-d' ) );
			}
			$week_dates[ $lweek['sunday'] ] = (object) [
				'Label'    => $lweek['sunday'],
				'week'     => '',
				'count'    => 0,
				'ordering' => '',
			];
		} else {
			while ( $fweek['sunday'] < $lweek['sunday'] ) {
				$week_dates[ $fweek['monday'] ] = (object) [
					'Label'    => $fweek['monday'],
					'week'     => '',
					'count'    => 0,
					'ordering' => '',
				];

				$date = new \DateTime( $fweek['sunday'] );
				$date->modify( 'next day' );

				$fweek = $this->get_date_range_for_week( $date->format( 'Y-m-d' ) );
			}
			$week_dates[ $lweek['monday'] ] = (object) [
				'Label'    => $lweek['monday'],
				'week'     => '',
				'count'    => 0,
				'ordering' => '',
			];
		}

		return $week_dates;
	}

	private function get_date_range_for_week( $date ) {
		$date_time = new \DateTime( $date );

		$start_week = get_option( 'start_of_week' );

		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( 0 == $start_week ) {
			if ( 'Sunday' === $date_time->format( 'l' ) ) {
				$sunday = date( 'Y-m-d', strtotime( $date ) );
			} else {
				$sunday = date( 'Y-m-d', strtotime( 'last sunday', strtotime( $date ) ) );
			}

			$saturday = 'Saturday' === $date_time->format( 'l' ) ? date( 'Y-m-d', strtotime( $date ) ) : date( 'Y-m-d', strtotime( 'next saturday', strtotime( $date ) ) );

			return [
				'sunday'   => $sunday,
				'saturday' => $saturday,
			];
		} else {
			if ( 'Monday' === $date_time->format( 'l' ) ) {
				$monday = date( 'Y-m-d', strtotime( $date ) );
			} else {
				$monday = date( 'Y-m-d', strtotime( 'last monday', strtotime( $date ) ) );
			}

			$sunday = 'Sunday' === $date_time->format( 'l' ) ? date( 'Y-m-d', strtotime( $date ) ) : date( 'Y-m-d', strtotime( 'next sunday', strtotime( $date ) ) );

			return [
				'monday' => $monday,
				'sunday' => $sunday,
			];
		}
	}

	private function get_dates_from_range( $start, $end, $format = 'Y-m-d' ) {

		$date_1 = $start;
		$date_2 = $end;
		$array  = [];

		if ( '' === $date_1 && '' === $date_2 ) {
			return [];
		}
		// Use strtotime function
		$variable_1 = strtotime( $date_1 );
		$variable_2 = strtotime( $date_2 );

		// Use for loop to store dates into array
		// 86400 sec = 24 hrs = 60*60*24 = 1 day
		for (
			$current_date = $variable_1;
			$current_date <= $variable_2;
			$current_date += ( 86400 )
		) {

			$store = date( 'Y-m-d', $current_date );

			$array[ $store ] = (object) [
				'Label'    => $store,
				'week'     => ( date( 'z', $current_date ) + 1 ) . '(' . date( 'y', $current_date ) . ')',
				'count'    => 0,
				'ordering' => date( 'y', $current_date ) . '-' . ( date( 'z', $current_date ) + 1 ),
			];
		}
		$array[] = new \stdClass();
		unset( $array[0] );
		return $array;
	}

	public function admin_scripts() {

		$screen = get_current_screen();

		if ( 'form-vibes_page_fv-analytics' === $screen->id ) {
			wp_enqueue_script( 'analytics-js', WPV_FV__URL . 'assets/dist/analytics.js', [ 'wp-components' ], WPV_FV__VERSION, true );
			wp_enqueue_style( 'analytics-css', WPV_FV__URL . 'assets/dist/analytics.css', '', WPV_FV__VERSION );
		}
	}

	public function admin_menu() {

		$caps = Plugin::$capabilities->get_caps();

		add_submenu_page( 'fv-leads', 'Form Vibes Analytics', 'Analytics', $caps['fv_analytics'], 'fv-analytics', [ $this, 'render_root' ], 2 );
	}

	public function render_root() {

		$caps = Plugin::$capabilities->get_caps();

		if ( ! Plugin::$capabilities->check( $caps['fv_analytics'] ) ) {
			return;
		}

		?>
		<div id="fv-analytics" class="fv-analytics"></div>
		<?php
	}
}
