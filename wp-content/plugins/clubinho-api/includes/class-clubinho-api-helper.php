<?php

class Clubinho_API_Helper {

  public static function get_children_list(WP_User $current_user) {
    $children_list = array();
    $children = new WP_Query(array(
      'post_type'      => 'child',
      'posts_per_page' => -1,
      'post_status'    => 'publish',
      'author'         => $current_user->ID
    ));

    if ($children->have_posts()) {
      while ($children->have_posts()) {
        $children->the_post();
        $child = array(
          'id'       => $children->post->ID,
          'name'     => get_the_title(),
          'age'      => get_field('age'),
          'avatar'   => get_field('avatar', $children->post->ID),
          'points'   => get_field('points', $children->post->ID),
          'timeline' => array()
        );

        $user_events = get_field('events', $children->post->ID);
        $user_redeems = have_rows('redeemed');
        $timeline = array();

        if ($user_events && count($user_events)) {
          $events = new WP_Query(array(
            'post_type'       => 'event',
            'posts_per_page'  => -1,
            'post_status'     => 'publish',
            'post__in'        => $user_events
          ));

          if ($events->have_posts()) {
            while ($events->have_posts()) {
              $events->the_post();

              array_push($timeline, array(
                'type'   => 'event',
                'id'     => $events->post->ID,
                'title'  => $events->post->post_title,
                'date'   => get_field('date') . ' ' . get_field('time'),
                'points' => 10 // FIXME: get dynamic value
              ));
            }
          }
        }

        if ($user_redeems) {
          while(have_rows('redeemed', $children->post->ID)) {
            the_row();
                
            array_push($timeline, array(
              'type'   => 'reedem',
              'prize'  => get_sub_field('prize'),
              'points' => get_sub_field('pontuation'),
              'date'   => get_sub_field('date') . " 00:00:00"
            ));
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
      return ($b['age'] - $a['age']);
    });

    return $children_list;
  }

  public static function send_forgot_password($user_login) {
    global $wpdb, $wp_hasher;

    if (strpos($user_login, '@')) {
      $user_data = get_user_by('email', trim($user_login));
      
      if (empty($user_data)) {
        return new WP_Error(
        'invalid-email-for-forget-password', 
          'E-mail de usuário inválido.', 
          array('status' => 403)
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
        array('status' => 403)
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
      array('user_activation_key' => $hashed), 
      array('user_login' => $user_login)
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
        array('status' => 403)
      );
    }

    return true;
  }

  public static function validate_cpf($cpf) {
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

  public static function apply_mask_string($mask, $text) {
    $text = str_replace(' ', '', $text);

    for($i = 0; $i < strlen($text); $i++) {
      $mask[strpos($mask, '#')] = $text[$i];
    }

    return $mask;
  }

  public static function remove_mask_string($value, $type = 'cpf') {
    return preg_replace('/[\\.-]*/i', '', $value, -1);
  }

  public static function get_acf_key($field_name) {
    global $wpdb;
    
    $length = strlen($field_name);
    
    return $wpdb->get_var("
      SELECT `meta_key`
      FROM {$wpdb->postmeta}
      WHERE `meta_key` LIKE 'field_%' AND `meta_value` LIKE '%\"name\";s:$length:\"$field_name\";%';
      ");
  }
}
