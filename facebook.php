<?php

// Include Facebook location setting and library
//define( 'FACEBOOK_SDK_V4_SRC_DIR', __DIR__ . '/facebook/' );
require_once( __DIR__ . '/autoload.php' );

use Facebook\HttpClients\FacebookHttpable;
use Facebook\HttpClients\FacebookCurl;
use Facebook\HttpClients\FacebookCurlHttpClient;
use Facebook\Entities\AccessToken;
use Facebook\Entities\SignedRequest;
use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookJavaScriptLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;
use Facebook\FacebookRequestException;
use Facebook\FacebookOtherException;
use Facebook\FacebookAuthorizationException;
use Facebook\FacebookThrottleException;
use Facebook\GraphObject;
use Facebook\GraphUser;
use Facebook\GraphLocation;
use Facebook\GraphSessionInfo; 

// Needed to pass session data between pages
if( !isset( $_SESSION ) ) {
	session_start();
}

/*
 * Function to check for a cached version of this request, if not available submit request to Facebook and cache the results.
 * 
 * Parameters:
 * - session:			Valid Session containing App or User token for accessing Facebook.
 * - apiURL:			Facebook Graph API request.
 * - dataType:			Type of data requested e.g. Page Profile, Page Posts, Individual Post etc.
 * - cacheLimit:		Expiry of individual cached item (in minutes) before being re-read from Twitter.
 * - cacheReset:		Expiry of cached items (in minutes) before being completely reset.
 * 
 * Return:
 * - Graph API data containing Post(s) or Page Profile if successfully retrieved from the cache or Facebook itself otherwise NULL or False.
 */
function snc_doCheckFacebookCache ( $session, $apiURL, $dataType, $cacheLimit = SNC_FACEBOOK_DEF_CACHE_LIMIT, $cacheReset = SNC_FACEBOOK_DEF_CACHE_RESET ) {

	// Initialize parameters
	$requestMethod = "GET";
	$graphObject = null;
	$timestampKey = $apiURL . "_timestamp";

	//snc_setLogMessage( "snc_doCheckFacebookCache():\n=============" );
	//snc_setLogMessage( date("Y-m-d H:i:s") );
	
	// Extract Type of Request to assist with naming of cache file
	$requestType =
		str_replace( " ", "-", 
			str_replace( "/", "-", 
				str_replace( "_", "-", 
					strtolower( $dataType ) . " " . str_replace( "/posts", "", substr( $apiURL, 1, strpos( $apiURL, "?" ) -1 ) )
				)
			)
		);
	
	$cacheFile = SNC_BASE_PATH . "cache/facebook/" . $requestType . ".data";
	
	// Check for existence of cached result within caching period (limit)
	if ( file_exists( $cacheFile ) ) {
		//snc_setLogMessage( "File '$cacheFile' exists" );
		$data = unserialize( file_get_contents( $cacheFile ) );
		if ( $data[$timestampKey] > time() - $cacheLimit * 60 ) {
			//snc_setLogMessage( "Cached item still valid" );
			$graphObject = $data[$apiURL];
		}
	}

	// If cache doesn't exist or is older than caching period (limit) then fetch data from Facebook
	if ( !$graphObject ) { 

		// Check if cache needs resetting i.e. deleting
		if ( file_exists( $cacheFile ) ) {
			//snc_setLogMessage( "Retrieve remainder of cache" );
			$otherData = unserialize( file_get_contents( $cacheFile ) );
			if ( $otherData['cache_updated'] < time() - $cacheReset * 60 ) {
				snc_setLogMessage( "Clear cache as expired" );
				unset( $otherData ); // clear whole cache
			}
		} 	
		
		//snc_setLogMessage( "Need to query Facebook" );

		// Retrieve data from Facebook
		$graphObject = ( new FacebookRequest( $session, $requestMethod, $apiURL ) )->execute()->getGraphObject();

		// Each page gets its own key / value
		$data = array(
			$apiURL => $graphObject, 
			$timestampKey => time()
		);

		if ( isset( $otherData ) ) {
			//snc_setLogMessage( "Merge remainder of cache with new data" );
			$data = array_merge( $data, $otherData );
		}
		
		$data['cache_updated'] = time();

		try {
			//snc_setLogMessage( "Create or Update '$cacheFile'" );
			$numChars = file_put_contents( $cacheFile, serialize( $data ) );
			if ( !$numChars ) {
				$_SESSION["warning"] = "Unable to cache Facebook information.";
				snc_setLogMessage( "facebook.php - snc_doCheckFacebookCache() warning = " . $_SESSION["warning"] );
			}
		} catch ( Exception $e ) {
			$_SESSION["error"] = "Facebook caching problem occurred.";
			snc_setLogMessage( "facebook.php - snc_doCheckFacebookCache() exception = " . print_r( $ex, true ) );
		}
	}

	return $graphObject;
}

/*
 * Function to obtain a Facebook App Token.
 * 
 * Parameters:
 * - fbAppId		: App ID for Facebook
 * - fbSecret		: Facebook Secret
 * - session		: an existing Facebook Session
 * 
 * Return:
 * - App Token.
 */
