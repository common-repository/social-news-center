<?php

// Needed to pass session data between pages
if( !isset( $_SESSION ) ) {
	session_start();
}

/*
 * Function to check for a cached version of this request, if not available submit request to Instagram and cache the results.
 * 
 * Parameters:
 * - instagramSettings:	Instagram API access settings.
 * - apiURL:			Instagram request.
 * - dataType:			Type of data requested e.g. User Profile, User Media etc.
 * - cacheLimit:		Expiry of individual cached item (in minutes) before being re-read from Instagram.
 * - cacheReset:		Expiry of cached items (in minutes) before being completely reset.
 * 
 * Return:
 * - JSON data containing Post(s) or User Profile if successfully retrieved from the cache or Instagram itself otherwise NULL or False.
 */
function snc_doCheckInstagramCache ( $instagramSettings, $apiURL, $dataType, $cacheLimit = SNC_INSTAGRAM_DEF_CACHE_LIMIT, $cacheReset = SNC_INSTAGRAM_DEF_CACHE_RESET ) {
	
	// Initialize parameters
	$requestMethod = "GET";
	$instagramResponse = null;
	$timestampKey = $apiURL . "_timestamp";

	snc_setLogMessage( "snc_doCheckInstagramCache():" );
//	snc_setLogMessage( date("Y-m-d H:i:s") );
	snc_setLogMessage( "apiURL = " . $apiURL );
	
	// Extract Type of Request to assist with naming of cache file
 	$requestType = "";
 	
 	if ( strpos( $apiURL, "?" ) > 0 ) {
		$requestType = 
			str_replace( "@", "", 
				str_replace( "/", "-", 
					str_replace( "_", "-", 
						str_replace( SNC_INSTAGRAM_API_URL, "",
							str_replace( SNC_INSTAGRAM_URL, "",
								substr( $apiURL, 0, strpos( $apiURL, "?" ) -1 )
							)
						)
					)
				)
			);
	} else {
		$requestType = 
			str_replace( "@", "", 
				str_replace( "/", "-", 
					str_replace( "_", "-", 
						str_replace( SNC_INSTAGRAM_API_URL, "",
							str_replace( SNC_INSTAGRAM_URL, "", $apiURL )
						)
					)
				)
			);
	}
	
	// Modify Request Type for cache file name if needed
	switch ( $dataType ) {
		case "User Profile":
			$requestType .= "-profile";
			break;
		case "User Recent Media":
			break;
	}
	
	$cacheFile = SNC_BASE_PATH . "cache/instagram/" . str_replace( "-.", ".", $requestType . ".data" );
	snc_setLogMessage( "cacheFile after = " . $cacheFile );

	// Check for existence of cached result within caching period (limit)
	if ( file_exists( $cacheFile ) ) {
		snc_setLogMessage( "File '$cacheFile' exists" );
		$data = unserialize( file_get_contents( $cacheFile ) );
		if ( $data[$timestampKey] > time() - $cacheLimit * 60 ) {
			snc_setLogMessage( "Cached item still valid" );
			$instagramResponse = $data[$apiURL];
		}
	}

	// If cache doesn't exist or is older than caching period (limit) then fetch data from Instagram
	if ( !$instagramResponse ) { 

		// Check if cache needs resetting i.e. deleting
		if ( file_exists( $cacheFile ) ) {
			snc_setLogMessage( "Retrieve remainder of cache" );
			$otherData = unserialize( file_get_contents( $cacheFile ) );
			if ( $otherData['cache_updated'] < time() - $cacheReset * 60 ) {
				snc_setLogMessage( "Clear cache as expired" );
				unset( $otherData ); // clear cached file
			}
		}
		
		snc_setLogMessage( "Need to query Instagram" );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $apiURL );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 20 );
		$response = curl_exec( $ch );
		curl_close( $ch );
		 
		//snc_setLogMessage( "Instagram API response = " . print_r( $response, true ) . "\n" );

		if ( $response !== false ) {
			$instagramResponse = json_decode( $response, $assoc = TRUE );
			//snc_setLogMessage( "Response JSON = " . print_r( $instagramResponse, true ) . "\n" );
		}

		// Each page gets its own key / value
		$data = array(
			$apiURL => $instagramResponse, 
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
				$_SESSION["warning"] = "Unable to cache Instagram information.";
				snc_setLogMessage( "instagram.php - snc_doCheckInstagramCache() warning #2 = " . $_SESSION["warning"] );
			}
		} catch ( Exception $e ) {
			$_SESSION["error"] = "Instagram caching problem occurred. Please contact support.";
			snc_setLogMessage( "instagram.php - snc_doCheckInstagramCache() exception = " . print_r( $ex, true ) );
		}
	}

	return $instagramResponse;
}

