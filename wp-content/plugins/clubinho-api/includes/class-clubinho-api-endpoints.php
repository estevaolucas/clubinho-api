<?php

class Clubinho_API_Endpoints {

  private $helper;

  public function __construct() {
    $this->namespace = 'wp/v1';

    $this->helper = new Clubinho_API_Helper();
  }

  public function create_user($request) {
    $params = $request->get_params();
    list($first, $last) = explode(' ', $params['name']);

    $data = [
      'user_login'   => $this->helper->remove_mask_string($params['cpf']),
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

      update_field($this->helper->get_acf_key('cpf'), $this->helper->remove_mask_string($params['cpf']), $acf_user_id);

      if ($params['address']) {
        update_field($this->helper->get_acf_key('address'), $params['address'], $acf_user_id);        
      } 

      if ($params['zipcode']) {
        update_field($this->helper->get_acf_key('zipcode'), $params['zipcode'], $acf_user_id);        
      } 

      if ($params['phone']) {
        update_field($this->helper->get_acf_key('phone'), $params['phone'], $acf_user_id);        
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
      $cpf = $this->helper->apply_mask_string('###.###.###-##', $cpf);
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
      'children'      => $this->helper->get_children_list($current_user)
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
      update_field($this->helper->get_acf_key('age'), $params['age'], $child_id);
      update_field($this->helper->get_acf_key('avatar'), $params['avatar'], $child_id);
      add_post_meta($child_id, 'created_at', date('Y-m-d H:i:s'));

      $data = $this->prepare_for_response([
        'message'  => "A criança {$params['name']} foi adicionada.",
        'children' => $this->helper->get_children_list($current_user)
      ]);

      return new WP_REST_Response($data, 200);
    }

    return new WP_Error(
      'child-not-created', 
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
      update_field($this->helper->get_acf_key('avatar'), $params['avatar'], $child_id);
      update_field($this->helper->get_acf_key('age'), $params['age'], $child_id);
      update_post_meta($child_id, 'updated_at', date('Y-m-d H:i:s'));

      $data = $this->prepare_for_response([
        'message'  => "A criança {$params['name']} foi atualizada.",
        'children' => $this->helper->get_children_list($current_user)
      ]);

      return new WP_REST_Response($data, 200);
    }

    return new WP_Error(
      'child-not-updated', 
      $child_id->get_error_message(), 
      ['status' => 403]
    );
  }

  public function remove_child($request) {
    $current_user = wp_get_current_user();
    $id = $request->get_param('id');

    $child_id = wp_delete_post($id);

    if ($child_id) {
      update_post_meta($child_id, 'deleted_at', date('Y-m-d H:i:s'));

      $data = $this->prepare_for_response([
        'message'  => "A criança foi removida.",
        'children' => $this->helper->get_children_list($current_user)
      ]);

      return new WP_REST_Response($data, 200);
    }

    return new WP_Error(
      'child-not-removed', 
      $child_id->get_error_message(), 
      ['status' => 403]
    );
  }

  public function confirm_event_for_child($request) {
    $current_user = wp_get_current_user();
    $id = $request->get_param('id');
    $eventId = $request->get_param('eventId');

    $events = get_field($this->helper->get_acf_key('events'), $id);
    $events[] = $eventId;
    update_field($this->helper->get_acf_key('events'), $events, $id);

    $data = $this->prepare_for_response([
      'message' => 'Evento confirmado'
    ]);

    return new WP_REST_Response($data, 200);
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
        update_field('cpf', $this->helper->remove_mask_string($params['cpf']), $user_id);
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
    $email_sent = $this->helper->send_forgot_password($email);
    
    if (!is_wp_error($email_sent)) {
      $data = $this->prepare_for_response([
        'message' => 'O link para redefinir a senha foi enviado para seu e-mail.'
      ]);

      return new WP_REST_Response($data, 200);
    } else {
      return $email_sent;
    }
  }

  private function prepare_for_response( $data ) {
    return [ 'data' => $data ];
  }

  // check if a given request has authorization
  public function user_authorized( $request ) {
    return current_user_can('read');
  }

  public function get_user_default_args($type = 'create_user') {
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
          if (!$this->helper->validate_cpf($cpf)) {
            return new WP_Error('-', 'CPF inválido');
          }

          $is_cpf_used = username_exists($this->helper->remove_mask_string($cpf));

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

  public function get_child_default_args($type = 'create_child') {
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

    if ($type == 'remove_child') {
      $args = [];
    }

    if ($type == 'update_child' || $type == 'remove_child') {
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

          if (!$child->have_posts()) {
            return new WP_Error('-', 'Não há criança com esses dados!');
          }
        } 
      ];
    }

    return $args;
  }

  public function get_child_confirm_default_args() {
    $args = [
      'id' => [
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

          if (!$child->have_posts()) {
            return new WP_Error('-', 'Não há criança com esses dados!');
          }
        }
      ],
      'eventId' => [
        'required' => true,
        'validate_callback' => function($eventId, $request, $key) {
          $current_user = wp_get_current_user();
          $event = new WP_Query([
            'p'              => $eventId,
            'post_type'      => 'event',
            'posts_per_page' => -1,
            'post_status'    => 'publish'
          ]);

          if (!$event->have_posts()) {
            return new WP_Error('-', 'Não há criança com esses dados!');
          } else {
            $events = get_field($this->helper->get_acf_key('events'), $request->get_param('id'));
            
            if (is_array($events) && in_array($eventId, $events)) {
              return new WP_Error('-', 'Evento já confirmado!');
            }
          }
        }
      ]
    ];

    return $args;
  }
}
