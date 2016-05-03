<?php

include(TEMPLATEPATH . '/inc/acf-repeater/acf-repeater.php');

add_action('after_setup_theme', function() {

});

add_action('admin_menu', function() {
  remove_menu_page('edit.php');
  remove_menu_page('edit.php?post_type=page');
  remove_menu_page('edit-comments.php');
});
