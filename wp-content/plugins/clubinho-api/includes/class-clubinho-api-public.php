<?php

class Clubinho_API_Public extends WP_REST_Controller {
  protected $loader;
  protected $endpoints;

  public function __construct() {
    $this->namespace = 'v1';

    $this->load_dependencies(); 
    $this->add_filters();
    $this->add_actions();
  }

  private function load_dependencies() {
    $path = plugin_dir_path(dirname(__FILE__));

    require_once $path . 'includes/class-clubinho-api-loader.php';
    require_once $path . 'includes/class-clubinho-api-helper.php';
    require_once $path . 'includes/class-clubinho-api-endpoints.php';

    $this->loader = new Clubinho_API_Loader();
    $this->endpoints = new Clubinho_API_Endpoints();
  }

  public function register_post_types() {
    register_post_type('child', array(
      'labels' => array(
        'name' => 'Crianças',
        'singular_name' => 'Criança'
      ),
      'public' => true,
      'has_archive' => false,
      'rewrite' => array('slug' => 'children'),
    ));

    register_post_type('event', array(
      'labels' => array(
        'name' => 'Eventos',
        'singular_name' => 'Evento'
      ),
      'public' => true,
      'has_archive' => false,
      'rewrite' => array('slug' => 'events'),
    ));
  }

  public function register_routes() {
    // create user
    register_rest_route( $this->namespace, '/create-user', array(
      'methods'             => WP_REST_Server::EDITABLE,
      'callback'            => array($this->endpoints, 'create_user'),
      'args'                => $this->endpoints->get_user_default_args()
    ));

    // create/login facebook user
    register_rest_route( $this->namespace, '/facebook', array(
      'methods'             => WP_REST_Server::EDITABLE,
      'callback'            => array($this->endpoints, 'create_or_signin_from_facebook'),
      'args'                => array('access_token' => array('required' => true))
    ));

    register_rest_route( $this->namespace, '/me', array(
      // get user data
      array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => array($this->endpoints, 'get_user_data'),
        'permission_callback' => array($this->endpoints, 'user_authorized')
      ),
      // update user data
      array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => array($this->endpoints, 'update_user_data'),
        'permission_callback' => array($this->endpoints, 'user_authorized'),
        'args'                => $this->endpoints->get_user_default_args('update_user')
      )
    ));

    // add child
    register_rest_route($this->namespace, '/me/child', array(
      'methods'             => WP_REST_Server::EDITABLE,
      'callback'            => array($this->endpoints, 'create_child'),
      'permission_callback' => array($this->endpoints, 'user_authorized'),
      'args'                => $this->endpoints->get_child_default_args()
    ));

    register_rest_route($this->namespace, '/me/child/(?P<id>\d+)', array(
      // update child data
      array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => array($this->endpoints, 'update_child'),
        'permission_callback' => array($this->endpoints, 'user_authorized'),
        'args'                => $this->endpoints->get_child_default_args('update_child')
      ),
      // remove child
      array(
        'methods'             => WP_REST_Server::DELETABLE, 
        'callback'            => array($this->endpoints, 'remove_child'),
        'permission_callback' => array($this->endpoints, 'user_authorized'),
        'args'                => $this->endpoints->get_child_default_args('remove_child')
      )
    ));

    register_rest_route($this->namespace, '/me/child/(?P<id>\d+)/confirm/(?P<eventId>\d+)', array(
      'methods'             => WP_REST_Server::EDITABLE,
      'callback'            => array($this->endpoints, 'confirm_event_for_child'),
      'permission_callback' => array($this->endpoints, 'user_authorized'),
      'args'                => $this->endpoints->get_child_confirm_default_args()
    ));

    // send link to create a new password
    register_rest_route($this->namespace, '/forgot-password', array(
      'methods'             => WP_REST_Server::EDITABLE,
      'callback'            => array($this->endpoints, 'forgot_password'),
      'args'                => array('email' => array('required' => true)),
    ));

    register_rest_route($this->namespace, '/get-schedule-list', array(
      'methods'             => WP_REST_Server::READABLE,
      'callback'            => array($this->endpoints, 'get_schedule'),
      'args'                => array('size' => array('required' => false)),
    ));

    register_rest_route($this->namespace, 'token', array(
      'methods'               => WP_REST_Server::EDITABLE,
      'callback'              => array($this->endpoints, 'generate_token'),
    ));

    register_rest_route($this->namespace, 'token/validate', array(
      'methods'               => WP_REST_Server::EDITABLE,
      'callback'              => array($this->endpoints, 'validate_token'),
    ));
  }

  public function add_filters() {
    $this->loader->add_filter('jwt_auth_token_before_dispatch', $this, 'filter_for_auth_response', 20, 2);
    $this->loader->add_filter('manage_edit-child_columns', $this, 'filter_admin_manage_coluns');
    $this->loader->add_filter('rest_url_prefix', $this, 'filter_api_prefix');
    $this->loader->add_filter('rest_endpoints', $this, 'filter_remove_default_endpoints');
  }

  public function add_actions() {
    $this->loader->add_action('manage_child_posts_custom_column', $this, 'action_manage_coluns', 10, 2);

    $this->loader->add_action('save_post', $this, 'action_calculate_points_on_save');
    $this->loader->add_action('admin_menu', $this, 'action_admin_menu');
  }

  public function filter_for_auth_response($data, WP_User $user) {
    return array_merge($data, $this->endpoints->get_user_data(null, $user)->data);
  }

  public function filter_admin_manage_coluns($coluns) {
    $coluns['title']  = 'Nome';
    $coluns['age']    = 'Idade';
    $coluns['avatar'] = 'Avatar';
    $coluns['points'] = 'Pontuação';
    $coluns['author'] = 'Pai';

    unset($coluns['date']);

    return $coluns;
  }

  public function filter_api_prefix($prefix) { 
    return 'api';
  }

  public function filter_remove_default_endpoints($endpoints) {
    foreach($endpoints as $route => $data) {
      if (0 === stripos($route, '/wp/v2') || 0 === stripos($route, '/oembed/1.0')) {
        unset($endpoints[$route]);
      }
    }

    return $endpoints;
  }

  public function action_manage_coluns($column_name, $post_id) {
    if ($column_name == 'age') { 
      echo get_field('age', $post_id);
    } else if ($column_name == 'author') { 
      echo get_post_field('post_author', $post_id);
    } else if ($column_name == 'avatar') { 
      echo get_field('avatar', $post_id);
    } else if ($column_name == 'points') { 
      $points = get_post_meta($post_id, 'points');

      if (count($points)) {
        echo $points[0];
      }
    } 
  }

  public function action_calculate_points_on_save($post_id) {
    if (wp_is_post_revision($post_id) || get_post_type($post_id) !== 'child') {
      return;
    }

    $points_per_event = 10;
    $events = get_field('events', $post_id);
    $redeems = get_field('redeemed', $post_id);
    $pontuation = 0;

    if ($events && count($events)) {
      $pontuation = count($events) * $points_per_event;

      if ($redeems) {
        foreach($redeems as $redeem) {
          $pontuation = $pontuation - intval($redeem['pontuation']);
        }
      }
    }
        
    update_post_meta($post_id, 'points', $pontuation, false);
  }

  public function action_admin_menu() {
    remove_menu_page('edit.php');
    remove_menu_page('edit.php?post_type=page');
    remove_menu_page('edit-comments.php');
  }

  public function run() {
    $this->register_post_types();
    $this->loader->run();
  }
}