function snc_getFacebookAppToken ( $fbAppId, $fbSecret, $session ) {

	$accessToken = "";

	if ( isset( $session ) ) {
		try {
			$request = ( 
				new FacebookRequest( 
					$session, 
					"GET", 
					"/oauth/access_token?client_id=" . $fbAppId . "&client_secret=" . $fbSecret . "&grant_type=client_credentials" 
				) 
			);
			$response = $request->execute();
			$accessToken = $response->getGraphObject()->getProperty( "access_token" );

		} catch ( FacebookAuthorizationException $ex ) {
			$_SESSION["warning"] = "Could not complete Facebook authorization.";
			snc_setLogMessage( "facebook.php - snc_getFacebookAppToken() exception = " . print_r( $ex, true ) );
		} catch ( FacebookThrottleException $ex ) {
			if ( $ex.getHttpStatusCode() == 4 ) {
				$accessToken = "";
			}
		} catch ( Exception $ex ) {
			$_SESSION["error"] = "Could not obtain access to Facebook.";
			snc_setLogMessage( "facebook.php - snc_getFacebookAppToken() exception = " . print_r( $ex, true ) );
		}
	}
	
	return( $accessToken );
}

/*
 * Function to get an individual Facebook post.
 * 
 * Parameters:
 * - session:			Facebook session.
 * - fullPostIds:		Full ID for Facebook Post i.e. Page ID + '_' + Post ID.
 * 
 * Return:
 * - HTML containing retrieved Post.
 */
