<?php

// If uninstall is not called from WordPress, exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

// Remove options
$sncOptionsList = array(
	'snc_db_version',
	'snc_posts_per_account_main',
	'snc_posts_per_account_popup',
	'snc_posts_total_maximum',
	'snc_cache_limit',
	'snc_cache_reset',
	'snc_default_cookie_timeout',
	'snc_layout_style',
	'snc_facebook_status',
	'snc_facebook_app_id',
	'snc_facebook_secret',
	'snc_instagram_status',
	'snc_instagram_client_id',
	'snc_instagram_client_secret',
	'snc_instagram_access_token',
	'snc_instagram_access_token_status',
	'snc_twitter_status',
	'snc_twitter_consumer_key',
	'snc_twitter_consumer_secret',
	'snc_twitter_oauth_access_token',
	'snc_twitter_oauth_access_token_secret',
	'snc_twitter_replies',
	'snc_twitter_retweets',
);

foreach ( $sncOptionsList as $optionName ) {
	delete_option( $optionName );
}
 
// For site options in Multisite
//delete_site_option( $option_name );  
 
// Drop custom database tables
global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS snc_api_usage_stats;" );
$wpdb->query( "DROP TABLE IF EXISTS snc_networks;" );
$wpdb->query( "DROP TABLE IF EXISTS snc_user_access;" );
