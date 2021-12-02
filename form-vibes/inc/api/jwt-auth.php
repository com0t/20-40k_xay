<?php

namespace FormVibes\API;

class FV_JWT_Auth {

	private $plugin_name;
	private $version;
	private $loader;


	public function __construct() {
		$this->plugin_name = 'fv';
		$this->version     = '1';
		$this->loader      = new FV_JWT_Auth_Loader();
		$this->define_public_hooks();
	}

	private function define_public_hooks() {
		$plugin_public = new FV_API( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'rest_api_init', $plugin_public, 'add_submissions_api_routes' );
		$this->loader->add_filter( 'rest_api_init', $plugin_public, 'add_cors_support' );
		$this->loader->add_filter( 'rest_pre_dispatch', $plugin_public, 'rest_pre_dispatch', 10, 2 );
		$this->loader->add_filter( 'determine_current_user', $plugin_public, 'determine_current_user', 10 );
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}
}