function snc_getFacebookIndivPost ( $session, $fullPostIds ) {

	$content = "";

	$sncBaseUrl = SNC_BASE_URL;

	// see if we have a session
	if ( isset( $session ) && sizeof( $fullPostIds ) > 0 ) {
		
		$fullPostId = $fullPostIds[0];
		
		try {
			
			// Write to log file
			snc_setLogMessage( "fullPostId=" . $fullPostId );

			// graph api request for page data
			//$graphObject = ( new FacebookRequest( $session, "GET", "/" . $fullPostId . "?fields=id,from{id,name},to,story,message,picture,link,type,created_time,likes,shares,comments" ) )->execute()->getGraphObject();

			// Check if cached version of Facebook request available otherwise make fresh call to Facebook
			$apiURL = "/" . $fullPostId . "?fields=id,from{id,name,picture},to,story,message,picture,full_picture,link,type,created_time,likes,shares,comments";
			snc_setLogMessage( "facebook.php - snc_getFacebookIndivPost() apiURL = $apiURL" );
			$graphObject = snc_doCheckFacebookCache( $session, $apiURL, "Individual Post" );
			
			// Process Post from the Graph API result
			if ( $graphObject ) {
				$post = $graphObject;

				// Write to log file
				snc_setLogMessage( print_r( $post, true ) );

				$id = $post->getProperty( "id" );														// The post ID
				$from = $post->getProperty( "from" );													// Information about the profile that posted the message.
				$to = $post->getProperty( "to" );														// Information about the original poster as this is a Shared post.
				$story = $post->getProperty( "story" );													// Text from stories not intentionally generated by users, such as those generated when two people become friends, or when someone else posts on the person's wall.
				$message = $post->getProperty( "message" );												// The status message in the post.
				$picture = $post->getProperty( "picture" );												// The picture scraped from any link included with the post.
				$fullPicture = $post->getProperty( "full_picture" );									// The full picture scraped from any link included with the post.
				$link = $post->getProperty( "link" );													// The link attached to this post.
				$name = $post->getProperty( "name" );													// The name of the link.
				$type = $post->getProperty( "type" );													// A string indicating the object type of this post.
				$createdTime = $post->getProperty( "created_time" );									// The time the post was initially published.
				//$updatedTime = $post->getProperty("updated_time");									// The time of the last change to this post, or the comments on it.
				$likes = $post->getProperty("likes");													// List of Users who have 'liked' this Post.
				$shares = $post->getProperty("shares");													// The shares count of this post. For public posts, it is only shown after the post has been shared more than 10 times.
				$comments = $post->getProperty("comments");												// List of Comments on this Post.
				
				//$content .= "Post: <pre>" . print_r( $post, true ) . "</pre>";

				// Calculate how long ago Post was posted
				$postDate = date_create( $createdTime );
				//$createdAt = date_format( $postDate, "Y/m/d H:i:s" ) . " GMT";
				$timeDiff = snc_getHowLongAgo( $postDate );

				// Process extracted fields ready for display markup
				$sharesCount = 0;
				if ( isset( $shares ) ) {
					$sharesCount = $shares->getProperty( "count" );
				}
				$sharesCountTxt = $sharesCount;
				if ( $sharesCount < 10 ) {
					$sharesCountTxt = "<10";
				}
				
				// Populate Message with Story contents if empty
				if ( $message == "" ) {
					$message = $story;
				}
				
				/////////////////////
				// Write to log file
				$tempOutput = 
					"POST:\nid = $id\n" .
					"from = " . print_r( $from, true ) . "\n" .
					"from (name) = " . ( $from ? $from->getProperty( "name" ) : "" ) . "\n" .
					"from (id) = " . ( $from ? $from->getProperty( "id" ) : "" ) . "\n" .
					"to = " . print_r( $to, true ) . "\n";
				$tempOutput .= <<<EOF
story = $story
message = $message
picture = $picture
link = $link
name = $name
type = $type
createdTime = $createdTime\n
EOF;

				$tempOutput .= 
					"likes (data) = " . print_r( ( $likes ? $likes->getProperty( "data" ) : "" ), true ) . "\n" .
					"shares (count) = " . ( $shares ? $shares->getProperty( "count" ) : "" ) . "\n" .
					"comments (data) = " . print_r( ( $comments ? $comments->getProperty( "data" ) : "" ), true ) . "\n";
				//snc_setLogMessage( $tempOutput );
				//////////////////////

				// Replace Link in Message with a clickable one
				$message = snc_getCleanEllipsis( snc_getConvertReturnsToHtml( snc_getConvertLinksToHtml( $message, true ) ) );
				
				$facebookUserId = "";
				if ( isset( $_SESSION['facebook_id'] ) ) {
					$facebookUserId = $_SESSION['facebook_id'];
				}
				
				snc_setLogMessage( "snc_getFacebookIndivPost() - facebookUserId = " . $facebookUserId );
				
				// Create Likes icon and number plus determine the image to be displayed
				$likesUrl = $sncBaseUrl . "images/icon_like.png";
				$likesCount = 0;
				$likesIconTxt = "likes";
				$likesIconImg = $sncBaseUrl . "images/facebook/fb-likes.png";
				$likesList = array();
				if ( isset( $likes ) ) {
					$likesList = $likes->getProperty( "data" )->asArray();
					$likesCount = sizeof( $likesList );
					foreach ( $likesList as $likeItem ) {
						$likeUserId = $likeItem->id;
						if ( $facebookUserId == $likeUserId ) {
							$likesIconTxt = "liked";
							$likesIconImg = $sncBaseUrl . "images/facebook/fb-liked.png";
						}
					}
				}
				$likesTxt = "";
				if ( $likesCount > 0 ) { 
					$likesUrl = $sncBaseUrl . "images/icon_likes.png";
					if ( $likesCount == 25 ) { 
						$likesTxt = "+";
					}
				}

				// Create Comments number plus determine if image needs to be displayed
				$commentsCount = 0;
				$commentsTxt = "No Comments";
				$commentsList = array();
				if ( isset( $comments ) ) {
					$commentsList = $comments->getProperty( "data" )->asArray();
					$commentsCount = sizeof( $commentsList );
					$commentsTxt = "Recent Comments (" . $commentsCount;
					if ( $commentsCount == 25 ) { 
						$commentsTxt .= "+";
					}
					$commentsTxt .= ")";
				}
	
				// Write to log file
				//snc_setLogMessage( "likes=" . print_r( $likes->{"data"}, true) );
				
				// Retrieve Name & ID of Poster
				$posterName = $from->getProperty( "name" );
				$posterId = $from->getProperty( "id" );
				
				//$pagePictures = snc_getFacebookPictures( $session, array( "/" . $posterId ) );	// Retrieve Pictures for Pages being processed - NO LONGER REQUIRED
				//$posterPictureUrl = $pagePictures[$posterId];									//  - NO LONGER REQUIRED
				
				// Format Profile image of Poster
				$posterImage = "";
				if ( $from && $from->getProperty( "picture" ) && $from->getProperty( "picture" )->getProperty( "url" ) ) {
					$posterPictureUrl = $from->getProperty( "picture" )->getProperty( "url" );
					$posterImage .= <<<EOF
<img class="sncBodyImage" src="$posterPictureUrl" alt="$posterName" title="$posterName" />
EOF;
				}

				// Create link to original post
				$postLinkId = str_replace( "_", "/posts/", $id );

				// Prepare HTML links for images and within plugin
				$facebookLogoLink = $sncBaseUrl . "images/facebook/FB-f-Logo__blue_29.png";

				// Construct Shared icon URL
				$defaultSharedIconUrl = $sncBaseUrl . "images/facebook/fb-shared.png";

				// Output Post
				$content .= <<<EOF
	<div class="sncPopUpIndivPostItem">
		<table width="100%" cellspacing="0" cellpadding="1">
			<tr class="sncHeader">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="48">$posterImage</td>
							<td align="center"><strong><a href="" onclick="snc_getLoadSocialPopup( '$sncBaseUrl', 'facebook', '$posterId' );return false;">$posterName</a></strong></td>
							<td width="48"><a href="https://www.facebook.com/$postLinkId" rel="nofollow" target="_blank"><img class="sncBodyImage" src="$facebookLogoLink" alt="original Post" title="original Post" /></a></td>
						</tr>
					</table>
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncBody">
				<td align="center">
EOF;
				if ( $picture != "" && $fullPicture != "" ) {
					$content .= <<<EOF
					<a href="" onClick="snc_doOpenPopupLink( '$fullPicture', 'image' );return false;" rel="nofollow"><img class="sncBodyImage" src="$picture" alt="view larger picture" title="view larger picture" /></a><br />
EOF;
				} else if ( $picture != "" ) {
					if ( $type == "photo" && $link != "" ) {
						$content .= <<<EOF
					<img class="sncBodyImage" src="$picture" alt="View Original Photo" title="View Original Photo" /></a><br />
EOF;
					} else {
						$content .= <<<EOF
					<img class="sncBodyImage" src="$picture" /><br />
EOF;
					}
				}
				//$content .= "<pre>" . print_r( $item, true ) . "</pre>";
				$content .= <<<EOF
					<p>$message</p>
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncFooter">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="33%"><div class="fb-like"><img src="$likesIconImg" alt="$likesIconTxt" title="$likesIconTxt" /></div></td>
							<td width="33%" align="center"></td>
							<td width="33%" align="right"><div class="fb-share-button" data-href="https://www.facebook.com/$postLinkId" data-layout="button" style="background-image: url('$defaultSharedIconUrl');"></div></td>
						</tr>
						<tr>
							<td width="33%" class="sncFooterLeft">$likesCount$likesTxt</td>
							<td width="33%" class="sncFooterCenter">$timeDiff</td>
							<td width="33%" class="sncFooterRight">$sharesCountTxt</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncCommentsHeader"><td>$commentsTxt</td></tr>
EOF;
				if ( !empty( $commentsList ) ) {
					$content .= <<<EOF
			<tr>
				<td>
					<table width="100%" class="sncComments" cellspacing="0" cellpadding="1">
EOF;
					foreach ( $commentsList as $comment ) {
						$id = $comment->{"id"};
						$from = $comment->{"from"};
						$fromId = $from->{"id"};
						$fromName = $from->{"name"};
						$message = $comment->{"message"};
						$createdTime = $comment->{"created_time"};

						// Calculate how long ago Comment was made
						$commentDate = date_create( $createdTime );
						$timeDiff = snc_getHowLongAgo( $commentDate );				
						
						$content .= <<<EOF
						<tr class="sncCommentsLine"><td></td></tr>
						<tr>
							<td width="100%" valign="top"><span class="sncCommentsName">$fromName:</span> $message <span class="sncCommentsSmall"><em>$timeDiff</em></span></td>
						</tr>
EOF;
					}
					$content .= <<<EOF
					</table>
				</td>
			</tr>
EOF;
				}
				$content .= <<<EOF
		</table>
		<!--div class="fb-post" data-href="https://www.facebook.com/$postLinkId" data-width="350"></div-->
	</div>
EOF;
			}
		} catch( FacebookAuthorizationException $ex ) {
			$_SESSION["warning"] = "Could not retrieve Facebook Post. Please ensure you are logged into Facebook.";
			//snc_setLogMessage( "facebook.php - snc_getFacebookPictures() exception = " . print_r( $ex, true ) );
		} catch( Exception $ex ) {
			$_SESSION["error"] = "Could not retrieve Facebook Post. Please contact support.";
			snc_setLogMessage( "facebook.php - snc_getFacebookIndivPost() exception = " . print_r( $ex, true ) );
		}
	}
	
	return $content;
}

