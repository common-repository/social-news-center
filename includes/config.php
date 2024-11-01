<?php

/***********************
 * COMMON SNC SETTINGS *
 ***********************/

// Include Wordpress or Stand-alone settings
if ( defined( 'ABSPATH' ) ) {
	require_once( "config-wp.php" );
} else {
	require_once( "config-std.php" );
}

// Configuration constants
define( "SNC_DB_VERSION", "0.02" );
define( "SNC_IMAGE_MAX_WIDTH", 220 ); // in pixels
define( "SNC_IMAGE_MAX_HEIGHT", 220 ); // in pixels
define( "SNC_MAX_LINK_LENGTH", 26 );

// Facebook Application settings
define( "FACEBOOK_SDK_V4_SRC_DIR", SNC_BASE_PATH . "/facebook/" );

// Instagram query settings
define( "SNC_INSTAGRAM_API_URL", "https://api.instagram.com/v1/" );
define( "SNC_INSTAGRAM_URL", "https://www.instagram.com/" );

// Twitter query settings
define( "SNC_TWITTER_API_URL", "https://api.twitter.com/1.1/" );
