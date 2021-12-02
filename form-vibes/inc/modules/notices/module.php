<?php

namespace FormVibes\Modules\Notices;

use FormVibes\Classes\Utils;

class Module {

	private static $instance = null;

	private static $show_notice = true;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		//delete_transient('fv_remind_later');
		add_filter( 'admin_footer_text', [ $this, 'admin_footer_text' ] );

		add_action( 'admin_print_scripts', [ $this, 'fv_disable_admin_notices' ] );
	}

	public function admin_footer_text( $footer_text ) {
		$screen = get_current_screen();
		// Todo:: Show on plugin screens
		$fv_screens = [
			'toplevel_page_fv-leads',
			'form-vibes_page_fv-analytics',
			'form-vibes_page_fv-db-settings',
			'form-vibes_page_fv-logs',
			'edit-fv_data_profile',
			'edit-fv_export_profile',
		];

		if ( in_array( $screen->id, $fv_screens, true ) ) {
			$footer_text = sprintf(
				/* translators: 1: Form Vibes, 2: Link to plugin review */
				__( 'Enjoyed %1$s? Please leave us a %2$s rating. We really appreciate your support!', 'wpv-fv' ),
				'<strong>' . __( 'Form Vibes', 'wpv-fv' ) . '</strong>',
				'<a href="https://wordpress.org/support/plugin/form-vibes/reviews/#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			);
		}

		return $footer_text;
	}

	public function fv_disable_admin_notices() {
		global $wp_filter;
		$screen     = get_current_screen();
		$fv_screens = [
			'toplevel_page_fv-leads',
			'form-vibes_page_fv-analytics',
			'form-vibes_page_fv-db-settings',
			'form-vibes_page_fv-logs',
		];

		if ( in_array( $screen->id, $fv_screens, true ) ) {
			if ( is_user_admin() ) {
				if ( isset( $wp_filter['user_admin_notices'] ) ) {
					unset( $wp_filter['user_admin_notices'] );
				}
			} elseif ( isset( $wp_filter['admin_notices'] ) ) {
				unset( $wp_filter['admin_notices'] );
			}
			if ( isset( $wp_filter['all_admin_notices'] ) ) {
				unset( $wp_filter['all_admin_notices'] );
			}
		}

		$this->fv_review_box();
		$this->fv_pro_purchase();
		add_action( 'admin_notices', [ $this, 'update_pro_from_05' ] );
		add_action( 'admin_notices', [ $this, 'show_disable_free_notice' ] );
		add_action( 'admin_notices', [ $this, 'fv_table_notice' ] );
	}

	// show notice to update pro from 0.5 to latest.
	public function update_pro_from_05() {

		if ( defined( 'WPV_PRO_FV_VERSION' ) ) {
			$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );
			if ( WPV_PRO_FV_VERSION === '0.5' && $is_pro_activated && wpv_fv()->can_use_premium_code__premium_only() ) {
				$updateUrl = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=form-vibes-pro/form-vibes-pro.php' ), 'update-plugin_form-vibes-pro' );
				?>
					<div class="notice notice-info is-dismissible">
						<p>
							It seems you have not updated <b>Form Vibes Pro</b> to the latest version.
							<a href="plugins.php" class="button-primary">Update Plugin</a>
						</p>
					</div>
				<?php
			}
		}
	}

	public function show_disable_free_notice() {
		if ( wpv_fv()->can_use_premium_code__premium_only() && file_exists( WPV_FV__PATH . 'inc/pro/bootstrap.php' ) ) {
				// check if free plugin is activated.
			if ( is_plugin_active( 'form-vibes/form-vibes.php' ) ) {
				Utils::show_disable_free_notice();
			}
		}
	}

	public function fv_review_box() {
		if ( isset( $_GET['remind_later'] ) || isset( $_GET['review_done'] ) ) {
			if ( isset( $_GET['fv_nonce'] ) && ! wp_verify_nonce( $_GET['fv_nonce'], 'wp_rest' ) ) {
				die( 'Sorry, your nonce did not verify!' );
			}

			if ( isset( $_GET['remind_later'] ) ) {
				add_action( 'admin_notices', [ $this, 'fv_remind_later' ] );

			} elseif ( isset( $_GET['review_done'] ) ) {
				add_action( 'admin_notices', [ $this, 'fv_review_done' ] );
			}
		}else {
				add_action( 'admin_notices', [ $this, 'fv_review' ] );
		}
	}

	public function fv_review() {
		$show_review = get_transient( 'fv_remind_later' );

		$review_status = get_option( 'fv-review' );

		if ( 'done' !== $review_status ) {
			if ( ( '' === $show_review || false === $show_review ) && self::$show_notice ) {
				global $wpdb;

				$rowcount       = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries" );
				$current_screen = get_current_screen();
				$page_id        = $current_screen->id;
				$fv_page_id_arr = [
					'toplevel_page_fv-leads',
					'form-vibes_page_fv-analytics',
					'edit-fv_export_profile',
					'edit-fv_data_profile',
					'form-vibes_page_fv-db-settings',
					'form-vibes_page_fv-logs',
				];
				$hide_logo      = '';
				if ( in_array( $page_id, $fv_page_id_arr, true ) ) {
					$hide_logo = 'fv-hide-logo';
				}
				if ( $rowcount > 9 ) {
					self::$show_notice = false;
					?>
					<div class="fv-review notice notice-success is-dismissible">
						<div class="fv-logo
					<?php

						echo esc_html( $hide_logo );
					?>
							"
						>
							<svg viewBox="0 0 1340 1340" version="1.1" width="3.5rem">
								<g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
									<g id="Artboard" transform="translate(-534.000000, -2416.000000)" fill-rule="nonzero">
										<g id="g2950" transform="translate(533.017848, 2415.845322)">
											<circle id="circle2932" fill="#FF6634" cx="670.8755" cy="670.048026" r="669.893348"></circle>
											<path d="M1151.33208,306.590013 L677.378555,1255.1191 C652.922932,1206.07005 596.398044,1092.25648 590.075594,1079.88578 L589.97149,1079.68286 L975.423414,306.590013 L1151.33208,306.590013 Z M589.883553,1079.51122 L589.97149,1079.68286 L589.940317,1079.74735 C589.355382,1078.52494 589.363884,1078.50163 589.883553,1079.51122 Z M847.757385,306.589865 L780.639908,441.206555 L447.47449,441.984865 L493.60549,534.507865 L755.139896,534.508386 L690.467151,664.221407 L558.27749,664.220865 L613.86395,775.707927 L526.108098,951.716924 L204.45949,306.589865 L847.757385,306.589865 Z" id="Combined-Shape" fill="#FFFFFF"></path>
										</g>
									</g>
								</g>
							</svg>
						</div>
						<div class="fv-review-content">
							<p class="fv-review-desc">
						<?php

						echo 'Form Vibes has already captured 10+ form submissions. That’s awesome! Could you please do a BIG favor and give it a 5-star rating on WordPress? <br/> Just to help us spread the word and boost our motivation. <br/><b>~ Anand Upadhyay</b>'
						?>
							</p>
							<span class="fv-notic-link-wrapper">
								<a class="fv-notice-link" target="_blank" href="https://wordpress.org/support/plugin/form-vibes/reviews/#new-post" class="button button-primary"><span class="dashicons dashicons-heart"></span><?php esc_html_e( 'Ok, you deserve it!', 'wpv-fv' ); ?></a>
								<a class="fv-notice-link" href="<?php echo
								esc_html(
								add_query_arg(
									[
										'remind_later' => 'later',
										'fv_nonce'		=> wp_create_nonce( 'wp_rest' )
									]
									)
									 ); ?>"><span class="dashicons dashicons-schedule"></span><?php esc_html_e( 'May Be Later', 'wpv-fv' ); ?></a>
								<a class="fv-notice-link" href="
							<?php
							echo esc_html(
								add_query_arg(
									[
										'review_done' => 'done',
										'fv_nonce'		=> wp_create_nonce( 'wp_rest' )
									]
									)
							);
							?>
							"><span class="dashicons dashicons-smiley"></span><?php esc_html_e( 'Already Done', 'wpv-fv' ); ?></a>
							</span>
						</div>
					</div>
						<?php
				}
			}
		}
	}

	public function fv_pro_purchase() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );
		if ( $is_pro_activated ) {
			return;
		}

		if ( isset( $_GET['fv_nonce'] ) && ! wp_verify_nonce( $_GET['fv_nonce'], 'wp_rest' ) ) {
			die( 'Sorry, your nonce did not verify!' );
		}

		if ( isset( $_GET['fv_pro_later'] ) ) {
			add_action( 'admin_notices', [ $this, 'fv_pro_later' ] );

		} elseif ( isset( $_GET['fv_pro_done'] ) ) {
			add_action( 'admin_notices', [ $this, 'fv_pro_done' ] );
		} else {
			add_action( 'admin_notices', [ $this, 'fv_pro_purchase' ] );
		}

		$check_review = get_option( 'fv_pro_purchase' );

		if ( ! $check_review ) {
			$review = [
				'installed' => current_time( 'yy/m/d' ),
				'status'    => '',
			];

			update_option( 'fv_pro_purchase', $review );
		}

		$check_review = get_option( 'fv_pro_purchase' );

		$start = $check_review['installed'];
		$end   = current_time( 'yy/m/d' );

		$days = $this->date_diff( $start, $end );

		if ( $days < 6 ) {
			return;
		}

		if ( ( '' === $check_review['status'] || 'remind_later' === $check_review['status'] ) && self::$show_notice ) {
			add_action( 'admin_notices', [ $this, 'fv_pro_purchase' ], 10 );
		}
	}

	public function date_diff( $start, $end ) {
		$start_time = strtotime( $start );
		$end_time   = strtotime( $end );
		$date_diff  = $end_time - $start_time;
		return round( $date_diff / 86400 );
	}

	public function fv_remind_later() {
		set_transient( 'fv_remind_later', 'show again', WEEK_IN_SECONDS );
	}

	public function fv_review_done() {
		update_option( 'fv-review', 'done', false );
	}

	public function fv_table_notice() {
		$screen = get_current_screen();

		if ( $screen->id === 'form-vibes_page_fv-db-settings' ) {
			global $wpdb;
			$table_exist = true;

			$settings   = get_option( 'fvSettings' );
			$debug_mode = false;
			if ( $settings && array_key_exists( 'debug_mode', $settings ) ) {
				$debug_mode = $settings['debug_mode'];
			}

			if ( ! $debug_mode ) {
				return;
			}

			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fv_enteries'" ) === null ) {
				$table_exist = false;
			}
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fv_entry_meta'" ) === null ) {
				$table_exist = false;
			}
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fv_logs'" ) === null ) {
				$table_exist = false;
			}

			if ( $table_exist ) {
				return;
			} else {
				?>
				<div class="fv-notice notice notice-error">
					<div class="fv-notice-content">
						<span>
						<?php esc_html_e( 'Database update required.', 'wpv-fv' ); ?>
						</span>
						<span class="fv-notice-action">
							<a href="<?php echo esc_html( add_query_arg( 'fv_db_update', 'yes' ) . add_query_arg( 'fv_nonce', wp_create_nonce( 'wp_rest' ) ) ); ?>"><?php esc_html_e( 'Click here!', 'wpv-fv' ); ?></a>
						</span>
					</div>
				</div>
				<?php
			}
		}
	}

}