/*
 * Function to check Instagram User access settings.
 * 
 * Parameters:
 * - instagramSettings:	Instagram API access settings.
 * 
 * Return:
 * - TRUE if verified otherwise FALSE.
 */
function snc_doCheckInstagramSettings ( $instagramSettings ) {
	
	$result = false;
	
	$apiURL = "https://api.instagram.com/v1/users/self/?access_token=" . $instagramSettings["access_token"];

	snc_setLogMessage( "apiURL = $apiURL\n" );

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $apiURL );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 20 );
	$response = curl_exec( $ch );
	curl_close( $ch );
	 
	snc_setLogMessage( "Instagram API response = " . print_r( $response, true ) . "\n" );

	if ( $response !== false ) {
		$jsonResponse = json_decode( $response );
		snc_setLogMessage( "Response JSON = " . print_r( $jsonResponse, true ) . "\n" );
		if ( !empty( $jsonResponse ) && isset( $jsonResponse->data->username ) ) {
			$result = true;
		}
	}

	return $result;
}

/*
 * Function to get an individual Instagram media item with comments.
 * 
 * Parameters:
 * - instagramSettings:	Instagram API access settings.
 * - itemIds:			ID of individual Media items on Instagram, only the first will be retrieved.
 * 
 * Return:
 * - HTML containing retrieved Post.
 */
