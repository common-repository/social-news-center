<?php

/***************************
 * WORDPRESS PHP FUNCTIONS *
 ***************************/

/*
 * Function to get the remaining usage in an API for a particular User or App. Check will be made to ensure expired totals are not returned.
 * 
 * Rules:
 * a) No record in table then return empty array.
 * b) Expired record in table then return empty array.
 * c) Record exists but 0 not reached, return array of Amount Left & Reset Timestamp.
 * d) Record exists and 0 reached, return array of Amount Left & Reset Timestamp.
 * 
 * Calling code should only consider (d) as a status where a call to Social Media site should NOT be made.
 * 
 * Parameters:
 * - site				: Name of the Social Media site.
 * - resource			: Name of API resource within the API.
 * - accessToken		: User or Access Token used to access the API.
 * 
 * Return:
 * - list of remaining calls left and next reset timestamp if limit reached plus calling parameters.
 */
function snc_getApiUsageLeft ( $site, $resource, $accessToken ) {
	
	global $wpdb, $current_user;
	
	$usageLeft = array(); // resetTimestamp & amountLeft

	$sql = <<<EOF
		SELECT	remaining, 
				rate_reset 
		FROM	snc_api_usage_stats 
		WHERE	rate_reset > UNIX_TIMESTAMP() 
		AND		access_token = %s 
		AND		resource = %s 
		AND		site = %s 
		ORDER BY remaining ASC;
EOF;

	$wpdb->flush();
	$result = stripslashes_deep( $wpdb->get_results( $wpdb->prepare( $sql, $accessToken, $resource, $site ), ARRAY_A ) );
	//snc_setLogMessage( "functions-wp.php - snc_getApiUsageLeft() : SQL = " . $sql );

	// Process the results of the SQL query
	if ( $result ) {
		$row = $result[0];
		$usageLeft["site"] = $site;
		$usageLeft["resource"] = $resource;
		$usageLeft["accessToken"] = $accessToken;
		$usageLeft["amountLeft"] = $row["remaining"];
		$usageLeft["resetTimestamp"] = $row["rate_reset"];
	}
	
	return $usageLeft;
}

/*
 * Retrieve customs styles set within WordPress settings.
 *
 * Return:
 * - <style> tag containing custom settings.
 */ 
function snc_getCustomStylesWP () {
	$stylesHtml = "";
	
	$sncBaseUrl = SNC_BASE_URL;
	
	switch ( SNC_LAYOUT_STYLE ) {
		case "white":
			$stylesHtml .=<<< EOF
	<style>
		.sncHeader td {
			background-color: #FFFFFF;
			color: #000000;
		}
		.sncHeader a {
			color: #000000;
		}
		.sncSubHeader {
			color: #000000;
		}
		.sncSubHeader a {
			color: #000000;
		}
		.sncBody td {
			background-color: #FFFFFF;
			color: #000000;
		}
		.sncBody a {
			color: #000000;
		}
		.sncFooter td {
			background-color: #FFFFFF;
			color: #000000;
		}
		.sncFooter a {
			color: #000000;
		}
		#sncFavorite {
		   background-image: url('$sncBaseUrl/images/twitter/favorite_light.png');
		}
		#sncFavorite:hover {
		   background-image: url('$sncBaseUrl/images/twitter/favorite_dark.png');
		}
		#sncReply {
		   background-image: url('$sncBaseUrl/images/twitter/reply_light.png');
		}
		#sncReply:hover {
		   background-image: url('$sncBaseUrl/images/twitter/reply_dark.png');
		}
		#sncRetweet {
		   background-image: url('$sncBaseUrl/images/twitter/retweet_light.png');
		}
		#sncRetweet:hover {
		   background-image: url('$sncBaseUrl/images/twitter/retweet_dark.png');
		}
	</style>
