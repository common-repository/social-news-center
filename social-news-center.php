<?php

/**
 * Plugin Name: Social News Center
 * Plugin URI: https://wordpress.org/plugins/social-news-center
 * Description: Social News Center plugin that displays the latest Posts from Social Media sites such as Facebook, Instagram & Twitter for specified Pages & Accounts. Page and User Profiles can also be viewed together with support for performing actions such as Like, Share, Favorite, Retweet and more.
 * Version: 0.0.8
 * Author: P.R.Gowling
 * Author URI: https://twitter.com/pgowling
 * Text Domain: social-news-center
 * License: GPL2
 */

/*  Copyright 2014  P.R.Gowling  (email : info@beerinfinity.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

defined( 'ABSPATH' ) or die( "You do not have permission to access this item!" );

require_once( plugin_dir_path( __FILE__ ) . "includes/config.php" );
require_once( plugin_dir_path( __FILE__ ) . "includes/functions.php" );

// Include Social Media functions that will be used as a result of AJAX calls
//if ( SNC_FACEBOOK_FLAG ) {
	require_once( plugin_dir_path( __FILE__ ) . "facebook.php" );
//}
//if ( SNC_INSTAGRAM_FLAG ) {
	require_once( plugin_dir_path( __FILE__ ) . "instagram.php" );
//}
//if ( SNC_TWITTER_FLAG ) {
	require_once( plugin_dir_path( __FILE__ ) . "twitter.php" );
//}

require_once( plugin_dir_path( __FILE__ ) . "twitter/twitteroauth/autoload.php" );

use Abraham\TwitterOAuth\TwitterOAuth;

/*
 * Function to define own CSS and Javascript files for Admin Options pages.
 */
function snc_addAdminCssAndJsFiles () {
	snc_addCssAndJsFiles();
}
add_action( 'admin_enqueue_scripts', 'snc_addAdminCssAndJsFiles' ); 

/*
 * Function to define own CSS and Javascript files.
 */
function snc_addCssAndJsFiles () {

	//snc_setLogMessage( "snc_addCssAndJsFiles():" );

	// Load style sheets
	wp_enqueue_style( "snc-css-magnific", plugins_url( "css/magnific-popup.css", __FILE__ ) );
	wp_enqueue_style( "snc-css-style", plugins_url( "css/style.css", __FILE__ ) );
    
	// Embed Isotope Javascript functions to manage display info in boxes
	wp_enqueue_script( "snc-js-isotope", plugins_url( "js/isotope.pkgd.min.js", __FILE__ ), array( "jquery" ) );

	// Embed Javascript functions to check that images have been loaded in browser
	wp_enqueue_script( "snc-js-imagesloaded", plugins_url( "js/imagesloaded.pkgd.min.js", __FILE__ ), array( "jquery" ) );

	// Embed Magnific Popup Javascript functions
	wp_enqueue_script( "snc-js-magnific-popup", plugins_url( "js/jquery.magnific-popup.min.js", __FILE__ ), array( "jquery" ) );

	// Embed application specific Javascript functions
	wp_enqueue_script( "snc-js-functions", plugins_url( "js/functions.js", __FILE__ ), array( "jquery" ) );
	wp_enqueue_script( "snc-js-functions-wp", plugins_url( "js/functions-wp.js", __FILE__ ), array( "snc-js-functions" ) );
	
	// Embed Social Network specific Javascript functions
	if ( SNC_FACEBOOK_FLAG ) {
		wp_enqueue_script( "snc-js-facebook", plugins_url( "js/facebook.js", __FILE__ ), array( "snc-js-functions" ) );
	}
	if ( SNC_INSTAGRAM_FLAG ) {
		wp_enqueue_script( "snc-js-instagram", plugins_url( "js/instagram.js", __FILE__ ), array( "snc-js-functions" ) );
	}
	if ( SNC_TWITTER_FLAG ) {
		wp_enqueue_script( "snc-js-twitter", plugins_url( "js/twitter.js", __FILE__ ), array( "snc-js-functions" ) );
	}

	// Setup Ajax script according to HTTP or HTTPS on server
	$pluginsUrl = plugins_url();
	$httpTxt = substr( $pluginsUrl, strpos( $pluginsUrl, "://" ) );
	wp_localize_script( "snc-js-functions-wp", "the_ajax_script", array( "ajax_url" => admin_url( "admin-ajax.php", $httpTxt ) ) );
}
add_action( 'wp_enqueue_scripts', 'snc_addCssAndJsFiles' ); 

/*
 * Function to display Facebook Posts.
 * 
 * Parameters:
 * - pageIds 			: List of Page Profiles (needs to include preceeding '/' e.g. /Beer2Infinity).
 * - numPostsPerAccount	: Max number of Posts to display per Social Media account.
 * - maxNumPosts		: Max number of Posts to display per Social Media network.
 * 
 * Return:
 * - generated HTML.
 */
function snc_getFacebookPosts ( $pageIds, $numPostsPerAccount = SNC_DEFAULT_NUM_POSTS, $maxNumPosts = SNC_MAX_NUM_POSTS ) {

	global $wpdb, $current_user;
	
	snc_setLogMessage( "snc_getFacebookPosts():" );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	$content = "";
	
	// Setup JSON of parameters to call Twitter
	$postsParams = array(
		"target" => "sncIsotope",
		"path" => SNC_BASE_URL,
		"site" => "facebook",
		"ids" => $pageIds,
		"numPosts" => $numPostsPerAccount,
		"maxPosts" => $maxNumPosts,
		"infoType" => "posts",
	);

	$jsonParams = json_encode( $postsParams );
	
	$fbAppId = SNC_FACEBOOK_APP_ID;

	// https://developers.facebook.com/blog/post/2012/05/08/how-to--improve-the-experience-for-returning-users/
	
	$content .= <<<EOF
	<div id="fb-root"></div>
	<script type="text/javascript">
		window.fbAsyncInit = function() {
			FB.init({
				appId		: '$fbAppId',
				xfbml		: false,
				status		: true,
				cookie		: true,
				version		: 'v2.7'
			});

			// Check if the current user is logged in and has authorized the app then display the Facebook Posts
			FB.getLoginStatus(
				function ( response ) {
					snc_doCheckLoginStatus( response );
					snc_getSocialMediaPosts( $jsonParams );
				}, 
				true
			);
		};
	</script>
EOF;

	return( $content );
}
add_shortcode( 'sncFacebookPosts', 'snc_getFacebookPosts' );

/*
 * Function to process AJAX call to empty cache for Social Media network.
 * 
 * Return:
 * - JSON response.
 */
