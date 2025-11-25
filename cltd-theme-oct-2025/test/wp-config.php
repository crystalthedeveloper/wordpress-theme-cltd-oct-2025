<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'bitnami_wordpress' );

/** Database username */
define( 'DB_USER', 'bn_wordpress' );

/** Database password */
define( 'DB_PASSWORD', '9206f5ca0268ec5276ce1a371e9d7929ba72121cfd0034113c2436c7d87c2dfc' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1:3306' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'm<=:OOp%W16<N9Jr&/PYyN>VRW-KQz)uZZ2PzfT@6yr9KR=/<`i8J%C7T)g2@_Qb' );
define( 'SECURE_AUTH_KEY',  '7QG@2%[~6r|H3%{hZ}Gp xTPrjB&;e>Fr:UpT?hxAP}};Q0tw:YovHT%im~VUrFG' );
define( 'LOGGED_IN_KEY',    '3&#_G}D)61/hh-^?yR16A}:>f`!TfvXe;uNdP=bE}-kr[0+JeKCmr=P:& jQ2P24' );
define( 'NONCE_KEY',        '>:n`=TTpHLdKYOHq}n~&ZA}takP}B(s*,Fwx}OvrMPZy`fp+%1W8#/Y# Yk*9.i#' );
define( 'AUTH_SALT',        '.~c_aI,.i7^Jl}-q5[XDE[<0{aX/C$4D2&ACXJY)rsjI $](!J*^W:!T=gj$hUM8' );
define( 'SECURE_AUTH_SALT', 'NA/S9BjReXwWHaOdsT1fNA8~TQb5]QNCjY.MN>m|,tvS*hpdi#uE9N}T&/v3.p5K' );
define( 'LOGGED_IN_SALT',   '2TO`9<.5&M$EYcZy)9cU|PFFuqQ;3KaxzZF|n-hyU|BsjgM~?gj9,9&a?0J0s2Je' );
define( 'NONCE_SALT',       '8m~DM[q_JLuu]wTBm2QqW]8pZvsLm%B72XDL:%g%?y)M9oT2i$vKIyk+6D<)8t0`' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



define( 'FS_METHOD', 'direct' );
/**
 * The WP_SITEURL and WP_HOME options are configured to access from any hostname or IP address.
 * If you want to access only from an specific domain, you can modify them. For example:
 *  define('WP_HOME','http://example.com');
 *  define('WP_SITEURL','http://example.com');
 *
 */
if ( defined( 'WP_CLI' ) ) {
	$_SERVER['HTTP_HOST'] = '127.0.0.1';

}

define( 'WP_HOME', 'https://www.crystalthedeveloper.ca' );
define( 'WP_SITEURL', 'https://www.crystalthedeveloper.ca' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

/**
 * Disable pingback.ping xmlrpc method to prevent WordPress from participating in DDoS attacks
 * More info at: https://docs.bitnami.com/general/apps/wordpress/troubleshooting/xmlrpc-and-pingback/
 */
if ( !defined( 'WP_CLI' ) ) {
	// remove x-pingback HTTP header
	add_filter("wp_headers", function($headers) {
		unset($headers["X-Pingback"]);
		return $headers;
	});
	// disable pingbacks
	add_filter( "xmlrpc_methods", function( $methods ) {
		unset( $methods["pingback.ping"] );
		return $methods;
	});
}
