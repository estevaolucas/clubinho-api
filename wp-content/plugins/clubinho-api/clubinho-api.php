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

function clubinho_init() {
  if (!class_exists( 'WP_REST_Controller')) {
    return;
  }

  require plugin_dir_path(__FILE__) . 'includes/class-clubinho-api-public.php';

  global $api;
  $api = new Clubinho_API_Public();
  $api->run();
}

add_action('init', 'clubinho_init', 0);

function clubinho_rest_api_init() {
  global $api;
  $api->register_routes();
}

add_action('rest_api_init', 'clubinho_rest_api_init', 0);

// register_activation_hook(__FILE__, function() {
//   require_once plugin_dir_path(__FILE__) . 'includes/class-clubinho-api-activator.php';
//   Clubinho_API_Activator::activate();
// });

// register_deactivation_hook(__FILE__, function() {
//   require_once plugin_dir_path(__FILE__) . 'includes/class-clubinho-api-deactivator.php';
//   Clubinho_API_Deactivator::deactivate();
// });