EOF;
			break;
		case "black":
			$stylesHtml .=<<< EOF
	<style>
		.sncHeader td {
			background-color: #000000;
			color: #FFFFFF;
		}
		.sncHeader a {
			color: #FFFFFF;
		}
		.sncSubHeader {
			color: #FFFFFF;
		}
		.sncSubHeader a {
			color: #FFFFFF;
		}
		.sncBody td {
			background-color: #000000;
			color: #FFFFFF;
		}
		.sncBody a {
			color: #FFFFFF;
		}
		.sncFooter td {
			background-color: #000000;
			color: #FFFFFF;
		}
		.sncFooter a {
			color: #FFFFFF;
		}
		#sncFavorite {
		   background-image: url('$sncBaseUrl/images/twitter/favorite_dark.png');
		}
		#sncFavorite:hover {
		   background-image: url('$sncBaseUrl/images/twitter/favorite_light.png');
		}
		#sncReply {
		   background-image: url('$sncBaseUrl/images/twitter/reply_dark.png');
		}
		#sncReply:hover {
		   background-image: url('$sncBaseUrl/images/twitter/reply_light.png');
		}
		#sncRetweet {
		   background-image: url('$sncBaseUrl/images/twitter/retweet_dark.png');
		}
		#sncRetweet:hover {
		   background-image: url('$sncBaseUrl/images/twitter/retweet_light.png');
		}
	</style>
EOF;
			break;
		case "red":
			$stylesHtml .=<<< EOF
	<style>
		.sncHeader td {
			background-color: #c93d3d;
			color: #FFFFFF;
		}
		.sncHeader a {
			color: #FFFFFF;
		}
		.sncSubHeader {
			color: #FFFFFF;
		}
		.sncSubHeader a {
			color: #FFFFFF;
		}
		.sncBody td {
			background-color: #d7b8b8;
			color: #000000;
		}
		.sncBody a {
			color: #000000;
		}
		.sncFooter td {
			background-color: #c95f5f;
			color: #FFFFFF;
		}
		.sncFooter a {
			color: #FFFFFF;
		}
		#sncFavorite {
		   background-image: url('$sncBaseUrl/images/twitter/favorite_white.png');
		}
		#sncFavorite:hover {
		   background-image: url('$sncBaseUrl/images/twitter/favorite_black.png');
		}
		#sncReply {
		   background-image: url('$sncBaseUrl/images/twitter/reply_white.png');
		}
		#sncReply:hover {
		   background-image: url('$sncBaseUrl/images/twitter/reply_black.png');
		}
		#sncRetweet {
		   background-image: url('$sncBaseUrl/images/twitter/retweet_white.png');
		}
		#sncRetweet:hover {
		   background-image: url('$sncBaseUrl/images/twitter/retweet_black.png');
		}
	</style>
EOF;
			break;
		case "green":
			$stylesHtml .=<<< EOF
	<style>
		.sncHeader td {
			background-color: #317231;
			color: #FFFFFF;
		}
		.sncHeader a {
			color: #FFFFFF;
		}
		.sncSubHeader {
			color: #FFFFFF;
		}
		.sncSubHeader a {
			color: #FFFFFF;
		}
		.sncBody td {
			background-color: #b3cfb3;
			color: #000000;
		}
		.sncBody a {
			color: #000000;
		}
		.sncFooter td {
			background-color: #487248;
			color: #FFFFFF;
		}
		.sncFooter a {
			color: #FFFFFF;
		}
		#sncFavorite {
		   background-image: url('$sncBaseUrl/images/twitter/favorite_white.png');
		}
		#sncFavorite:hover {
		   background-image: url('$sncBaseUrl/images/twitter/favorite_black.png');
		}
		#sncReply {
		   background-image: url('$sncBaseUrl/images/twitter/reply_white.png');
		}
		#sncReply:hover {
		   background-image: url('$sncBaseUrl/images/twitter/reply_black.png');
		}
		#sncRetweet {
		   background-image: url('$sncBaseUrl/images/twitter/retweet_white.png');
		}
		#sncRetweet:hover {
		   background-image: url('$sncBaseUrl/images/twitter/retweet_black.png');
		}
	</style>