/*
 * Function to get the latest Facebook posts for a Page.
 * 
 * Parameters:
 * - session:			Facebook session.
 * - pageIds:			List of IDs for Facebook Pages.
 * - limit:				number of Posts to retrieve and display per Page.
 * - maxPosts:			number of Posts to retrieve for all Pages in total.
 * 
 * Return:
 * - HTML containing retrieved Posts.
 */
function snc_getFacebookPagePosts ( $session, $pageIds, $limit = SNC_DEFAULT_NUM_POSTS, $maxPosts = SNC_MAX_NUM_POSTS ) {

	$content = "";

	$sncBaseUrl = SNC_BASE_URL;

	// see if we have a session
	if ( isset( $session ) ) {
		
		//$pagePictures = snc_getFacebookPictures( $session, $pageIds );	// Retrieve Pictures for Pages being processed
		$posterIds = array();	// List of Page IDs so snc_getFacebookPictures() can be called with the numeric IDs rather than name IDs

		$posts = array();	// Vehicle for storing Posts as a call will be made to Facebook per Page
		
		foreach ( $pageIds as $pageId ) {

			try {

				// graph api request for page data
				//$graphObject = ( new FacebookRequest( $session, "GET", $pageId . "/posts?fields=id,from{id,name},picture,message,likes,shares,created_time,name,description,link&limit=" . $limit ) )->execute()->getGraphObject();
				
				// Check if cached version of Facebook request available otherwise make fresh call to Facebook
				$apiURL = $pageId . "/posts?fields=id,from{id,name,picture},picture,full_picture,message,likes,shares,created_time,name,description,link&limit=" . $limit;
				snc_setLogMessage( "facebook.php - snc_getFacebookPagePosts() apiURL = $apiURL" );
				$graphObject = snc_doCheckFacebookCache( $session, $apiURL, "Page Posts" );

				// Extract each Post from the Graph API result for current Page
				if ( $graphObject ) {
					$postList = array();
					if ( in_array( "data", $graphObject->getPropertyNames() ) ) {
						
						// Convert Graph API result to an array for easier processing
						$postList = $graphObject->getProperty( "data" )->asArray(); 
					}
					//$content .= "<pre>" . print_r( $postList, true ) . "</pre>";

					// Trim Posts per Page if greater than limit
					if ( sizeof( $postList ) > $limit ) {
						$postList = array_slice( $postList, 0, $limit );
					}
			
					foreach ( $postList as $post ) {

						// Write to log file
						//snc_setLogMessage( print_r( $post, true ) );

						$createdTime = $post->{"created_time"};
						$posts[$createdTime] = $post;
						
						// Save Poster ID's for call to retrieve Profile pictures
						$from = ( isset( $post->{"from"} ) ? $post->{"from"} : null );
						if ( isset( $post->{"from"} ) ) {
							$posterIds[] = "/" . $from->{"id"};
						}
					}
				}
			} catch( FacebookAuthorizationException $ex ) {
				//$_SESSION["warning"] = "Could not retrieve some Facebook Posts.";
				//snc_setLogMessage( "facebook.php - snc_getFacebookPagePosts() exception = " . print_r( $ex, true ) );
			} catch( FacebookRequestException $ex ) {
				$_SESSION["warning"] = "Could not retrieve some Facebook Posts.";
				snc_setLogMessage( "facebook.php - snc_getFacebookPagePosts() exception = " . print_r( $ex, true ) );
			} catch( Exception $ex ) {
				$_SESSION["error"] = "Could not retrieve some Facebook Posts. Please contact support.";
				snc_setLogMessage( "facebook.php - snc_getFacebookPagePosts() exception = " . print_r( $ex, true ) );
			}
		}
				
		//$pagePictures = snc_getFacebookPictures( $session, $posterIds );	// Retrieve Pictures for Pages being processed - NO LONGER REQUIRED

		$facebookUserId = "";
		if ( isset( $_SESSION['facebook_id'] ) ) {
			$facebookUserId = $_SESSION['facebook_id'];
		}
		
		snc_setLogMessage( "snc_getFacebookPagePosts() - facebookUserId = " . $facebookUserId );

		// Sort Posts so in Descending Date order - now done by Isotope
		//krsort( $posts );
		
		// Crude limiting of number of Posts which will favor earlier specified accounts/users over the latter
		$posts = array_slice( $posts, 0, $maxPosts );

		foreach ( $posts as $createdTime => $post ) {
				
			try {
				$id = ( isset( $post->{"id"} ) ? $post->{"id"} : 0 );									// The post ID
				$createdTime = ( isset( $post->{"created_time"} ) ? $post->{"created_time"} : null );	// The time the post was initially published.
				$from = ( isset( $post->{"from"} ) ? $post->{"from"} : null );							// Information about the profile that posted the message.
				$likes = ( isset( $post->{"likes"} ) ? $post->{"likes"} : "" );							// List of Users who have 'liked' this Post.
				$link = ( isset( $post->{"link"} ) ? $post->{"link"} : "" );							// The link attached to this post.
				$name = ( isset( $post->{"name"} ) ? $post->{"name"} : "" );							// The name of the link.
//				$description = ( isset( $post->{"description"} ) ? $post->{"description"} : "" );		// Text associated with the link.
				$message = ( isset( $post->{"message"} ) ? $post->{"message"} : "" );					// The status message in the post.
				$picture = ( isset( $post->{"picture"} ) ? $post->{"picture"} : "" );					// The picture scraped from any link included with the post.
				$fullPicture = ( isset( $post->{"picture"} ) ? $post->{"full_picture"} : "" );				// The full picture scraped from any link included with the post.
				$shares = ( isset( $post->{"shares"} ) ? $post->{"shares"} : null );					// The shares count of this post. For public posts, it is only shown after the post has been shared more than 10 times.
//				$statusType = $post->{"status_type"};													// Description of the type of a status update.
//				$type = $post->{"type"};																// A string indicating the object type of this post.
//				$updatedTime = $post->{"updated_time"};													// The time of the last change to this post, or the comments on it.
				
				//$content .= "Post: <pre>" . print_r( $post, true ) . "</pre>";

				// Calculate how long ago Post was posted
				$postDate = date_create( $createdTime );
				//$createdAt = date_format( $postDate, "Y/m/d H:i:s" ) . " GMT";
				$timeDiff = snc_getHowLongAgo( $postDate );
				$timestamp = $postDate->getTimestamp();

				// Process extracted fields ready for display markup
				$sharesCount = 0;
				if ( isset( $shares->{"count"} ) ) {
					$sharesCount = $shares->{"count"};
				}
				$sharesCountTxt = $sharesCount;
				if ( $sharesCount < 10 ) {
					$sharesCountTxt = "<10";
				}
				
				// Populate Message with Story contents if empty
				if ( $message == "" ) {
					$message = "$name<br /> $link";
				}

				// Replace Link in Message with a clickable one
				$message = snc_getCleanEllipsis( snc_getConvertReturnsToHtml( snc_getSummaryWithLinks( snc_getConvertLinksToHtml( $message, true ) ) ) );

				// Create Likes icon and number plus determine the image to be displayed
				$likesUrl = $sncBaseUrl . "images/icon_like.png";
				$likesCount = 0;
				$likesIconTxt = "likes";
				$likesIconImg = $sncBaseUrl . "images/facebook/fb-likes.png";
				$likesList = array();
				if ( isset( $likes->{"data"} ) ) {
					$likesList = $likes->{"data"};
					snc_setLogMessage( "likesList = " . print_r( $likesList, true ) );
					$likesCount = sizeof( $likesList );
					foreach ( $likesList as $likeItem ) {
						$likeUserId = $likeItem->id;
						if ( $facebookUserId == $likeUserId ) {
							$likesIconTxt = "liked";
							$likesIconImg = $sncBaseUrl . "images/facebook/fb-liked.png";
						}
					}
				}
				$likesTxt = "";
				if ( $likesCount > 0 ) { 
					$likesUrl = $sncBaseUrl . "images/icon_likes.png";
					if ( $likesCount == 25 ) { 
						$likesTxt = "+";
					}
				}

				// Create Page name and link
				$posterName = $from->{"name"};
				$posterId = $from->{"id"};
				$posterPictureUrl = "";
				if ( isset( $from->{"picture"} ) && isset( $from->{"picture"}->{"data"} ) && isset( $from->{"picture"}->{"data"}->{"url"} ) ) {
					$posterPictureUrl = $from->{"picture"}->{"data"}->{"url"};
				}
				
				// Format Profile image of Poster
				$posterImage = "";
				if ( $posterPictureUrl != "" ) {
					$posterImage .= <<<EOF
<img class="sncBodyImage" src="$posterPictureUrl" alt="$posterName" title="$posterName" />
EOF;
				}

				// Create link to original post
				$postLinkId = str_replace( "_", "/posts/", $id );

				// Prepare HTML links for images and within plugin
				$facebookLogoLink = $sncBaseUrl . "images/facebook/FB-f-Logo__blue_29.png";

				// Construct Shared icon URL
				$defaultSharedIconUrl = $sncBaseUrl . "images/facebook/fb-shared.png";

				// Output Post
				$content .= <<<EOF
	<div class="sncItem">
		<table width="100%" cellspacing="0" cellpadding="1">
			<tr class="sncHeader">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="48">$posterImage</td>
							<td align="center"><strong><a class="sncHeaderTitle" href="#magnific-inline-popup" onclick="snc_getLoadSocialPopup( '$sncBaseUrl', 'facebook', '$posterId' );return false;">$posterName</a></strong></td>
							<td width="48"><a href="https://www.facebook.com/$postLinkId" rel="nofollow" target="_blank"><img class="sncBodyImage" src="$facebookLogoLink" alt="original Post" title="original Post" /></a></td>
						</tr>
					</table>
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncBody">
				<td align="center">
EOF;

				if ( $picture != "" && $fullPicture != "" ) {
					$content .= <<<EOF
					<a href="$fullPicture" rel="nofollow" class="magnific-image-link"><img class="sncBodyImage" src="$picture" alt="view larger picture" title="view larger picture" /></a><br />
EOF;
				}
				else if ( $picture != "" ) {
					$content .= <<<EOF
					<img class="sncBodyImage" src="$picture" /><br />
EOF;
				}
				//$content .= "<pre>" . print_r( $item, true ) . "</pre>";
				$content .= <<<EOF
					$message
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncFooter">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="33%"><div class="fb-like"><img src="$likesIconImg" alt="$likesIconTxt" title="$likesIconTxt" /></div></td>
							<td width="33%" class="sncViewPost"><a class="sncInlineLink" href="#magnific-inline-popup" onclick="snc_getLoadSocialPopup( '$sncBaseUrl', 'facebook', '$id' );return false;">view post</a></td>
							<td width="33%" align="right"><div class="fb-share-button" data-href="https://www.facebook.com/$postLinkId" data-layout="button" style="background-image: url('$defaultSharedIconUrl');"></div></td>
						</tr>
						<tr>
							<td width="33%" class="sncFooterLeft">$likesCount$likesTxt</td>
							<td width="33%" class="sncFooterCenter">$timeDiff</td>
							<td width="33%" class="sncFooterRight">$sharesCountTxt</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<div id="sncTimestamp" class="sncTimestamp">$timestamp</div>
	</div>
EOF;
				
				/*$content .= <<<EOF
<div align="center">
	<div class="fb-like-box" data-href="$fbLink" data-width="500" data-height="500" data-colorscheme="light" data-show-faces="false" data-header="true" data-stream="true" data-show-border="true"></div>
</div>
EOF;*/
			} catch( Exception $ex ) {
				$_SESSION["error"] = "Could not retrieve Facebook Posts. Please contact support.";
				snc_setLogMessage( "facebook.php - snc_getFacebookPagePosts() exception = " . print_r( $ex, true ) );
			}
		}
	}
	
	return $content;
}

