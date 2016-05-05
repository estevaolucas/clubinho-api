<?php

/**
 * @wordpress-plugin
 * Plugin Name:       API REST Clubinho
 * Description:       Esse plugin cri os post-types e endpoints necessários para a API
 * Version:           1.0.0
 * Author:            Estevão Lucas
 * Text Domain:       clubinho-api
 * Domain Path:       /languages
 */

// if this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

$api = null;

add_filter( 'rest_url_prefix', function( $prefix ) { return 'api'; } );


add_action('init', function() {
  if (!class_exists( 'WP_REST_Controller')) {
    return;
  }

  require plugin_dir_path(__FILE__) . 'includes/class-clubinho-api-public.php';

  global $api;
  $api = new Clubinho_API_Public();
  $api->run();
}, 0);

add_action('rest_api_init', function() {
  global $api;
  $api->register_routes();
}, 30);

register_activation_hook(__FILE__, function() {
  require_once plugin_dir_path(__FILE__) . 'includes/class-clubinho-api-activator.php';
  Clubinho_API_Activator::activate();
});

register_deactivation_hook(__FILE__, function() {
  require_once plugin_dir_path(__FILE__) . 'includes/class-clubinho-api-deactivator.php';
  Clubinho_API_Deactivator::deactivate();
});