EOF;
			break;
		case "blue":
			$stylesHtml .=<<< EOF
	<style>
		.sncHeader td {
			background-color: #3b5ed0;
			color: #FFFFFF;
		}
		.sncHeader a {
			color: #FFFFFF;
		}
		.sncSubHeader {
			color: #FFFFFF;
		}
		.sncSubHeader a {
			color: #FFFFFF;
		}
		.sncBody td {
			background-color: #c0c8e1;
			color: #000000;
		}
		.sncBody a {
			color: #000000;
		}
		.sncFooter td {
			background-color: #6f86cf;
			color: #FFFFFF;
		}
		.sncFooter a {
			color: #FFFFFF;
		}
		#sncFavorite {
		   background-image: url('$sncBaseUrl/images/twitter/favorite_white.png');
		}
		#sncFavorite:hover {
		   background-image: url('$sncBaseUrl/images/twitter/favorite_black.png');
		}
		#sncReply {
		   background-image: url('$sncBaseUrl/images/twitter/reply_white.png');
		}
		#sncReply:hover {
		   background-image: url('$sncBaseUrl/images/twitter/reply_black.png');
		}
		#sncRetweet {
		   background-image: url('$sncBaseUrl/images/twitter/retweet_white.png');
		}
		#sncRetweet:hover {
		   background-image: url('$sncBaseUrl/images/twitter/retweet_black.png');
		}
	</style>
EOF;
			break;
		default:
			break;
	}
	
	return $stylesHtml;
}
	
/*
 * Retrieve access credentials for a site using the Cookie.
 *
 * Parameters:
 * - cookie			: Cookie sent by User's browser.
 * 
 * Return:
 * - List of site access credentials.
 */ 
function snc_getSiteCredsByCookie ( $cookie ) {
	
	global $wpdb, $current_user;
	
	$credList = array();

	$sql = <<<EOF
		SELECT	ua.cookie, 
				ua.token, 
				ua.secret, 
				ua.network_id, 
				n.name, 
				ua.user_id, 
				ua.username 
		FROM	snc_user_access ua, 
				snc_networks n 
		WHERE	ua.network_id = n.id 
		AND		ua.cookie = %s;
EOF;

	$wpdb->flush();
	$result = stripslashes_deep( $wpdb->get_results( $wpdb->prepare( $sql, $cookie ), ARRAY_A ) );
	//snc_setLogMessage( "functions-wp.php - snc_getSiteCredsByCookie() : SQL = " . $sql );
		
	// Process the results of the SQL query
	if ( $result ) {
		$row = $result[0];
		$credList["cookie"] = $row["cookie"];
		$credList["token"] = $row["token"];
		$credList["secret"] = $row["secret"];
		$credList["networkId"] = $row["network_id"];
		$credList["site"] = $row["name"];
		$credList["userId"] = $row["user_id"];
		$credList["username"] = $row["username"];
	}
		
	return $credList;
}

/*
 * Retrieve access credentials for a site using the Access Token & Secret.
 *
 * Parameters:
 * - token			: Access Token provided by Social Media site.
 * - secret			: Access Token Secret provided by Social Media site.
 * 
 * Return:
 * - List of site access credentials.
 */ 
function snc_getSiteCredsByToken ( $token, $secret ) {
	
	global $wpdb, $current_user;

	$credList = array();

	$sql = <<<EOF
		SELECT	ua.cookie, 
				ua.token, 
				ua.secret, 
				ua.network_id, 
				n.name, 
				ua.user_id, 
				ua.username 
		FROM	snc_user_access ua, 
				snc_networks n 
		WHERE	ua.network_id = n.id 
		AND		ua.token = %s 
		AND		ua.secret = %s;
EOF;

	$wpdb->flush();
	$result = stripslashes_deep( $wpdb->get_results( $wpdb->prepare( $sql, $token, $secret ), ARRAY_A ) );
	//snc_setLogMessage( "functions-wp.php - snc_getSiteCredsByToken() : SQL = " . $sql );
		
	// Process the results of the SQL query
	if ( $result ) {
		$row = $result[0];
		$credList["cookie"] = $row["cookie"];
		$credList["token"] = $row["token"];
		$credList["secret"] = $row["secret"];
		$credList["networkId"] = $row["network_id"];
		$credList["site"] = $row["name"];
		$credList["userId"] = $row["user_id"];
		$credList["username"] = $row["username"];
	}
		
	return $credList;
}

/*
 * Retrieve access credentials for a site using the Network and User.
 *
 * Parameters:
 * - site			: Name of Social Media site.
 * - username		: User Name/ID for Social Media site.
 * 
 * Return:
 * - List of site access credentials.
 */ 
