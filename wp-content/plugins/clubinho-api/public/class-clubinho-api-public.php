<?php

class Clubinho_API_Public extends WP_REST_Controller {

  public function __construct() {
    $this->namespace = 'wp/v1';
  }

  /**
   * Register the routes for the objects of the controller.
   */
  public function register_routes() {

    register_rest_route( $this->namespace, '/me', [
      [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => [$this, 'get_user_data'],
        'permission_callback' => [$this, 'user_authorized'],
        'args'                => [],
      ]
    ]);

    register_rest_route( $this->namespace, '/forgot-password', [
      [
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => [$this, 'forgot_password'],
        'args'                => [
          'email', [
            'default' => true
          ]
        ],
      ]
    ]);
  }

  public function get_user_data($request) {

    $current_user = wp_get_current_user();
    $user_id = "user_{$current_user->ID}";
    $data = [
      'id'        => $current_user->ID,
      'name'      => $current_user->display_name,
      'email'     => $current_user->user_email,
      'cpf'       => get_field('cpf', $user_id),
      'address'   => get_field('address', $user_id),
      'zipcode'   => get_field('zipcode', $user_id),
      'phone'     => get_field('phone', $user_id),
  
      'children'  => [
        [
          'name'    => 'Estevao',
          'age'     => 16,
          'avatar'  => 'ana',
          'score'   => 120
        ]
      ]
    ];

  //   if (function_exists('mycred')) {
  //     $mycred = mycred();
  //     $mycred->add_creds(
  //   'reference',
  //   $current_user->ID,
  //   10,
  //   'Evento'
  // );

  //     var_dump($mycred->get_users_balance($current_user->ID));
  //     exit();
  //   }
    
    return new WP_REST_Response($this->prepare_for_response($data), 200);
  }

  public function forgot_password( $request ) {

    $params     = $request->get_params();
    $email      = $params['email'];
    $email_sent = $this->send_forgot_password($email);
    
    if (!is_wp_error($email_sent)) {
      $data = [
        'message' => 'O link para redefinir a senha foi enviado para seu e-mail.'
      ];

      return new WP_REST_Response($this->prepare_for_response($data), 200);
    } else {
      return $email_sent;
    }
  }

  private function send_forgot_password( $user_login ) {
    global $wpdb, $wp_hasher;

    if (strpos($user_login, '@')) {
      $user_data = get_user_by('email', trim($user_login));
      
      if (empty($user_data)) {
        return new WP_Error(
        'invalid-email-for-forget-password', 
          'E-mail de usuário inválido.', 
          ['status' => 403]
        );
      }
    } else {
      $login = trim($user_login);
      $user_data = get_user_by('login', $login);
    }

    do_action('lostpassword_post');

    if ( !$user_data ) return false;

    // redefining user_login ensures we return the right case in the email
    $user_login = $user_data->user_login;
    $user_email = $user_data->user_email;

    // do_action('retreive_password', $user_login);  // Misspelled and deprecated
    do_action('retrieve_password', $user_login);

    $allow = apply_filters('allow_password_reset', true, $user_data->ID);

    if (!$allow || is_wp_error($allow)) {
      return new WP_Error(
        'cant-send-forget-password-email', 
        'E-mail não pode ser enviado. Problema técnico. 2', 
        ['status' => 403]
      );
    }

    $key = wp_generate_password( 20, false );
    do_action( 'retrieve_password_key', $user_login, $key );

    if (empty($wp_hasher)) {
      require_once ABSPATH . 'wp-includes/class-phpass.php';
      $wp_hasher = new PasswordHash(8, true);
    }

    $hashed = $wp_hasher->HashPassword( $key );
    $wpdb->update( $wpdb->users, 
      ['user_activation_key' => $hashed], 
      ['user_login' => $user_login]
    );

    $message  = 'Alguém pediu que a senha da seguinte conta seja redefinida:' . '\r\n\r\n';
    $message .= sprintf('E-mail: %s', $user_email) . '\r\n\r\n';
    $message .= 'Se isso foi um erro, apenas ignore este e-mail e nada acontecerá' . '\r\n\r\n';
    $message .= __('Para redefinir sua senha, visite o seguinte endereço:') . '\r\n\r\n';
    $message .= network_site_url('wp-login.php?action=rp&key=$key&login=' . rawurlencode($user_login), 'login') . '\r\n\r\n';

    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

    $title = sprintf('%s - Redefinir sua senha', $blogname);
    $title = apply_filters('retrieve_password_title', $title);
    $message = apply_filters('retrieve_password_message', $message, $key);

    if ($message && !wp_mail($user_email, $title, $message) ) {
      return new WP_Error(
        'cant-send-forget-password-email', 
        'E-mail não pode ser enviado. Problema técnico.', 
        ['status' => 403]
      );
    }

    return true;
  }

  // Check if a given request has authorization
  public function user_authorized( $request ) {
    return current_user_can( 'activate_plugins' );
  }

  private function prepare_for_response( $data ) {
    return [ 'data' => $data ];
  }
}
