<?php
define( 'WP_CACHE', true ); // Added by WP Rocket

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'gravit04_wp240' );

/** Database username */
define( 'DB_USER', 'gravit04_wp240' );

/** Database password */
define( 'DB_PASSWORD', '5p0@m-1m7S' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         '0cpb2tir0fvdje4susczxzu21pxka8o6r7nyxd7ydt6zua6fugnbm3dcqkxy9dsr' );
define( 'SECURE_AUTH_KEY',  'hokipkezotkar8urebqubbumixuxwqy3erytbeb4c1rqmo6bmonsjvteyavaykpy' );
define( 'LOGGED_IN_KEY',    'e9vjix5yd7wkixarzx4cvjo1raurvrtqah2qpcidfh0wizrhio0xsftgj67sbdat' );
define( 'NONCE_KEY',        'eblkqikkrpl9dflpwrukjnnykddtq5fcozrihbh80xpzzqtlz8gdp0g6unxt8aye' );
define( 'AUTH_SALT',        'scmlo48lzvcvpkqduc54rt5uqu1qrairxs0mcisw7ip0dozzzcvu7xtutfp9wpoo' );
define( 'SECURE_AUTH_SALT', '49xgpgtmlui7xmfqwwtdia7b2purv5ehp6ythtk9zzhebk2ja9tfzf4bskww7swx' );
define( 'LOGGED_IN_SALT',   'yyszmrt1fko5wdtsvbqnkpjo1pfq02jo5wlvjqihruhcbgob3wsynxfah9zhdda3' );
define( 'NONCE_SALT',       'jejbn1bd924ojlzfmimrkleurwyejvl1dfpyippp1fy8z0szb1ena4l0q9m0f7iz' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp0c_';

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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