/*
 * Function to get the Facebook profile for a particular Page.
 * 
 * Parameters:
 * - session:			Facebook session.
 * - pageIds:			List of IDs for Facebook Pages.
 * 
 * Return:
 * - HTML containing retrieved Page Profile.
 */
function snc_getFacebookPageProfile ( $session, $pageIds ) {

	$content = "";
	
	$sncBaseUrl = SNC_BASE_URL;

	// see if we have a session
	if ( isset( $session ) ) {
		
		foreach ( $pageIds as $pageId ) {

			try {

				// graph api request for page data
				//$graphObject = ( new FacebookRequest( $session, "GET", "/" . $pageId . "?fields=id,name,about,category,link,likes,talking_about_count,picture{url},website" ) )->execute()->getGraphObject();

				// Check if cached version of Facebook request available otherwise make fresh call to Facebook
				$apiURL = "/" . $pageId . "?fields=id,name,about,category,link,likes,talking_about_count,picture{url},website";
				$graphObject = snc_doCheckFacebookCache( $session, $apiURL, "Page Profile", 120, 1440 ); // override cache so Profile retrieved every 2 hours & refreshed every day
				
				if ( $graphObject ) {
					$page = $graphObject;

					// Write to log file
					snc_setLogMessage( "snc_getFacebookPageProfile() - page=" . print_r( $page, true ) );

					// Create Page name and link
					$posterId = $page->getProperty( "id" );
					$posterName = $page->getProperty( "name" );
					$posterAbout = $page->getProperty( "about" );
					$posterCategory = $page->getProperty( "category" );
					$posterUrl = $page->getProperty( "link" );
//					$posterLikes = $page->getProperty( "likes" );
					$posterTalkingAbout = $page->getProperty( "talking_about_count" );
					$posterPicture = $page->getProperty( "picture" )->asArray();
					$posterPictureUrl = snc_getValueForKey( $posterPicture, "url", "" );
					
					// Create page website
					$posterWebSite = $page->getProperty( "website" );
					if ( substr( $posterWebSite, 0, 4 ) != "http" ) {
						$posterWebSite = "http://" . $posterWebSite;
					}
					
					// Create page profile
					$profileTxt = "";
					if ( $posterWebSite != "" ) {
						$wwwIconLink = $sncBaseUrl . "images/icon_link.png";
						$profileTxt =<<<EOF
<a href="$posterWebSite" rel="nofollow" target="_blank"><img src="$wwwIconLink" alt="view website" title="view website" /></a>
EOF;
					}
					
					// Format User Profile image
					$profileImage = "";
					if ( $posterPictureUrl != "" ) {
						$profileImage .= <<<EOF
<img src="$posterPictureUrl" alt="$posterName" title="$posterName" />
EOF;
					}

					// Prepare HTML links for images and within plugin
					$facebookIconLink = $sncBaseUrl . "images/icon_facebook.png";

					// Output Profile
					$content .= <<<EOF
	<div class="sncPopUpProfileItem">
		<div class="fb-page" data-href="$posterUrl" data-hide-cover="false" data-show-facepile="false" data-show-posts="false"></div>
		<table width="100%" cellspacing="0" cellpadding="1">
			<!--tr class="sncHeader"><td colspan="2" align="center"><strong>$posterName</strong></td></tr-->
			<tr class="sncBody">
				<td>
					<table width="100%" cellpadding="0" cellspacing="0">
						<tr class="sncBodyLeft">
							<td width="30%">Profile:</td>
							<td width="70%"><a href="$posterUrl" rel="nofollow" target="_blank"><img src="$facebookIconLink" alt="view Facebook page" title="view Facebook page" /></a></td>
						</tr>
						<tr class="sncBodyLeft">
							<td>Popularity:</td>
							<td>$posterTalkingAbout</td>
						</tr>
						<tr class="sncBodyLeft">
							<td>Category:</td>
							<td>$posterCategory</td>
						</tr>
						<tr class="sncBodyLeft">
							<td>Web Site:</td>
							<td>$profileTxt</td>
						</tr>
EOF;
					if ( $posterAbout != "" ) {
						$posterAbout = snc_getCleanEllipsis( snc_getConvertLinksToHtml( $posterAbout ) );
						$content .= <<<EOF
						<tr class="sncBody">
							<td colspan="2" align="center"><em>$posterAbout</em></td>
						</tr>
EOF;
					}
			$content .= <<<EOF
					</table>
				</td>
			</tr>
		</table>
	</div>
EOF;
				}
			} catch ( Exception $ex ) {
				$_SESSION["error"] = "Could not retrieve Facebook Profile. Please contact support.";
				snc_setLogMessage( "facebook.php - snc_getFacebookPageProfile() exception = " . print_r( $ex, true ) );
			}
		}
	}
	
	return $content;
}

