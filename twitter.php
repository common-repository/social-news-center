<?php

// Include Twitter library
if ( !class_exists( "TwitterAPIExchange" ) ):
	require_once( __DIR__ . '/twitter/TwitterAPIExchange.php' );
endif;

// Needed to pass session data between pages
if( !isset( $_SESSION ) ) {
	session_start();
}

/*
 * Function to check for a cached version of this request, if not available submit request to Twitter and cache the results.
 * 
 * Parameters:
 * - twitterSettings:	Twitter API access settings.
 * - apiURL:			Twitter request.
 * - getfield:			Parameter string for Twitter request.
 * - cacheLimit:		Expiry of individual cached item (in minutes) before being re-read from Twitter.
 * - cacheReset:		Expiry of cached items (in minutes) before being completely reset.
 * 
 * Return:
 * - JSON data containing Tweet(s) or User Profile if successfully retrieved from the cache or Twitter itself otherwise NULL or False.
 */
function snc_doCheckTwitterCache ( $twitterSettings, $apiURL, $getfield, $cacheLimit = SNC_TWITTER_DEF_CACHE_LIMIT, $cacheReset = SNC_TWITTER_DEF_CACHE_RESET ) {
	
	// based on http://kenmorico.com/php-twitter-caching-like-a-champ

	// Initialize parameters
	$requestMethod = "GET";
	$twitterResponse = null;
	$timestampKey = $apiURL . "_timestamp";

	//snc_setLogMessage( "snc_doCheckTwitterCache():\n=============" );
	//snc_setLogMessage( date("Y-m-d H:i:s") );
	//snc_setLogMessage( "apiURL = " . $apiURL );
	
	// Extract Type of Request to assist with naming of cache file
	$requestType =
		str_replace( "/", "-", 
			str_replace( "_", "-", 
				str_replace( ".json", "", 
					str_replace( SNC_TWITTER_API_URL, "", $apiURL )
				)
			)
		);
	
	// Generate caching file name
	$queryString = "";
	$queryParams = explode( "&", $getfield );
	if ( sizeof( $queryParams ) > 0 ) {
		$keyCriteria = explode( "=", $queryParams[sizeof( $queryParams ) -1] );
		if ( sizeof( $keyCriteria ) > 0 ) {
			$queryString = "-" . 
				str_replace( "@", "", 
					str_replace( "%23", "hashtag-", 
						str_replace( "%24", "symbol-", $keyCriteria[1] )
					)
				);
		}
	}
	
	$cacheFile = SNC_BASE_PATH . "cache/twitter/" . $requestType . "-" . md5( snc_getSanitizeFileName ( $queryString . "-" . $twitterSettings["oauth_access_token"] ) ) . ".data";
	snc_setLogMessage( "cacheFile after = " . $cacheFile );
	
	// Check for existence of cached result within caching period (limit)
	if ( file_exists( $cacheFile ) ) {
		snc_setLogMessage( "File '$cacheFile' exists" );
		$data = unserialize( file_get_contents( $cacheFile ) );
		if ( $data[$timestampKey] > time() - $cacheLimit * 60 ) {
			snc_setLogMessage( "Cached item still valid" );
			$twitterResponse = $data[$apiURL];
		}
	}

	// If cache doesn't exist or is older than caching period (limit) then fetch data from Twitter
	if ( !$twitterResponse ) { 

		// Check if cache needs resetting i.e. deleting
		if ( file_exists( $cacheFile ) ) {
			snc_setLogMessage( "Retrieve remainder of cache" );
			$otherData = unserialize( file_get_contents( $cacheFile ) );
			if ( $otherData['cache_updated'] < time() - $cacheReset * 60 ) {
				snc_setLogMessage( "Clear cache as expired" );
				unset( $otherData ); // clear cached file
			}
		} 	
		
		snc_setLogMessage( "Need to query Twitter" );
		$resourceName = str_replace( ".json", "", 
							str_replace( SNC_TWITTER_API_URL, "", "/" . $apiURL ) 
						);
		
		// Get latest API usage details for current Access Token
		$usageLeft = snc_getApiUsageLeft( "twitter", $resourceName, $twitterSettings["oauth_access_token"] );
		//snc_setLogMessage( "usageLeft = " . print_r( $usageLeft, true ) );

		// Retrieve data from Twitter providing API usage limit not exceeded
		if ( empty( $usageLeft ) || $usageLeft["amountLeft"] > 0 ) {
			
			$twitter = new TwitterAPIExchange( $twitterSettings );
			$response = $twitter
							->setGetfield( $getfield )
							->buildOauth( $apiURL, $requestMethod )
							->performRequest();
			//snc_setLogMessage( "response:\n" . print_r( $response, true ) );
			list( $header, $body ) = explode( "\r\n\r\n", $response, 2 );
			$twitterResponse = json_decode( $body, $assoc = TRUE );

			if ( $header != "" ) {

				// Parse HTTP Header then log API usage statistics
				$httpHeaders = http_parse_headers( $header );
				//snc_setLogMessage( "httpHeader:\n\n" . print_r( $httpHeaders, true ) );
				$status = ( isset( $httpHeaders["status"] ) ? $httpHeaders["status"] : "" );
				$rateLimit = ( isset( $httpHeaders["x-rate-limit-limit"] ) ? $httpHeaders["x-rate-limit-limit"] : -1 );
				$remaining = ( isset( $httpHeaders["x-rate-limit-remaining"] ) ? $httpHeaders["x-rate-limit-remaining"] : -1 );
				$rateReset = ( isset( $httpHeaders["x-rate-limit-reset"] ) ? $httpHeaders["x-rate-limit-reset"] : -1 );
				snc_setLogApiUsageStats( 
					"twitter", 
					"twitter.php - snc_doCheckTwitterCache()", 
					$resourceName,
					$getfield, 
					$twitterSettings["oauth_access_token"], 
					$status, 
					$rateLimit, 
					$remaining, 
					$rateReset 
				);
			
				// Check API rate limits and whether they have been, or are going to be, reached
				$rateResetDate = date_create( date( DATE_ATOM, $rateReset ) );
				//snc_setLogMessage( "timestamps (now & reset) = " . time() . " & " . $rateReset );
				//snc_setLogMessage( "headerDate (now & reset) = " . date( DATE_ATOM ) . " & " . date( DATE_ATOM, $rateReset ) );
				$rateUsageMsg = snc_doCheckRateUsage( $status, $remaining, $rateResetDate );
				if ( $rateUsageMsg != "" ) {
					$_SESSION["warning"] = "Twitter load is currently high, please try again in " . $rateUsageMsg . ".";
					snc_setLogMessage( "twitter.php - snc_doCheckTwitterCache() warning #1 = " . $_SESSION["warning"] );
				}
			}
			
			// Each page gets its own key / value
			$data = array(
				$apiURL => $twitterResponse, 
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
					$_SESSION["warning"] = "Unable to cache Twitter information.";
					snc_setLogMessage( "twitter.php - snc_doCheckTwitterCache() warning #2 = " . $_SESSION["warning"] );
				}
			} catch ( Exception $e ) {
				$_SESSION["error"] = "Twitter caching problem occurred. Please contact support.";
				snc_setLogMessage( "twitter.php - snc_doCheckTwitterCache() exception = " . print_r( $ex, true ) );
			}
		} else if ( !empty( $usageLeft ) && $usageLeft["amountLeft"] == 0 ) {

			// Usage Limit exceeded so inform User
			$rateResetDate = date_create( date( DATE_ATOM, $usageLeft["resetTimestamp"] ) );
			$rateUsageMsg = snc_getHowLongAgo( $rateResetDate, $suffix = "" );
			$_SESSION["warning"] = "Twitter load is currently high, please try again in " . $rateUsageMsg . ".";
			snc_setLogMessage( "twitter.php - snc_doCheckTwitterCache() error = " . $_SESSION["warning"] );
		}
	}

	return $twitterResponse;
}

/*
 * Function to check limit of REST API usage.
 * 
 * Parameters:
 * - status				: HTTP response header status code.
 * - remaining			: Amount remaining within Rate Limit.
 * - rateResetDate		: Date when Rate Limit is reset.
 * 
 * Return:
 * - blank if nothing to report otherwise an error message for the User.
 */
function snc_doCheckRateUsage ( $status, $remaining, $rateResetDate ) {
	
	$timeMsg = "";
	
	// Check if Rate Limit is exhausted
	if ( $remaining == 0 ) {
		$timeMsg = snc_getHowLongAgo( $rateResetDate, $suffix = "" );
	}

	return $timeMsg;
}

