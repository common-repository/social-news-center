<?php

/**************************
 * WORDPRESS PHP SETTINGS *
 **************************/

// Configuration constants
define( "SNC_LOG_FLAG", WP_DEBUG );
define( "SNC_BASE_PATH", str_replace( "includes/", "", plugin_dir_path( __FILE__ ) ) );
define( "SNC_BASE_URL", str_replace( "includes/", "", plugin_dir_url( __FILE__ ) ) );

// Info sections constants
define( "SNC_FACEBOOK_FLAG", ( esc_attr( get_option( "snc_facebook_status" ) ) == "enabled" ? true : false ) );
define( "SNC_INSTAGRAM_FLAG", ( esc_attr( get_option( "snc_instagram_status" ) ) == "enabled" ? true : false ) );
define( "SNC_TWITTER_FLAG", ( esc_attr( get_option( "snc_twitter_status" ) ) == "enabled" ? true : false ) );
define( "SNC_DEFAULT_NUM_POSTS", get_option( "snc_posts_per_account_main" ) );
define( "SNC_POPUP_NUM_POSTS", get_option( "snc_posts_per_account_popup" ) );
define( "SNC_MAX_NUM_POSTS", get_option( "snc_posts_total_maximum" ) );
define( "SNC_LAYOUT_STYLE", get_option( "snc_layout_style" ) );
define( "SNC_COOKIE_TIMEOUT", get_option( "snc_default_cookie_timeout" ) );

// Facebook Application settings
define( "SNC_FACEBOOK_APP_ID", esc_attr( get_option( "snc_facebook_app_id" ) ) );
define( "SNC_FACEBOOK_SECRET", esc_attr( get_option( "snc_facebook_secret" ) ) );

define( "SNC_FACEBOOK_DEF_CACHE_LIMIT", get_option( "snc_cache_limit" ) ); // time for an individual cached item to expire (mins)
define( "SNC_FACEBOOK_DEF_CACHE_RESET", get_option( "snc_cache_reset" ) ); // time for all cached items to expire (mins)

// Instagram Application Client settings
$instagramSettings = array();
$instagramSettings["client_id"]		= esc_attr( get_option( "snc_instagram_client_id" ) );
$instagramSettings["client_secret"] = esc_attr( get_option( "snc_instagram_client_secret" ) );
$instagramSettings["access_token"] = esc_attr( get_option( "snc_instagram_access_token" ) );
$instagramSettings["access_token_status"] = esc_attr( get_option( "snc_instagram_access_token_status" ) );
$instagramSettings["grant_type"] 	= "authorization_code";
$instagramSettings["redirect_uri"] 	= site_url() . "/wp-admin/admin-ajax.php?action=snc_get_instagram_code";
$instagramSettings["user_redirect_uri"] 	= site_url() . "/wp-admin/admin-ajax.php?action=snc_instagram_oauth";
$instagramSettings["scope"] 		= array( 
										"basic",			// to read a user’s profile info and media
										"public_content",	// to read any public profile info and media on a user’s behalf
//										"follower_list",	// to read the list of followers and followed-by users
//										"comments",			// to post and delete comments on a user’s behalf
//										"relationships",	// to follow and unfollow accounts on a user’s behalf
//										"likes"				// to like and unlike media on a user’s behalf
									);

define( "SNC_INSTAGRAM_DEF_CACHE_LIMIT", get_option( "snc_cache_limit" ) ); // time for an individual cached item to expire (mins)
define( "SNC_INSTAGRAM_DEF_CACHE_RESET", get_option( "snc_cache_reset" ) ); // time for all cached items to expire (mins)

// Twitter Application Consumer & OAuth settings
$twitterSettings = array();
$twitterSettings["consumer_key"]				= esc_attr( get_option( "snc_twitter_consumer_key" ) );
$twitterSettings["consumer_secret"] 			= esc_attr( get_option( "snc_twitter_consumer_secret" ) );
$twitterSettings["oauth_access_token"] 			= esc_attr( get_option( "snc_twitter_oauth_access_token" ) );
$twitterSettings["oauth_access_token_secret"]	= esc_attr( get_option( "snc_twitter_oauth_access_token_secret" ) );
$twitterSettings["oauth_callback"] 				= site_url() . "/wp-admin/admin-ajax.php?action=snc_twitter_oauth";


define( "SNC_TWITTER_DEF_CACHE_LIMIT", get_option( "snc_cache_limit" ) ); // time for an individual cached item to expire (mins)
define( "SNC_TWITTER_DEF_CACHE_RESET", get_option( "snc_cache_reset" ) ); // time for all cached items to expire (mins)

define( "SNC_TWITTER_EXCLUDE_REPLIES", ( esc_attr( get_option( "snc_twitter_replies" ) ) == "include" ? false : true ) );
define( "SNC_TWITTER_INCLUDE_RETWEETS", ( esc_attr( get_option( "snc_twitter_retweets" ) ) == "include" ? true : false ) );