/*
 * Function to get Facebook pictures for Profile Pages.
 * 
 * Parameters:
 * - session:			Facebook session.
 * - pageIds:			List of IDs for Facebook Pages (Note: each ID must be preceeded with a '/').
 * 
 * Return:
 * - List of Picture URLs.
 */
function snc_getFacebookPictures ( $session, $pageIds ) {

	$pictureList = array();	// Vehicle for storing Pictures as a call will be made to Facebook per Page
			
	// see if we have a session
	if ( isset( $session ) ) {
		
		foreach ( $pageIds as $pageId ) {

			try {

				// graph api request for page data
				//$graphObject = ( new FacebookRequest( $session, "GET", $pageId . "?fields=picture{url}" ) )->execute()->getGraphObject();

				// Check if cached version of Facebook request available otherwise make fresh call to Facebook
				$apiURL = $pageId . "?fields=picture{url}";
				$graphObject = snc_doCheckFacebookCache( $session, $apiURL, "Page Picture", 120, 1440 ); // override cache so Profile retrieved every 2 hours & refreshed every day

				$pictureList[$graphObject->getProperty( "id" )] = $graphObject->getProperty( "picture" )->asArray()["url"];
				
			} catch( FacebookAuthorizationException $ex ) {
				//$_SESSION["warning"] = "Encountered a problem retrieving a Facebook Picture.";
				//snc_setLogMessage( "facebook.php - snc_getFacebookPictures() exception = " . print_r( $ex, true ) );
			} catch( FacebookRequestException $ex ) {
				$_SESSION["warning"] = "Could not retrieve a Facebook Picture.";
				snc_setLogMessage( "facebook.php - snc_getFacebookPictures() exception = " . print_r( $ex, true ) );
			} catch( Exception $ex ) {
				$_SESSION["error"] = "Could not retrieve a Facebook Picture. Please contact support.";
				snc_setLogMessage( "facebook.php - snc_getFacebookPictures() exception = " . print_r( $ex, true ) );
			}
		}
	}
	
	return $pictureList;
}