/*
 * Function to check Twitter User access settings.
 * 
 * Parameters:
 * - twitterSettings:	Twitter API access settings.
 * 
 * Return:
 * - TRUE if verified otherwise FALSE.
 */
function snc_doCheckTwitterSettings ( $twitterSettings ) {

	$apiURL = "https://api.twitter.com/1.1/account/verify_credentials.json";
	$requestMethod = "GET";
	$getfield = "?include_entities=false&skip_status=true";
	$twitter = new TwitterAPIExchange( $twitterSettings );
	$response = $twitter
					->setGetfield( $getfield )
					->buildOauth( $apiURL, $requestMethod )
					->performRequest();
	list( $header, $body ) = explode( "\r\n\r\n", $response, 2 );
	$twitterResponse = json_decode( $body, $assoc = TRUE );
	
/*	
	$twitterResponse = json_decode( 
							$twitter
								->setGetfield( $getfield )
								->buildOauth( $url, $requestMethod )
								->performRequest(), 
							$assoc = TRUE
						);
*/
	// Write to log file
	snc_setLogMessage( "snc_doCheckTwitterSettings():\n\n" . print_r( $twitterResponse, true ) );

	if ( isset( $twitterResponse["error"] ) || isset( $twitterResponse["errors"] ) ) {
		return false;
	} else {

		// Ensure Twitter ID saved as Session variable
		if ( isset( $twitterResponse["screen_name"] ) ) {
			$_SESSION["twitter_id"] = $twitterResponse["screen_name"];
		}

		return true;
	}
}

/*
 * Replace Twitter Tags (Hashtags, Symbols, User Mentions & URLs) in Twitter textwith HTML links.
 *
 * Parameters:
 * - tweetText	: Original text to be processed.
 * 
 * Return:
 * - Original text with Tags converted to HTML links.
 */ 
function snc_getConvertTwitterTagsToLinks ( $tweetText ) {
	
	// Patterns for replacing parts of Tweet text
//	$hashTagLinkPattern = '#<a href="https://twitter.com/hashtag/%s?src=hash" rel="nofollow" target="_blank">%s</a>';
	$hashTagLinkPattern = '#<a href="" onclick="snc_getLoadSocialPopup( \'' . SNC_BASE_URL . '\', \'twitter\', \'#%s\' );return false;">%s</a>';
//	$symbolLinkPattern = '$<a href="https://twitter.com/search?q=%%24%s&src=ctag" rel="nofollow" target="_blank">%s</a>';
	$symbolLinkPattern = '$<a href="" onclick="snc_getLoadSocialPopup( \'' . SNC_BASE_URL . '\', \'twitter\', \'$%s\' );return false;">%s</a>';
//	$userMentionLinkPattern = '@<a href="https://twitter.com/%s" rel="nofollow" target="_blank">%s</a>';
	$userMentionLinkPattern = '@<a href="" onclick="snc_getLoadSocialPopup( \'' . SNC_BASE_URL . '\', \'twitter\', \'@%s\' );return false;">%s</a>';
	//$urlLinkPattern = '<a href="%s" rel="nofollow" target="_blank"%s>%s</a>';
	//$mediaLinkPattern = '<a href="%s" rel="nofollow" target="_blank">%s</a>';
	
	$entityList = array(); // Array to hold substitutions that will be sorted in reverse order then applied

	// Need to convert URLs first before handling other symbol types otherwise this links will also get re-converted
	$tweetText = snc_getConvertLinksToHtml( $tweetText );


	// Retrieve all Hash Tags within text
	preg_match_all( "/(#\w+)/", $tweetText, $matches );

	if ( sizeof( $matches ) > 0 ) {
		foreach ( $matches[0] as $match ) {
			$entity = new stdclass();
			$entity->start = strpos( $tweetText, $match );
			$entity->length = strlen( $match );
			$hashTagTxt = substr( $match, 1, $entity->length -1 );
			$entity->replace = sprintf( $hashTagLinkPattern, $hashTagTxt, $hashTagTxt );
			$entityList[$entity->start] = $entity;
		}
	}

	// Retrieve all Symbol Links within text
	preg_match_all( '/(\$\w+)/', $tweetText, $matches );

	// Write to log file
	//snc_setLogMessage( print_r( $matches, true ) );
	
	if ( sizeof( $matches ) > 0 ) {
		foreach ( $matches[0] as $match ) {
			$entity = new stdclass();
			$entity->start = strpos( $tweetText, $match );
			$entity->length = strlen( $match );
			$hashTagTxt = substr( $match, 1, $entity->length -1 );
			$entity->replace = sprintf( $symbolLinkPattern, $hashTagTxt, $hashTagTxt );
			$entityList[$entity->start] = $entity;
		}
	}

	// Retrieve all User Mention Links within text
//	preg_match_all( "/(@\w+)/", $tweetText, $matches );
	preg_match_all( "/\B\@([\w\-]+)/", $tweetText, $matches );

	if ( sizeof( $matches ) > 0 ) {
		foreach ( $matches[0] as $match ) {
			$entity = new stdclass();
			$entity->start = strpos( $tweetText, $match );
			$entity->length = strlen( $match );
			$hashTagTxt = substr( $match, 1, $entity->length -1 );
			$entity->replace = sprintf( $userMentionLinkPattern, $hashTagTxt, $hashTagTxt );
			$entityList[$entity->start] = $entity;
		}
	}

	//$entity->replace = sprintf( $urlLinkPattern, $url["url"], $url["expanded_url"], $url["display_url"] );
	//$entity->replace = sprintf( $mediaLinkPattern, $media["url"], $media["expanded_url"], $media["display_url"] );
	
	krsort( $entityList );

	foreach ( $entityList as $entity ) {
		//$indicesTxt .= "<br />'" . mb_substr( $tweetText, $entity->start, $entity->length ) . "' (" . $entity->start . "," . $entity->length . ") -> '" . $entity->replace . "'<br />";
		$tweetText = mb_substr_replace( $tweetText, $entity->replace, $entity->start, $entity->length );
	}
	
	return $tweetText;
}

/*
 * Function to get the latest Twitter posts by a particular HashTag.
 * 
 * Parameters:
 * - twitterSettings:	Twitter API access settings.
 * - searchCriterias:	List of search criteria e.g. words, Hash Tags, Symbols.
 * - count:				Number of Tweets to retrieve and display for HashTag.
 * 
 * Return:
 * - HTML containing retrieved Posts.
 */
