<?php

define('WP_DEBUG', true);

// ** MySQL settings ** //
/** The name of the database for WordPress */
define('DB_NAME', 'clubinho');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', 'root');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

define('AUTH_KEY',         '%z,(F;23#%[UN|~mU&~1s^`H0gc>_vawV3aAsSn|#pqhIT16FOWp_957M<<Bvk{t');
define('SECURE_AUTH_KEY',  'j{M-V+y-kR4y~xH|.VfO:=a{i+06Z4TF2UD_d2 #+%>;nl!x/`RPUwRBb;[tb=g+');
define('LOGGED_IN_KEY',    '|DKHiE=.8hvms_ b5(;`=)0cR,QTBoDPakz0 *M$_NS}rNx|hvWqp]]l+Yx6rU,?');
define('NONCE_KEY',        '-dd@@ZhC?xYcCN-=z/|q%kkVR}?3W-d#hb7T1}-}QA.Cj]sC-kg`-}5#(^yTCUht');
define('AUTH_SALT',        ')po3gw3>_Q2)mfIqm@4N&C,O.FPZC:[yv(nU<-l7[gIWx&%}S)Wm+<>p+j,AL8wA');
define('SECURE_AUTH_SALT', 'oo?DG_b*ZW1(4K7k/k-BlwY5**^kUPg,j-95)lJ,lFu(iDc3,)>&|ft1]d)+^,j|');
define('LOGGED_IN_SALT',   'L`)ON,[g%6=M<}Li+QE+,~dWFKd]jsy-lp7+>kR9U0v2mp&:Bl;_*iy$t|s0$m@!');
define('NONCE_SALT',       '5{Lrgy-aPiP:+[*{c#je(.l6[5tnq%+3d6 T/eDg-T^P5iy79r8e0F=~< B~NZ9,');


$table_prefix = 'wp_';

define('JWT_AUTH_SECRET_KEY', 'clubinho-api-token');
define('JWT_AUTH_CORS_ENABLE', true);


/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