/*
 * Function to establish a Facebook session.
 * 
 * Parameters:
 * - fbAppId		: App ID for Facebook
 * - fbSecret		: Facebook Secret
 * - accessToken	: optional Access Token
 * 
 * Return:
 * - established Facebook session.
 */
function snc_getFacebookSession ( $fbAppId, $fbSecret, $accessToken = "" ) {

	// Initialise Application with app id and secret
	FacebookSession::setDefaultApplication( $fbAppId, $fbSecret );

	if ( $accessToken != "" ) { // If you already have a valid access token then renew session
		$session = new FacebookSession( $accessToken );
	} else { // If you're making app-level requests then create a new session
		$session = FacebookSession::newAppSession();
	}

	// To validate the session:
	try {
		$session->validate();
	} catch ( FacebookRequestException $ex ) {
		$_SESSION["warning"] = "Could not establish a Facebook session.";
		snc_setLogMessage( "facebook.php - snc_getFacebookSession() exception = " . print_r( $ex, true ) );
	} catch ( Exception $ex ) {
		$_SESSION["error"] = "Could not establish a Facebook session. Please contact support.";
		snc_setLogMessage( "facebook.php - snc_getFacebookSession() exception = " . print_r( $ex, true ) );
	}
	
	return( $session );
}

/*
 * Function to obtain a Facebook User Token provided they are already logged in.
 * 
 * Parameters:
 * - fbAppId		: App ID for Facebook
 * - fbSecret		: Facebook Secret
 * 
 * Return:
 * - User Token.
 */