function snc_getInstagramIndivMedia ( $instagramSettings, $itemIds ) {

	snc_setLogMessage( "snc_getInstagramIndivMedia():" );

	$content = "";
	
	$sncBaseUrl = SNC_BASE_URL;
		
	$post = array();	// Vehicle for storing Post for specified Media item from User
	
	if ( sizeof( $itemIds ) > 0 ) {
		
		list( $itemId, $userId ) = explode( " - ", $itemIds[0] );
	
		$url = SNC_INSTAGRAM_URL . $userId . "/media/";
		
		// Check if cached version of Instagram request available otherwise make fresh call to Instagram
		$instagramResponse = snc_doCheckInstagramCache( $instagramSettings, $url, "User Recent Media" );
		
		// Write to log file
		snc_setLogMessage( "url = " . $url . ", sizeof = " . sizeof( $instagramResponse["items"] ) );
		//snc_setLogMessage( "instagramResponse = " . print_r( $instagramResponse, true ) );

		if ( !empty( $instagramResponse ) && isset( $instagramResponse["status"] ) && $instagramResponse["status"] == "ok"  && isset( $instagramResponse["items"] ) ) {
			
			// Process Posts
			foreach ( $instagramResponse["items"] as $item ) {
				
				snc_setLogMessage( "userId = $userId, id = " . snc_getValueForKey( $item, "id", "" ) );
				
				// Place matching Post into an array
				if ( empty( $post ) && $itemId == snc_getValueForKey( $item, "id", "" ) ) {
					$post = $item;
				}
			}
		}
	}
	
	snc_setLogMessage( "post = " . print_r( $post, true ) );

	if ( !empty( $post ) ) {

		// Extract fields for an individual item
		$itemCode = snc_getValueForKey( $post, "code", "" );
//		$itemLocation = snc_getValueForKey( $post, "location", "" );
		$itemImages = snc_getValueForKey( $post, "images", array() );
		$itemThumbnail = snc_getValueForKey( $itemImages, "thumbnail", array() );
		$itemLowRes = snc_getValueForKey( $itemImages, "low_resolution", array() );
		$itemStdRes = snc_getValueForKey( $itemImages, "standard_resolution", array() );
//		$itemCanViewComments = snc_getValueForKey( $post, "can_view_comments", false );
		$itemComments = snc_getValueForKey( $post, "comments", array() );
		$itemCommentsCount = snc_getValueForKey( $itemComments, "count", 0 );
		$itemCommentsData = snc_getValueForKey( $itemComments, "data", array() );
		$itemCaptionText = snc_getValueForKey( snc_getValueForKey( $post, "caption", array() ), "text", "" );
		$itemLink = snc_getValueForKey( $post, "link", "" );
		$itemLikes = snc_getValueForKey( $post, "likes", array() );
		$itemLikesCount = snc_getValueForKey( $itemLikes, "count", 0 );
		$itemLikesData = snc_getValueForKey( $itemLikes, "data", array() );

		$itemCreatedTime = snc_getValueForKey( $post, "created_time", "" );
		snc_setLogMessage( "itemCreatedTime = " . $itemCreatedTime );
		
		$itemType = snc_getValueForKey( $post, "type", "" );
		$itemId = snc_getValueForKey( $post, "id", 0 );
		$itemUser = snc_getValueForKey( $post, "user", array() );

		// Extract fields for an individual user
		$itemUsername = snc_getValueForKey( $itemUser, "username", "" );
		$itemUserProfilePicture = snc_getValueForKey( $itemUser, "profile_picture", "" );
		$itemUserFullName = snc_getValueForKey( $itemUser, "full_name", "" );
		$itemUserId = snc_getValueForKey( $itemUser, "id", "" );

		// Extract pop-up image URL
		$itemMediaUrl = "";
		$itemStdResUrl = snc_getValueForKey( $itemStdRes, "url", "" );
		if ( $itemStdResUrl != "" ) {
			$itemMediaUrl = $itemStdResUrl;
		} else {
			$itemMediaUrl = snc_getValueForKey( $itemLowRes, "url", "" );
		}

		// Modify Caption text for item
		$itemCaptionTextOrig = $itemCaptionText;
		$itemCaptionText = snc_getCleanEllipsis( $itemCaptionText );
		$itemCaptionText = snc_getHtmlWrap( $itemCaptionText, 35, "-\n" );

		// Calculate how long ago the post was created
		$itemDate = new DateTime();
		$itemDate->setTimestamp( $itemCreatedTime );
		$timeDiff = snc_getHowLongAgo( $itemDate );
		$timestamp = $itemCreatedTime;

		// Format User Profile image for Header
		$profileImage = "";
		if ( $itemUserProfilePicture != "" ) {
			$profileImage .= <<<EOF
<img class="sncBodyImage" src="$itemUserProfilePicture" width="46" height="46" alt="$itemUserFullName" title="$itemUserFullName" />
EOF;
		}

		// Format Item image
		$itemThumbnailUrl = "";
		$itemThumbnailWidth = "";
		$itemThumbnailHeight = "";

		if ( !empty( $itemThumbnail ) ) {
			$itemThumbnailUrl = $itemThumbnail['url'];
			$itemThumbnailWidth = $itemThumbnail['width'];
			$itemThumbnailHeight = $itemThumbnail['height'];
		}

		// Determine what type of Likes & Comments icons should be displayed
		$commentsId = "sncComments";
		$commentsTxt = "comments";
		snc_setLogMessage( "itemCommentsData =" . print_r( $itemCommentsData, true ) );
		foreach ( $itemCommentsData as $comment ) {
			if ( isset( $comment["from"] ) && isset( $comment["from"]["username"] ) ) { 
				if ( $comment["from"]["username"] == $itemUsername ) {
					$commentsId = "sncCommented";
					$commentsTxt = "commented";
				}
			}
		}

		$likesId = "sncLikes";
		$likesTxt = "likes";
		snc_setLogMessage( "itemLikesData =" . print_r( $itemLikesData, true ) );
		foreach ( $itemLikesData as $user ) {
			if ( $user["username"] == $itemUsername ) {
				$likesId = "sncLiked";
				$likesTxt = "liked";
			}
		}
		
		// Format header title
		//$headerTitle = snc_getBulkWordwrap( $userName );
		$headerTitle = snc_getHtmlWrap( $itemUserFullName, 15, "-\n" );

		// Prepare HTML links for images and within plugin
		$instagramLogoLink = $sncBaseUrl . "images/instagram/instagram-logo-color-32.png";

		// Output Post
		$content .= <<<EOF
	<div class="sncItem">
		<table width="100%" cellspacing="0" cellpadding="1">
			<tr class="sncHeader">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="48">$profileImage</td>
							<td align="center">
								<strong><a class="sncHeaderTitle" href="" onclick="snc_getLoadSocialPopup( '$sncBaseUrl', 'instagram', '@$itemUsername' );return false;">$headerTitle</a></strong><br />
								<a class="sncSubHeader" href="" onclick="snc_getLoadSocialPopup( '$sncBaseUrl', 'instagram', '@$itemUsername' );return false;">@$itemUsername</a>
							</td>
							<td width="48"><a href="$itemLink" rel="nofollow" target="_blank"><img class="sncBodyImage" src="$instagramLogoLink" alt="original Post" title="original Post" /></a></td>
						</tr>
					</table>
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncBody">
				<td align="center">
EOF;

		$itemThumbnailWidth = "";
		$itemThumbnailHeight = "";

		if ( $itemMediaUrl != "" && $itemThumbnailUrl != "" ) {
			$content .= <<<EOF
					<a href="" onClick="snc_doOpenPopupLink( '$itemMediaUrl', 'image' );return false;" rel="nofollow"><img class="sncBodyImage" src="$itemThumbnailUrl" width="$itemThumbnailWidth" height="$itemThumbnailHeight" alt="view larger picture" title="view larger picture" /></a><br />\n
EOF;
		} else if ( $itemThumbnailUrl != "" ) {
			$content .= <<<EOF
					<img class="sncBodyImage" src="$itemThumbnailUrl" width="$itemThumbnailWidth" height="$itemThumbnailHeight" alt="view larger picture" title="view larger picture" /><br />\n
EOF;
		}

		// Create Comments number
		$commentsCount = 0;
		$commentsTxt = "No Comments";
		if ( !empty( $itemCommentsData ) ) {
			$commentsCount = sizeof( $itemCommentsData );
			$commentsTxt = "Recent Comments (" . $commentsCount;
			if ( $commentsCount == 25 ) { 
				$commentsTxt .= "+";
			}
			$commentsTxt .= ")";
		}


		//$content .= "<pre>" . print_r( $item, true ) . "</pre>";
		$content .= <<<EOF
					$itemCaptionText
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncFooter">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="33%"><div id="$likesId" title="$likesTxt"></div></td>
							<td width="33%">&nbsp;</td>
							<td width="33%"><div id="$commentsId" title="$commentsTxt"></td>
						</tr>
						<tr>
							<td width="33%" class="sncFooterLeft">$itemLikesCount</td>
							<td width="33%" class="sncFooterCenter">$timeDiff</td>
							<td width="33%" class="sncFooterRight">$itemCommentsCount</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncCommentsHeader"><td>$commentsTxt</td></tr>
EOF;
		if ( !empty( $itemCommentsData ) ) {

			$content .= <<<EOF
			<tr>
				<td>
					<table width="100%" class="sncComments" cellspacing="0" cellpadding="1">
EOF;
			for ( $i = sizeof( $itemCommentsData ) -1; $i >= 0; $i-- ) {
				$comment = $itemCommentsData[$i];
				
				$id = snc_getValueForKey( $comment, "id", "" );
				$from = snc_getValueForKey( $comment, "from", array() );
				$fromId = snc_getValueForKey( $from, "id", array() );
				$fromName = snc_getValueForKey( $from, "full_name", array() );
				$message = snc_getValueForKey( $comment, "text", array() );
				$createdTime = snc_getValueForKey( $comment, "created_time", array() );

				// Calculate how long ago the comment was created
				$commentDate = new DateTime();
				$commentDate->setTimestamp( $createdTime );
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
	</div>
EOF;
	}

	return $content;
}

/*
 * Function to get the recent Instagram media by a particular User.
 * 
 * See https://www.instagram.com/developer/endpoints/users/#get_users_media_recent
 * 
 * Parameters:
 * - instagramSettings:	Instagram API access settings.
 * - userIds:			List of User Accounts on Instagram.
 * - count:				number of Posts to retrieve and display per Account.
 * - maxPosts:			number of Posts to retrieve for all Accounts in total.
 * 
 * Return:
 * - HTML containing retrieved Posts.
 */
function snc_getInstagramMediaByUser ( $instagramSettings, $userIds, $count = SNC_DEFAULT_NUM_POSTS, $maxPosts = SNC_MAX_NUM_POSTS ) {
	
	snc_setLogMessage( "snc_getInstagramMediaByUser():" );

	$content = "";
	
	$sncBaseUrl = SNC_BASE_URL;
		
	$posts = array();	// Vehicle for storing Posts for each User as a call will be made to Instagram per Account
	
	foreach ( $userIds as $userId ) {
	
		$url = SNC_INSTAGRAM_URL . str_replace( "@", "", $userId ) . "/media/";
		
		// Check if cached version of Instagram request available otherwise make fresh call to Instagram
		$instagramResponse = snc_doCheckInstagramCache( $instagramSettings, $url, "User Recent Media" );
		
		// Write to log file
		snc_setLogMessage( "url = " . $url . ", count = " . $count . ", sizeof = " . sizeof( $instagramResponse["items"] ) );
		//snc_setLogMessage( "instagramResponse = " . print_r( $instagramResponse, true ) );

		if ( !empty( $instagramResponse ) && isset( $instagramResponse["status"] ) && $instagramResponse["status"] == "ok"  && isset( $instagramResponse["items"] ) ) {
			
			// Trim Posts per Account if greater than limit
			if ( sizeof( $instagramResponse["items"] ) > $count ) {
				$instagramResponse["items"] = array_slice( $instagramResponse["items"], 0, $count );
			}
			
			// Process Posts
			foreach ( $instagramResponse["items"] as $post ) {
				
				snc_setLogMessage( "userId = $userId, code = " . snc_getValueForKey( $post, "code", "" ) );
				
				// Place Posts into an array using their Timestamp so they can be sorted
				$sortTimestamp = snc_getValueForKey( $post, "created_time", "" );
				$posts[$sortTimestamp] = $post;
			}
		}
	}
	
	// Crude limiting of number of Posts which will favor earlier specified accounts/users over the latter
	snc_setLogMessage( "# of posts = " . sizeof( $posts ) . ", maxPosts = " . $maxPosts );
	$posts = array_slice( $posts, 0, $maxPosts );
	snc_setLogMessage( "posts = " . print_r( $posts, true ) );

	if ( sizeof( $posts ) > 0 ) {

		foreach ( $posts as $timestamp => $post ) {
			
			// Extract fields for an individual item
			$itemCode = snc_getValueForKey( $post, "code", "" );
//			$itemLocation = snc_getValueForKey( $post, "location", "" );
			$itemImages = snc_getValueForKey( $post, "images", array() );
			$itemThumbnail = snc_getValueForKey( $itemImages, "thumbnail", array() );
			$itemLowRes = snc_getValueForKey( $itemImages, "low_resolution", array() );
			$itemStdRes = snc_getValueForKey( $itemImages, "standard_resolution", array() );
//			$itemCanViewComments = snc_getValueForKey( $post, "can_view_comments", false );
			$itemComments = snc_getValueForKey( $post, "comments", array() );
			$itemCommentsCount = snc_getValueForKey( $itemComments, "count", 0 );
			$itemCommentsData = snc_getValueForKey( $itemComments, "data", array() );
			$itemCaptionText = snc_getValueForKey( snc_getValueForKey( $post, "caption", array() ), "text", "" );
			$itemLink = snc_getValueForKey( $post, "link", "" );
			$itemLikes = snc_getValueForKey( $post, "likes", array() );
			$itemLikesCount = snc_getValueForKey( $itemLikes, "count", 0 );
			$itemLikesData = snc_getValueForKey( $itemLikes, "data", array() );

			$itemCreatedTime = snc_getValueForKey( $post, "created_time", "" );
			snc_setLogMessage( "itemCreatedTime = " . $itemCreatedTime );
			
			$itemType = snc_getValueForKey( $post, "type", "" );
			$itemId = snc_getValueForKey( $post, "id", 0 );
			$itemUser = snc_getValueForKey( $post, "user", array() );

			// Extract fields for an individual user
			$itemUsername = snc_getValueForKey( $itemUser, "username", "" );
			$itemUserProfilePicture = snc_getValueForKey( $itemUser, "profile_picture", "" );
			$itemUserFullName = snc_getValueForKey( $itemUser, "full_name", "" );
			$itemUserId = snc_getValueForKey( $itemUser, "id", "" );

			// Extract pop-up image URL
			$itemMediaUrl = "";
			$itemStdResUrl = snc_getValueForKey( $itemStdRes, "url", "" );
			if ( $itemStdResUrl != "" ) {
				$itemMediaUrl = $itemStdResUrl;
			} else {
				$itemMediaUrl = snc_getValueForKey( $itemLowRes, "url", "" );
			}

			// Modify Caption text for item
			$itemCaptionTextOrig = $itemCaptionText;
			$itemCaptionText = snc_getCleanEllipsis( $itemCaptionText );
			$itemCaptionText = snc_getHtmlWrap( $itemCaptionText, 35, "-\n" );

			// Calculate how long ago the post was created
			$itemDate = new DateTime();
			$itemDate->setTimestamp( $itemCreatedTime );
			$timeDiff = snc_getHowLongAgo( $itemDate );
			$timestamp = $itemCreatedTime;

			// Format User Profile image for Header
			$profileImage = "";
			if ( $itemUserProfilePicture != "" ) {
				$profileImage .= <<<EOF
<img class="sncBodyImage" src="$itemUserProfilePicture" width="46" height="46" alt="$itemUserFullName" title="$itemUserFullName" />
EOF;
			}

			// Format Item image
			$itemThumbnailUrl = "";
			$itemThumbnailWidth = "";
			$itemThumbnailHeight = "";

			if ( !empty( $itemThumbnail ) ) {
				$itemThumbnailUrl = $itemThumbnail['url'];
				$itemThumbnailWidth = $itemThumbnail['width'];
				$itemThumbnailHeight = $itemThumbnail['height'];
			}

			// Determine what type of Likes & Comments icons should be displayed
			$commentsId = "sncComments";
			$commentsTxt = "comments";
			snc_setLogMessage( "itemCommentsData =" . print_r( $itemCommentsData, true ) );
			foreach ( $itemCommentsData as $comment ) {
				if ( isset( $comment["from"] ) && isset( $comment["from"]["username"] ) ) { 
					if ( $comment["from"]["username"] == $itemUsername ) {
						$commentsId = "sncCommented";
						$commentsTxt = "commented";
					}
				}
			}

			$likesId = "sncLikes";
			$likesTxt = "likes";
			snc_setLogMessage( "itemLikesData =" . print_r( $itemLikesData, true ) );
			foreach ( $itemLikesData as $user ) {
				if ( $user["username"] == $itemUsername ) {
					$likesId = "sncLiked";
					$likesTxt = "liked";
				}
			}
			
			// Format header title
			//$headerTitle = snc_getBulkWordwrap( $userName );
			$headerTitle = snc_getHtmlWrap( $itemUserFullName, 15, "-\n" );

			// Prepare HTML links for images and within plugin
			$instagramLogoLink = $sncBaseUrl . "images/instagram/instagram-logo-color-32.png";

			// Output Post
			$content .= <<<EOF
	<div class="sncItem">
		<table width="100%" cellspacing="0" cellpadding="1">
			<tr class="sncHeader">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="48">$profileImage</td>
							<td align="center">
								<strong><a class="sncHeaderTitle" href="#magnific-inline-popup" onclick="snc_getLoadSocialPopup( '$sncBaseUrl', 'instagram', '@$itemUsername' );return false;">$headerTitle</a></strong><br />
								<a class="sncSubHeader" href="#magnific-inline-popup" onclick="snc_getLoadSocialPopup( '$sncBaseUrl', 'instagram', '@$itemUsername' );return false;">@$itemUsername</a>
							</td>
							<td width="48"><a href="$itemLink" rel="nofollow" target="_blank"><img class="sncBodyImage" src="$instagramLogoLink" alt="original Post" title="original Post" /></a></td>
						</tr>
					</table>
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncBody">
				<td align="center">
EOF;

			$itemThumbnailWidth = "";
			$itemThumbnailHeight = "";

			if ( $itemMediaUrl != "" && $itemThumbnailUrl != "" ) {
				$content .= <<<EOF
					<a href="$itemMediaUrl" rel="nofollow" class="magnific-image-link"><img class="sncBodyImage" src="$itemThumbnailUrl" width="$itemThumbnailWidth" height="$itemThumbnailHeight" alt="view larger picture" title="view larger picture" /></a><br />\n
EOF;
			} else if ( $itemThumbnailUrl != "" ) {
				$content .= <<<EOF
					<img class="sncBodyImage" src="$itemThumbnailUrl" width="$itemThumbnailWidth" height="$itemThumbnailHeight" alt="view larger picture" title="view larger picture" /><br />\n
EOF;
			}

			//$content .= "<pre>" . print_r( $item, true ) . "</pre>";
			$content .= <<<EOF
					$itemCaptionText
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncFooter">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="33%"><div id="$likesId" title="$likesTxt"></div></td>
							<td width="33%" class="sncViewComments"><a class="sncInlineLink" href="#magnific-inline-popup" onclick="snc_getLoadSocialPopup( '$sncBaseUrl', 'instagram', '$itemId - $itemUsername' );return false;">view post</a></td>
							<td width="33%"><div id="$commentsId" title="$commentsTxt"></td>
						</tr>
						<tr>
							<td width="33%" class="sncFooterLeft">$itemLikesCount</td>
							<td width="33%" class="sncFooterCenter">$timeDiff</td>
							<td width="33%" class="sncFooterRight">$itemCommentsCount</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<div id="sncTimestamp" class="sncTimestamp">$timestamp</div>
	</div>
EOF;
		}
	}

	return $content;
}

/*
 * Function to get an Instagram User Profile.
 * 
 * Parameters:
 * - instagramSettings:	Instagram API access settings.
 * - userIds:			List of User Accounts on Instagram, only the first will be retrieved.
 * 
 * Return:
 * - HTML containing retrieved User Profile.
 */
function snc_getInstagramUserProfile ( $instagramSettings, $userIds ) {

	snc_setLogMessage( "snc_getInstagramUserProfile():" );

	$content = "";
	
	$sncBaseUrl = SNC_BASE_URL;

	foreach ( $userIds as $userId ) {
	
		$url = SNC_INSTAGRAM_URL . Str_replace( "@", "", $userId ) . "/?__a=1";
		
		// Check if cached version of Instagram request available otherwise make fresh call to Instagram
		$instagramResponse = snc_doCheckInstagramCache( $instagramSettings, $url, "User Profile", 120, 1440 ); // override cache so Profile retrieved every 2 hours & refreshed every day
		
		// Log usage stats - TODO
		//snc_setLogApiUsageStats ( $site, $functionCall, $resource, $rateLimit, $remaining, $limitReset );
		
		// Write to log file
		snc_setLogMessage( "instagramResponse:\n" . print_r( $instagramResponse, true ) );

		if ( isset( $instagramResponse["error"] ) ) {
			$errorMsg = "'" . str_replace( ".", "", $instagramResponse["error"] ) . "'.";
			if ( $instagramResponse["error"] == "Not authorized." ) {
				$errorMsg .= " An account may be protected.";
			} else if ( $instagramResponse["error"] == "Invalid or expired token." ) {
				$errorMsg .= " Please contact support.";
			}
			$_SESSION["warning"] = "Instagram problem encountered - " . $errorMsg;
			snc_setLogMessage( "instagram.php - snc_getTwitterUserProfile() warning = " . $_SESSION["warning"] );
		} else if ( isset( $instagramResponse["errors"] ) ) {
			$errorMsg = "'" . str_replace( ".", "", $twitterResponse["errors"][0]["message"] ) . "'.";
			$_SESSION["warning"] = "Instagram problem encountered - " . $errorMsg;
			snc_setLogMessage( "instagram.php - snc_getInstagramUserProfile() warning = " . $_SESSION["warning"] );
		} else if ( !empty( $instagramResponse ) ) {

			// Extract fields for an individual user
			$user = snc_getValueForKey( $instagramResponse, "user", array() );
			$userName = snc_getValueForKey( $user, "username", "" );
			$userFollows = snc_getValueForKey( $user, "follows", array() );
			$userFollowsCount = snc_getValueForKey( $userFollows, "count", 0 );
			$userFollowedBy = snc_getValueForKey( $user, "followed_by", array() );
			$userFollowedByCount = snc_getValueForKey( $userFollowedBy, "count", 0 );
			$userProfilePicUrl = snc_getValueForKey( $user, "profile_pic_url", "" );
			$userFullName = snc_getValueForKey( $user, "full_name", "" );
			$userId = snc_getValueForKey( $user, "id", "" );
			$userBiography = snc_getValueForKey( $user, "biography", "" );
			$userExternalUrl = snc_getValueForKey( $user, "external_url", "" );

			// Create user profile
			$profileTxt = "";
			if ( $userExternalUrl != "" ) {
				$wwwIconLink = $sncBaseUrl . "images/icon_link.png";
				$profileTxt =<<<EOF
<a href="$userExternalUrl" rel="nofollow" target="_blank"><img src="$wwwIconLink" alt="view website" title="view website" /></a>
EOF;
			}
			
			// Format User Profile image
			$profileImage = "";
			if ( $userProfilePicUrl != "" ) {
				$profileImage .= <<<EOF
<img src="$userProfilePicUrl" alt="$userFullName" title="$userFullName" />
EOF;
			}
//			<tr class="sncHeader"><td colspan="3" align="center"><strong>$userName</strong><button title="Close (Esc)" type="button" class="mfp-close mfp-close-btn-in">x</button></td></tr>

			// Format header title
			//$headerTitle = snc_getBulkWordwrap( $userFullName, 20 );
			$headerTitle = snc_getHtmlWrap( $userFullName, 20, "-\n" );

			// Output Profile
			$content .= <<<EOF
	<div class="sncPopUpProfileItem">
		<table width="100%" cellspacing="0" cellpadding="1">
EOF;

			// Prepare HTML links for images and within plugin
			$instagramLogoLink = $sncBaseUrl . "images/instagram/instagram-logo-color-32.png";
			$instagramIconLink = $sncBaseUrl . "images/icon_instagram.png";
			
			$instagramUserUrl = SNC_INSTAGRAM_URL . $userName;

			$content .= <<<EOF
			<tr class="sncHeader">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="48">$profileImage</td>
							<td align="center">
								<strong>$headerTitle</strong><br />
								<span class="sncSubHeader">@<a href="$instagramUserUrl">$userName</a></span>
							</td>
							<td width="48"><a href="$instagramUserUrl" rel="nofollow" target="_blank"><img src="$instagramLogoLink" alt="view Instagram profile" title="view Instagram profile" /></a></td>
						</tr>
					</table>
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncBody">
				<td>
					<table width="100%" cellpadding="0" cellspacing="0">
						<tr class="sncBodyLeft">
							<td width="30%">Profile:</td>
							<td width="70%"><a href="$instagramUserUrl" rel="nofollow" target="_blank"><img src="$instagramIconLink" alt="view Instagram profile" title="view Instagram profile" /></a></td>
						</tr>
						<tr class="sncBodyLeft">
							<td>Following:</td>
							<td>$userFollowsCount</td>
						</tr>
						<tr class="sncBodyLeft">
							<td>Followers:</td>
							<td>$userFollowedByCount</td>
						</tr>
EOF;
			if ( $profileTxt != "" ) {
				$content .= <<<EOF
						<tr class="sncBodyLeft">
							<td>Web Site:</td>
							<td>$profileTxt</td>
						</tr>
EOF;
			}
			if ( $userBiography != "" ) {
				$userBiograhy = snc_getCleanEllipsis( $userBiography );
				$content .= <<<EOF
						<tr class="sncBody">
							<td colspan="2" align="center"><em>$userBiography</em></td>
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
	}
	
	return $content;
}
