<?php

class Clubinho_API_Public extends WP_REST_Controller {

  public function __construct() {
    $this->namespace = 'wp/v1';
  }

  /**
   * Register the routes for the objects of the controller.
   */
  public function register_routes() {

    // Create user
    register_rest_route( $this->namespace, '/create-user', [
      [
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => [$this, 'create_user'],
        'args'                => $this->get_user_default_args()
      ]
    ]);

    // Create/login facebook user
    register_rest_route( $this->namespace, '/facebook', [
      [
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => [$this, 'create_or_signin_from_facebook'],
        'args'                => [
          'access_token' => [
            'required' => true
          ]
        ]
      ]
    ]);

    register_rest_route( $this->namespace, '/me', [
      // get user data
      [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => [$this, 'get_user_data'],
        'permission_callback' => [$this, 'user_authorized'],
        'args'                => [],
      ],
      // update user data
      [
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => [$this, 'update_user_data'],
        'permission_callback' => [$this, 'user_authorized'],
        'args'                => $this->get_user_default_args('update_user')
      ]
    ]);

    // add child
    register_rest_route( $this->namespace, '/me/child', [
      [
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => [$this, 'create_child'],
        'permission_callback' => [$this, 'user_authorized'],
        'args'                => $this->get_child_default_args()
      ]
    ]);

    // update child data
    register_rest_route( $this->namespace, '/me/child/(?P<id>\d+)', [
      [
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => [$this, 'update_child'],
        'permission_callback' => [$this, 'user_authorized'],
        'args'                => $this->get_child_default_args()
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
      'user_login'   => $this->remove_mask_string($params['cpf']),
      'user_pass'    => $params['password'],
      'user_email'   => $params['email'],
      'first_name'   => $first,
      'last_name'    => $last,
      'display_name' => $params['name']
    ];

    $user_id = wp_insert_user($data);

    if (!is_wp_error($user_id)) { 
      add_user_meta($user_id, 'created_at', date('Y-m-d H:i:s'));
      add_user_meta($user_id, 'facebook_user', false);

      $acf_user_id = "user_{$user_id}";

      update_field($this->get_acf_key('cpf'), $this->remove_mask_string($params['cpf']), $acf_user_id);

      if ($params['address']) {
        update_field($this->get_acf_key('address'), $params['address'], $acf_user_id);        
      } 

      if ($params['zipcode']) {
        update_field($this->get_acf_key('zipcode'), $params['zipcode'], $acf_user_id);        
      } 

      if ($params['phone']) {
        update_field($this->get_acf_key('phone'), $params['phone'], $acf_user_id);        
      }       

      $data = $this->prepare_for_response(['message' => 'Usuário criado']);
      return new WP_REST_Response($data, 200);
    } else {
      return new WP_Error(
        'user-not-created', 
        $user_id->get_error_message(), 
        ['status' => 403]
      );
    }
  }

  public function create_or_signin_from_facebook($request) {
    global $wp_rest_server;

    $params = $request->get_params();

    $api_endpoint = "https://graph.facebook.com/me/?fields=id,name,first_name,last_name,email&access_token={$params['access_token']}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL,$api_endpoint);
    
    $result = curl_exec($ch);
    
    curl_close($ch);

    $result   = json_decode($result, true);
    $email    = $result['email'];
    $username = $result['id'];

    if (isset($result['error'])) {
      return new WP_Error(
        'user-not-created', 
        $result['error']['message'], 
        ['status' => 403]
      );
    }

    if (isset($email)) {
      $email_exists = email_exists($email);

      if ($email_exists) {
        $user = get_user_by('email', $email);
        $user_id = $user->ID;
        $user_name = $user->user_login;
      } 

      if (!$user_id && $email_exists == false) {
        $password = wp_generate_password(12, false);

        $data = [
          'user_login'  => $username,
          'user_pass'   => $password,
          'user_email'  => $email,
          'first_name'  => $result['first_name'],
          'last_name'   => $result['last_name'],
          'display_name'=> $result['name']
        ];

        $user_id = wp_insert_user($data);
        
        if (is_wp_error($user_id)) {
          return new WP_Error(
            'user-not-created', 
            $user_id->get_error_message(), 
            ['status' => 403]
          );
        } else {
          add_user_meta($user_id, 'facebook_user', true);
          add_user_meta($user_id, 'created_at', date('Y-m-d H:i:s'));
        }
      }

      $password = wp_generate_password(12, false);
      wp_set_password($password, $user_id);

      $login_request = new WP_REST_Request( 'POST', '/jwt-auth/v1/token');
      $login_request->set_param('username', $username);
      $login_request->set_param('password', $password);
      rest_ensure_response($login_request);

      $response = $wp_rest_server->dispatch($login_request);

      if (!is_wp_error($response)) {
        return $response;
      } 

      return $response;
    } else {
      return new WP_Error(
        'user-not-created', 
        'Necessário permitir o acesso ao seu email.', 
        ['status' => 403]
      );
    }
  }

  public function get_user_data($request = null, WP_User $current_user = null) {
    if (!isset($current_user)) {
      $current_user = wp_get_current_user();
    }
    
    $user_id = "user_{$current_user->ID}";
    $cpf = get_field('cpf', $user_id);

    if ($cpf) {
      $cpf = $this->apply_mask_string('###.###.###-##', $cpf);
    } else {
      $cpf = null;
    }

    $address = get_field('address', $user_id);
    $zipcode = get_field('zipcode', $user_id);
    $phone = get_field('phone', $user_id);

    $data = [
      'id'            => $current_user->ID,
      'name'          => $current_user->display_name,
      'email'         => $current_user->user_email,
      'cpf'           => $cpf,
      'address'       => $address ? $address : null,
      'zipcode'       => $zipcode ? $zipcode : null,
      'phone'         => $phone   ? $phone   : null,
      'facebook_user' => !!get_user_meta($current_user->ID, 'facebook_user', true),
      'children'      => $this->get_children_list($current_user)
    ];
    
    return new WP_REST_Response($this->prepare_for_response($data), 200);
  }

  public function create_child($request) {
    $current_user = wp_get_current_user();
    $params = $request->get_params();

    $child_id = wp_insert_post([
      'post_author' => $current_user->ID,
      'post_title'  => $params['name'],
      'post_status' => 'publish',
      'post_type'   => 'child'
    ]);

    if (!is_wp_error($child_id)) {
      update_field($this->get_acf_key('age'), $params['age'], $child_id);
      update_field($this->get_acf_key('avatar'), $params['avatar'], $child_id);
      add_post_meta($child_id, 'created_at', date('Y-m-d H:i:s'));

      $data = $this->prepare_for_response([
        'message'  => "A criança {$params['name']} foi adicionada.",
        'children' => $this->get_children_list($current_user)
      ]);

      return new WP_REST_Response($data, 200);
    }

    return new WP_Error(
      'child-not-added', 
      $child_id->get_error_message(), 
      ['status' => 403]
    );
  }

  public function update_child($request) {
    $current_user = wp_get_current_user();
    $params = $request->get_params();

    $child_id = wp_update_post([
      'ID'          => $params['id'],
      'post_title'  => $params['name']
    ]);

    if (!is_wp_error($child_id)) {
      update_field($this->get_acf_key('avatar'), $params['avatar'], $child_id);
      update_field($this->get_acf_key('age'), $params['age'], $child_id);
      update_post_meta($child_id, 'updated_at', date('Y-m-d H:i:s'));

      $data = $this->prepare_for_response([
        'message'  => "A criança {$params['name']} foi atualizada.",
        'children' => $this->get_children_list($current_user)
      ]);

      return new WP_REST_Response($data, 200);
    }

    return new WP_Error(
      'child-not-added', 
      $child_id->get_error_message(), 
      ['status' => 403]
    );
  }

  public function update_user_data($request) {
    $current_user = wp_get_current_user();
    
    $params = $request->get_params();
    list($first, $last) = explode(' ', $params['name']);
    
    if ($current_user) {
      $user_id = "user_{$current_user->ID}";
      $updated = wp_update_user([
        'user_pass'   => $params['password'],
        'display_name'=> $params['name'],
        'first_name'  => $first,
        'last_name'   => $last
      ]);

      if (is_wp_error($updated)) {
        update_field('cpf', $this->remove_mask_string($params['cpf']), $user_id);
        update_field('address', $params['address'], $user_id);
        update_field('zipcode', $params['zipcode'], $user_id);
        update_field('phone', $params['phone'], $user_id);
        
        return $this->get_user_data();
      } 

      return new WP_Error(
        'user-not-updated', 
        $updated->get_error_message(), 
        ['status' => 403]
      );
    }
  }

  public function forgot_password( $request ) {
    $email      = $request->get_params('email');
    $email_sent = $this->send_forgot_password($email);
    
    if (!is_wp_error($email_sent)) {
      $data = $this->prepare_for_response([
        'message' => 'O link para redefinir a senha foi enviado para seu e-mail.'
      ]);

      return new WP_REST_Response($data, 200);
    } else {
      return $email_sent;
    }
  }

  // Check if a given request has authorization
  public function user_authorized( $request ) {
    return current_user_can('read');
  }

  private function prepare_for_response( $data ) {
    return [ 'data' => $data ];
  }

  private function get_user_default_args($type = 'create_user') {
    $args = [
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
        'validate_callback' => function($cpf, $request, $key) use ($type) {
          if (!$this->validate_cpf($cpf)) {
            return new WP_Error('-', 'CPF inválido');
          }

          $is_cpf_used = username_exists($this->remove_mask_string($cpf));

          if ($type != 'update_user' && $is_cpf_used) {
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
          if (!preg_match('/^[0-9]{5}(-)?[0-9]{3}$/', trim($zipcode))) {
            return new WP_Error('-', 'CEP inválido');
          }
        }
      ],
      'phone' => [
        'required' => false,
        'validate_callback' => function($phone) {
          if (!preg_match('/^\([0-9]{2}\) [0-9]{4}-[0-9]{4,5}$/', trim($phone))) {
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
    ];

    if ($type == 'update_user') {
      unset($args['email']);

      $args['password']['required'] = false;
      $args['password_confirmation']['required'] = false;

      $args['address']['required'] = true;
      $args['zipcode']['required'] = true;
      $args['phone']['required'] = true;
    }

    return $args;
  }

  private function get_child_default_args($type = 'create_child') {
    $args = [
      'name' => [
        'required' => true,
        'validate_callback' => function($name, $request, $key) {
          $current_user = wp_get_current_user();
          $children = new WP_Query([
            'post_type'      => 'child',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'author'         => $current_user->ID
          ]);

          $exists = false;
          if ($children->have_posts()) {
            while ($children->have_posts()) {
              $children->the_post();

              if ($name == get_the_title()) {
                $exists = true;

                $id = $request->get_param('id');
                if ($id == get_the_ID()) {
                  $exists = false;
                }
              }
            }
          }

          if ($exists) {
            return new WP_Error('-', 'Um filho com esse nome já foi cadastrado.');
          }
        }
      ],
      'age' => [
        'required' => true
      ],
      'avatar' => [
        'required' => true,
        'validate_callback' => function($avatar, $request, $key) {
          $avatars = ['ana', 'luiz', 'maria'];

          if (!in_array($avatar, $avatars)) {
            return new WP_Error('-', 'Avatar não válido');
          }
        }
      ]
    ];

    if ($type == 'update_child') {
      $args['id'] = [
        'required' => true,
        'validate_callback' => function($id, $request, $key) {
          $current_user = wp_get_current_user();
          $child = new WP_Query([
            'p'              => $id,
            'post_type'      => 'child',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'author'         => $current_user->ID
          ]);

          if ($child->have_posts()) {
            return new WP_Error('-', 'Não há criança com esses dados!');
          }
        } 
      ];
    }

    return $args;
  }

  private function get_children_list(WP_User $current_user) {
    $children_list = [];
    $children = new WP_Query([
      'post_type'      => 'child',
      'posts_per_page' => -1,
      'post_status'    => 'publish',
      'author'         => $current_user->ID
    ]);

    if ($children->have_posts()) {
      while ($children->have_posts()) {
        $children->the_post();
        $child = [
          'id'       => $children->post->ID,
          'name'     => get_the_title(),
          'age'      => get_field('age'),
          'avatar'   => get_field('avatar', $children->post->ID),
          'points'   => get_field('points', $children->post->ID),
          'timeline' => []
        ];

        $user_events = get_field('events', $children->post->ID);
        $user_redeems = have_rows('redeemed');
        $timeline = [];

        if ($user_events && count($user_events)) {
          $events = new WP_Query([
            'post_type'       => 'event',
            'posts_per_page'  => -1,
            'post_status'     => 'publish',
            'post__in'        => $user_events
          ]);

          if ($events->have_posts()) {
            while ($events->have_posts()) {
              $events->the_post();

              array_push($timeline, [
                'type'  => 'event',
                'id'    => $events->post->ID,
                'title' => $events->post->post_title,
                'date'  => get_field('date') . ' ' . get_field('time')
              ]);
            }
          }
        }

        if ($user_redeems) {
          while(have_rows('redeemed', $children->post->ID)) {
            the_row();
                
            array_push($timeline, [
              'type'  => 'reedem',
              'prize'      => get_sub_field('prize'),
              'pontuation' => get_sub_field('pontuation'),
              'date'       => get_sub_field('date')
            ]);
          }
        }

        usort($timeline, function($a, $b) {
          $date1 = strtotime($a['date']);
          $date2 = strtotime($b['date']);

          return ($date1 - $date2);
        });

        $child['timeline'] = $timeline;

        array_push($children_list, $child);
      }
    }

    usort($children_list, function($a, $b) {
      return ($a['age'] - $a['age']);
    });

    return $children_list;
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

  private function validate_cpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', (string) $cpf);

    if (strlen($cpf) != 11) {
      return false;
    }
    
    for ($i = 0, $j = 10, $sum = 0; $i < 9; $i++, $j--) {
      $sum += $cpf{$i} * $j;
    }

    $rest = $sum % 11;
    
    if ($cpf{9} != ($rest < 2 ? 0 : 11 - $rest)) {
      return false;
    }

    for ($i = 0, $j = 11, $sum = 0; $i < 10; $i++, $j--) {
      $sum += $cpf{$i} * $j;
    }
    
    $rest = $sum % 11;

    return $cpf{10} == ($rest < 2 ? 0 : 11 - $rest);
  }

  private function apply_mask_string($mask, $text) {
    $text = str_replace(' ', '', $text);

    for($i = 0; $i < strlen($text); $i++) {
      $mask[strpos($mask, '#')] = $text[$i];
    }

    return $mask;
  }

  private function remove_mask_string($value, $type = 'cpf') {
    return preg_replace('/[\\.-]*/i', '', $value, -1);
  }

  private function get_acf_key($field_name) {
    global $wpdb;
    
    $length = strlen($field_name);
    
    return $wpdb->get_var("
      SELECT `meta_key`
      FROM {$wpdb->postmeta}
      WHERE `meta_key` LIKE 'field_%' AND `meta_value` LIKE '%\"name\";s:$length:\"$field_name\";%';
      ");
  }
}