function snc_getFacebookUserToken ( $fbAppId, $fbSecret ) {

	$accessToken = "";

	try {

		// Initialize the SDK
		FacebookSession::setDefaultApplication( $fbAppId, $fbSecret );

		// Create the login helper
		$helper = new FacebookJavaScriptLoginHelper();

		// Retrieve session via helper
		$session = $helper->getSession();
		
		// Check if User logged in by the existence of a session
		if ( $session ) {

			// Retrieve User Token
			$accessToken = $session->getToken();
			
			// Save Facebook User ID
			$_SESSION["facebook_id"] = $session->getUserId();
			snc_setLogMessage( "_SESSION['facebook_id'] = " . $_SESSION["facebook_id"] );
		} else {
			unset( $_SESSION['facebook_id'] );
		}

	} catch ( FacebookAuthorizationException $ex ) {
		$_SESSION["warning"] = "Could not complete Facebook authorization.";
		snc_setLogMessage( "facebook.php - snc_getFacebookUserToken() exception = " . print_r( $ex, true ) );
	} catch ( FacebookThrottleException $ex ) {
		if ( $ex.getHttpStatusCode() == 17 ) {
			$accessToken = "";
		}
	} catch ( Exception $ex ) {
		$_SESSION["error"] = "Could not obtain access to Facebook. Please contact support.";
		snc_setLogMessage( "facebook.php - snc_getFacebookUserToken() exception = " . print_r( $ex, true ) );
	}

	return( $accessToken );
}

// Perform inline code when run in Stand-alone mode only
if ( !defined( 'ABSPATH' ) ) {

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

	snc_setLogMessage( "*** START facebook.php ***" );

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

	//error_log( "userToken = '$userToken'" );
	//error_log( "appToken = '$appToken'" );
	//error_log( "Session: " . print_r($session, true) );

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
			switch ( $infoType ) {
				case "post":

					// Call Facebook to retrieve Profile
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

	// Return any errors & data to calling script
	$response = new stdClass();

	if ( isset( $_SESSION["error"] ) ) {
		$response->error = $_SESSION["error"];
	} elseif ( isset( $_SESSION["warning"] ) ) {
		$response->warning = $_SESSION["warning"];
	} elseif ( isset( $_SESSION["message"] ) ) {
		$response->message = $_SESSION["message"];
	}
	do_sncResetSessionMessages();

	$response->data = $content;
	echo json_encode( $response );

	//snc_setLogMessage( print_r( json_encode( $response ), true ) );

	snc_setLogMessage( "*** END facebook.php ***" );
}

?>