function snc_getSiteCredsByUser ( $site, $username ) {
	
	global $wpdb, $current_user;

	$credList = array();

	$sql = <<<EOF
		SELECT	ua.cookie, 
				ua.token, 
				ua.secret, 
				ua.network_id, 
				n.name, 
				ua.user_id, 
				ua.username 
		FROM	snc_user_access ua, 
				snc_networks n 
		WHERE	n.name = %s 
		AND		ua.network_id = n.id 
		AND		ua.username = %s;
EOF;

	$wpdb->flush();
	$result = stripslashes_deep( $wpdb->get_results( $wpdb->prepare( $sql, $site, $username ), ARRAY_A ) );
	//snc_setLogMessage( "functions-wp.php - snc_getSiteCredsByUser() : SQL = " . $sql );
		
	// Process the results of the SQL query
	if ( $result ) {
		$row = $result[0];
		$credList["cookie"] = $row["cookie"];
		$credList["token"] = $row["token"];
		$credList["secret"] = $row["secret"];
		$credList["networkId"] = $row["network_id"];
		$credList["site"] = $row["name"];
		$credList["userId"] = $row["user_id"];
		$credList["username"] = $row["username"];
	}
		
	return $credList;
}

/*
 * Log API usage statistics for later analysis in case of limits being exceeded.
 *
 * Parameters:
 * - site			: Name of Social Network e.g. Facebook, Twitter.
 * - callingFunc	: Function from which the call was made.
 * - resource		: Type of resource being used.
 * - query			: Query string used to interrogate the Social Network.
 * - accessToken	: Access Token identifier of the User otherwise of the Application.
 * - status			: HTTP response header status code.
 * - rateLimit		: Limit for the resource.
 * - remaining		: Amount remaining within Rate Limit.
 * - rateReset		: Time left until Rate Limit is reset.
 */ 
function snc_setLogApiUsageStats ( $site, $callingFunc, $resource, $query, $accessToken, $status, $rateLimit, $remaining, $rateReset ) {

	global $wpdb, $current_user;
	
	// Retrieve news articles
	$sql = <<<EOF
		INSERT INTO snc_api_usage_stats (
				site,
				calling_function,
				resource,
				query,
				access_token,
				status,
				rate_limit,
				remaining,
				rate_reset,
				logged
		) 
		VALUES ( %s, %s, %s, %s, %s, %s, %d, %d, %d, NOW() );
EOF;

	$wpdb->flush();
	$result = $wpdb->query( $wpdb->prepare( $sql, $site, $callingFunc, $resource, $query, $accessToken, $status, $rateLimit, $remaining, $rateReset ) );
}

/*
 * Save access credentials for a site.
 *
 * Parameters:
 * - token			: Access Token.
 * - secret			: Access Token Secret.
 * - site			: Name of Social Media site.
 * - username		: User Name/ID for Social Media site.
 * 
 * Return:
 * - Generated or regenerated cookie.
 */ 