function snc_getTweetsBySearch ( $twitterSettings, $searchCriterias, $count = SNC_POPUP_NUM_POSTS ) {
	
	$content = "";

	$sncBaseUrl = SNC_BASE_URL;

	$tweets = array();									// Vehicle for storing Tweets for each User as a call will be made to Twitter per Account
	$tweetIds = "";										// Vehicle for storing Tweet IDs as an extra call will be made to determine Favorited & Retweeted status
	$tweetIdList = array();								// Vehicle for storing Favorited & Retweeted statuses
	
	foreach ( $searchCriterias as $searchCriteria ) {

		// Prepare request parameters
		$encodedChar = "";
		$searchStr = $searchCriteria;
		if ( substr( $searchCriteria, 0, 1 ) == "#" ) {
			$encodedChar = "%23";
			$searchStr = substr( $searchCriteria, 1 );
		} else if ( substr( $searchCriteria, 0, 1 ) == "$" ) {
			$encodedChar = "%24";
			$searchStr = substr( $searchCriteria, 1 );
		}

		// Construct Twitter API call
		$url = SNC_TWITTER_API_URL . "search/tweets.json";
//		$getfield = "?q=" . $encodedChar . $searchStr . "&count=" . $count . "&result_type=recent";
		$getfield = "?count=" . $count . "&q=" . $encodedChar . $searchStr;
		
		// Check if cached version of Twitter request available otherwise make fresh call to Twitter
		$twitterResponse = snc_doCheckTwitterCache( $twitterSettings, $url, $getfield );

		// Write to log file
		//snc_setLogMessage( print_r( $twitterResponse, true ) );

		if ( isset( $twitterResponse["error"] ) ) {
			$errorMsg = "'" . str_replace( ".", "", $twitterResponse["error"] ) . "'.";
			if ( $twitterResponse["error"] == "Not authorized." ) {
				$errorMsg .= " An account may be protected.";
			}
			$_SESSION["warning"] = "Twitter problem encountered - " . $errorMsg;
			snc_setLogMessage( "twitter.php - snc_getTweetsBySearch() warning = " . $_SESSION["warning"] );
		} else if ( isset( $twitterResponse["errors"] ) ) {
			$errorMsg = "'" . str_replace( ".", "", $twitterResponse["errors"][0]["message"] ) . "'.";
			if ( $twitterResponse["errors"][0]["message"] == "Invalid or expired token." ) {
				$errorMsg .= " Please contact support.";
			}
			$_SESSION["warning"] = "Twitter problem encountered - " . $errorMsg;
			snc_setLogMessage( "twitter.php - snc_getTweetsBySearch() warning = " . $_SESSION["warning"] );
		} else if ( !empty( $twitterResponse ) ) {
			$rawTweets = $twitterResponse["statuses"];
		
			// Write to log file
			//snc_setLogMessage( "rawTweets: " . print_r( $rawTweets, true ) );
			
			foreach ( $rawTweets as $tweet ) {
				
				// Place Tweets into an array using their Timestamp so they can be sorted
				$sortTimestamp = date_create( snc_getValueForKey( $tweet, "created_at", "" ) )->getTimestamp();
				$tweets[$sortTimestamp] = $tweet;
				
				// Save Tweet IDs as a comma-delimited string for use later
				if ( $tweetIds != "" ) {
					$tweetIds .= ",";
				}
				$tweetIds .= snc_getValueForKey( $tweet, "id", 0 );
			}
		}
	}
	
	// Retrieve Favorited & Retweeted values per tweet as the values within the search results are not populated correctly
	if ( $tweetIds != "" ) {

		// Construct Twitter API call
		$url = SNC_TWITTER_API_URL . "statuses/lookup.json";
		$getfield = "?id=" . $tweetIds;
		
		// Check if cached version of Twitter request available otherwise make fresh call to Twitter
		$twitterResponse = snc_doCheckTwitterCache( $twitterSettings, $url, $getfield );

		// Write to log file
		//snc_setLogMessage( "statuses/lookup.json:" . print_r( $twitterResponse, true ) );

		if ( isset( $twitterResponse["error"] ) ) {
			$errorMsg = "'" . str_replace( ".", "", $twitterResponse["error"] ) . "'.";
			if ( $twitterResponse["error"] == "Not authorized." ) {
				$errorMsg .= " An account may be protected.";
			}
			$_SESSION["warning"] = "Twitter problem encountered - " . $errorMsg;
			snc_setLogMessage( "twitter.php - snc_getTweetsBySearch() warning = " . $_SESSION["warning"] );
		} else if ( isset( $twitterResponse["errors"] ) ) {
			$errorMsg = "'" . str_replace( ".", "", $twitterResponse["errors"][0]["message"] ) . "'.";
			if ( $twitterResponse["errors"][0]["message"] == "Invalid or expired token." ) {
				$errorMsg .= " Please contact support.";
			}
			$_SESSION["warning"] = "Twitter problem encountered - " . $errorMsg;
			snc_setLogMessage( "twitter.php - snc_getTweetsBySearch() warning = " . $_SESSION["warning"] );
		} else if ( !empty( $twitterResponse ) ) {
			
			// Process results and use Tweet Status for data unless Retweeted Status exists
			foreach ( $twitterResponse as $tweet ) {
				$tweetRetweetStatus = snc_getValueForKey( $tweet, "retweeted_status", array() );
				if ( sizeof( $tweetRetweetStatus ) > 0 ) {
					$tweet = $tweetRetweetStatus;
				}
				$tweetIdList[snc_getValueForKey( $tweet, "id", 0 )] = 
					array( 
						"favorited" => snc_getValueForKey( $tweet, "favorited", 0 ),
						"retweeted" => snc_getValueForKey( $tweet, "retweeted", 0 ),
					);
			}
		}
		//snc_setLogMessage( "tweetIdList:" . print_r( $tweetIdList, true ) );
	}
	
	// Crude limiting of number of Tweets which will cater for a drop in the setting only when cached results are used
	$tweets = array_slice( $tweets, 0, $count );

	if ( sizeof( $tweets ) > 0 ) {
		foreach ( $tweets as $timestamp => $tweet ) {
		
			// Extract fields for an individual item
			$tweetCreatedAt = snc_getValueForKey( $tweet, "created_at", "" );
			$tweetId = snc_getValueForKey( $tweet, "id", 0 );
			$tweetText = snc_getValueForKey( $tweet, "text", "" );
			$tweetRetweetCount = snc_getValueForKey( $tweet, "retweet_count", 0 );
			$tweetFavoriteCount = snc_getValueForKey( $tweet, "favorite_count", 0 );
			$tweetRetweetStatus = snc_getValueForKey( $tweet, "retweeted_status", array() );

			// For Retweets certain fields need to be set to the originals
			if ( sizeof( $tweetRetweetStatus ) > 0 ) {
				$tweetId = snc_getValueForKey( $tweetRetweetStatus, "id", 0 );
				$tweetCreatedAt = snc_getValueForKey( $tweetRetweetStatus, "created_at", "" );
				$tweetRetweetCount = snc_getValueForKey( $tweetRetweetStatus, "retweet_count", 0 );
				$tweetFavoriteCount = snc_getValueForKey( $tweetRetweetStatus, "favorite_count", 0 );
			}
			
			$tweetFavorited = $tweetIdList[$tweetId]["favorited"];
			$tweetRetweeted = $tweetIdList[$tweetId]["retweeted"];

			// Extract fields for an individual user
			$user = $tweet["user"]; // extract User sub-array
			$userName = snc_getValueForKey( $user, "name", "" );
			$userScreenName = snc_getValueForKey( $user, "screen_name", "" );
			$userProfileImageUrlHttps = snc_getValueForKey( $user, "profile_image_url_https", "" );
			
			// Modify Tweet text for entities
			$origTweetText = $tweetText;
			//$entitiesText = print_r( $tweet["entities"], true );
			$tweetText = snc_getCleanEllipsis( snc_getTwitterEntitiesText( $tweet, $tweetText, true ) );

			// Calculate how long ago tweet was posted or retweeted
			$postDate = date_create( $tweetCreatedAt );
			$createdAt = date_format( date_create( $tweetCreatedAt ), "Y/m/d H:i:s" );
			$usedDate = date_format( $postDate, "Y/m/d H:i:s" );
			$timeDiff = snc_getHowLongAgo( $postDate );
			$timestamp = $postDate->getTimestamp();

			// Process extracted fields ready for display markup
			$favoritedUrl = $sncBaseUrl . "images/icon_favorite.png";
			if ( $tweetFavoriteCount > 0 ) {
				$favoritedUrl = $sncBaseUrl . "images/icon_favorited.png";
			}
			
			// Format User Profile image for Header
			$profileImage = "";
			if ( $userProfileImageUrlHttps != "" ) {
				$profileImage .= <<<EOF
<img class="sncBodyImage" src="$userProfileImageUrlHttps" alt="$userName" title="$userName" />
EOF;
			}

			// Format Tweet image
			$mediaUrl = "";
			$mediaWidth = "";
			$mediaHeight = "";

			if ( isset( $tweet['entities']['media'] ) && $tweet['entities']['media'][0]['type'] == 'photo' ) {
				$mediaItem = $tweet['entities']['media'][0];
				$mediaUrl = $mediaItem['media_url_https'];
				$mediaWidth = $mediaItem['sizes']['small']['w'];
				$mediaHeight = $mediaItem['sizes']['small']['h'];
				
				// Adjust dimensions if Width too large
				if ( $mediaWidth > SNC_IMAGE_MAX_WIDTH ) {
					$mediaHeight = ( int )( $mediaHeight * ( SNC_IMAGE_MAX_WIDTH / $mediaWidth ) );
					$mediaWidth = SNC_IMAGE_MAX_WIDTH;
				}

				// Adjust dimensions if Height too large
				if ( $mediaHeight > SNC_IMAGE_MAX_HEIGHT ) {
					$mediaWidth = ( int )( $mediaWidth * ( SNC_IMAGE_MAX_HEIGHT / $mediaHeight ) );
					$mediaHeight = SNC_IMAGE_MAX_HEIGHT;
				}
			}
			
			// Determine what type of Retweet & Favorite icons should be displayed
			$retweetId = "sncRetweet";
			if ( $tweetRetweeted > 0 ) {
				$retweetId = "sncRetweeted";
			}
			$favoriteId = "sncFavorite";
			if ( $tweetFavorited > 0 ) {
				$favoriteId = "sncFavorited";
			}

			// Format header title
			//$headerTitle = snc_getBulkWordwrap( $userName );
			$headerTitle = snc_getHtmlWrap( $userName, 15, "-\n" );
			
			// Prepare HTML links for images and within plugin
			$twitterLogoLink = $sncBaseUrl . "images/twitter/Twitter_logo_blue_32.png";

			// Output Tweet
			$content .= <<<EOF
	<div class="sncPopUpPostItem">
		<table width="100%" cellspacing="0" cellpadding="1">
			<tr class="sncHeader">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="48">$profileImage</td>
							<td align="center">
								<strong>$headerTitle</strong><br />
								<a class="sncSubHeader" href="#magnific-inline-popup" onclick="snc_getLoadSocialPopup( '$sncBaseUrl', 'twitter', '@$userScreenName' );return false;">@$userScreenName</a>
							</td>
							<td width="48"><a href="https://twitter.com/$userScreenName/status/$tweetId" rel="nofollow" target="_blank"><img class="sncBodyImage" src="$twitterLogoLink" alt="original Tweet" title="original Tweet" /></a></td>
						</tr>
					</table>
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncBody">
				<td align="center">
EOF;
			if ( $mediaUrl != "" ) {
				$content .= <<<EOF
					<a href="" onClick="snc_doOpenPopupLink( '$mediaUrl', 'image' );return false;" rel="nofollow"><img class="sncBodyImage" src="$mediaUrl" width="$mediaWidth" height="$mediaHeight" alt="view larger picture" title="view larger picture" /></a><br />
EOF;
			}
			//$content .= "<pre>" . print_r( $item, true ) . "</pre>";
			$content .= <<<EOF
					$tweetText
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncFooter">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="33%"><a href="https://twitter.com/intent/favorite?tweet_id=$tweetId"><div id="$favoriteId" title="favorite"></div></a></td>
							<td width="33%"><a href="https://twitter.com/intent/tweet?in_reply_to=$tweetId"><div id="sncReply" title="reply"></div></a></td>
							<td width="33%"><a href="https://twitter.com/intent/retweet?tweet_id=$tweetId"><div id="$retweetId" title="retweet"></div></a></td>
						</tr>
						<tr>
							<td width="33%" class="sncFooterLeft">$tweetFavoriteCount</td>
							<td width="33%" class="sncFooterCenter">$timeDiff</td>
							<td width="33%" class="sncFooterRight">$tweetRetweetCount</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<div id="sncTimestamp" class="sncTimestamp">$timestamp</div>
	</div>
EOF;
//			$content .= "<pre>" . print_r( $tweet, true ) . "</pre>";
		}
	}
	
	return $content;
}

