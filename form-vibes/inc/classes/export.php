<?php
// phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date
namespace FormVibes\Classes;

use FormVibes\Pro\Classes\Helper;
use FormVibes\Classes\Utils;
class Export {

	/**
	 * __construct
	 *
	 * @param  mixed $params $params A var.
	 * @return void
	 */
	public function __construct( $params ) {
		if ( '' !== $params ) {
			$this->export_to_csv( $params );
		}

		$uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'form-vibes';
		if ( ! file_exists( $uploads_dir ) ) {
			wp_mkdir_p( $uploads_dir );
		}
		add_action( 'init', [ $this, 'fv_export_csv' ] );
	}

	public function fv_export_csv() {

		if ( isset( $_POST['btnExport'] ) ) {

			if ( ! wp_verify_nonce( $_POST['fv_nonce'], 'fv_ajax_nonce' ) ) {
				die( 'Sorry, your nonce did not verify!' );
			}

			$params = (array) json_decode( stripslashes( $_REQUEST['fv_export_data'] ) );

			new Export( $params );
		}
	}

	private function export_to_csv( $params ) {

		$fv_settings = get_option( 'fvSettings' );

		if ( $fv_settings && array_key_exists( 'csv_export_reason', $fv_settings ) && $fv_settings['csv_export_reason'] ) {
			Utils::set_export_reason( $params['description'] );
		}

		$plugin  = lcfirst( $params['plugin'] );
		$form_id = $params['form_id'];
		$name    = $plugin . '-' . $form_id . '-' . date( 'Y/m/d' );
		$name    = apply_filters( 'formvibes/quickexport/filename', $name, $params );

		$params['data_return_type'] = [
			'with-column-keys',
		];

		$fv_query      = new FV_Query( $params );
		$res           = $fv_query->get_result();
		$data          = $res['data'];
		$columns_obj   = new FV_Columns( $params );
		$cols          = $columns_obj->get_columns()['columns'];
		$fv_status_arr = Utils::get_fv_status();
		$fv_status     = [];
		foreach ( $fv_status_arr as $value ) {
			$fv_status[ $value['key'] ] = $value['value'];
		}

		$columns = [];
		if ( Utils::is_pro() ) {
			foreach ( $cols as $col ) {
				if ( $col['visible'] === true) {
					$columns[] = $col['alias'];
				}
			}
		} else {
			foreach ( $cols as $col ) {
				$columns[] = $col['alias'];
			}
		}

		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $params['is_pro'] == 1 && $plugin != 'caldera' ) {
				$columns[] = 'Status';
		}

		/* Settings file headers */
		header( 'Pragma: public' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Cache-Control: private', false );
		header( 'Content-Type: text/csv;charset=utf-8' );
		header( 'Content-Disposition: attachment;filename=' . $name . '.csv' );

		$fp = fopen( 'php://output', 'w' );

		if ( isset( $data ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
			fwrite( $fp, "\xEF\xBB\xBF" );
			fputcsv( $fp, array_values( $columns ) );
			foreach ( $data as $values ) {
				$temp = [];
				foreach ( $cols as $col ) {
					if ( $col['visible'] ) {
						if ( array_key_exists( $col['colKey'], $values ) ) {
							$temp[ $col['colKey'] ] = stripslashes( $values[ $col['colKey'] ] );
						} else {
							$temp[ $col['colKey'] ] = '';
						}
					}
				}
				// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				if ( array_key_exists( 'fv_status', $values ) && $params['is_pro'] == 1 ) {
					$status_key = $values['fv_status'];
					if ( array_key_exists( $status_key, $fv_status ) ) {
						$temp['fv_status'] = $fv_status[ $status_key ];
					} else {
						$temp['fv_status'] = 'Unread';
					}
				}
				fputcsv( $fp, $temp, ',', '"' );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		fclose( $fp );

		$exported_data = ob_get_contents();
		die();
	}
}