function snc_setSiteCredentials ( $token, $secret, $site, $username ) {
	
	global $wpdb, $current_user;
	
	$cookie = "";

	$insertSql = <<<EOF
		INSERT INTO snc_user_access (
				cookie, 
				token, 
				secret, 
				network_id, 
				user_id, 
				username
		) 
		SELECT	%s, %s, %s, n.id, 0, %s 
		FROM	snc_networks n 
		WHERE	n.name = %s;
EOF;

	$updateSql = <<<EOF
		UPDATE	snc_user_access 
		SET		cookie = %s,
				token = %s,
				secret = %s 
		WHERE	network_id = %d 
		AND		username = %s;
EOF;

	snc_setLogMessage( "functions-wp.php - snc_setSiteCredentials() : params ($token, $secret, $site, $username)" );

	// Check if Site Credentials already exist
	$credsList = snc_getSiteCredsByToken( $token, $secret );
	snc_setLogMessage( "functions-wp.php - snc_setSiteCredentials() : credsList via snc_getSiteCredsByToken() = " . print_r( $credsList, true ) );
	
	if ( !empty( $credsList ) && $credsList["cookie"] != "" ) {
		$cookie = $credsList["cookie"];
		snc_setLogMessage( "functions-wp.php - snc_setSiteCredentials() : cookie retrieved = " . $cookie );
	} else {
		
		// Check if User entry already exists
		$credsList = snc_getSiteCredsByUser( $site, $username );
		snc_setLogMessage( "functions-wp.php - snc_setSiteCredentials() : credsList via snc_getSiteCredsByUser() = " . print_r( $credsList, true ) );

		// Generate new cookie
		snc_setLogMessage( "functions-wp.php - snc_setSiteCredentials() : calling password_hash()" );
		$cookie = password_hash( $site . $username, PASSWORD_BCRYPT );
		snc_setLogMessage( "functions-wp.php - snc_setSiteCredentials() : cookie = " . $cookie );
			
		if ( !empty( $credsList ) && $credsList["networkId"] > 0 && $credsList["username"] != "" ) {
			//snc_setLogMessage( "functions-wp.php - snc_setSiteCredentials() : update" );

			// Update existing record
			$wpdb->flush();
			$result = $wpdb->query( $wpdb->prepare( $updateSql, $cookie, $token, $secret, $credsList["networkId"], $credsList["username"] ) );
			snc_setLogMessage( "functions-wp.php - snc_setSiteCredentials() : update SQL = " . $updateSql );
		} else {
			//snc_setLogMessage( "functions-wp.php - snc_setSiteCredentials() : insert" );

			// Create new record
			$wpdb->flush();
			$result = $wpdb->query( $wpdb->prepare( $insertSql, $cookie, $token, $secret, $username, $site ) );
			snc_setLogMessage( "functions-wp.php - snc_setSiteCredentials() : insert SQL = " . $insertSql );
		}
	}
		
	return $cookie;
}

/*
 * Add menu link to Admin Setting page.
 */ 
function snc_setAdminSettingsMenu () {
	add_menu_page( 
		'Social News Center',			// Page title – used in the title tag of the page (shown in the browser bar) when it is displayed.
		'Social News Center', 			// Menu title – used in the menu on the left.
		'administrator', 				// Capability – the user level allowed to access the page.
		'social-news-center-settings', 	// Menu slug – the slug used for the page in the URL.
		'snc_getAdminSettingsPage', 	// Function – the name of the function you will be using to output the content of the page.
		'dashicons-admin-generic' 		// Icon – A url to an image or a Dashicons string.
										// Position – The position of your item within the whole menu.
	);
}
add_action( 'admin_menu', 'snc_setAdminSettingsMenu' );

/*
 * Register options for Admin Settings.
 */ 
function snc_setAdminSettingsInit () {
	register_setting( 'snc-settings-group', 'snc_posts_per_account_main' );
	register_setting( 'snc-settings-group', 'snc_posts_per_account_popup' );
	register_setting( 'snc-settings-group', 'snc_posts_total_maximum' );
	register_setting( 'snc-settings-group', 'snc_cache_limit' );
	register_setting( 'snc-settings-group', 'snc_cache_reset' );
	register_setting( 'snc-settings-group', 'snc_layout_style' );
	register_setting( 'snc-settings-group', 'snc_default_cookie_timeout' );
	register_setting( 'snc-settings-group', 'snc_facebook_status' );
	register_setting( 'snc-settings-group', 'snc_facebook_app_id' );
	register_setting( 'snc-settings-group', 'snc_facebook_secret' );
	register_setting( 'snc-settings-group', 'snc_instagram_status' );
	register_setting( 'snc-settings-group', 'snc_instagram_client_id' );
	register_setting( 'snc-settings-group', 'snc_instagram_client_secret' );
	register_setting( 'snc-settings-group', 'snc_instagram_access_token' );
	register_setting( 'snc-settings-group', 'snc_instagram_access_token_status' );
	register_setting( 'snc-settings-group', 'snc_twitter_status' );
	register_setting( 'snc-settings-group', 'snc_twitter_consumer_key' );
	register_setting( 'snc-settings-group', 'snc_twitter_consumer_secret' );
	register_setting( 'snc-settings-group', 'snc_twitter_oauth_access_token' );
	register_setting( 'snc-settings-group', 'snc_twitter_oauth_access_token_secret' );
	register_setting( 'snc-settings-group', 'snc_twitter_replies' );
	register_setting( 'snc-settings-group', 'snc_twitter_retweets' );
}
add_action( 'admin_init', 'snc_setAdminSettingsInit' );