/*
 * Function to get the latest Twitter posts by a particular User.
 * 
 * Parameters:
 * - twitterSettings:	Twitter API access settings.
 * - screenNames:		List of User Accounts on Twitter.
 * - count:				number of Tweets to retrieve and display per Account.
 * - maxPosts:			number of Tweets to retrieve for all Accounts in total.
 * 
 * Return:
 * - HTML containing retrieved Posts.
 */
function snc_getTweetsByUser ( $twitterSettings, $screenNames, $count = SNC_DEFAULT_NUM_POSTS, $maxPosts = SNC_MAX_NUM_POSTS ) {
	
	$content = "";
	
	$sncBaseUrl = SNC_BASE_URL;
	
	/* Information from own timeline regarding own Tweets:
	extract(
		array(
			"tweetCreatedAt" => "",						// UTC time when this Tweet was created.
			"retweetCreatedAt" => "",					// UTC time when this Tweet was retweeted.
			"tweetFavoriteCount" => 0,					// Indicates approximately how many times this Tweet has been “favorited” by Twitter users.
			"tweetFavorited" => "",						// Indicates whether this Tweet has been favorited by the authenticating user.
			"tweetFilterLevel" => "",					// Indicates the maximum value of the filter_level parameter which may be used and still stream this Tweet. So a value of medium will be streamed on none, low, and medium streams.
			"tweetId" => 0,								// The integer representation of the unique identifier for this Tweet.
			"tweetIdStr" => "",							// The string representation of the unique identifier for this Tweet.
			"inReplyToScreenName" => "",				// If the represented Tweet is a reply, this field will contain the screen name of the original Tweet’s author.
			"inReplyToStatusId" => 0,					// If the represented Tweet is a reply, this field will contain the integer representation of the original Tweet’s ID.
			"inReplyToStatusIdStr" => "",				// If the represented Tweet is a reply, this field will contain the string representation of the original Tweet’s ID.
			"inReplyToUserId" => 0,						// If the represented Tweet is a reply, this field will contain the integer representation of the original Tweet’s author ID.
			"inReplyToUserIdStr" => "",					// If the represented Tweet is a reply, this field will contain the string representation of the original Tweet’s author ID.
			"tweetLang" => "",							// When present, indicates a BCP 47 language identifier corresponding to the machine-detected language of the Tweet text, or “und” if no language could be detected.
			"tweetPossiblySensitive" => "",				// This field only surfaces when a tweet contains a link. The meaning of the field doesn’t pertain to the tweet content itself, but instead it is an indicator that the URL contained in the tweet may contain content or media identified as sensitive content.
			"tweetRetweetCount" => 0,					// Number of times this Tweet has been retweeted.
			"tweetRetweetStatus" => array(),			
			"tweetRetweeted" => "",						// Indicates whether this Tweet has been retweeted by the authenticating user.
			"tweetSource" => "",						// Utility used to post the Tweet, as an HTML-formatted string. Tweets from the Twitter website have a source value of web.
			"tweetText" => "",							// The actual UTF-8 text of the status update.
			"tweetTruncated" => "",						// Indicates whether the value of the text parameter was truncated, for example, as a result of a retweet exceeding the 140 character Tweet length.
			"tweetWithheldCopyright" => "",				// When present and set to “true”, it indicates that this piece of content has been withheld due to a DMCA complaint.
			"tweetWithheldInCountries" => array(),		// When present, indicates a list of uppercase two-letter country codes this content is withheld from.
			"tweetWithheldScope" => "",					// When present, indicates whether the content being withheld is the “status” or a “user.”
		)
	);*/

	/* Information about the User who tweeted the tweet:
	extract(
		array(
			"userContributorsEnabled" => "",			// Indicates that the user has an account with “contributor mode” enabled, allowing for Tweets issued by the user to be co-authored by another account.
            "userCreatedAt" => "",						// The UTC datetime that the user account was created on Twitter.
            "userDefaultProfile" => "",					// When true, indicates that the user has not altered the theme or background of their user profile.
            "userDefaultProfileImage" => "",			// When true, indicates that the user has not uploaded their own avatar and a default egg avatar is used instead.
            "userDescription" => "",					// The user-defined UTF-8 string describing their account.
			"userFavouritesCount" => 0,					// The number of tweets this user has favorited in the account’s lifetime. 
            "userFollowRequestSent" => "",				// When true, indicates that the authenticating user has issued a follow request to this protected user account.
            "userFollowing" => 0,						// When true, indicates that the authenticating user is following this user.
            "userFollowersCount" => 0,					// The number of followers this account currently has.
            "userFriendsCount" => 0,					// The number of users this account is following (AKA their “followings”). 
            "userGeoEnabled" => "",						// When true, indicates that the user has enabled the possibility of geotagging their Tweets.
            "userId" => 0,								// The integer representation of the unique identifier for this User.
			"userIdStr" => "",							// The string representation of the unique identifier for this User. 
			"userIsTranslator" => "",					// When true, indicates that the user is a participant in Twitter’s translator community.
            "userLang" => "",							// The BCP 47 code for the user’s self-declared user interface language. 
            "userListedCount" => 0,						// The number of public lists that this user is a member of.
            "userLocation" => "",						// The user-defined location for this account’s profile. Not necessarily a location nor parseable. This field will occasionally be fuzzily interpreted by the Search service.
			"userName" => "",							// The name of the user, as they’ve defined it. Not necessarily a person’s name. Typically capped at 20 characters, but subject to change.
			"userNotifications" => "",					// May incorrectly report “false” at times. Indicates whether the authenticated user has chosen to receive this user’s tweets by SMS.
            "userProfileBackgroundColor" => "",			// The hexadecimal color chosen by the user for their background.
            "userProfileBackgroundImageUrl" => "",		// A HTTP-based URL pointing to the background image the user has uploaded for their profile.
            "userProfileBackgroundImageUrlHttps" => "",	// A HTTPS-based URL pointing to the background image the user has uploaded for their profile.
            "userProfileBackgroundTile" => 0,			// When true, indicates that the user’s profile_background_image_url should be tiled when displayed.
            "userProfileBannerUrl" => "",				// The HTTPS-based URL pointing to the standard web representation of the user’s uploaded profile banner. 
            "userProfileImageUrl" => "",				// A HTTP-based URL pointing to the user’s avatar image.
            "userProfileImageUrlHttps" => "",			// A HTTPS-based URL pointing to the user’s avatar image.
			"userProfileLinkColor" => "",				// The hexadecimal color the user has chosen to display links with in their Twitter UI.
            "userProfileSidebarBorderColor" => "",		// The hexadecimal color the user has chosen to display sidebar borders with in their Twitter UI.
            "userProfileSidebarFillColor" => "",		// The hexadecimal color the user has chosen to display sidebar backgrounds with in their Twitter UI.
            "userProfileTextColor" => "",				// The hexadecimal color the user has chosen to display text with in their Twitter UI.
            "userProfileUseBackgroundImage" => 0,		// When true, indicates the user wants their uploaded background image to be used.
			"userProtected" => "",						// When true, indicates that this user has chosen to protect their Tweets. 
            "userScreenName" => "",						// The screen name, handle, or alias that this user identifies themselves with. screen_names are unique but subject to change.
            "userShowAllInlineMedia" => "",				// Indicates that the user would like to see media inline.
			"userStatusesCount" => 0,					// The number of tweets (including retweets) issued by the user.
            "userTimeZone" => "",						// A string describing the Time Zone this user declares themselves within.
            "userUrl" => "",							// A URL provided by the user in association with their profile.
            "userUtcOffset" => 0,						// The offset from GMT/UTC in seconds.
            "userVerified" => "",						// When true, indicates that the user has a verified account.
            "userWithheldInCountries" => "",			// When present, indicates a textual representation of the two-letter country codes this user is withheld from.
            "userWithheldScope" => "",					// When present, indicates whether the content being withheld is the “status” or a “user”.
		)
	);*/

	//$contributors = array();							// A collection of brief user objects (usually only one) indicating users who contributed to the authorship of the tweet, on behalf of the official tweet author.
	//$coordinates = array();							// Represents the geographic location of this Tweet as reported by the user or client application.
	//$currentUserRetweet = array();					// Only surfaces on methods supporting the include_my_retweet parameter, when set to true.
	//$entities = array();								// Entities which have been parsed out of the text of the Tweet.
	//$place = array();									// When present, indicates that the tweet is associated (but not necessarily originating from) a Place. 
	//$scope = array();									// A set of key-value pairs indicating the intended contextual delivery of the containing Tweet. 
	//$retweetedStatus = array();						// This attribute contains a representation of the original Tweet that was retweeted.
	//$status = array();								// If possible, the user’s most recent tweet or retweet. In some circumstances, this data cannot be provided and this field will be omitted, null, or empty.
	
	$tweets = array();									// Vehicle for storing Tweets for each User as a call will be made to Twitter per Account
	
	foreach ( $screenNames as $screenName ) {
	
		$url = SNC_TWITTER_API_URL . "statuses/user_timeline.json";
		$getfield = "?count=" . $count . "&exclude_replies=" . SNC_TWITTER_EXCLUDE_REPLIES . "&include_rts=" . SNC_TWITTER_INCLUDE_RETWEETS . "&screen_name=" . $screenName;
		
		// Check if cached version of Twitter request available otherwise make fresh call to Twitter
		$twitterResponse = snc_doCheckTwitterCache( $twitterSettings, $url, $getfield );
		
		// Write to log file
		snc_setLogMessage( "twitter.php - snc_getTweetsByUser(): url = >>>" . $url . "<<<" );
		snc_setLogMessage( "twitter.php - snc_getTweetsByUser(): count = $count, maxPosts = $maxPosts" );
		snc_setLogMessage( "twitter.php - snc_getTweetsByUser(): getfield = >>>" . $getfield . "<<<" );
		//snc_setLogMessage( "twitter.php - snc_getTweetsByUser(): twitterResponse = " . print_r( $twitterResponse, true ) );

		if ( isset( $twitterResponse["error"] ) ) {
			$errorMsg = "'" . str_replace( ".", "", $twitterResponse["error"] ) . "'.";
			if ( $twitterResponse["error"] == "Not authorized." ) {
				$errorMsg .= " An account may be protected.";
			}
			$_SESSION["warning"] = "Twitter problem encountered - " . $errorMsg;
			snc_setLogMessage( "twitter.php - snc_getTweetsByUser() warning = " . $_SESSION["warning"] );
		} else if ( isset( $twitterResponse["errors"] ) ) {
			$errorMsg = "'" . str_replace( ".", "", $twitterResponse["errors"][0]["message"] ) . "'.";
			if ( $twitterResponse["errors"][0]["message"] == "Invalid or expired token." ) {
				$errorMsg .= " Please contact support.";
			}
			$_SESSION["warning"] = "Twitter problem encountered - " . $errorMsg;
			snc_setLogMessage( "twitter.php - snc_getTweetsByUser() warning = " . $_SESSION["warning"] );
		} else if ( !empty( $twitterResponse ) ) {
			
			// Trim Tweets per Account if greater than limit
			if ( sizeof( $twitterResponse ) > $count ) {
				$twitterResponse = array_slice( $twitterResponse, 0, $count );
			}
			
			// Process Tweets
			foreach ( $twitterResponse as $tweet ) {
				
				// Place Tweets into an array using their Timestamp so they can be sorted
				$sortTimestamp = date_create( snc_getValueForKey( $tweet, "created_at", "" ) )->getTimestamp();
				$tweets[$sortTimestamp] = $tweet;
			}
		}
	}
	
	// Crude limiting of number of Tweets which will favor earlier specified accounts/users over the latter
	$tweets = array_slice( $tweets, 0, $maxPosts );

	if ( sizeof( $tweets ) > 0 ) {

		foreach ( $tweets as $timestamp => $tweet ) {
		
			// Extract fields for an individual item
			$tweetCreatedAt = snc_getValueForKey( $tweet, "created_at", "" );
			$tweetId = snc_getValueForKey( $tweet, "id", 0 );
			$tweetText = snc_getValueForKey( $tweet, "text", "" );
			$tweetRetweetCount = snc_getValueForKey( $tweet, "retweet_count", 0 );
			$tweetRetweeted = snc_getValueForKey( $tweet, "retweeted", 0 );
			$tweetFavoriteCount = snc_getValueForKey( $tweet, "favorite_count", 0 );
			$tweetFavorited = snc_getValueForKey( $tweet, "favorited", 0 );
			$tweetRetweetStatus = snc_getValueForKey( $tweet, "retweeted_status", array() );
			
			// For Retweets certain fields need to be set to the originals
			if ( sizeof( $tweetRetweetStatus ) > 0 ) {
				$tweetId = snc_getValueForKey( $tweetRetweetStatus, "id", 0 );
				$tweetCreatedAt = snc_getValueForKey( $tweetRetweetStatus, "created_at", "" );
				$tweetRetweetCount = snc_getValueForKey( $tweetRetweetStatus, "retweet_count", 0 );
				$tweetRetweeted = snc_getValueForKey( $tweetRetweetStatus, "retweeted", 0 );
				$tweetFavoriteCount = snc_getValueForKey( $tweetRetweetStatus, "favorite_count", 0 );
				$tweetFavorited = snc_getValueForKey( $tweetRetweetStatus, "favorited", 0 );
			}

			// Extract fields for an individual user
			$user = $tweet["user"]; // extract User sub-array
			$userName = snc_getValueForKey( $user, "name", "" );
			$userScreenName = snc_getValueForKey( $user, "screen_name", "" );
			$userUrl = snc_getValueForKey( $user, "url", "" );
			//$userProfileImageUrl = snc_getValueForKey( $user, "profile_image_url", "" );
			$userProfileImageUrlHttps = snc_getValueForKey( $user, "profile_image_url_https", "" );
			
			// Modify Tweet text for entities
			$origTweetText = $tweetText;
			//$entitiesText = print_r( $tweet["entities"], true );
			$tweetText = snc_getCleanEllipsis( snc_getTwitterEntitiesText( $tweet, $tweetText ) );
//			$tweetText = snc_getBulkWordwrap( $tweetText, 35 );
			$tweetText = snc_getHtmlWrap( $tweetText, 35, "-\n" );


			// Calculate how long ago tweet was posted or retweeted
			$postDate = date_create( $tweetCreatedAt );
			$timeDiff = snc_getHowLongAgo( $postDate );
			$timestamp = $postDate->getTimestamp();


			// Process extracted fields ready for display markup
			$favoritedUrl = $sncBaseUrl . "images/icon_favorite.png";
			if ( $tweetFavoriteCount > 0 ) {
				$favoritedUrl = $sncBaseUrl . "images/icon_favorited.png";
			}
			
			// Format User Profile image for Header
			$profileImage = "";
			if ( $userProfileImageUrlHttps != "" ) {
				$profileImage .= <<<EOF
<img class="sncBodyImage" src="$userProfileImageUrlHttps" alt="$userName" title="$userName" />
EOF;
			}

			$profileTxt = "";
			if ( $userUrl != "" ) {
				$imgLink = $sncBaseUrl . "images/icon_link.png";
				$profileTxt =<<<EOF
<a href="$userUrl" rel="nofollow" target="_blank"><img class="sncBodyImage" src="$imgLink" alt="view website" title="view website" /></a>
EOF;
			}
			
			// Format Tweet image
			$mediaUrl = "";
			$mediaWidth = "";
			$mediaHeight = "";

			if ( isset( $tweet['entities']['media'] ) && $tweet['entities']['media'][0]['type'] == 'photo' ) {
				$mediaItem = $tweet['entities']['media'][0];
				$mediaUrl = $mediaItem['media_url_https'];
				$mediaWidth = $mediaItem['sizes']['small']['w'];
				$mediaHeight = $mediaItem['sizes']['small']['h'];
				
				// Adjust dimensions if Width too large
				if ( $mediaWidth > SNC_IMAGE_MAX_WIDTH ) {
					$mediaHeight = ( int )( $mediaHeight * ( SNC_IMAGE_MAX_WIDTH / $mediaWidth ) );
					$mediaWidth = SNC_IMAGE_MAX_WIDTH;
				}

				// Adjust dimensions if Height too large
				if ( $mediaHeight > SNC_IMAGE_MAX_HEIGHT ) {
					$mediaWidth = ( int )( $mediaWidth * ( SNC_IMAGE_MAX_HEIGHT / $mediaHeight ) );
					$mediaHeight = SNC_IMAGE_MAX_HEIGHT;
				}
			}
			
			// Determine what type of Retweet & Favorite icons should be displayed
			$retweetId = "sncRetweet";
			if ( $tweetRetweeted > 0 ) {
				$retweetId = "sncRetweeted";
			}
			$favoriteId = "sncFavorite";
			if ( $tweetFavorited > 0 ) {
				$favoriteId = "sncFavorited";
			}

			// Format header title
			//$headerTitle = snc_getBulkWordwrap( $userName );
			$headerTitle = snc_getHtmlWrap( $userName, 15, "-\n" );
			
			// Prepare HTML links for images and within plugin
			$twitterLogoLink = $sncBaseUrl . "images/twitter/Twitter_logo_blue_32.png";

			// Output Tweet
			$content .= <<<EOF
	<div class="sncItem">
		<table width="100%" cellspacing="0" cellpadding="1">
			<tr class="sncHeader">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="48">$profileImage</td>
							<td align="center">
								<strong><a class="sncHeaderTitle" href="#magnific-inline-popup" onclick="snc_getLoadSocialPopup( '$sncBaseUrl', 'twitter', '@$userScreenName' );return false;">$headerTitle</a></strong><br />
								<a class="sncSubHeader" href="#magnific-inline-popup" onclick="snc_getLoadSocialPopup( '$sncBaseUrl', 'twitter', '@$userScreenName' );return false;">@$userScreenName</a>
							</td>
							<td width="48"><a href="https://twitter.com/$userScreenName/status/$tweetId" rel="nofollow" target="_blank"><img class="sncBodyImage" src="$twitterLogoLink" alt="original Tweet" title="original Tweet" /></a></td>
						</tr>
					</table>
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncBody">
				<td align="center">
EOF;
			if ( $mediaUrl != "" ) {
				$content .= <<<EOF
					<a href="$mediaUrl" rel="nofollow" class="magnific-image-link"><img class="sncBodyImage" src="$mediaUrl" width="$mediaWidth" height="$mediaHeight" alt="view larger picture" title="view larger picture" /></a><br />
EOF;
			}
			//$content .= "<pre>" . print_r( $item, true ) . "</pre>";
			$content .= <<<EOF
					$tweetText
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
			<tr class="sncFooter">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="33%"><a href="https://twitter.com/intent/favorite?tweet_id=$tweetId"><div id="$favoriteId" title="favorite"></div></a></td>
							<td width="33%"><a href="https://twitter.com/intent/tweet?in_reply_to=$tweetId"><div id="sncReply" title="reply"></div></a></td>
							<td width="33%"><a href="https://twitter.com/intent/retweet?tweet_id=$tweetId"><div id="$retweetId" title="retweet"></div></a></td>
						</tr>
						<tr>
							<td width="33%" class="sncFooterLeft">$tweetFavoriteCount</td>
							<td width="33%" class="sncFooterCenter">$timeDiff</td>
							<td width="33%" class="sncFooterRight">$tweetRetweetCount</td>
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
 * Replace Entities (Hashtags, Symbols, User Mentions, URLs & Media) in Tweet text.
 *
 * Parameters:
 * - tweet		: Tweet as an Array.
 * - tweetText	: Original Tweet text.
 * - popupFlag	: Indicates whether the Tweet will appear in a popup window or not as this impacts how media links are handled.
 * 
 * Return:
 * - Tweet text with Entities replaced.
 */ 
function snc_getTwitterEntitiesText ( $tweet, $tweetText, $popupFlag = false ) {

	// Retrieve Entities from the Tweet
	$entities = $tweet["entities"];
	
	// If processing a Retweet then retrieve Entities & Text from this instead
	if ( isset( $tweet["retweeted_status"] ) ) {
		$entities = $tweet["retweeted_status"]["entities"];
		$tweetText = $tweet["retweeted_status"]["text"];
	}
	
	// Extract different types of Entities from Tweet
	$hashTags = snc_getValueForKey ( $entities, "hashtags", array() );
	$symbols = snc_getValueForKey ( $entities, "symbols", array() );
	$userMentions = snc_getValueForKey ( $entities, "user_mentions", array() );
	$urls = snc_getValueForKey ( $entities, "urls", array() );
	$medias = snc_getValueForKey ( $entities, "media", array() );
	
	// Patterns for replacing parts of Tweet text
//	$hashTagLinkPattern = '#<a href="#postsPopup" class="open-popup-link" onclick="snc_getLoadSocialPopup( \'postsPopup\', \'twitter\', \'#%s\' );">%s</a>';
	$hashTagLinkPattern = '#<a class="sncInlineLink" href="#magnific-inline-popup" onclick="snc_getLoadSocialPopup( \'' . SNC_BASE_URL . '\', \'twitter\', \'#%s\' );return false;">%s</a>';
//	$symbolLinkPattern = '$<a href="#postsPopup" class="open-popup-link" onclick="snc_getLoadSocialPopup( \'postsPopup\', \'twitter\', \'$%s\' );">%s</a>';
	$symbolLinkPattern = '$<a class="sncInlineLink" href="#magnific-inline-popup" onclick="snc_getLoadSocialPopup( \'' . SNC_BASE_URL . '\', \'twitter\', \'$%s\' );return false;">%s</a>';
//	$userMentionLinkPattern = '@<a href="#profilePopup" class="open-popup-link" onclick="snc_getLoadSocialPopup( \'profilePopup\', \'twitter\', \'@%s\' );">%s</a>';
	$userMentionLinkPattern = '@<a class="sncInlineLink" href="#magnific-inline-popup" onclick="snc_getLoadSocialPopup( \'' . SNC_BASE_URL . '\', \'twitter\', \'@%s\' );return false;">%s</a>';
	$urlLinkPattern = '<a href="%s" rel="nofollow" target="_blank"%s>%s</a>'; // middle '%s' is for Popup class
	$mediaLinkPattern = '<a href="%s" rel="nofollow"%s>%s</a>'; // middle '%s' is for Popup class or JavaScript 'onclick()' function call
	
	$entityList = array(); // Array to hold substitutions that will be sorted in reverse order then applied
	$indicesTxt = "<br /><br />*-*-*-*-*<br />";

	// Extract Hash Tags to be replaced with links
	foreach ( $hashTags as $hashTag ) {
		$entity = new stdclass();
		$entity->start = $hashTag["indices"][0];
		$entity->end = $hashTag["indices"][1];
		$entity->length = $hashTag["indices"][1] - $hashTag["indices"][0];
		$entity->replace = sprintf( $hashTagLinkPattern, $hashTag["text"], $hashTag["text"] );
		$entityList[$entity->start] = $entity;
		$indicesTxt .= "<br />HashTags:<br />" . print_r( $hashTag["indices"], true );
	}

	// Replace Symbols with links
	foreach ( $symbols as $symbol ) {
		$entity = new stdclass();
		$entity->start = $symbol["indices"][0];
		$entity->end = $symbol["indices"][1];
		$entity->length = $symbol["indices"][1] - $symbol["indices"][0];
		$entity->replace = sprintf( $symbolLinkPattern, $symbol["text"], $symbol["text"] );
		$entityList[$entity->start] = $entity;
		$indicesTxt .= "<br />Symbols:<br />" . print_r( $symbol["indices"], true );
	}

	// Replace User Mentions with links
	foreach ( $userMentions as $userMention ) {
		$entity = new stdclass();
		$entity->start = $userMention["indices"][0];
		$entity->end = $userMention["indices"][1];
		$entity->length = $userMention["indices"][1] - $userMention["indices"][0];
		$entity->replace = sprintf( $userMentionLinkPattern, $userMention["screen_name"], $userMention["screen_name"] );
		$entityList[$entity->start] = $entity;
		$indicesTxt .= "<br />User Mentions:<br />" . print_r( $userMention["indices"], true );
	}

	// Replace URLs with links
	foreach ( $urls as $url ) {
		$entity = new stdclass();
		$entity->start = $url["indices"][0];
		$entity->end = $url["indices"][1];
		$entity->length = $url["indices"][1] - $url["indices"][0];
		
		// Determine Popup class for this type of content
		$className = (
			snc_getPopupClass( $url["expanded_url"] ) != "" ?
			snc_getPopupClass( $url["expanded_url"] ) :
			snc_getPopupClass( $url["display_url"] )
		);
		$classTxt = "";
		if ( $className != "" ) {
			$classTxt = ' class="' . $className . '"';
		}
		
		// Shorten display name for link if needed
		$displayUrl = $url["display_url"];
		if ( strlen( $displayUrl ) > SNC_MAX_LINK_LENGTH ) {
			$displayUrl = substr( $url["display_url"], 0, SNC_MAX_LINK_LENGTH - 3 ) . "...";
		}
		
//		$entity->replace = sprintf( $urlLinkPattern, $url["url"], $url["expanded_url"], $url["display_url"] );
		$entity->replace = sprintf( $urlLinkPattern, $url["expanded_url"], $classTxt, $displayUrl );
		$entityList[$entity->start] = $entity;
		$indicesTxt .= "<br />URLs:<br />" . print_r( $url["indices"], true );
	}
	
	foreach ( $medias as $key => $media ) {
		
		$entity = new stdclass();
		$entity->start = $media["indices"][0];
		$entity->end = $media["indices"][1];
		$entity->length = $media["indices"][1] - $media["indices"][0];
		$entity->count = $media["type"]; // needed to check if it is a photo
		//$entity->replace = sprintf( $mediaLinkPattern, $media["url"], $media["expanded_url"], $media["display_url"] );
		
		// Determine Popup class for this type of content
		$className = (
			snc_getPopupClass( $media["media_url_https"] ) != "" ?
			snc_getPopupClass( $media["media_url_https"] ) :
			snc_getPopupClass( $media["display_url"] )
		);
		$classTxt = "";
		if ( $className != "" ) {
			$classTxt = ' class="' . $className . '"';
		}
		
		// Shorten display name for link if needed
		$displayUrl = $media["display_url"];
		if ( strlen( $displayUrl ) > SNC_MAX_LINK_LENGTH ) {
			$displayUrl = substr( $media["display_url"], 0, SNC_MAX_LINK_LENGTH - 3 ) . "...";
		}

//		$entity->replace = sprintf( $mediaLinkPattern, $media["expanded_url"], $classTxt, $displayUrl );
		if ( $popupFlag ) {
			$mediaUrl = $media["media_url_https"];
			$jsText = <<<EOF
 onClick="snc_doOpenPopupLink( '$mediaUrl', 'image' );return false;"
EOF;
			$entity->replace = sprintf( $mediaLinkPattern, "", $jsText, $displayUrl );
		} else {
			$entity->replace = sprintf( $mediaLinkPattern, $media["media_url_https"], $classTxt, $displayUrl );
		}
		$entityList[$entity->start] = $entity;
		$indicesTxt .= "<br />Media:<br />" . print_r( $media["indices"], true );
	}

	krsort( $entityList );

	$indicesTxt .= "<br /><br />*-*-*-*-*<br />";
	$indicesTxt .= "<br />Original text:<br />" . $tweetText;
	$indicesTxt .= "<br /><br />*-*-*-*-*<br />";
	
	foreach ( $entityList as $entity ) {
		$indicesTxt .= "<br />'" . mb_substr( $tweetText, $entity->start, $entity->length ) . "' (" . $entity->start . "," . $entity->length . ") -> '" . $entity->replace . "'<br />";
		$tweetText = mb_substr_replace( $tweetText, $entity->replace, $entity->start, $entity->length );
	}
	
	//$tweetText .= $indicesTxt . "<br /><br />*-*-*-*-*<br />";
	//$tweetText .= $indicesTxt . "<br />*-*-*-*-*<br />" . print_r( $entityList, true );

	// If Retweet then add appropriate prefix
	if ( isset( $tweet["retweeted_status"] ) ) {
		$userScreenName = $tweet["retweeted_status"]["user"]["screen_name"];
		$tweetText = "RT " . sprintf( $userMentionLinkPattern, $userScreenName, $userScreenName ) . ": " . $tweetText;
	}
	
	return $tweetText;
}

/*
 * Function to get the Twitter profile for a particular User.
 * 
 * Parameters:
 * - twitterSettings:	Twitter API access settings.
 * - screenNames:		List of User Accounts on Twitter (though usually only 1).
 * 
 * Return:
 * - HTML containing retrieved User Profile.
 */
function snc_getTwitterUserProfile ( $twitterSettings, $screenNames ) {
	
	$content = "";

	$sncBaseUrl = SNC_BASE_URL;

	//$contributors = array();							// A collection of brief user objects (usually only one) indicating users who contributed to the authorship of the tweet, on behalf of the official tweet author.
	//$coordinates = array();							// Represents the geographic location of this Tweet as reported by the user or client application.
	//$currentUserRetweet = array();					// Only surfaces on methods supporting the include_my_retweet parameter, when set to true.
	//$entities = array();								// Entities which have been parsed out of the text of the Tweet.
	//$place = array();									// When present, indicates that the tweet is associated (but not necessarily originating from) a Place. 
	//$scope = array();									// A set of key-value pairs indicating the intended contextual delivery of the containing Tweet. 
	//$retweetedStatus = array();						// This attribute contains a representation of the original Tweet that was retweeted.
	//$status = array();								// If possible, the user’s most recent tweet or retweet. In some circumstances, this data cannot be provided and this field will be omitted, null, or empty.
	
	foreach ( $screenNames as $screenName ) {
	
		$url = SNC_TWITTER_API_URL . "users/show.json";
//		$getfield = "?screen_name=" . $screenName . "&count=1"; // better URL?
		$getfield = "?count=1" . "&screen_name=" . $screenName; // better URL?
		
		// Check if cached version of Twitter request available otherwise make fresh call to Twitter
		$twitterResponse = snc_doCheckTwitterCache( $twitterSettings, $url, $getfield, 120, 1440 ); // override cache so Profile retrieved every 2 hours & refreshed every day
		
		// Log usage stats - TODO
		//snc_setLogApiUsageStats ( $site, $functionCall, $resource, $rateLimit, $remaining, $limitReset );
		
		// Write to log file
		//snc_setLogMessage( "twitterResponse:\n" . print_r( $twitterResponse, true ) );

		if ( isset( $twitterResponse["error"] ) ) {
			$errorMsg = "'" . str_replace( ".", "", $twitterResponse["error"] ) . "'.";
			if ( $twitterResponse["error"] == "Not authorized." ) {
				$errorMsg .= " An account may be protected.";
			} else if ( $twitterResponse["error"] == "Invalid or expired token." ) {
				$errorMsg .= " Please contact support.";
			}
			$_SESSION["warning"] = "Twitter problem encountered - " . $errorMsg;
			snc_setLogMessage( "twitter.php - snc_getTwitterUserProfile() warning = " . $_SESSION["warning"] );
		} else if ( isset( $twitterResponse["errors"] ) ) {
			$errorMsg = "'" . str_replace( ".", "", $twitterResponse["errors"][0]["message"] ) . "'.";
			$_SESSION["warning"] = "Twitter problem encountered - " . $errorMsg;
			snc_setLogMessage( "twitter.php - snc_getTwitterUserProfile() warning = " . $_SESSION["warning"] );
		} else if ( !empty( $twitterResponse ) ) {
	
			// Extract fields for an individual user
//			$userId = snc_getValueForKey( $twitterResponse, "id", "" );
			$userName = snc_getValueForKey( $twitterResponse, "name", "" );
			$userScreenName = snc_getValueForKey( $twitterResponse, "screen_name", "" );
//			$userLocation = snc_getValueForKey( $twitterResponse, "location", "" );
			$userDescription = snc_getValueForKey( $twitterResponse, "description", "" );
			$userUrl = snc_getValueForKey( $twitterResponse, "url", "" );
//			$userProtected = snc_getValueForKey( $twitterResponse, "protected" );
			$userFollowersCount = snc_getValueForKey( $twitterResponse, "followers_count", 0 );
			$userFriendsCount = snc_getValueForKey( $twitterResponse, "friends_count", 0 );
//			$userListedCount = snc_getValueForKey( $twitterResponse, "listed_count", 0 );
			$userCreatedAt = snc_getValueForKey( $twitterResponse, "created_at", "" );
//			$userVerified = snc_getValueForKey( $twitterResponse, "verified" );
//			$userStatusesCount = snc_getValueForKey( $twitterResponse, "statuses_count", 0 );
            $userProfileBannerUrl = snc_getValueForKey( $twitterResponse, "profile_banner_url", "" );
            $userProfileBackgroundImageUrlHttps = snc_getValueForKey( $twitterResponse, "profile_background_image_url_https", "" );
			$userProfileImageUrlHttps = snc_getValueForKey( $twitterResponse, "profile_image_url_https", "" );
			
			// Calculate how long ago tweet was posted
			$postDate = date_create( $userCreatedAt );
			$createdAt = date_format( $postDate, "M d, Y" );

			// Create user profile
			$profileTxt = "";
			if ( $userUrl != "" ) {
				$wwwIconLink = $sncBaseUrl . "images/icon_link.png";
				$profileTxt =<<<EOF
<a href="$userUrl" rel="nofollow" target="_blank"><img src="$wwwIconLink" alt="view website" title="view website" /></a>
EOF;
			}
			
			// Format User Profile image
			$profileImage = "";
			if ( $userProfileImageUrlHttps != "" ) {
				$profileImage .= <<<EOF
<img src="$userProfileImageUrlHttps" alt="$userName" title="$userName" />
EOF;
			}
//			<tr class="sncHeader"><td colspan="3" align="center"><strong>$userName</strong><button title="Close (Esc)" type="button" class="mfp-close mfp-close-btn-in">x</button></td></tr>

			// Format User Profile header
			$headerImage = "";
			if ( $userProfileBannerUrl != "" ) {
				
				// Get dimensions of image
//				list( $width, $height ) = snc_getJpegSize( $userProfileBannerUrl );
				$dimensions = snc_getJpegSize( $userProfileBannerUrl );
				$width = 0;
				$height = 0;
				
				// Check image was accessible
				if ( $dimensions ) {
					list( $width, $height ) = $dimensions;
				} else {
					$userProfileBannerUrl = "";
				}
				
				// Resize if necessary - still needed for Firefox as CSS 'img' style resizing does not work
				if ( $width > 280 ) {
					$height = intval( $height * ( 280 / $width ) );
					$width = 280;
				}
				
				// Populate image if found
				if ( $width > 0 ) {
					$headerImage .= <<<EOF
<img src="$userProfileBannerUrl" alt="$userName" title="$userName" width="$width" height="$height" />
EOF;
				}
			} 
			if ( $userProfileBannerUrl == "" && $userProfileBackgroundImageUrlHttps != "" ) {
				// Get dimensions of image
				$dimensions = snc_getJpegSize( $userProfileBackgroundImageUrlHttps );
				$width = 0;
				$height = 0;
				
				// Check image was accessible
				if ( $dimensions ) {
					list( $width, $height ) = $dimensions;
				} else {
					$userProfileBackgroundImageUrlHttps = "";
				}
				
				// Resize if necessary - still needed for Firefox as CSS 'img' style resizing does not work
				if ( $width > 280 ) {
					$height = intval( $height * ( 280 / $width ) );
					$width = 280;
				}
				
				// Populate image if found
				if ( $width > 0 ) {
					$headerImage .= <<<EOF
<img src="$userProfileBackgroundImageUrlHttps" alt="$userName" title="$userName" width="$width" height="$height" />
EOF;
				}
			}
			
			// Format header title
			//$headerTitle = snc_getBulkWordwrap( $userName, 20 );
			$headerTitle = snc_getHtmlWrap( $userName, 20, "-\n" );

			// Output Profile
			$content .= <<<EOF
	<div class="sncPopUpProfileItem">
		<table width="100%" cellspacing="0" cellpadding="1">
EOF;

			// Display header image if one exists
			if ( $headerImage != "" ) {
				$content .= <<<EOF
			<tr class="sncBodyCenter"><td>$headerImage</td></tr>
			<tr class="sncLine"><td></td></tr>
EOF;
			}
			
			// Prepare HTML links for images and within plugin
			$twitterLogoLink = $sncBaseUrl . "images/twitter/Twitter_logo_blue_32.png";
			$twitterIconLink = $sncBaseUrl . "images/icon_twitter.png";

			$content .= <<<EOF
			<tr class="sncHeader">
				<td>
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="48">$profileImage</td>
							<td align="center">
								<strong>$headerTitle</strong><br />
								<span class="sncSubHeader">@<a href="https://twitter.com/intent/user?screen_name=$userScreenName">$userScreenName</a></span>
							</td>
							<td width="48"><a href="https://twitter.com/$userScreenName" rel="nofollow" target="_blank"><img src="$twitterLogoLink" alt="view Twitter profile" title="view Twitter profile" /></a></td>
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
							<td width="35%"><a href="https://twitter.com/$userScreenName" rel="nofollow" target="_blank"><img src="$twitterIconLink" alt="view Twitter profile" title="view Twitter profile" /></a></td>
							<td width="35%" rowspan="2" valign="top">
								<iframe
									src="//platform.twitter.com/widgets/follow_button.html?screen_name=$userScreenName&show_screen_name=false&show_count=false"
									style="width: 60px; height: 20px; float: right;"
									allowtransparency="true"
									frameborder="0"
									scrolling="no">
								</iframe>
							</td>
						</tr>
						<tr class="sncBodyLeft">
							<td>Following:</td>
							<td>$userFriendsCount</td>
						</tr>
						<tr class="sncBodyLeft">
							<td>Followers:</td>
							<td colspan="2" width="100%">$userFollowersCount</td>
						</tr>
						<tr class="sncBodyLeft">
							<td>Joined:</td>
							<td colspan="2">$createdAt</td>
						</tr>
						<tr class="sncBodyLeft">
							<td>Web Site:</td>
							<td colspan="2">$profileTxt</td>
						</tr>
EOF;
			if ( $userDescription != "" ) {
				$userDescription = snc_getCleanEllipsis( snc_getConvertTwitterTagsToLinks( $userDescription ) );
				$content .= <<<EOF
						<tr class="sncBody">
							<td colspan="3" align="center"><em>$userDescription</em></td>
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

// Perform inline code when run in Stand-alone mode only
if ( !defined( 'ABSPATH' ) ) {

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

	snc_setLogMessage( "*** START twitter.php ***" );

	$cookie = get_sncCookie( "snc_twitter_access_cookie" );
	//snc_setLogMessage( "Cookie first: " . $cookie );

	// Retrieve User Access Token & Secret using cookie (if it already exists)
	if ( empty( $_SESSION['oauth_access_token'] ) && empty( $_SESSION['oauth_access_token_secret'] ) && $cookie != "" ) {
		$credList = getSiteCredsByCookie( $cookie );
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
	snc_setLogMessage( "Cookie before: " . get_sncCookie( "snc_twitter_access_cookie" ) );

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
			snc_setLogMessage( "twitter.php - main() warning = " . $_SESSION["warning"] );
			
			// Authorization was Denied or Cancelled so remove existing Session variables & Cookies
			unset( $_SESSION['oauth_access_token'] );
			unset( $_SESSION['oauth_access_token_secret'] );
			unset( $_SESSION['twitter_id'] );
			setCookie( "snc_twitter_access_cookie", "", time()-3600 );
		}
	}

	// Write to log file
	snc_setLogMessage( "twitterSettings() after: " . print_r( $twitterSettings, true ) );
	snc_setLogMessage( "SESSION() after: " . print_r( $_SESSION, true ) );
	snc_setLogMessage( "Cookie after: >>>" . $cookie . "<<<" ); // cannot display via get_sncCookie() as not returned from AJAX call yet

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

	//snc_setLogMessage( print_r( $response, true ) );
	//snc_setLogMessage( print_r( json_encode( $response ), true ) );

	snc_setLogMessage( "*** END twitter.php ***" );
}

?>