function snc_doEmptyCache_callback () {

	snc_setLogMessage( "snc_doEmptyCache_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Return any errors & data to calling script via this class
	$response = new stdClass();
	$response->emptied = false;
	$response->error = "";

	$site = "";

	// Retrieve parameters from call
	if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
		if ( isset( $_POST["site"] ) ) {
			$site = $_POST["site"];
		}
	}

	snc_setLogMessage( "site = $site" );

	// Empty cache for specified Social Media network
	if ( $site != "" ) {
		$folderName = SNC_BASE_PATH . "cache/$site/*";
		snc_setLogMessage( "snc_doEmptyCache_callback() Folder name = '$folderName'" );
		$fileList = glob( $folderName );
		if ( !empty( $fileList ) ) {
			$fileList = array_map( "unlink", $fileList );
			snc_setLogMessage( "snc_doEmptyCache_callback() Files deleted = " . print_r( $fileList, true ) );
		}
		$response->emptied = true;
	} else {
		snc_setLogMessage( "Social Media network not specified" );
		$response->error = "Social Media network not specified";
	}

	//snc_setLogMessage( print_r( json_encode( $response ), true ) );
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_empty_cache', 'snc_doEmptyCache_callback' );
add_action( 'wp_ajax_nopriv_snc_empty_cache', 'snc_doEmptyCache_callback' );

/*
 * Function to process AJAX calls for Facebook Posts.
 * 
 * Return:
 * - JSON response.
 */
function snc_getFacebookPosts_callback () {
	
	snc_setLogMessage( "snc_getFacebookPosts_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Calculate start time of call to Social Network
	$startTimes = explode( " ", microtime() );
	$startTime = $startTimes[1] + $startTimes[0];
	//snc_setLogMessage( "snc_getFacebookPosts_callback() - start timestamp = " . $startTime );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	// Initialise parameters
	extract(
		array(
			"pageIds" => array(),
			"numPosts" => 0,
			"maxPosts" => 0,
			"infoType" => "",
			"content" => "",
			"userToken" => "",
			"appToken" => "",
		)
	);

	// Retrieve User Token if logged in otherwise establish an App Token
	$userToken = snc_getFacebookUserToken( SNC_FACEBOOK_APP_ID, SNC_FACEBOOK_SECRET );
	if ( $userToken != "" ) {

		// Retrieve Facebook Session using User Token
		$session = snc_getFacebookSession( SNC_FACEBOOK_APP_ID, SNC_FACEBOOK_SECRET, $userToken );
	} else {

		// Retrieve Facebook Session
		$session = snc_getFacebookSession( SNC_FACEBOOK_APP_ID, SNC_FACEBOOK_SECRET );
		$appToken = snc_getFacebookAppToken( SNC_FACEBOOK_APP_ID, SNC_FACEBOOK_SECRET, $session );
	}

	snc_setLogMessage( "userToken = '$userToken'" );
	snc_setLogMessage( "appToken = '$appToken'" );
	snc_setLogMessage( "Session: " . print_r( $session, true ) );
	snc_setLogMessage( "POST: " . print_r( $_POST, true ) );

	// Retrieve parameters from call
	if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
		if ( isset( $_POST["infoType"] ) ) {
			$infoType = $_POST["infoType"];
		}
		if ( isset( $_POST["ids"] ) ) {
			$pageIds = $_POST["ids"];
		}
		if ( isset( $_POST["numPosts"] ) ) {
			$numPosts = $_POST["numPosts"];
		}
		if ( isset( $_POST["maxPosts"] ) ) {
			$maxPosts = $_POST["maxPosts"];
		}

		if ( $session && ( $userToken != "" || $appToken != "" ) ) {
			snc_setLogMessage( "snc_getFacebookPosts_callback() - infoType = " . $infoType );
			switch ( $infoType ) {
				case "post":

					// Call Facebook to retrieve Individual Post
					$content .= snc_getFacebookIndivPost( $session, $pageIds );
					break;
				case "posts":

					// Call Facebook to retrieve Posts
					$content .= snc_getFacebookPagePosts( $session, $pageIds, $numPosts, $maxPosts );
					break;
				case "profile":

					// Call Facebook to retrieve Profile
					$content .= snc_getFacebookPageProfile( $session, $pageIds );
					break;
			}
		}
	}

	// Calculate finish time of call to Social Network and time taken
	$finishTimes = explode( " ", microtime() );
	$finishTime = $finishTimes[1] + $finishTimes[0];
	//snc_setLogMessage( "snc_getFacebookPosts_callback() - finish timestamp = " . $finishTime );
	snc_setLogMessage( "snc_getFacebookPosts_callback() - time taken = " . ( $finishTime - $startTime ) );

	// Return any errors & data to calling script
	$response = new stdClass();

	if ( isset( $_SESSION["error"] ) ) {
		$response->error = $_SESSION["error"];
	} elseif ( isset( $_SESSION["warning"] ) ) {
		$response->warning = $_SESSION["warning"];
	} elseif ( isset( $_SESSION["message"] ) ) {
		$response->message = $_SESSION["message"];
	}
	snc_doResetSessionMessages();

	// Ensure UTF-8 encoding to cope with Japanese characters
	$response->data = mb_convert_encoding( $content, "UTF-8", "auto" );
	//snc_setLogMessage( "snc_getFacebookPosts_callback() - response = " . print_r( $response, true ) );

	/*
	 * Code for debugging JSON encoding issues due to special characters
	$xyz = json_encode( $response );
	$jsonErrorMsg = "";

    switch ( json_last_error() ) {
        case JSON_ERROR_NONE:
            $jsonErrorMsg = ' - No errors';
        break;
        case JSON_ERROR_DEPTH:
            $jsonErrorMsg = ' - Maximum stack depth exceeded';
        break;
        case JSON_ERROR_STATE_MISMATCH:
            $jsonErrorMsg = ' - Underflow or the modes mismatch';
        break;
        case JSON_ERROR_CTRL_CHAR:
            $jsonErrorMsg = ' - Unexpected control character found';
        break;
        case JSON_ERROR_SYNTAX:
            $jsonErrorMsg = ' - Syntax error, malformed JSON';
        break;
        case JSON_ERROR_UTF8:
            $jsonErrorMsg = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
        break;
        default:
            $jsonErrorMsg = ' - Unknown error';
        break;
    }
	snc_setLogMessage( "snc_getFacebookPosts_callback() - json_last_error() = " . $jsonErrorMsg );
	*/
	 
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_facebook_posts', 'snc_getFacebookPosts_callback' );
add_action( 'wp_ajax_nopriv_snc_facebook_posts', 'snc_getFacebookPosts_callback' );

/*
 * Function to process AJAX call for verifying Facebook credentials.
 * 
 * Return:
 * - JSON response.
 */
function snc_doFacebookVerifyCreds_callback () {

	snc_setLogMessage( "snc_doFacebookVerifyCreds_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Return any errors & data to calling script via this class
	$response = new stdClass();
	$response->verified = false;
	$response->error = "";

	// Facebook settings
	$fbAppId = "";
	$fbSecret = "";

	// Retrieve parameters from call
	if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
		if ( isset( $_POST["FACEBOOK_APP_ID"] ) ) {
			$fbAppId = $_POST["FACEBOOK_APP_ID"];
		}
		if ( isset( $_POST["FACEBOOK_SECRET"] ) ) {
			$fbSecret = $_POST["FACEBOOK_SECRET"];
		}
	}

	snc_setLogMessage( "fbAppId = $fbAppId, fbSecret = $fbSecret" );

	// Validate Facebook settings
	if ( $fbAppId != "" && $fbSecret != "" ) {
		
		// Retrieve Facebook Session
		$session = snc_getFacebookSession( $fbAppId, $fbSecret );
		
		// Retrieve App Token
		$appToken = snc_getFacebookAppToken( $fbAppId, $fbSecret, $session );
		
		if ( $appToken != "" ) {
			snc_setLogMessage( "checkFacebookSettings() = true (so App Access)" );
			$response->verified = true;
		} else {
			snc_setLogMessage( "Facebook Settings = false (so No App Access)" );
			$response->error = "Authorization failed";
			unset( $_SESSION['facebook_id'] );
		}
	} else {
		snc_setLogMessage( "Facebook Settings = false (so No App Access)" );
		$response->error = "Authorization failed";
		unset( $_SESSION['facebook_id'] );
	}

	//snc_setLogMessage( print_r( json_encode( $response ), true ) );
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_facebook_verify_creds', 'snc_doFacebookVerifyCreds_callback' );
add_action( 'wp_ajax_nopriv_snc_facebook_verify_creds', 'snc_doFacebookVerifyCreds_callback' );

/*
 * Function to display Social Media Posts.
 * 
 * Parameters:
 * - atts				: List of parameters i.e. an associative array:
 * - numPostsPerAccount	: Max number of Posts to display per Social Media account.
 * - maxNumPosts		: Max number of Posts to display for all Social Media accounts.
 * 
 * 		[sncSocialMediaPosts facebook="/Beer2Infinity" instagram="@placeboworld" twitter="@beer2infinity"]
 * 					or
 * 		[sncSocialMediaPosts facebook="/Beer2Infinity;/testScreenName" instagram="@placeboworld;@testAccountName" twitter="@beer2infinity;@testPageProfile"]
 * 					or
 * 		[sncSocialMediaPosts facebook="/Beer2Infinity;/testScreenName" instagram="@placeboworld;@testAccountName" twitter="@beer2infinity;@testPageProfile" header="Y"]
 * 					or
 * 		[sncSocialMediaPosts facebook="/Beer2Infinity;/testScreenName" instagram="@placeboworld;@testAccountName" twitter="@beer2infinity;@testPageProfile" header="N"]
 * 
 * Omitting 'header' parameter is the same as header = "Y" in that the <div> placement tag will be included.
 * 
 * Return:
 * - generated HTML.
 */
function snc_getSocialMediaPosts ( $atts, $numPostsPerAccount = SNC_DEFAULT_NUM_POSTS ) {

	// Reset log file
	//resetLog();

	snc_setLogMessage( "*** START - snc_getSocialMediaPosts() ***" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}
	
	snc_setLogMessage( "_SESSION = " . print_r( $_SESSION, true ) );
	snc_setLogMessage( "sessionId = " . session_id() . ", numPostsPerAccount = $numPostsPerAccount" );

	$content = "";
	
	// Apply custom styles set within WordPress or Standalone settings
	if ( defined( 'ABSPATH' ) ) {
		$content .= snc_getCustomStylesWP();
	} else {
		$content .= snc_getCustomStylesStd();
	}

	// Check for whether optional parameters set as WP shortcode does not set it
	if ( $numPostsPerAccount == "" ) {
		$numPostsPerAccount = SNC_DEFAULT_NUM_POSTS;
	}
	
	$maxNumPosts = SNC_MAX_NUM_POSTS;
	
	// Modify Max Number of Posts so it is per Network based on parameters supplied
	$networksCount = count( $atts );
	if ( array_key_exists( "header", $atts ) ) {
		$networksCount = $networksCount -1; // ignore 'header' parameter
	}
	if ( $networksCount > 1 ) {
		$maxNumPosts = intval( $maxNumPosts / $networksCount );
	}
	
	// Include Page Header HTML if indicated otherwise just have Error Message <div>
	if ( !isset( $atts["header"] ) || $atts["header"] == "Y" ) {
		$content .= snc_getPageHeaderHtml();
	} else {
		$content .= <<<EOF
	<div id="sncMsgTxt" align="center"></div>
	<div id="sncIsotope"></div>
EOF;
	}

	snc_setLogMessage( "snc_getSocialMediaPosts(): atts = " . print_r( $atts, true ) );

	// Process parameters
	if ( !empty( $atts ) ) {
		foreach ( $atts as $key => $value ) {
			
			// Convert parameter to lowercase
			$value = strtolower( $value );
			
			//$content .= "key = $key" . "<br />";
			//$content .= "value = $value" . "<br />";
			/*
			// Convert single parameter to an array
			if ( !is_array( $value ) ) {
				$value = array( $value );
			}
			*/
			$value = explode( ";", $value );
			//$content .= "value=" . print_r( $value, true ) . "<br />";
			
			// Call the appropriate Social Media network function
			switch ( strtolower( $key ) ) {
				case "entity":
				case "member":
					$content .= snc_getMixedPosts( $key, $value[0], $numPostsPerAccount, $maxNumPosts );
					snc_setLogMessage( "snc_getSocialMediaPosts(): call to get $key posts" );
					break;
				case "facebook":
					if ( SNC_FACEBOOK_FLAG ) {
						$content .= snc_getFacebookPosts( $value, $numPostsPerAccount, $maxNumPosts );
						snc_setLogMessage( "snc_getSocialMediaPosts(): call to get Facebook posts" );
					}
					break;
				case "instagram":
					if ( SNC_INSTAGRAM_FLAG ) {
						$content .= snc_getInstagramPosts( $value, $numPostsPerAccount, $maxNumPosts );
						snc_setLogMessage( "snc_getSocialMediaPosts(): call to get Instagram posts" );
					}
					break;
				case "twitter":
					if ( SNC_TWITTER_FLAG ) {
						$content .= snc_getTwitterPosts( $value, $numPostsPerAccount, $maxNumPosts );
						snc_setLogMessage( "snc_getSocialMediaPosts(): call to get Twitter posts" );
					}
					break;
				case "header":
					// do nothing
					break;
				default:
					snc_setLogMessage( "snc_getSocialMediaPosts(): unrecognised Social Media network = '$key'" );
					break;
			}
		}
	}

	snc_setLogMessage( "*** END - snc_getSocialMediaPosts() ***" );

	return( $content );
}
add_shortcode( 'sncSocialMediaPosts', 'snc_getSocialMediaPosts' );

/*
 * Function to display Instagram Posts.
 * 
 * Parameters:
 * - screenNames 		: List of Screen Names (needs to include preceeding '@' e.g. @placeboworld).
 * - numPostsPerAccount	: Max number of Posts to display per Social Media account.
 * - maxNumPosts		: Max number of Posts to display per Social Media network.
 * 
 * Return:
 * - generated HTML.
 */
function snc_getInstagramPosts ( $screenNames, $numPostsPerAccount = SNC_DEFAULT_NUM_POSTS, $maxNumPosts = SNC_MAX_NUM_POSTS ) {

	global $wpdb, $current_user;
	
	snc_setLogMessage( "snc_getInstagramPosts():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	$content = "";
	
	$pluginUrl = SNC_BASE_URL;
	
	// Setup JSON of parameters to call snc_getInstagramPosts
	$postsParams = array(
		"target" => "sncIsotope",
		"path" => $pluginUrl,
		"site" => "instagram",
		"ids" => $screenNames,
		"numPosts" => $numPostsPerAccount,
		"maxPosts" => $maxNumPosts,
		"infoType" => "posts",
	);

	$jsonParams = json_encode( $postsParams );
	
	// Determine whether or not the User is logged in re: Instagram so the right Login/Logout button can be displayed
	$loggedInFlag = "";
	if ( snc_getCookie( "snc_instagram_access_cookie" ) != "" ) {
		$loggedInFlag = "Y";
	}

	$content .= <<<EOF
	<script type="text/javascript">
		snc_getSocialMediaPosts( $jsonParams );
	</script>
EOF;

	return( $content );
}
add_shortcode( 'sncInstagramPosts', 'snc_getInstagramPosts' );

/*
 * Function to process AJAX calls for Instagram Posts.
 * 
 * Return:
 * - JSON response.
 */
function snc_getInstagramPosts_callback () {

	global $instagramSettings;
	
	snc_setLogMessage( "snc_getInstagramPosts_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Calculate start time of call to Social Network
	$startTimes = explode( " ", microtime() );
	$startTime = $startTimes[1] + $startTimes[0];
	//snc_setLogMessage( "snc_getInstagramPosts_callback() - start timestamp = " . $startTime );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	snc_setLogMessage( "sessionId = " . session_id() );

	// Initialise parameters
	extract(
		array(
			"screenNames" => array(),
			"numPosts" => 0,
			"maxPosts" => 0,
			"infoType" => "",
			"content" => "",
			"cookie" => "",
		)
	);

	// Return any errors & data to calling script via this class
	$response = new stdClass();
	$response->inLogin = false;

	$cookie = snc_getCookie( "snc_instagram_access_cookie" );
	snc_setLogMessage( "Cookie first: " . $cookie );

	// Retrieve User Access Token using cookie (if it already exists)
	if ( empty( $_SESSION['instagram_access_token'] ) && $cookie != "" ) {
		$credList = snc_getSiteCredsByCookie( $cookie );
		snc_setLogMessage( "credList = " . print_r( $credList, true ) );
		if ( isset( $credList["token"] ) && isset( $credList["username"] ) ) {
			$_SESSION['instagram_access_token'] = $credList["token"];
			$_SESSION['instagram_id'] = $credList["username"];
		} else {
			setCookie( "snc_instagram_access_cookie", "", time()-3600 );
		}
	}

	//snc_setLogMessage( "instagramSettings[] before: " . print_r( $instagramSettings, true ) );
	snc_setLogMessage( "SESSION() before: " . print_r( $_SESSION, true ) );
	snc_setLogMessage( "Cookie before: " . snc_getCookie( "snc_instagram_access_cookie" ) );

	// Determine whether User has authorized access to Instagram, and validate them, otherwise use App settings
	if ( !empty( $_SESSION['instagram_access_token'] ) ) {
		$userInstagramSettings = $instagramSettings;
		$userInstagramSettings['access_token'] = $_SESSION['instagram_access_token'];
		
		// Validate User Instagram settings
		if ( snc_doCheckInstagramSettings( $userInstagramSettings ) ) {
			snc_setLogMessage( "snc_doCheckInstagramSettings() = true (so User Access)" );
			$instagramSettings = $userInstagramSettings;
			
			// Save User Access Token for use when they return
			$cookie = snc_setSiteCredentials( $_SESSION['instagram_access_token'], "", "instagram", $_SESSION['instagram_id'] );
			setCookie( 'snc_instagram_access_cookie', $cookie, time() +SNC_COOKIE_TIMEOUT ); 
			$response->inLogin = true;
		} else {
			snc_setLogMessage( "snc_doCheckInstagramSettings() = false (so App Access)" );
			$_SESSION["warning"] = "Instagram authorization was Denied or Cancelled.";
			snc_setLogMessage( "snc_getInstagramPosts_callback() warning = " . $_SESSION["warning"] );
			
			// Authorization was Denied or Cancelled so remove existing Session variables & Cookies
			unset( $_SESSION['instagram_access_token'] );
			unset( $_SESSION['instagram_id'] );
			setCookie( "snc_instagram_access_cookie", "", time()-3600 );
		}
	}

	// Write to log file
	snc_setLogMessage( "instagramSettings[] after: " . print_r( $instagramSettings, true ) );
	snc_setLogMessage( "SESSION() after: " . print_r( $_SESSION, true ) );
	snc_setLogMessage( "Cookie after: >>>" . $cookie . "<<<" ); // cannot display via snc_getCookie() as not returned from AJAX call yet
	snc_setLogMessage( "POST() after: " . print_r( $_POST, true ) );

	// Retrieve parameters from call
	if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
		if ( isset( $_POST["infoType"] ) ) {
			$infoType = $_POST["infoType"];
		}
		if ( isset( $_POST["ids"] ) ) {
			$screenNames = $_POST["ids"];
		}
		if ( isset( $_POST["numPosts"] ) ) {
			$numPosts = $_POST["numPosts"];
		}
		if ( isset( $_POST["maxPosts"] ) ) {
			$maxPosts = $_POST["maxPosts"];
		}

		switch ( $infoType ) {
			case "post":

				// Call Instagram to retrieve Individual Media item with Comments
				$content = snc_getInstagramIndivMedia( $instagramSettings, $screenNames );
				break;
			case "posts":

				// Call Instagram to retrieve User Recent Media
				$content = snc_getInstagramMediaByUser( $instagramSettings, $screenNames, $numPosts, $maxPosts );
				break;
			case "profile":

				// Call Instagram to retrieve Profile
				$content = snc_getInstagramUserProfile( $instagramSettings, $screenNames );
				break;
		}
	}

	// Calculate finish time of call to Social Network and time taken
	$finishTimes = explode( " ", microtime() );
	$finishTime = $finishTimes[1] + $finishTimes[0];
	//snc_setLogMessage( "snc_getInstagramPosts_callback() - finish timestamp = " . $finishTime );
	snc_setLogMessage( "snc_getInstagramPosts_callback() - time taken = " . ( $finishTime - $startTime ) );

	if ( isset( $_SESSION["error"] ) ) {
		$response->error = $_SESSION["error"];
	} elseif ( isset( $_SESSION["warning"] ) ) {
		$response->warning = $_SESSION["warning"];
	} elseif ( isset( $_SESSION["message"] ) ) {
		$response->message = $_SESSION["message"];
	}
	snc_doResetSessionMessages();

	$response->data = $content;
	//snc_setLogMessage( print_r( json_encode( $response ), true ) );
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_instagram_posts', 'snc_getInstagramPosts_callback' );
add_action( 'wp_ajax_nopriv_snc_instagram_posts', 'snc_getInstagramPosts_callback' );

/*
 * Function to retrieve Instagram Code.
 * 
 * Parameters:
 * - code			: Code returned during Authorization from Instagram.
 */
function snc_getInstagramCode_callback () {
	
	snc_setLogMessage( "snc_getInstagramCode_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	$code = "";
	
	if ( $_SERVER['REQUEST_METHOD'] == 'GET' ) {		
		if ( isset( $_GET['code'] ) ) {
			$code = $_GET['code'];
		}
	}

	snc_setLogMessage( "code = $code\n" );
	echo <<<EOF
<input type="hidden" id="snc_instagram_code" name="snc_instagram_code" value="$code" />
EOF;
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_get_instagram_code', 'snc_getInstagramCode_callback' );
add_action( 'wp_ajax_nopriv_snc_get_instagram_code', 'snc_getInstagramCode_callback' );

/*
 * Function to generate Instagram Access Token after retrieving Code.
 * 
 * Parameters:
 * - clientId		: Instagram Client ID setting.
 * - clientSecret	: Instagram Client Secret setting.
 * - code			: Code returned during Authorization from Instagram.
 */
function snc_getInstagramAccessToken_callback () {
	
	global $instagramSettings;
	
	snc_setLogMessage( "snc_getInstagramAccessToken_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Return any errors & data to calling script via this class
	$response = new stdClass();
	$response->accessToken = "";
	$response->error = "";

	// ToDo - check_ajax_referer() for checking call came from a legitimate place

	// Initialise parameters
	extract(
		array(
			"clientId" => "",
			"clientSecret" => "",
			"code" => "",
		)
	);

	// Initialise other variables
	$formData = array();	// To hold Form data if New or Updated item
	$testInstagramSettings = array();

	// Retrieve Instagram settings
	if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {		

		// Extract form parameters
		foreach ( $_POST as $field => $value ) {
			
			if ( get_magic_quotes_gpc() && !is_array( $value ) ) {
				$value = stripslashes( $value );
			}
			
			if ( !is_array( $value ) ) {
				$formData[$field] = strip_tags( $value );
			} else {
				$formData[$field] = $value;
			}
			
			// Make fields available for populating form again in case of error or to pass onto next step
			switch ( $field ) {
				case "clientId": $clientId = $formData[$field]; break; 
				case "clientSecret": $clientSecret = $formData[$field]; break; 
				case "code": $code = $formData[$field]; break; 
				default:
					break;
			}
		}
	}

	snc_setLogMessage( "clientId = $clientId, clientSecret = $clientSecret, code = $code\n" );

	// Call Instagram API
	if ( $clientId != "" && $clientSecret != "" && $code != "" ) {
		
		// Prepare parameters for generation of Access Token
		$testInstagramSettings["client_id"] = $clientId;
		$testInstagramSettings["client_secret"] = $clientSecret;
		$testInstagramSettings["grant_type"] = $instagramSettings["grant_type"];
		$testInstagramSettings["redirect_uri"] = $instagramSettings["redirect_uri"];
		$testInstagramSettings["code"] = $code;
		
		// Prepare URL to call within API
		$apiURL = "https://api.instagram.com/oauth/access_token";
		
		snc_setLogMessage( "apiURL = $apiURL\n" );

		// Request Access Token
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $apiURL );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 20 );
		curl_setopt( $ch, CURLOPT_POST, true ); 
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $testInstagramSettings );
		$result = curl_exec( $ch );
		curl_close( $ch );
		 
		snc_setLogMessage( "Access Token response = " . print_r( $result, true ) . "\n" );

		// Process result from API call
		if ( $result !== false ) {
			$jsonResponse = json_decode( $result );
			snc_setLogMessage( "Access Token JSON = " . print_r( $jsonResponse, true ) . "\n" );
			if ( !empty( $jsonResponse ) && isset( $jsonResponse->access_token ) ) {
				$response->accessToken = $jsonResponse->access_token;
			}
		}
	} else {
		
		// Generate error message
		$response->error = "Missing Instagram settings";
	}

	//snc_setLogMessage( print_r( json_encode( $response ), true ) );
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_get_instagram_access_token', 'snc_getInstagramAccessToken_callback' );
add_action( 'wp_ajax_nopriv_snc_get_instagram_access_token', 'snc_getInstagramAccessToken_callback' );

/*
 * Function to process AJAX call for logging in to Instagram.
 * 
 * Based on example from - https://www.instagram.com/developer/authentication/
 * 
 * Return:
 * - JSON response.
 */
function snc_doInstagramLogin_callback () {

	global $instagramSettings;
	
	snc_setLogMessage( "snc_doInstagramLogin_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	// Write to log file
	snc_setLogMessage( "sessionId = " . session_id() );
	snc_setLogMessage( "returnUrl = " . $_POST["returnUrl"] );
	snc_setLogMessage( "instagramSettings: " . print_r( $instagramSettings, true ) );

	// Temporarily store the current page to return to after calling the API
	$_SESSION["instagram_request_return_url"] = $_POST["returnUrl"];

	// Return URL with Instagram API to call to get Code that is needed to obtain an Access Token
	$response = new stdClass();
	$response->url = "https://api.instagram.com/oauth/authorize/?client_id=" . $instagramSettings["client_id"] . "&redirect_uri=" . $instagramSettings["user_redirect_uri"] . "&response_type=code" . "&scope=" . implode( "+", $instagramSettings["scope"] );
	snc_setLogMessage( "response->url = " . $response->url );

	// Return any errors & data to calling script via this class
	//snc_setLogMessage( print_r( json_encode( $response ), true ) );
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_instagram_login', 'snc_doInstagramLogin_callback' );
add_action( 'wp_ajax_nopriv_snc_instagram_login', 'snc_doInstagramLogin_callback' );

/*
 * Function to process response to logging in to Instagram.
 * 
 * Based on: https://www.instagram.com/developer/authentication/
 */
function snc_doInstagramOAuth_callback () {

	global $instagramSettings;
	
	snc_setLogMessage( "snc_doInstagramOAuth_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	snc_setLogMessage( "sessionId = " . session_id() );
	
	$accessToken = "";

	if ( !empty( $_GET['code'] ) && $_GET['code'] != "" ) {
	 
		snc_setLogMessage( "code = " . $_GET['code'] );
			
		// Prepare parameters for generation of Access Token
		$testInstagramSettings = array();
		$testInstagramSettings["client_id"] = $instagramSettings["client_id"];
		$testInstagramSettings["client_secret"] = $instagramSettings["client_secret"];
		$testInstagramSettings["grant_type"] = $instagramSettings["grant_type"];
		$testInstagramSettings["redirect_uri"] = $instagramSettings["user_redirect_uri"];
		$testInstagramSettings["code"] = $_GET['code'];
		
		// Prepare URL to call within API
		$apiURL = "https://api.instagram.com/oauth/access_token";
		
		snc_setLogMessage( "apiURL = $apiURL\n" );
		snc_setLogMessage( "testInstagramSettings[] = " . print_r( $testInstagramSettings, true ) );

		// Request Access Token
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $apiURL );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 20 );
		curl_setopt( $ch, CURLOPT_POST, true ); 
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $testInstagramSettings );
		$result = curl_exec( $ch );
		curl_close( $ch );
		 
		snc_setLogMessage( "Access Token response = " . print_r( $result, true ) . "\n" );

		// Process result from API call
		if ( $result !== false ) {
			$jsonResponse = json_decode( $result );
			snc_setLogMessage( "Access Token JSON = " . print_r( $jsonResponse, true ) . "\n" );
			if ( !empty( $jsonResponse ) && isset( $jsonResponse->access_token ) ) {
				$accessToken = $jsonResponse->access_token;

				snc_setLogMessage( "accessToken = " . $accessToken );
				
				if ( $accessToken != "" ) {
					
					// Save User's Access Token & associated Secret in session for use to access Twitter
					$_SESSION['instagram_access_token'] = $accessToken;
						 
					// Retrieve some Instagram Profile information for User
					if ( isset( $jsonResponse->user->username ) ) {
						$_SESSION['instagram_id'] = $jsonResponse->user->username;
			 
						// Write to log file
						//snc_setLogMessage( "instagram_id = " . $jsonResponse->username );
					}
				}
			}
		}
	} else {
		
		// Authorization was Denied or Cancelled so remove existing Session & Cookie
		unset( $_SESSION['instagram_access_token'] );
		unset( $_SESSION['instagram_id'] );
		setCookie( "snc_instagram_access_cookie", "", time()-3600 );
	}

	// Retrieve return URL so the Session variable can be reset to avoid accidental returns to the calling page, if empty then return to Home Page
	$returnUrl = $_SESSION["instagram_request_return_url"];
	if ( $returnUrl == "" ) {
		$returnUrl = "index.php";
	}

	// Cleanup SESSION wrt Request settings
	unset( $_SESSION['instagram_request_return_url'] );

	snc_setLogMessage( "SESSION: " . print_r( $_SESSION, true ) );

	//redirect to main page. Your own
	header( 'Location: ' . $returnUrl );
}
add_action( 'wp_ajax_snc_instagram_oauth', 'snc_doInstagramOAuth_callback' );
add_action( 'wp_ajax_nopriv_snc_instagram_oauth', 'snc_doInstagramOAuth_callback' );

/*
 * Function to process AJAX call for logging out of Instagram.
 * 
 * Return:
 * - JSON response.
 */
function snc_doInstagramLogout_callback () {

	snc_setLogMessage( "snc_doInstagramLogout_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	// Return any errors & data to calling script via this class
	$response = new stdClass();
	$response->inLogin = false;

	// Remove access settings to Twitter
	unset( $_SESSION['instagram_access_token'] );
	unset( $_SESSION['instagram_id'] );
	setCookie( "snc_instagram_access_cookie", "", time()-3600 );

	snc_setLogMessage( "SESSION: " . print_r( $_SESSION, true ) );

	$response->data = "";
	//snc_setLogMessage( print_r( json_encode( $response ), true ) );
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_instagram_logout', 'snc_doInstagramLogout_callback' );
add_action( 'wp_ajax_nopriv_snc_instagram_logout', 'snc_doInstagramLogout_callback' );

/*
 * Function to process AJAX call for verifying Instagram credentials.
 * 
 * Return:
 * - JSON response.
 */
function snc_doInstagramVerifyCreds_callback () {

	global $instagramSettings;
	
	snc_setLogMessage( "snc_doInstagramVerifyCreds_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Return any errors & data to calling script via this class
	$response = new stdClass();
	$response->verified = false;
	$response->error = "";

	// Twitter Consumer & OAuth settings
	$testInstagramSettings = array();

	// Retrieve parameters from call
	if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
		if ( isset( $_POST["access_token"] ) ) {
			$testInstagramSettings["access_token"] = $_POST["access_token"];
		}
	}

	snc_setLogMessage( "testInstagramSettings = " . print_r( $testInstagramSettings, true ) );

	// Validate Instagram settings
	if ( snc_doCheckInstagramSettings( $testInstagramSettings ) ) {
		snc_setLogMessage( "snc_doCheckInstagramSettings() = true (so App Access)" );
		$response->verified = true;
	} else {
		snc_setLogMessage( "snc_doCheckInstagramSettings() = false (so No App Access)" );
		$response->error = "Authorization failed";
	}

	//snc_setLogMessage( print_r( json_encode( $response ), true ) );
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_instagram_verify_creds', 'snc_doInstagramVerifyCreds_callback' );
add_action( 'wp_ajax_nopriv_snc_instagram_verify_creds', 'snc_doInstagramVerifyCreds_callback' );

/*
 * Function to process AJAX call for saving Instagram settings.
 * 
 * Return:
 * - JSON response.
 */
function snc_doInstagramSaveSettings_callback () {

	$response = new stdClass();
	
	// Retrieve parameters from call
	if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
		if ( isset( $_POST["clientId"] ) ) {
			update_option( "snc_instagram_client_id", $_POST["clientId"] );
		}
		if ( isset( $_POST["clientId"] ) ) {
			update_option( "snc_instagram_client_secret", $_POST["clientSecret"] );
		}
	}

	// Return any errors via this class
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_instagram_save_settings', 'snc_doInstagramSaveSettings_callback' );
add_action( 'wp_ajax_nopriv_snc_instagram_save_settings', 'snc_doInstagramSaveSettings_callback' );

/*
 * Function to display Twitter Posts.
 * 
 * Parameters:
 * - screenNames 		: List of Screen Names (needs to include preceeding '@' e.g. @beer2infinity).
 * - numPostsPerAccount	: Max number of Posts to display per Social Media account.
 * - maxNumPosts		: Max number of Posts to display per Social Media network.
 * 
 * Return:
 * - generated HTML.
 */
function snc_getTwitterPosts ( $screenNames, $numPostsPerAccount = SNC_DEFAULT_NUM_POSTS, $maxNumPosts = SNC_MAX_NUM_POSTS ) {

	global $wpdb, $current_user;
	
	snc_setLogMessage( "snc_getTwitterPosts():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	$content = "";
	
	$pluginUrl = SNC_BASE_URL;
	
	// Setup JSON of parameters to call Twitter
	$postsParams = array(
		"target" => "sncIsotope",
		"path" => $pluginUrl,
		"site" => "twitter",
		"ids" => $screenNames,
		"numPosts" => $numPostsPerAccount,
		"maxPosts" => $maxNumPosts,
		"infoType" => "posts",
	);

	$jsonParams = json_encode( $postsParams );
	
	// Determine whether or not the User is logged in re: Twitter so the right Login/Logout button can be displayed
	$loggedInFlag = "";
	if ( snc_getCookie( "snc_twitter_access_cookie" ) != "" ) {
		$loggedInFlag = "Y";
	}

	$content .= <<<EOF
	<script type="text/javascript">
		snc_getSocialMediaPosts( $jsonParams );
	</script>
EOF;

	return( $content );
}
add_shortcode( 'sncTwitterPosts', 'snc_getTwitterPosts' );
	
/*
 * Function to process AJAX calls for Twitter Posts.
 * 
 * Return:
 * - JSON response.
 */
function snc_getTwitterPosts_callback () {

	global $twitterSettings;
	
	snc_setLogMessage( "snc_getTwitterPosts_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Calculate start time of call to Social Network
	$startTimes = explode( " ", microtime() );
	$startTime = $startTimes[1] + $startTimes[0];
	//snc_setLogMessage( "snc_getTwitterPosts_callback() - start timestamp = " . $startTime );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	snc_setLogMessage( "sessionId = " . session_id() );

	// Initialise parameters
	extract(
		array(
			"screenNames" => array(),
			"numPosts" => 0,
			"maxPosts" => 0,
			"infoType" => "",
			"content" => "",
			"cookie" => "",
		)
	);

	// Return any errors & data to calling script via this class
	$response = new stdClass();
	$response->twLogin = false;

	$cookie = snc_getCookie( "snc_twitter_access_cookie" );
	snc_setLogMessage( "Cookie first: " . $cookie );

	// Retrieve User Access Token & Secret using cookie (if it already exists)
	if ( empty( $_SESSION['oauth_access_token'] ) && empty( $_SESSION['oauth_access_token_secret'] ) && $cookie != "" ) {
		$credList = snc_getSiteCredsByCookie( $cookie );
		snc_setLogMessage( "credList = " . print_r( $credList, true ) );
		if ( isset( $credList["token"] ) && isset( $credList["secret"] ) && isset( $credList["username"] ) ) {
			$_SESSION['oauth_access_token'] = $credList["token"];
			$_SESSION['oauth_access_token_secret'] = $credList["secret"];
			$_SESSION['twitter_id'] = $credList["username"];
		} else {
			setCookie( "snc_twitter_access_cookie", "", time()-3600 );
		}
	}

	//snc_setLogMessage( "twitterSettings() before: " . print_r( $twitterSettings, true ) );
	snc_setLogMessage( "SESSION() before: " . print_r( $_SESSION, true ) );
	snc_setLogMessage( "Cookie before: " . snc_getCookie( "snc_twitter_access_cookie" ) );

	// Determine whether User has authorized access to Twitter, and validate them, otherwise use App settings
	if ( !empty( $_SESSION['oauth_access_token'] ) && !empty( $_SESSION['oauth_access_token_secret'] ) ) {
		$userTwitterSettings = $twitterSettings;
		$userTwitterSettings['oauth_access_token'] = $_SESSION['oauth_access_token'];
		$userTwitterSettings['oauth_access_token_secret'] = $_SESSION['oauth_access_token_secret'];
		
		// Validate User Twitter settings
		if ( snc_doCheckTwitterSettings( $userTwitterSettings ) ) {
			snc_setLogMessage( "snc_doCheckTwitterSettings() = true (so User Access)" );
			$twitterSettings = $userTwitterSettings;
			
			// Save User Access Token & Secret for use when they return
			$cookie = snc_setSiteCredentials( $_SESSION['oauth_access_token'], $_SESSION['oauth_access_token_secret'], "twitter", $_SESSION['twitter_id'] );
			setCookie( 'snc_twitter_access_cookie', $cookie, time() +SNC_COOKIE_TIMEOUT ); 
			$response->twLogin = true;
		} else {
			snc_setLogMessage( "snc_doCheckTwitterSettings() = false (so App Access)" );
			$_SESSION["warning"] = "Twitter authorization was Denied or Cancelled.";
			snc_setLogMessage( "snc_getTwitterPosts_callback() warning = " . $_SESSION["warning"] );
			
			// Authorization was Denied or Cancelled so remove existing Session variables & Cookies
			unset( $_SESSION['oauth_access_token'] );
			unset( $_SESSION['oauth_access_token_secret'] );
			unset( $_SESSION['twitter_id'] );
			setCookie( "snc_twitter_access_cookie", "", time()-3600 );
		}
	}

	// Write to log file
	snc_setLogMessage( "twitterSettings[] after: " . print_r( $twitterSettings, true ) );
	snc_setLogMessage( "SESSION() after: " . print_r( $_SESSION, true ) );
	snc_setLogMessage( "Cookie after: >>>" . $cookie . "<<<" ); // cannot display via snc_getCookie() as not returned from AJAX call yet
	snc_setLogMessage( "POST() after: " . print_r( $_POST, true ) );

	// Retrieve parameters from call
	if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
		if ( isset( $_POST["infoType"] ) ) {
			$infoType = $_POST["infoType"];
		}
		if ( isset( $_POST["ids"] ) ) {
			$screenNames = $_POST["ids"];
		}
		if ( isset( $_POST["numPosts"] ) ) {
			$numPosts = $_POST["numPosts"];
		}
		if ( isset( $_POST["maxPosts"] ) ) {
			$maxPosts = $_POST["maxPosts"];
		}

		switch ( $infoType ) {
			case "hashtag":

				// Call Twitter to retrieve HashTag Posts
				$content = snc_getTweetsBySearch( $twitterSettings, $screenNames );
				break;
			case "posts":

				// Call Twitter to retrieve User Timeline Posts
				$content = snc_getTweetsByUser( $twitterSettings, $screenNames, $numPosts, $maxPosts );
				break;
			case "profile":

				// Call Twitter to retrieve Profile
				$content = snc_getTwitterUserProfile( $twitterSettings, $screenNames );
				break;
			case "symbol":

				// Call Twitter to retrieve Symbol Posts
				$content = snc_getTweetsBySearch( $twitterSettings, $screenNames );
				break;
		}
	}

	// Calculate finish time of call to Social Network and time taken
	$finishTimes = explode( " ", microtime() );
	$finishTime = $finishTimes[1] + $finishTimes[0];
	//snc_setLogMessage( "snc_getTwitterPosts_callback() - finish timestamp = " . $finishTime );
	snc_setLogMessage( "snc_getTwitterPosts_callback() - time taken = " . ( $finishTime - $startTime ) );

	if ( isset( $_SESSION["error"] ) ) {
		$response->error = $_SESSION["error"];
	} elseif ( isset( $_SESSION["warning"] ) ) {
		$response->warning = $_SESSION["warning"];
	} elseif ( isset( $_SESSION["message"] ) ) {
		$response->message = $_SESSION["message"];
	}
	snc_doResetSessionMessages();

	$response->data = $content;
	//snc_setLogMessage( print_r( json_encode( $response ), true ) );
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_twitter_posts', 'snc_getTwitterPosts_callback' );
add_action( 'wp_ajax_nopriv_snc_twitter_posts', 'snc_getTwitterPosts_callback' );

/*
 * Function to process AJAX call for logging in to Twitter.
 * 
 * Based on example from - http://hayageek.com/login-with-twitter/
 * 
 * Return:
 * - JSON response.
 */
function snc_doTwitterLogin_callback () {

	global $twitterSettings;
	
	snc_setLogMessage( "snc_doTwitterLogin_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	// Write to log file
	snc_setLogMessage( "sessionId = " . session_id() );
	snc_setLogMessage( "returnUrl = " . $_POST["returnUrl"] );
	snc_setLogMessage( "twitterSettings: " . print_r( $twitterSettings, true ) );

	$_SESSION["twitter_request_return_url"] = $_POST["returnUrl"];

	// Setup Twitter connection and retrieve a temporary Request Token which will be used for the User to Authorize this App
	$connection = new TwitterOAuth( $twitterSettings["consumer_key"], $twitterSettings["consumer_secret"] );
	$request_token = $connection->oauth( "oauth/request_token", array( "oauth_callback" => $twitterSettings["oauth_callback"] ) );

	// Write to log file
	snc_setLogMessage( "request_token: " . print_r( $request_token, true ) );

	// Return any errors & data to calling script via this class
	$response = new stdClass();
	$response->url = "";

	if ( $request_token ) {

		// Save Request Token & associated Secret to verify response from Twitter is authentic and not a hack
		$_SESSION["request_token"] = $request_token["oauth_token"];
		$_SESSION["request_token_secret"] = $request_token["oauth_token_secret"];

		switch ( $connection->getLastHttpCode() ) {
			case 200:
			
				// Build Authorization URL
				$url = $connection->url( "oauth/authorize", array( "oauth_token" => $request_token["oauth_token"] ) );

				// Write to log file
				snc_setLogMessage( "redirecting to url: " . $url );
				
				// Populate URL that the browser is to call in order to authorize with Twitter
				$response->url = $url;
				break;
			default:
				$errMsg = "Connection to Twitter failed";
				snc_setLogMessage( "snc_doTwitterLogin_callback() : ERROR = " . $errMsg . "!" );
				$response->error = $errMsg;
				$_SESSION["twitter_request_return_url"] = "";
				break;
		}
	} else {
		$errMsg = "Did not receive a Request Token from Twitter";
		snc_setLogMessage( "snc_doTwitterLogin_callback() : ERROR = " . $errMsg . "!" );
		$response->error = $errMsg;
	}

	// Return any errors & data to calling script via this class
	//snc_setLogMessage( print_r( json_encode( $response ), true ) );
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_twitter_login', 'snc_doTwitterLogin_callback' );
add_action( 'wp_ajax_nopriv_snc_twitter_login', 'snc_doTwitterLogin_callback' );

/*
 * Function to process response to logging in to Twitter.
 * 
 * Based on: http://qnimate.com/wordpress-frontend-twitter-oauth-login/
 */
function snc_doTwitterOAuth_callback () {

	global $twitterSettings;
	
	snc_setLogMessage( "snc_doTwitterOAuth_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	snc_setLogMessage( "sessionId = " . session_id() );

	if ( !empty( $_GET['oauth_token'] ) && !empty( $_SESSION['request_token'] ) && !empty( $_SESSION['request_token_secret'] ) ) {
	 
		snc_setLogMessage( "request oauth_token = " . $_GET['oauth_token'] . ", response oauth_verifier = " . $_GET['oauth_verifier'] );

		// Setup connection to Twitter using the original Request Token & associated Secret to receive result of Authorization
		$connection = new TwitterOAuth( $twitterSettings['consumer_key'], $twitterSettings['consumer_secret'], $_SESSION['request_token'], $_SESSION['request_token_secret'] );
		$access_token = $connection->oauth( "oauth/access_token", array( "oauth_verifier" => $_GET['oauth_verifier'] ) );
		
		snc_setLogMessage( "access_token: " . print_r( $access_token, true ) );
		
		if ( $access_token ) {
			
			// Save User's Access Token & associated Secret in session for use to access Twitter
			$_SESSION['oauth_access_token'] = $access_token['oauth_token'];
			$_SESSION['oauth_access_token_secret'] = $access_token['oauth_token_secret'];
			
			// Establish connection to Twitter with User's Access Token & associated Secret
			$connection = new TwitterOAuth( $twitterSettings['consumer_key'], $twitterSettings['consumer_secret'], $access_token['oauth_token'], $access_token['oauth_token_secret'] );
			
			// Verify whether Authentication was successful
			$params = array();
			$params['include_entities'] = 'false';
			$content = $connection->get( 'account/verify_credentials', $params );
			//snc_setLogMessage( "content: " . print_r( $content, true ) );
	 
			// Retrieve some Twitter Profile information for User
			if ( $content && isset( $content->screen_name ) ) {
				$_SESSION['twitter_id'] = $content->screen_name;
				//$_SESSION['name'] = $content->name;
				//$_SESSION['image'] = $content->profile_image_url;
	 
				// Write to log file
				//snc_setLogMessage( "name = " . $content->name . ", image = " . $content->profile_image_url . ", twitter_id = " . $content->screen_name );
			}
		}
	} else {
		
		// Authorization was Denied or Cancelled so remove existing Session & Cookie
		unset( $_SESSION['oauth_access_token'] );
		unset( $_SESSION['oauth_access_token_secret'] );
		unset( $_SESSION['twitter_id'] );
		setCookie( "snc_twitter_access_cookie", "", time()-3600 );
	}

	// Retrieve return URL so the Session variable can be reset to avoid accidental returns to the calling page, if empty then return to Home Page
	$returnUrl = $_SESSION["twitter_request_return_url"];
	if ( $returnUrl == "" ) {
		$returnUrl = "index.php";
	}

	// Cleanup SESSION wrt Request settings
	unset( $_SESSION['request_token'] );
	unset( $_SESSION['request_token_secret'] );
	unset( $_SESSION['twitter_request_return_url'] );

	snc_setLogMessage( "SESSION: " . print_r( $_SESSION, true ) );

	//redirect to main page. Your own
	header( 'Location: ' . $returnUrl );
}
add_action( 'wp_ajax_snc_twitter_oauth', 'snc_doTwitterOAuth_callback' );
add_action( 'wp_ajax_nopriv_snc_twitter_oauth', 'snc_doTwitterOAuth_callback' );

/*
 * Function to process AJAX call for logging out of Twitter.
 * 
 * Return:
 * - JSON response.
 */
function snc_doTwitterLogout_callback () {

	snc_setLogMessage( "snc_doTwitterLogout_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	// Return any errors & data to calling script via this class
	$response = new stdClass();
	$response->twLogin = false;

	// Remove access settings to Twitter
	unset( $_SESSION['oauth_access_token'] );
	unset( $_SESSION['oauth_access_token_secret'] );
	unset( $_SESSION['twitter_id'] );
	setCookie( "snc_twitter_access_cookie", "", time()-3600 );

	snc_setLogMessage( "SESSION: " . print_r( $_SESSION, true ) );

	$response->data = "";
	//snc_setLogMessage( print_r( json_encode( $response ), true ) );
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_twitter_logout', 'snc_doTwitterLogout_callback' );
add_action( 'wp_ajax_nopriv_snc_twitter_logout', 'snc_doTwitterLogout_callback' );

/*
 * Function to process AJAX call for verifying Twitter credentials.
 * 
 * Return:
 * - JSON response.
 */
function snc_doTwitterVerifyCreds_callback () {

	snc_setLogMessage( "snc_doTwitterVerifyCreds_callback():" );
	snc_setLogMessage( "snc_getCurPageURL = " . snc_getCurPageURL() );

	// Return any errors & data to calling script via this class
	$response = new stdClass();
	$response->verified = false;
	$response->error = "";

	// Twitter Consumer & OAuth settings
	$testTwitterSettings = array();

	// Retrieve parameters from call
	if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
		if ( isset( $_POST["consumer_key"] ) ) {
			$testTwitterSettings["consumer_key"] = $_POST["consumer_key"];
		}
		if ( isset( $_POST["consumer_secret"] ) ) {
			$testTwitterSettings["consumer_secret"] = $_POST["consumer_secret"];
		}
		if ( isset( $_POST["oauth_access_token"] ) ) {
			$testTwitterSettings["oauth_access_token"] = $_POST["oauth_access_token"];
		}
		if ( isset( $_POST["oauth_access_token_secret"] ) ) {
			$testTwitterSettings["oauth_access_token_secret"] = $_POST["oauth_access_token_secret"];
		}
	}

	snc_setLogMessage( "testTwitterSettings = " . print_r( $testTwitterSettings, true ) );

	// Validate Twitter settings
	if ( snc_doCheckTwitterSettings( $testTwitterSettings ) ) {
		snc_setLogMessage( "snc_doCheckTwitterSettings() = true (so App Access)" );
		$response->verified = true;
	} else {
		snc_setLogMessage( "snc_doCheckTwitterSettings() = false (so No App Access)" );
		$response->error = "Authorization failed";
	}

	//snc_setLogMessage( print_r( json_encode( $response ), true ) );
	echo json_encode( $response );
	die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_snc_twitter_verify_creds', 'snc_doTwitterVerifyCreds_callback' );
add_action( 'wp_ajax_nopriv_snc_twitter_verify_creds', 'snc_doTwitterVerifyCreds_callback' );

/*
 * Function to display mixed Posts for an Entity or Member from the central Social News Center server.
 * 
 * Parameters:
 * - ownerType 			: Type of Account owner i.e. 'entity' or 'member'.
 * - ownerId 			: Entity or Member ID.
 * - numPostsPerAccount	: Max number of Posts to display per Social Media account.
 * - maxNumPosts		: Max number of Posts to display per Social Media network.
 * 
 * Return:
 * - generated HTML.
 */
function snc_getMixedPosts ( $ownerType, $ownerId, $numPostsPerAccount = SNC_DEFAULT_NUM_POSTS, $maxNumPosts = SNC_MAX_NUM_POSTS ) {

	global $wpdb, $current_user;
	
	snc_setLogMessage( "snc_getMixedPosts():" );

	// Needed to pass session data between pages
	if( !isset( $_SESSION ) ) {
		session_start();
	}

	$networkIdList = array(); // Empty list means include Accounts for ALL social media Networks
	$accountsList = array();

	// Retrieve Accounts from the Social News Center server
	switch ( $ownerType ) {
		case "entity":
			$accountsList = sncsa_getAccountsByEntity( $ownerId, $networkIdList, "entity" ); // only returns Accounts for Entity & not its' Members
			break;
		case "member":
			$accountsList = sncsa_getAccountsByMember( $ownerId, $networkIdList );
			break;
	}

	$pagesList = array();
	
	// Process returned Accounts to extract & format the needed information
	foreach ( $accountsList as $accountItem ) {
		
		//$accountId = $accountItem['account_id'];
		$networkName = strtolower( $accountItem['network_name'] );
		$username = $accountItem['username'];

		snc_setLogMessage( "networkName = $networkName, username = $username" );
		
		switch ( $networkName ) {
			case "facebook":
				$pagesList[$networkName][] = "/" . $username;
				break;
			case "instagram":
				$pagesList[$networkName][] = "@" . $username;
				break;
			case "twitter":
				$pagesList[$networkName][] = "@" . $username;
				break;
		}
	}

	snc_setLogMessage( "pagesList = " . print_r( $pagesList, true ) );

	$content = "";

	// Call each of the relevant social media Networks
	foreach ( $pagesList as $networkName => $value ) {
		switch ( $networkName ) {
			case "facebook":
				if ( SNC_FACEBOOK_FLAG ) {
					$content .= snc_getFacebookPosts( $value, $numPostsPerAccount, $maxNumPosts );
					snc_setLogMessage( "snc_getMixedPosts(): call to get Facebook posts" );
				}
				break;
			case "instagram":
				if ( SNC_INSTAGRAM_FLAG ) {
					$content .= snc_getInstagramPosts( $value, $numPostsPerAccount, $maxNumPosts );
					snc_setLogMessage( "snc_getMixedPosts(): call to get Instagram posts" );
				}
				break;
			case "twitter":
				if ( SNC_TWITTER_FLAG ) {
					$content .= snc_getTwitterPosts( $value, $numPostsPerAccount, $maxNumPosts );
					snc_setLogMessage( "snc_getMixedPosts(): call to get Twitter posts" );
				}
				break;
			default:
				snc_setLogMessage( "snc_getMixedPosts(): unrecognised Social Media network = '$networkName'" );
				break;
		}
	}

	return( $content );
}
add_shortcode( 'sncMixedPosts', 'snc_getMixedPosts' );

/*
 * Function to create data tables required by SNC plugin
 */
function snc_doInstallTables() {

	global $wpdb;

	if ( get_option( "snc_db_version" ) != SNC_DB_VERSION ) {
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		//$charset_collate = $wpdb->get_charset_collate();

		$sql =<<<EOF
			CREATE TABLE IF NOT EXISTS snc_api_usage_stats (
				id int(11) NOT NULL AUTO_INCREMENT,
				site varchar(50) NOT NULL,
				calling_function varchar(100) NOT NULL,
				resource varchar(100) NOT NULL,
				query varchar(255) NOT NULL,
				access_token varchar(255) NOT NULL,
				status varchar(100) NOT NULL,
				rate_limit int(11) NOT NULL,
				remaining int(11) NOT NULL,
				rate_reset int(11) NOT NULL,
				logged datetime NOT NULL,
				PRIMARY KEY  (id), 
				KEY  function_call (calling_function, resource), 
				KEY  site (site), 
				KEY  access_token (access_token)
			) AUTO_INCREMENT=1;
EOF;

		dbDelta( $sql );

		$sql =<<<EOF
			CREATE TABLE IF NOT EXISTS snc_networks (
				id int(11) NOT NULL AUTO_INCREMENT,
				name varchar(50) NOT NULL,
				prefix_url varchar(255) NOT NULL,
				website varchar(255) NOT NULL,
				logo varchar(255) NOT NULL,
				thumbnail varchar(255) NOT NULL,
				counter int(11) NOT NULL,
				max_limit int(11) NOT NULL,
				timescale varchar(20) NOT NULL,
				status varchar(10) NOT NULL DEFAULT 'Active', 
				PRIMARY KEY  (id), 
				UNIQUE KEY  name (name), 
				KEY  status (status)
			) AUTO_INCREMENT=1;
EOF;

		dbDelta( $sql );

		$sql =<<<EOF
			CREATE TABLE IF NOT EXISTS snc_user_access (
				id int(11) NOT NULL AUTO_INCREMENT,
				cookie varchar(255) NOT NULL,
				token varchar(255) NOT NULL,
				secret varchar(255) NOT NULL,
				network_id int(11) NOT NULL,
				user_id int(11) NOT NULL,
				username varchar(100) NOT NULL, 
				PRIMARY KEY  (id), 
				KEY  cookie (cookie), 
				KEY  network_id (network_id), 
				KEY  user_id (user_id)
			) AUTO_INCREMENT=1;
EOF;

		dbDelta( $sql );
	
		add_option( 'snc_db_version', SNC_DB_VERSION );
	}
}
register_activation_hook( __FILE__, 'snc_doInstallTables' );

/*
 * Function to insert data into tables required by SNC plugin
 */
function snc_doInstallData() {

	global $wpdb;
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	
	// Social Media networks to be added as base data
	$socialNetworksList = array(
		array( 'Facebook', 'https://www.facebook.com/', 'http://www.facebook.com', '', '', 0, 0, '', 'Active' ), 
		array( 'Twitter', 'https://twitter.com/', 'http://www.twitter.com', '', '', 0, 0, '', 'Active' ), 
//		array( 'Google+', 'https://plus.google.com/', 'http://plus.google.com', '', '', 0, 0, '', 'Disabled' ), 
		array( 'Instagram', 'http://instagram.com/', 'http://www.instagram.com', '', '', 0, 0, '', 'Active' ), 
//		array( 'Pinterest', 'http://www.pinterest.com/', 'http://www.pinterest.com', '', '', 0, 0, '', 'Disabled' ), 
//		array( 'StumbleUpon', 'http://www.stumbleupon.com/stumbler/', 'http://www.stumbleupon.com', '', '', 0, 0, '', 'Disabled' ), 
//		array( 'Tumblr', 'http://*.tumblr.com', 'http://www.tumblr.com', 'tumblr-logo.png', 'tumblr-thumbnail.png', 0, 0, '', 'Disabled' ), 
//		array( 'Vine', 'https://vine.co/', 'https://vine.co', '', '', 0, 0, '', 'Disabled' ), 
	);

	// Get list of networks already in the database
	$sql = <<<EOF
		SELECT	name, 
				status 
		FROM	snc_networks 
		WHERE	1 = %d;
EOF;

	$wpdb->flush();
	$result = stripslashes_deep( $wpdb->get_results( $wpdb->prepare( $sql, 1 ), ARRAY_A ) );
	//snc_setLogMessage( "social-news-center.php - snc_doInstallData() : SQL = " . $sql );

	$existingNetworksList = array();

	// Process the results of the SQL query
	foreach ( $result as $row ) {
		$existingNetworksList[$row["name"]] = $row["status"];
	}

	//snc_setLogMessage( "social-news-center.php - snc_doInstallData() : existingNetworksList = " . print_r( $existingNetworksList, true ) );

	// Add network data
	foreach ( $socialNetworksList as $socialNetworkList ) {

		$name		= $socialNetworkList[0];
		$prefixUrl	= $socialNetworkList[1];
		$website	= $socialNetworkList[2];
		$logo		= $socialNetworkList[3];
		$thumbnail	= $socialNetworkList[4];
		$counter	= $socialNetworkList[5];
		$maxLimit	= $socialNetworkList[6];
		$timescale	= $socialNetworkList[7];
		$status		= $socialNetworkList[8];
		
		// Add networks that do not already exist
		if ( !array_key_exists( $name, $existingNetworksList ) ) {
			$wpdb->insert( 
				'snc_networks', 
				array(
					'name' => $name, 
					'prefix_url' => $prefixUrl, 
					'website' => $website, 
					'logo' => $logo, 
					'thumbnail' => $thumbnail, 
					'counter' => $counter, 
					'max_limit' => $maxLimit, 
					'timescale' => $timescale, 
					'status' => $status, 
				)
			);
		}
	}
	
	// SNC Options to be added as base data
	$sncOptionsList = array(
		array( 'snc_posts_per_account_main', '5', '', 'yes' ),
		array( 'snc_posts_per_account_popup', '8', '', 'yes' ),
		array( 'snc_posts_total_maximum', '20', '', 'yes' ),
		array( 'snc_cache_limit', '50', '', 'yes' ),
		array( 'snc_cache_reset', '60', '', 'yes' ),
		array( 'snc_layout_style', 'neutral', '', 'yes' ),
		array( 'snc_default_cookie_timeout', '2592000', '', 'yes' ),
		array( 'snc_facebook_status', 'disabled', '', 'yes' ),
		array( 'snc_facebook_app_id', '', '', 'yes' ),
		array( 'snc_facebook_secret', '', '', 'yes' ),
		array( 'snc_instagram_status', 'disabled', '', 'yes' ),
		array( 'snc_instagram_client_id', '', '', 'yes' ),
		array( 'snc_instagram_client_secret', '', '', 'yes' ),
		array( 'snc_instagram_access_token', '', '', 'yes' ),
		array( 'snc_instagram_access_token_status', 'invalid', '', 'yes' ),
		array( 'snc_twitter_status', 'disabled', '', 'yes' ),
		array( 'snc_twitter_consumer_key', '', '', 'yes' ),
		array( 'snc_twitter_consumer_secret', '', '', 'yes' ),
		array( 'snc_twitter_oauth_access_token', '', '', 'yes' ),
		array( 'snc_twitter_oauth_access_token_secret', '', '', 'yes' ),
		array( 'snc_twitter_replies', 'include', '', 'yes' ),
		array( 'snc_twitter_retweets', 'include', '', 'yes' ),
	);

	// Add SNC Options data
	foreach ( $sncOptionsList as $sncOptionList ) {

		$optionName		= $sncOptionList[0];
		$optionValue	= $sncOptionList[1];
		$deprecated		= $sncOptionList[2];
		$autoload		= $sncOptionList[3];
		
		// Add networks that do not already exist
		add_option( $optionName, $optionValue, $deprecated, $autoload );
	}
}
register_activation_hook( __FILE__, 'snc_doInstallData' );

/*
 * Function to perform database changes and associated data setup as required when upgrading the SNC plugin due to no support for 'register_activation_hook'
 */
function snc_doUpdateDbCheck() {

	if ( get_option( "snc_db_version" ) != SNC_DB_VERSION ) {
        snc_doInstallTables();
        snc_doInstallData();
    }
}
add_action( 'plugins_loaded', 'snc_doUpdateDbCheck' );

/* End of File */
