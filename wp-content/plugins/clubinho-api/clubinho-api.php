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

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-clubinho-api-activator.php
 */
function activate_clubinho_api() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-clubinho-api-activator.php';
	Clubinho_API_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-clubinho-api-deactivator.php
 */
function deactivate_clubinho_api() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-clubinho-api-deactivator.php';
	Clubinho_API_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_clubinho_api' );
register_deactivation_hook( __FILE__, 'deactivate_clubinho_api' );

add_action( 'rest_api_init', function () {
  if ( ! class_exists( 'WP_REST_Controller' ) ) {
    return;
  }
  require plugin_dir_path( __FILE__ ) . 'public/class-clubinho-api-public.php';

  $route = new Clubinho_API_Public();
  $route->register_routes();
}, 0 );

add_filter( 'jwt_auth_token_before_dispatch', function( $data, WP_User $user ){
  $data['name'] = $user->user_login;
  $data['cpf'] = '00000000000';
  $data['address'] = 'QNN 17';
  $data['zipcode'] = '72231-617';

  return $data;
}, 20, 2);

add_action( 'init', function() {
  register_post_type('child', [
    'labels' => [
      'name' => 'Crianças',
      'singular_name' => 'Criança'
    ],
    'public' => true,
    'has_archive' => false,
    'rewrite' => ['slug' => 'children'],
  ]);

  register_post_type('event', [
    'labels' => [
      'name' => 'Eventos',
      'singular_name' => 'Evento'
    ],
    'public' => true,
    'has_archive' => false,
    'rewrite' => ['slug' => 'events'],
  ]);

  add_theme_support( 'post-thumbnails' );
});

add_filter('manage_edit-child_columns', function( $defaults ) {
  $defaults['title']  = 'Nome';
  $defaults['age']    = 'Age';
  $defaults['avatar'] = 'Avatar';
  $defaults['points'] = 'Points';
  $defaults['author'] = 'Pai';

  unset($defaults['date']);

  return $defaults;
});

add_action('manage_child_posts_custom_column', function($column_name, $post_id) {
  if ($column_name == 'age') { 
    echo get_field('age', $post_id);
  } else if ($column_name == 'author') { 
    echo get_post_field('post_author', $post_id);
  } else if ($column_name == 'avatar') { 
    echo get_field('avatar', $post_id);
  } else if ($column_name == 'points') { 
    echo get_post_meta($post_id, 'points')[0];
  } 
}, 10, 2);

add_action('save_post', function( $post_id ) {
  if (wp_is_post_revision($post_id) || get_post_type($post_id) !== 'child') {
    return;
  }

  $points_per_event = 10;
  $events = get_field('events', $post_id);
  $redeems = get_field('redeemed', $post_id);
  $pontuation = 0;

  if (count($events) > 0) {
    $pontuation = count($events) * $points_per_event;

    foreach($redeems as $redeem) {
      $pontuation = $pontuation - intval($redeem['pontuation']);
    }
  }
      
  update_post_meta($post_id, 'points', $pontuation, false);
});
