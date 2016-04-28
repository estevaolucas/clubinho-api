<?php

class Clubinho_API_Public extends WP_REST_Controller {

  public function __construct() {
    $this->namespace = 'wp/v1';
  }

  /**
   * Register the routes for the objects of the controller.
   */
  public function register_routes() {

    register_rest_route( $this->namespace, '/create-user', [
      [
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => [$this, 'create_user'],
        'args'                => [
          'name' => [
            'required' => true
          ],
          'email' => [
            'required' => true,
            'description' => 'E-mail inválido.',
            'validate_callback' => function($email, $request, $key) {
              $is_email_valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
              $is_email_used = email_exists($email);

              $message = null;
              if (!$is_email_valid) {
                $message = 'E-mail inválido';
              } else if ($is_email_used) {
                $message = 'E-mail já cadastrado';
              }

              if ($message) {
                return new WP_Error('-', $message);
              }
            }
          ],
          'cpf' => [
            'description' => 'CPF inválido',
            'required' => true,
            'validate_callback' => function($cpf, $request, $key) {
              if (!$this->validate_cpf($cpf)) {
                return new WP_Error('-', 'CPF inválido');
              }

              if (username_exists($cpf)) {
                return new WP_Error('-', 'CPF já cadastrado.');
              }
            }
          ],
          'address' => [
            'required' => false
          ],
          'zipcode' => [
            'required' => false,
            'validate_callback' => function($zipcode, $request, $key) {
              if (!ereg('^[0-9]{5}(-)?[0-9]{3}$', trim($zipcode))) {
                return new WP_Error('-', 'CEP inválido');
              }
            }
          ],
          'phone' => [
            'required' => false,
            'validate_callback' => function($phone) {
              if (!ereg('^\([0-9]{2}\) [0-9]{4}-[0-9]{4,5}$', trim($phone))) {
                return new WP_Error('-', 'Telefone inválido');
              }
            }
          ],

          'password' => [
            'required' => true,
            'validate_callback' => function($password, $request) {
              if ($request->get_param('password_confirmation') !== $password) {
                return new WP_Error('-', 'Password não confere');
              }

              if (strlen($password) < 5) {
                return new WP_Error('-', 'Password precisa ter no mínimo 5 caracteres');
              }

              if (strlen($password) > 12) {
                return new WP_Error('-', 'Password precisa ter no máximo 12 caracteres');
              }
            }
          ],
          'password_confirmation' => [
            'required' => true
          ]
        ],
      ]
    ]);

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
            'required' => true
          ]
        ],
      ]
    ]);
  }

  public function create_user($request) {
    $params = $request->get_params();
    list($first, $last) = explode(' ', $params['name']);

    $data = [
      'user_login'   => $params['cpf'],
      'user_pass'    => $params['password'],
      'user_email'   => $params['email'],
      'first_name'   => $first,
      'last_name'    => $last,
      'display_name' => $params['name']
    ];

    $user_id = wp_insert_user($data);

    if (!is_wp_error($user_id)) { 
      $acf_user_id = "user_{$user_id}";

      update_field('cpf', $params['cpf'], $acf_user_id);

      if ($params['address']) {
        update_field('address', $params['address'], $acf_user_id);        
      } 

      if ($params['zipcode']) {
        update_field('zipcode', $params['zipcode'], $acf_user_id);        
      } 

      if ($params['phone']) {
        update_field('phone', $params['phone'], $acf_user_id);        
      }       

      return new WP_REST_Response(['message' => 'Usuário criado'], 200);
    } else {
      return new WP_Error(
        'user-not-created', 
        $user_id->get_error_message(), 
        ['status' => 403]
      );
    }
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
    
    return new WP_REST_Response($this->prepare_for_response($data), 200);
  }

  public function forgot_password( $request ) {
    $email      = $request->get_params('email');
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

  private function validate_cpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', (string) $cpf);

    if (strlen($cpf) != 11) {
      return false;
    }
    
    for ($i = 0, $j = 10, $soma = 0; $i < 9; $i++, $j--) {
      $soma += $cpf{$i} * $j;
    }

    $resto = $soma % 11;
    
    if ($cpf{9} != ($resto < 2 ? 0 : 11 - $resto)) {
      return false;
    }

    for ($i = 0, $j = 11, $soma = 0; $i < 10; $i++, $j--) {
      $soma += $cpf{$i} * $j;
    }
    
    $resto = $soma % 11;

    return $cpf{10} == ($resto < 2 ? 0 : 11 - $resto);
  }
}