/*
 * Generate HTML for Admin Settings page.
 */ 
function snc_getAdminSettingsPage () {
	
	global $instagramSettings;
	$redirectUri = $instagramSettings["redirect_uri"];
	
	// URL for popup that generates the Access Token for Instagram
	$instagramAccessTokenWizardUrl = get_site_url() . "/wp-admin/admin-ajax.php?action=snc_get_instagram_access_token";

	// Determine which Instagram button should be displayed plus the associated function
	$instagramButtonTxt = "Login";
	if ( !empty( $_SESSION['instagram_access_token'] ) ) {
		$instagramButtonTxt = "Generate";
	}

	// Define settings (section, setting name, row title, comment)
	$sncAdminSettingsList = array();
	$sncAdminSettingsList[] = array( "General", "snc_posts_per_account_main", "Posts per Account (main)", "displayed on main screen" );
	$sncAdminSettingsList[] = array( "General", "snc_posts_per_account_popup", "Posts per Account (popup)", "displayed in popups" );
	$sncAdminSettingsList[] = array( "General", "snc_posts_total_maximum", "Maximum posts displayed", "displayed for all accounts" );
	$sncAdminSettingsList[] = array( "General", "snc_cache_limit", "Cache limit (minutes)", "time for a cached item to expire" );
	$sncAdminSettingsList[] = array( "General", "snc_cache_reset", "Cache reset (minutes)", "time for a cached item to be reset <br />(must be greater than Cache limit)" );
	$sncAdminSettingsList[] = array( "General", "snc_default_cookie_timeout", "Cookie timeout (seconds)", "e.g. 86400 = 60 secs x 60 mins x 24 hrs" );
	$sncAdminSettingsList[] = array( "General", "snc_layout_style", "Layout style", "color scheme for item boxes" );
	$sncAdminSettingsList[] = array( "Facebook", "snc_facebook_status", "Status", "Enable/Disable network" );
	$sncAdminSettingsList[] = array( "Facebook", "snc_facebook_app_id", "App Id", "'FACEBOOK_APP_ID' field" );
	$sncAdminSettingsList[] = array( "Facebook", "snc_facebook_secret", "Secret", "'FACEBOOK_SECRET' field" );
	$sncAdminSettingsList[] = array( "Instagram", "snc_instagram_status", "Status", "Enable/Disable network" );
	$sncAdminSettingsList[] = array( "Instagram", "snc_instagram_client_id", "Client Id", "'Client ID' field" );
	$sncAdminSettingsList[] = array( "Instagram", "snc_instagram_client_secret", "Client Secret", "'Client Secret' field" );
	$sncAdminSettingsList[] = array( "Instagram", "snc_instagram_access_token", "Access Token", "'Access Token' field" );
	$sncAdminSettingsList[] = array( "Instagram", "snc_instagram_access_token_status", "Access Token validity", 
		"<input type=\"button\" id=\"instagramGenerateAccessTokenButton\" name=\"instagramGenerateAccessTokenButton\" value=\"$instagramButtonTxt\" 
			onClick=\"
				if ( document.getElementById( 'instagramGenerateAccessTokenButton' ).value == 'Login' ) {
					snc_doLoginInAdmin(
						'snc_instagram_client_id',
						'snc_instagram_client_secret',
						window.location.href
					);
				} else if ( document.getElementById( 'instagramGenerateAccessTokenButton' ).value == 'Generate' ) {
					snc_getInstagramCode( 
						'snc_instagram_client_id',
						'snc_instagram_client_secret',
						'snc_instagram_access_token',
						'snc_instagram_access_token_status',
						'snc_instagram_iframe',
						'$redirectUri'
					);
				}
				return false;
			\" />
		");
	$sncAdminSettingsList[] = array( "Instagram", "snc_instagram_iframe", "", "" );
	$sncAdminSettingsList[] = array( "Twitter", "snc_twitter_status", "Status", "Enable/Disable network" );
	$sncAdminSettingsList[] = array( "Twitter", "snc_twitter_consumer_key", "Consumer Key", "'consumer_key' field" );
	$sncAdminSettingsList[] = array( "Twitter", "snc_twitter_consumer_secret", "Consumer Secret", "'consumer_secret' field" );
	$sncAdminSettingsList[] = array( "Twitter", "snc_twitter_oauth_access_token", "OAuth Access Token", "'oauth_access_token' field" );
	$sncAdminSettingsList[] = array( "Twitter", "snc_twitter_oauth_access_token_secret", "OAuth Access Token Secret", "'oauth_access_token_secret' field" );
	$sncAdminSettingsList[] = array( "Twitter", "snc_twitter_replies", "Replies", "Include/Exclude replies to posts" );
	$sncAdminSettingsList[] = array( "Twitter", "snc_twitter_retweets", "Retweets", "Include/Exclude retweets of posts" );

	$pluginUrl = plugins_url();
	
	// Render HTML for Admin Settings page
	echo <<<EOF
	<div class="wrap">
		<h2>Social News Center settings</h2>
		
		<p>For further information on the settings below please refer to the file <a href="$pluginUrl/social-news-center/readme.txt" target="_blank">readme.txt</a> within the plugin folder.</p>

		<form method="post" id="sncAdminSettingsForm" name="sncAdminSettingsForm" action="options.php">
EOF;

	// WordPress functions to setup a settings form on an Admin page
	settings_fields( 'snc-settings-group' );
	do_settings_sections( 'snc-settings-group' );

	echo <<<EOF
			<table class="form-table">
EOF;

	$prevSection = "";
	foreach ( $sncAdminSettingsList as $indivSetting ) {
		
		$currSection = strtoupper( $indivSetting[0] );
		$currSectionLower = strtolower( $indivSetting[0] );
		$emptyCacheButtonName = $currSectionLower . "EmptyCacheButton";
		$testButtonName = $currSectionLower . "TestButton";
		$iconUrl = str_replace( "includes/", "", plugins_url( "images/icon_" . $currSectionLower . ".png", __FILE__ ) );

		// Start of new section
		if ( $currSection != $prevSection && $prevSection != "" ) {
			echo <<<EOF
				<tr><td colspan="3"><hr width="100%" /></td></tr>
				<tr valign="top">
					<td scope="row"><img src="$iconUrl" />&nbsp;<strong>$currSection</strong></td>
					<td>
						<input type="button" id="$emptyCacheButtonName" name="$emptyCacheButtonName" value="Empty Cache" onClick="snc_doEmptyCache('$currSectionLower');" />
						&nbsp;
						<input type="button" id="$testButtonName" name="$testButtonName" value="Test" onClick="snc_doTestSocialMediaSettings('$currSectionLower', this.form.id);" />
					</td>
					<td><em>Test '$currSectionLower' API settings</em></th>
				</tr>
EOF;
		} else if ( $currSection != $prevSection ) {
			echo <<<EOF
				<tr valign="top">
					<td scope="row" colspan="3"><strong>$currSection</strong></td>
				</tr>
EOF;
		}

		$key = $indivSetting[1];
		$title = $indivSetting[2];
		$comment = $indivSetting[3];
		if ( $comment != "" ) {
			$comment = "<em>" . $comment . "</em>";
		}
		$value = esc_attr( get_option( $key ) );

		// Determine which type of form field to use
		$fieldHtml = "";
		
		switch ( $key ) {
			case "snc_facebook_status":
			case "snc_instagram_status":
			case "snc_twitter_status":
				if ( $value == "enabled" ) {
					$fieldHtml .=<<<EOF
<input type="radio" id="$key" name="$key" value="enabled" checked="CHECKED" />Enabled 
<input type="radio" id="$key" name="$key" value="disabled" />Disabled
EOF;
				} else {
					$fieldHtml .=<<<EOF
<input type="radio" id="$key" name="$key" value="enabled" />Enabled 
<input type="radio" id="$key" name="$key" value="disabled" checked="CHECKED" />Disabled
EOF;
				}
				break;
			case "snc_twitter_replies":
			case "snc_twitter_retweets":
				if ( $value == "include" ) {
					$fieldHtml .=<<<EOF
<input type="radio" id="$key" name="$key" value="include" checked="CHECKED" />Include 
<input type="radio" id="$key" name="$key" value="exclude" />Exclude
EOF;
				} else {
					$fieldHtml .=<<<EOF
<input type="radio" id="$key" name="$key" value="include" />Include 
<input type="radio" id="$key" name="$key" value="exclude" checked="CHECKED" />Exclude
EOF;
				}
				break;
			case "snc_instagram_access_token":
			case "snc_instagram_access_token_status":
				$keyAlt = $key . "_alt";
				$fieldHtml .=<<<EOF
<input type="hidden" id="$key" name="$key" value="$value" />
<input type="text" id="$keyAlt" name="$keyAlt" value="$value" disabled />
EOF;
				break;
			case "snc_instagram_iframe":
				$fieldHtml .=<<<EOF
<iframe width="0" height="0" scrolling="no" frameBorder="0" id="$key" name="$key" 
	onload="
		if ( this.src != '' && this.contentDocument != null && this.contentDocument.getElementById( 'snc_instagram_code' ) != 'undefined' ) {
			snc_getInstagramAccessToken( 
				'snc_instagram_client_id',
				'snc_instagram_client_secret',
				'snc_instagram_access_token',
				'snc_instagram_access_token_status',
				this.contentDocument.getElementById( 'snc_instagram_code' ).value
			);
		}
		return false;
	">Browser not compatible.</iframe>
EOF;
				break;
			case "snc_posts_per_account_main":
			case "snc_posts_per_account_popup":
			case "snc_posts_total_maximum":
				$fieldHtml .=<<<EOF
<select id="$key" name="$key">
EOF;
				for ( $i=1; $i<=20; $i++ ) {
					$selectedTxt = "";
					if ( $value == $i ) {
						$selectedTxt = " selected=\"SELECTED\"";
					}
					$fieldHtml .=<<<EOF
	<option value="$i"$selectedTxt>$i</option>
EOF;
				}
				$fieldHtml .=<<<EOF
</select>
EOF;
				break;
			case "snc_layout_style":
				$fieldHtml .=<<<EOF
<select id="$key" name="$key">
EOF;
				$layoutStyleList = array( "white", "neutral", "black", "red", "green", "blue" );
				foreach ( $layoutStyleList as $layoutStyle ) {
					$selectedTxt = "";
					if ( $value == $layoutStyle ) {
						$selectedTxt = " selected=\"SELECTED\"";
					}
					$fieldHtml .=<<<EOF
	<option value="$layoutStyle"$selectedTxt>$layoutStyle</option>
EOF;
				}
				$fieldHtml .=<<<EOF
</select>
EOF;
				break;
			default:
				$fieldHtml .=<<<EOF
<input type="text" id="$key" name="$key" value="$value" />
EOF;
				break;
		}
		
		// Generate HTML table row
		$rowTitle = $title;
		if ( $title != "" ) {
			$rowTitle = "- " . $title . ":";
		}
		 
		echo <<<EOF
				<tr valign="top">
					<td scope="row">$rowTitle</td>
					<td>
						$fieldHtml
					</td>
					<td>$comment</th>
				</tr>
EOF;

		$prevSection = $currSection;
	}

	echo <<<EOF
			</table>
EOF;
		
		submit_button();

	echo <<<EOF
		</form>
	</div>
EOF;
}

/**
 * Redirect WordPress front end https URLs to http without a plugin
 *
 * Necessary when running forced SSL in admin and you don't want links to the front end to remain https.
 *
 * @link http://blackhillswebworks.com/?p=5088
 */
function snc_doBhwwSslTemplateRedirect() {

	if ( is_ssl() && ! is_admin() ) {
		if ( 0 === strpos( $_SERVER['REQUEST_URI'], 'http' ) ) {
			wp_redirect( preg_replace( '|^https://|', 'http://', $_SERVER['REQUEST_URI'] ), 301 );
			exit();
		} else {
			wp_redirect( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301 );
			exit();
		}
	}
}
add_action( 'template_redirect', 'snc_doBhwwSslTemplateRedirect', 1 );
