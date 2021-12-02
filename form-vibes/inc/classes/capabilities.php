<?php

namespace FormVibes\Classes;

class Capabilities {

	private static $instance = null;

	private $caps = [];

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {

		$this->set_caps();
	}

	private function set_caps() {

		$caps = [
			'fv_leads'     => 'publish_posts',
			'fv_analytics' => 'publish_posts',
			'fv_view_logs' => 'publish_posts',
		];

		$this->caps = $caps;
	}

	public function get_caps() {

		$this->caps = apply_filters( 'formvibes/capabilities', $this->caps );
		return $this->caps;
	}

	public function get_cap( $cap_key ) {
		if ( isset( $this->caps[ $cap_key ] ) ) {
			return $this->caps[ $cap_key ];
		}
		return false;
	}

	public static function check( $cap ) {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( ! $user->has_cap( $cap ) ) {
				return false;
			}
		}
		return true;
	}
}
