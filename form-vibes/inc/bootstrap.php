<?php

namespace FormVibes;

use  FormVibes\API\FV_JWT_Auth ;
use  FormVibes\Classes\Forms ;
use  FormVibes\Classes\Utils ;
use  FormVibes\Classes\Export ;
use  FormVibes\Classes\DbTables ;
use  FormVibes\Classes\Settings ;
use  FormVibes\Integrations\Cf7 ;
use  FormVibes\Classes\Capabilities ;
use  FormVibes\Classes\Permissions ;
use  FormVibes\Integrations\Caldera ;
use  FormVibes\Integrations\WpForms ;
use  FormVibes\Integrations\Elementor ;
use  FormVibes\Integrations\NinjaForms ;
use  FormVibes\Integrations\GravityForms ;
use  FormVibes\Integrations\BeaverBuilder ;
use  WP_Query ;

if ( !class_exists( 'FormVibes\\Plugin' ) ) {
    class Plugin
    {
        private static  $instance = null ;
        private  $current_tab = '' ;
        private static  $_forms = null ;
        private  $fv_title = 'Form Vibes' ;
        private static  $show_notice = true ;
        public static  $capabilities = null ;
        public static function instance()
        {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        private function __construct()
        {
            if ( wpv_fv()->can_use_premium_code__premium_only() && file_exists( WPV_FV__PATH . 'inc/pro/bootstrap.php' ) ) {
                // pro
                require_once WPV_FV__PATH . 'inc/pro/bootstrap.php';
            }
            add_action(
                'admin_enqueue_scripts',
                [ $this, 'admin_scripts' ],
                10,
                1
            );
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            // add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
            // add_action( 'wp_loaded', [ 'FormVibes\Classes\DbTables', 'fv_plugin_activated' ] );
            // add_action( 'plugins_loaded', [ 'FormVibes\Classes\DbTables', 'fv_plugin_activated' ] );
            add_filter( 'plugin_action_links_' . plugin_basename( WPV_FV__PATH . 'form-vibes.php' ), [ $this, 'settings_link' ], 10 );
            if ( !function_exists( 'is_plugin_active' ) ) {
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            if ( is_plugin_active( 'caldera-forms/caldera-core.php' ) ) {
                new Caldera();
            }
            if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
                new Cf7();
            }
            if ( is_plugin_active( 'elementor-pro/elementor-pro.php' ) || is_plugin_active( 'pro-elements/pro-elements.php' ) ) {
                new Elementor();
            }
            if ( is_plugin_active( 'bb-plugin/fl-builder.php' ) ) {
                new BeaverBuilder();
            }
            // check if ninja forms is activated.
            if ( is_plugin_active( 'ninja-forms/ninja-forms.php' ) ) {
                new NinjaForms();
            }
            // check if wp forms is activated.
            if ( is_plugin_active( 'wpforms-lite/wpforms.php' ) || is_plugin_active( 'wpforms/wpforms.php' ) ) {
                new WpForms();
            }
            // check if gravity forms is activated.
            if ( is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
                new GravityForms();
            }
            Settings::instance();
            add_action( 'admin_menu', [ $this, 'admin_menu' ], 9 );
            add_action( 'admin_menu', [ $this, 'admin_menu_after_pro' ] );
            add_filter(
                'plugin_row_meta',
                [ $this, 'plugin_row_meta' ],
                10,
                2
            );
            add_action( 'init', [ $this, 'fv_db_update' ] );
            $this->fv_title = apply_filters( 'formvibes/fv_title', 'Form Vibes' );
            self::$_forms = Forms::instance();
            new Export( '' );
            $this->load_modules();
            self::$capabilities = Capabilities::instance();
            add_filter( 'formvibes/global/settings', [ $this, 'set_table_size_limits' ] );
            // $jwt = new FV_JWT_Auth();
            // $jwt->run();
        }
        
        public function set_table_size_limits( $settings )
        {
            $settings['table_size_limits'] = Utils::get_table_size_limits();
            return $settings;
        }
        
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        // public function init_rest_api() {
        // die("hello");
        // $controllers = [
        // new \FormVibes\Api\AdminRest(),
        // ];
        // foreach ( $controllers as $controller ) {
        // $controller->register_routes();
        // }
        // }
        public function autoload( $class )
        {
            if ( 0 !== strpos( $class, __NAMESPACE__ ) ) {
                return;
            }
            
            if ( !class_exists( $class ) ) {
                $filename = strtolower( preg_replace( [
                    '/^' . __NAMESPACE__ . '\\\\/',
                    '/([a-z])([A-Z])/',
                    '/_/',
                    '/\\\\/'
                ], [
                    '',
                    '$1-$2',
                    '-',
                    DIRECTORY_SEPARATOR
                ], $class ) );
                $filename = WPV_FV__PATH . '/inc/' . $filename . '.php';
                if ( is_readable( $filename ) ) {
                    include $filename;
                }
            }
        
        }
        
        public function admin_scripts()
        {
            $screen = get_current_screen();
            wp_enqueue_style(
                'fv-style-css',
                WPV_FV__URL . 'assets/css/styles.css',
                [],
                WPV_FV__VERSION
            );
            wp_enqueue_script(
                'fv-js',
                WPV_FV__URL . 'assets/script/index.js',
                [],
                WPV_FV__VERSION,
                true
            );
            wp_localize_script( 'fv-js', 'fvGlobalVar', Utils::get_global_settings() );
            wp_enqueue_style( 'wp-components' );
            if ( 'form-vibes_page_fv-db-settings' === $screen->id ) {
                $this->load_settings_scripts();
            }
        }
        
        private function load_settings_scripts()
        {
            wp_enqueue_script(
                'setting-js',
                WPV_FV__URL . 'assets/dist/settings.js',
                [ 'wp-components' ],
                WPV_FV__VERSION,
                true
            );
            wp_enqueue_style(
                'setting-css',
                WPV_FV__URL . 'assets/dist/settings.css',
                '',
                WPV_FV__VERSION
            );
        }
        
        public function plugin_row_meta( $plugin_meta, $plugin_file )
        {
            
            if ( WPV_FV_PLUGIN_BASE === $plugin_file ) {
                $row_meta = [
                    'docs'    => '<a href="https://wpvibes.link/go/fv-all-docs-pp/" aria-label="' . esc_attr( __( 'View Documentation', 'wpv-fv' ) ) . '" target="_blank">' . __( 'Read Docs', 'wpv-fv' ) . '</a>',
                    'support' => '<a href="https://wpvibes.link/go/form-vibes-support/" aria-label="' . esc_attr( __( 'Support', 'wpv-fv' ) ) . '" target="_blank">' . __( 'Need Support', 'wpv-fv' ) . '</a>',
                ];
                $plugin_meta = array_merge( $plugin_meta, $row_meta );
            }
            
            return $plugin_meta;
        }
        
        public function admin_menu()
        {
        }
        
        public function admin_menu_after_pro()
        {
            $caps = self::$capabilities->get_caps();
            $this->cap_fv_view_logs = apply_filters( 'formvibes/cap/view_fv_logs', 'publish_posts' );
            add_submenu_page(
                'fv-leads',
                'Form Vibes Settings',
                'Settings',
                'manage_options',
                'fv-db-settings',
                [ $this, 'fv_db_settings' ],
                5
            );
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            // add_submenu_page( 'fv-leads', 'Form Vibes Settings', 'Settings', 'manage_options', 'fv-db-settings', [ $this, 'fv_db_settings' ], 5 );
        }
        
        public function update_pro_notice()
        {
            ?>
			<div class="fv-plugin-error error">
			<p>
				You are using an older version of <b>Form Vibes Pro.</b>
				Kindly <a href="plugins.php">update</a> to latest version.
			</p>
		</div>
			<?php 
        }
        
        private function check_capability( $cap )
        {
            
            if ( is_user_logged_in() ) {
                $user = wp_get_current_user();
                if ( !$user->has_cap( $cap ) ) {
                    return false;
                }
            }
            
            return true;
        }
        
        public function fv_db_settings()
        {
            ?>
				<div id="fv-settings-general"></div>
			<?php 
        }
        
        public function fv_db_settings1()
        {
            if ( isset( $_GET['fv_nonce'] ) && !wp_verify_nonce( $_GET['fv_nonce'], 'wp_rest' ) ) {
                die( 'Sorry, your nonce did not verify!' );
            }
            if ( isset( $_GET['tab'] ) ) {
                $this->current_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
            }
            $setting_pages = [
                'general' => __( 'General', 'wpv-fv' ),
            ];
            $setting_pages = apply_filters( 'formvibes/settings/pages', $setting_pages );
            ?>

			<div class="fv-settings-wrapper">

				<div class="fv-data-wrapper">
					<div class="fv-settings-content-wrapper">
						<nav aria-label="nav_links" class="fv-nav-tab-wrapper">
						<?php 
            foreach ( $setting_pages as $key => $label ) {
                ?>
								<a class="fv-nav-tab <?php 
                echo  ( '' === $this->current_tab && 'general' === $key || $key === $this->current_tab ? 'fv-tab-active' : '' ) ;
                ?>" href="admin.php?page=fv-db-settings&tab=<?php 
                echo  esc_html( $key ) ;
                ?>&fv_nonce=<?php 
                echo  esc_html( wp_create_nonce( 'wp_rest' ) ) ;
                ?>"><?php 
                echo  esc_html( $label ) ;
                ?></a>
								<?php 
            }
            ?>
						</nav>

						<div class="fv-settings-tab-content-wrapper">

						<?php 
            if ( '' === $this->current_tab || 'general' === $this->current_tab ) {
                ?>
								<div id="fv-settings-general"></div>
							<?php 
            }
            do_action( 'formvibes/settings/' . $this->current_tab );
            ?>

						</div>

					</div>
				</div>

			</div>

				<?php 
        }
        
        public function fv_render_controls()
        {
            ?>
			<div id="fv-render-controls" class="fv-render-controls-wrapper"></div>
			<?php 
        }
        
        public function get_nav_links()
        {
            $nav = [
                'toplevel_page_fv-leads'         => [
                'label'   => __( 'Submissions', 'wpv-fv' ),
                'link'    => admin_url( 'admin.php?page=fv-leads' ),
                'top_nav' => true,
            ],
                'form-vibes_page_fv-analytics'   => [
                'label'   => __( 'Analytics', 'wpv-fv' ),
                'link'    => admin_url( 'admin.php?page=fv-analytics' ),
                'top_nav' => true,
            ],
                'form-vibes_page_fv-db-settings' => [
                'label'   => __( 'Settings', 'wpv-fv' ),
                'link'    => admin_url( 'admin.php?page=fv-db-settings' ),
                'top_nav' => true,
            ],
                'get_support'                    => [
                'label'   => __( 'Get Support', 'wpv-fv' ),
                'link'    => 'https://wpvibes.link/go/form-vibes-support/',
                'top_nav' => true,
            ],
                'form-vibes_page_fv-logs'        => [
                'label'   => __( 'Event Log', 'wpv-fv' ),
                'link'    => admin_url( 'admin.php?page=fv-logs' ),
                'top_nav' => false,
            ],
            ];
            $nav = apply_filters( 'formvibes/nav_links', $nav );
            return $nav;
        }
        
        public function handle_pro()
        {
            wp_safe_redirect( 'https://go.elementor.com/docs-admin-menu/' );
            die;
        }
        
        public function fv_pro_purchase_box( $review )
        {
            if ( !self::$show_notice ) {
                return;
            }
            $review = get_option( 'fv_pro_purchase' );
            $remind_later = get_transient( 'fv_pro_remind_later' );
            $status = $review['status'];
            $current_screen = get_current_screen();
            $page_id = $current_screen->id;
            $fv_page_id_arr = [
                'toplevel_page_fv-leads',
                'form-vibes_page_fv-analytics',
                'edit-fv_export_profile',
                'edit-fv_data_profile',
                'form-vibes_page_fv-db-settings',
                'form-vibes_page_fv-logs'
            ];
            $hide_logo = '';
            if ( in_array( $page_id, $fv_page_id_arr, true ) ) {
                $hide_logo = 'fv-hide-logo';
            }
            if ( 'done' !== $status ) {
                
                if ( '' === $status && false === $remind_later ) {
                    ?>
					<div class="fv-pro-box notice notice-success">
						<div class="fv-logo <?php 
                    echo  esc_html( $hide_logo ) ;
                    ?>">
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
						<div class="fv-pro-content">
							<span class="fv-pro-content-message">
							<?php 
                    printf( 'Enjoying Form Vibes? Explore <b>%1$s</b> for more advanced features.', 'Form Vibes Pro' );
                    ?>

							</span>
							<span class="fv-go-pro-button">
								<a class="button button-primary " target="_blank" href="https://wpvibes.link/go/form-vibes-pro"><?php 
                    esc_html_e( 'Explore Pro!', 'wpv-fv' );
                    ?></a>

							</span>
							<a class="notice-dismiss" href="<?php 
                    echo  esc_html( add_query_arg( 'fv_pro_later', 'later' ) . add_query_arg( 'fv_nonce', wp_create_nonce( 'wp_rest' ) ) ) ;
                    ?>"></a>
						</div>
					</div>
					<?php 
                }
            
            }
        }
        
        public function fv_pro_later()
        {
            set_transient( 'fv_pro_remind_later', 'show again', MONTH_IN_SECONDS );
        }
        
        public function fv_pro_done()
        {
            $review = get_option( 'fv_pro_purchase' );
            $review['status'] = 'done';
            $review['purchased'] = current_time( 'yy/m/d' );
            update_option( 'fv_pro_purchase', $review, false );
        }
        
        public function settings_link( $links )
        {
            $url = admin_url( 'admin.php' ) . '?page=fv-db-settings';
            $settings_link = '<a class="fv-go-pro-menu" href=' . $url . '>Settings</a>';
            array_unshift( $links, $settings_link );
            if ( !function_exists( 'is_plugin_active' ) ) {
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $is_pro_activated = is_plugin_active( 'form-vibes-pro/form-vibes-pro.php' );
            
            if ( !$is_pro_activated ) {
                $mylinks = [ '<a class="fv-go-pro-menu" style="font-weight: bold; color : #93003c; text-shadow:1px 1px 1px #eee;" target="_blank" href="https://wpvibes.link/go/form-vibes-pro">Go Pro</a>' ];
                $links = array_merge( $links, $mylinks );
            }
            
            return $links;
        }
        
        public function fv_db_update()
        {
            if ( isset( $_GET['fv_nonce'] ) && !wp_verify_nonce( $_GET['fv_nonce'], 'wp_rest' ) ) {
                die( 'Sorry, your nonce did not verify!' );
            }
            if ( isset( $_GET['fv_db_update'] ) ) {
                DbTables::create_db_table();
            }
        }
        
        public function load_modules()
        {
            $modules = [
                'dashboard-widgets' => __( 'Dashboard Widgets', 'wpv-fv' ),
                'submissions'       => __( 'Submissions', 'wpv-fv' ),
                'analytics'         => __( 'Analytics', 'wpv-fv' ),
                'logs'              => __( 'Logs', 'wpv-fv' ),
            ];
            if ( Permissions::is_admin() ) {
                $modules['notices'] = __( 'Notices', 'wpv-fv' );
            }
            foreach ( $modules as $key => $val ) {
                $class_name = str_replace( '-', ' ', $key );
                $class_name = str_replace( ' ', '', ucwords( $class_name ) );
                $class_name = 'FormVibes\\Modules\\' . $class_name . '\\Module';
                $this->modules[$key] = $class_name::instance();
            }
        }
    
    }
    Plugin::instance();
} else {
}
