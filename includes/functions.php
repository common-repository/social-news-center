<?php

/************************
 * COMMON PHP FUNCTIONS *
 ************************/

// Include Wordpress or Stand-alone functions
if ( defined( 'ABSPATH' ) ) {
	require_once( "functions-wp.php" );
} else {
	require_once( "functions-std.php" );
}
require_once( "htmlwrap.php" );

/*
 * Convert a piece of text so that any long words get wrapped onto the next line.
 *
 * Parameters:
 * - myText		: Text to be processed.
 * - wordWidth	: Maximum width of a word before being wrapped onto a new line.
 * 
 * Return:
 * - modified text.
 */ 
function snc_getBulkWordwrap ( $myText, $wordWidth = 15 ) {
	$wordList = explode( " ", $myText );
	foreach ( $wordList as $origWord ) {
		if ( strlen( $origWord ) > $wordWidth ) {
			$newWord = wordwrap( $origWord, $wordWidth, "-<br />\n", true );
			$myText = str_replace( $origWord, $newWord, $myText );
		}
	}
	return $myText;
}

/*
 * Clean up ellipsis from text.
 *
 * Parameters:
 * - myText		: Text to be cleaned.
 * 
 * Return:
 * - text that has been cleaned.
 */ 
function snc_getCleanEllipsis ( $myText ) {
	$cleanedText = 
		str_replace(
			"&hellip;.",
			"&hellip;",
			str_replace(
				".&hellip;",
				"&hellip;",
				str_replace(
					"&hellip;&hellip;",
					"&hellip;",
					str_replace( 
						"&hellip;…",
						"&hellip;",
						str_replace( 
							"…&hellip;",
							"&hellip;",
							str_replace( 
								"……",
								"&hellip;",
								str_replace( 
									"...",
									"&hellip;",
									$myText
								)
							)
						)
					)
				)
			)
		);
				
	return $cleanedText;
}

/*
 * Convert links within a piece of text into HTML clickable links.
 *
 * Parameters:
 * - myText		: Text to be converted.
 * - popupFlag	: Flag indicating whether to assign Popup class to link if applicable.
 * 
 * Return:
 * - text that has been converted.
 */ 
function snc_getConvertLinksToHtml ( $myText, $popupFlag = false ) {

	// Correct any incomplete links within text first
/*
	$myText = ereg_replace("www\.", "http://www.", $myText);
	$myText = ereg_replace("http://http://www\.", "http://www.", $myText);
	$myText = ereg_replace("https://http://www\.", "https://www.", $myText);
*/

	$patternList = array(
		"/www\./",
		"/http:\/\/http:\/\/www\./",
		"/https:\/\/http:\/\/www\./",
	);

	$replacementList = array(
		"http://www.",
		"http://www.",
		"https://www.",
	);
	
	$myText = preg_replace( $patternList, $replacementList, $myText );
	
	// The Regular Expression filter
//	$regExUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
	$regExUrl = '$\b(https?|ftp|file)://[-A-Z0-9+&@#/%?=~_|!:,.;]*[-A-Z0-9+&@#/%=~_|]$i';

	snc_setLogMessage( "snc_getConvertLinksToHtml() - myText before=" . $myText );

	// Check if there is a url in the text
	preg_match_all( $regExUrl, $myText, $url );
		
	snc_setLogMessage( "snc_getConvertLinksToHtml() - url=" . print_r( $url, true ) );
	
	// Remove duplicate URLs as this messes up the conversion of links to HTML
	$url[0] = array_unique( $url[0] );
		
	foreach ( $url[0] as $k=>$v ) {
		
		$classTxt = "";
		
		// Assign Magnific Popup class
		if ( $popupFlag ) {
			$className = snc_getPopupClass( $url[0][$k] );
			if ( $className != "" ) {
				$classTxt = ' class="' . $className . '"';
			}
		}

		// make the urls hyper links
		$myText =  str_replace( 
						$url[0][$k], 
						'<a href="' . 
							$url[0][$k] . 
							'" target="_blank" rel="nofollow"' . 
							$classTxt . 
							'>' . 
							( 
								strlen( $url[0][$k] ) > SNC_MAX_LINK_LENGTH ? 
								substr( $url[0][$k], 0, SNC_MAX_LINK_LENGTH ) . "&hellip;" : 
								$url[0][$k] 
							) . 
							'</a>', 
						$myText
					);
	}

	snc_setLogMessage( "snc_getConvertLinksToHtml() - myText after=" . $myText );
	
	// Convert any emails in text to HTML "mailto" links
    $search  = array( '/<p>__<\/p>/', '/([a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4})/' );
    $replace = array( '<hr />', '<a href="mailto:$1">$1</a>' );
    $myText = preg_replace( $search, $replace, $myText );
    	
	//snc_setLogMessage( "snc_getConvertLinksToHtml() - myText after=" . $myText );
	
	return $myText;
}

/*
 * Convert carriage returns to HTML line breaks within a piece of text.
 *
 * Parameters:
 * - myText		: Text to be converted.
 * 
 * Return:
 * - text that has been converted.
 */ 
function snc_getConvertReturnsToHtml ( $myText ) {
	$myText = 
		str_replace( "\n", "<br />", 
			str_replace( "\r", "<br />", 
				str_replace( "\n\r", "<br />", 
					str_replace( "\r\n", "<br />", $myText )
				)
			)
		);
	
	return $myText;
}

/*
 * Function to retrieve the value of a specific cookie.
 *
 * Parameters:
 * - cookieName		: Name of cookie.
 * - currentValue	: Existing value to return if cookie not set.
 * 
 * Return:
 * - value of cookie.
 */
function snc_getCookie ( $cookieName, $currentValue = "" ) {
	
	if ( count( $_COOKIE ) > 0 && isset( $_COOKIE[$cookieName] ) ) {
		return( $_COOKIE[$cookieName] );
	} else {
		return( $currentValue );
	}
}

/*
 * Generate an Item box for adding to a SNC Isotope area.
 *
 * Parameters:
 * - title		: Text that will act as box header.
 * - imageUrl	: URL of image to be displayed in body.
 * - imageName	: Name of image to be displayed in body.
 * - thumbnail	: URL of image thumbnail.
 * - text		: Text that will be placed in the body of the box below the image.
 * - footer		: Text that will be placed in the box footer.
 * - timestamp	: Timestamp that will be used to order the box.
 * 
 * Return:
 * - Generated HTML Item box.
 */ 
function snc_getItemBox ( $title, $imageUrl, $imageName, $thumbnail, $text, $footer, $timestamp = 0 ) {
	
	$content = "";
	if ( $timestamp == 0 ) {
		$timestamp = time();
	}

	$content .= <<<EOF
	<div class="sncItem">
		<table width="100%" cellspacing="0" cellpadding="1">
			<tr class="sncHeader">
				<td align="center">
					<strong>$title</strong>
				</td>
			</tr>
			<tr class="sncLine"><td></td></tr>
EOF;
	
	// Generate body of the box
	if ( $imageUrl != "" || $thumbnail != "" ) {
		if ( $imageName == "" ) {
			$imageName = $title;
		}
		if ( $thumbnail == "" ) {
			$content .= <<<EOF
				<tr class="sncBody">
					<td align="center">
						<img src="$imageUrl" alt="$imageName" title="$imageName">
					</td>
				</tr>
EOF;
		} else {
			$content .= <<<EOF
				<tr class="sncBody">
					<td align="center">
						<a href="$imageUrl" rel="nofollow" class="magnific-image-link"><img src="$thumbnail" alt="$imageName" title="$imageName"></a>
					</td>
				</tr>
EOF;
		}
	}
	
	// Generate body text of the box
	if ( $text != "" ) {
		$content .= <<<EOF
			<tr class="sncBody">
				<td align="center">
					$text
				</td>
			</tr>
EOF;
	}
	
	// Add box separator
	if ( ( $imageUrl != "" || $thumbnail != "" || $text != "" ) && $footer != "" ) {
		$content .= <<<EOF
			<tr class="sncLine"><td></td></tr>
EOF;
	}

	// Generate footer of the box
	if ( $footer != "" ) {
		$content .= <<<EOF
			<tr class="sncFooter">
				<td align="center">
					$footer
				</td>
			</tr>
EOF;
	}
	
	// Add box timestamp
	$content .= <<<EOF
		</table>
		<div id="sncTimestamp" class="sncTimestamp">$timestamp</div>
	</div>
EOF;

	return $content;
}

/*
 * Convert a Date into 'how long ago' format, assumes my in server timezone.
 *
 * Parameters:
 * - myDate		: Date to be converted.
 * - suffix		: Word(s) to apply to the end of the phrase being constructed.
 * 
 * Return:
 * - sentence saying how long ago Date occured.
 */ 
function snc_getHowLongAgo ( $myDate, $suffix = "ago" ) {
	
	// Determine intervals between submitted date and now
	$interval = date_diff( date_create(), $myDate );
	$yearDiff = $interval->format( "%y" );
	$monthDiff = $interval->format( "%m" );
	$dayDiff = $interval->format( "%d" );
	$hourDiff = $interval->format( "%h" );
	$minDiff = $interval->format( "%i" );
	$secDiff = $interval->format( "%s" );
	$timeDiff = "";
	
	// Format the age of the date relative to now
	if ( $yearDiff > 1 ) {
		$timeDiff = "$yearDiff years";
	} elseif ( $yearDiff > 0 ) {
		$timeDiff = "$yearDiff year";
	} elseif ( $monthDiff > 1 ) {
		$timeDiff = "$monthDiff months";
	} elseif ( $monthDiff > 0 ) {
		$timeDiff = "$monthDiff month";
	} elseif ( $dayDiff > 1 ) {
		$timeDiff = "$dayDiff days";
	} elseif ( $dayDiff > 0 ) {
		$timeDiff = "$dayDiff day";
	} elseif ( $hourDiff > 1 ) {
		$timeDiff = "$hourDiff hours";
	} elseif ( $hourDiff > 0 ) {
		$timeDiff = "$hourDiff hour";
	} elseif ( $minDiff > 1 ) {
		$timeDiff = "$minDiff mins";
	} elseif ( $minDiff > 0 ) {
		$timeDiff = "$minDiff min";
	} elseif ( $secDiff > 1 ) {
		$timeDiff = "$secDiff secs";
	} elseif ( $secDiff > 0 ) {
		$timeDiff = "$secDiff sec";
	}
	
	return( trim( $timeDiff . " " . $suffix ) );
}

/*
 * Retrieve JPEG width and height without downloading/reading entire image.
 *
 * Parameters:
 * - img_loc	: URL to image.
 * 
 * Return:
 * - an array containing dimensions and other attributes or FALSE upon failure.
 */ 
function snc_getJpegSize ( $img_loc ) {
//    $handle = fopen($img_loc, "rb") or die("Invalid file stream.");
    $handle = @fopen($img_loc, "rb");
    $new_block = NULL;
    if($handle && !feof($handle)) {
        $new_block = fread($handle, 32);
        $i = 0;
        if($new_block[$i]=="\xFF" && $new_block[$i+1]=="\xD8" && $new_block[$i+2]=="\xFF" && $new_block[$i+3]=="\xE0") {
            $i += 4;
            if($new_block[$i+2]=="\x4A" && $new_block[$i+3]=="\x46" && $new_block[$i+4]=="\x49" && $new_block[$i+5]=="\x46" && $new_block[$i+6]=="\x00") {
                // Read block size and skip ahead to begin cycling through blocks in search of SOF marker
                $block_size = unpack("H*", $new_block[$i] . $new_block[$i+1]);
                $block_size = hexdec($block_size[1]);
                while(!feof($handle)) {
                    $i += $block_size;
                    $new_block .= fread($handle, $block_size);
                    if($new_block[$i]=="\xFF") {
                        // New block detected, check for SOF marker
                        $sof_marker = array("\xC0", "\xC1", "\xC2", "\xC3", "\xC5", "\xC6", "\xC7", "\xC8", "\xC9", "\xCA", "\xCB", "\xCD", "\xCE", "\xCF");
                        if(in_array($new_block[$i+1], $sof_marker)) {
                            // SOF marker detected. Width and height information is contained in bytes 4-7 after this byte.
                            $size_data = $new_block[$i+2] . $new_block[$i+3] . $new_block[$i+4] . $new_block[$i+5] . $new_block[$i+6] . $new_block[$i+7] . $new_block[$i+8];
                            $unpacked = unpack("H*", $size_data);
                            $unpacked = $unpacked[1];
                            $height = hexdec($unpacked[6] . $unpacked[7] . $unpacked[8] . $unpacked[9]);
                            $width = hexdec($unpacked[10] . $unpacked[11] . $unpacked[12] . $unpacked[13]);
                            return array($width, $height);
                        } else {
                            // Skip block marker and read block size
                            $i += 2;
                            $block_size = unpack("H*", $new_block[$i] . $new_block[$i+1]);
                            $block_size = hexdec($block_size[1]);
                        }
                    } else {
                        return FALSE;
                    }
                }
            }
        }
    }
    return FALSE;
}

/*
 * Assign build and return the full URL of current page.
 * 
 * Return:
 * - Full URL of current page.
 */ 
function snc_getCurPageURL () {
	$pageURL = 'http';
	if ( isset( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on" ) {
		$pageURL .= "s";
	}
	$pageURL .= "://";
	if ( $_SERVER["SERVER_PORT"] != "80" ) {
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}

/*
 * Generate HTML needed for the top of the page for this plugin to work.
 *
 * Parameters:
 * - sncDivFlag		: Flag indicating whether or not to include the <div> tag for Isotope layout.
 * 
 * Return:
 * - Generated HTML.
 */ 
function snc_getPageHeaderHtml ( $sncDivFlag = true ) {
	
	// Count number of Social Networks enabled
	$count = 0;
	( SNC_FACEBOOK_FLAG ? $count++ : $count );
	( SNC_INSTAGRAM_FLAG ? $count++ : $count );
	( SNC_TWITTER_FLAG ? $count++ : $count );
	
	// Determine width of Login placeholders
	$loginWidth = "33%";
	$loginWidth = ( $count == 2 ? "50%" : $loginWidth );
	$loginWidth = ( $count == 1 ? "100%" : $loginWidth );

	// Determine alignment of Login placeholders starting with defaults
	$fbAlign = "float: left";
	$inAlign = "margin: 0 auto";
	$twAlign = "float: right";
	
	// If 1 or 2 missing then adjust alignments
	switch ( $count ) {
		case 1:
			$fbAlign = ( SNC_FACEBOOK_FLAG ? "margin: 0 auto" : $fbAlign );
			$twAlign = ( SNC_TWITTER_FLAG ? "margin: 0 auto" : $twAlign );
			break;
		case 2:
			$inAlign = ( SNC_INSTAGRAM_FLAG && SNC_FACEBOOK_FLAG ? "float: right" : $inAlign );
			$inAlign = ( SNC_INSTAGRAM_FLAG && SNC_TWITTER_FLAG ? "float: left" : $inAlign );
			break;
		default:
			break;
	}
	
	$html = <<<EOF
		<div id="sncHeader">
			<div id="sncMsgTxt" align="center"></div>
EOF;
	if ( SNC_FACEBOOK_FLAG ) {
		$tmpLoginWidth = $loginWidth; // temporary until Instagram Login button visible
		if ( SNC_INSTAGRAM_FLAG ) { // temporary until Instagram Login button visible
			$loginWidth = "50%";
		}
		$html .= <<<EOF
			<div id="sncFbLoginArea" align="center" class="sncFacebookLogin" style="$fbAlign; width: $loginWidth;"><br /></div>
EOF;
		$loginWidth = $tmpLoginWidth; // temporary until Instagram Login button visible
	}
	if ( SNC_TWITTER_FLAG ) {
		$tmpLoginWidth = $loginWidth; // temporary until Instagram Login button visible
		if ( SNC_INSTAGRAM_FLAG ) { // temporary until Instagram Login button visible
			$loginWidth = "50%";
		}
  		$html .= <<<EOF
			<div id="sncTwLoginArea" align="center" class="sncTwitterLogin" style="$twAlign; width: $loginWidth;"><br /></div>
EOF;
		$loginWidth = $tmpLoginWidth; // temporary until Instagram Login button visible
	}
	if ( SNC_INSTAGRAM_FLAG ) {
		$tmpLoginWidth = $loginWidth; // temporary until Instagram Login button visible
		$loginWidth = "0%"; // temporary until Instagram Login button visible
		$html .= <<<EOF
			<div id="sncInLoginArea" align="center" class="sncInstagramLogin" style="$inAlign; width: $loginWidth;"><br /></div>
EOF;
		$loginWidth = $tmpLoginWidth; // temporary until Instagram Login button visible
	}
	if ( !SNC_FACEBOOK_FLAG && !SNC_INSTAGRAM_FLAG && !SNC_TWITTER_FLAG ) {
  		$html .= <<<EOF
			<p align="center">WARNING: No Social Media networks enabled. <br />Please check configuration of 'Social News Center' plugin.</div>
EOF;
	}
	
	// Finish off button area formatting
	if ( $count > 1 ) {
	$html .= <<<EOF
			<br />
EOF;
	}
	$html .= <<<EOF
			<br />
		</div>
EOF;

	if ( $sncDivFlag ) {
		$html .= <<<EOF
		<div id="sncIsotope"></div>
EOF;
	}
	
	return $html;
}

/*
 * Assign Magnific Popup class based on type of link content.
 *
 * Parameters:
 * - myUrl		: Link to be checked for content type.
 * 
 * Return:
 * - Magnific Popup class associated with content type.
 */ 
function snc_getPopupClass ( $myUrl ) {
	
	// The Regular Expression filter
	$regExUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
	//$regExUrl = '$\b(https?|ftp|file)://[-A-Z0-9+&@#/%?=~_|!:,.;]*[-A-Z0-9+&@#/%=~_|]$i';

	$className = "";
	
	// Check parameter supplied is a valid link
	if ( preg_match( $regExUrl, $myUrl, $url ) ) {
		
		// Assign Magnific Popup class if content match found
		$classList = array(
			//"dailymotion.com" => "magnific-?",
			//"instagr.am/" => "magnific-?",
			//"metacafe.com/" => "magnific-?",
			"pbs.twimg.com/" => "magnific-image-link",
			"amp.twimg.com/" => "magnific-popup-amazon",
			//"snpy.tv/" => "magnific-?",
			//"twitpic.com" => "magnific-image-link",
			//"twitvid.com" => "magnific-?",
			"vimeo.com/" => "magnific-popup-vimeo",
			//"vine.co/" => "magnific-?",
			"youtube.com/" => "magnific-popup-youtube",
			//"youtu.be/" => "magnific-popup-youtube",
			"//maps.google." => "magnific-popup-gmaps",
		);
			
		// Check if link includes any of these content types
		foreach ( $classList as $key => $value ) {
			if ( $className == "" && strpos( $myUrl, $key ) > 0 ) {
				$className = $value;
			}
		}
	}
	
	return $className;
}

/*
 * Retrieve a summary of the submitted text.
 *
 * Parameters:
 * - myText			: Piece of Text to be shortened.
 * - limit			: Length of the Summary to be returned.
 * 
 * Return:
 * - Summary of Text.
 */ 
function snc_getSummary ( $myText, $limit = 200 ) {
	$rtnText = substr( $myText, 0, $limit );
	
	// Append "..." (&hellip;) to show text has been shortened
	if ( strlen( $myText ) > strlen( $rtnText ) ) {
		$rtnText = $rtnText . "&hellip;";
	}
	
	return $rtnText;
}

/*
 * Retrieve a summary of the submitted text with intact links.
 *
 * Parameters:
 * - myText			: Piece of Text to be shortened.
 * - limit			: Length of the Summary to be returned.
 * 
 * Return:
 * - Summary of Text.
 */ 
function snc_getSummaryWithLinks ( $myText, $limit = 200 ) {
	$rtnText = substr( $myText, 0, $limit );
	$linkStart = strrpos( $rtnText, "<a href" );
	$linkEnd = strpos( $rtnText, "</a>" );
	
	if ( $linkStart > $linkEnd ) {
		$rtnText = substr( $myText, 0, strpos( $myText, "</a>", strrpos( $rtnText, "<a href" ) +3 ) );
	}
	
	// Append "..." to show text has been shortened
	if ( strlen( $myText ) > strlen( $rtnText ) ) {
		$rtnText = $rtnText . "&hellip;";
	}
	
	return $rtnText;
}

/*
 * Retrieve a value from an array if it exists otherwise return same value.
 *
 * Parameters:
 * - myArray		: Array to be searched.
 * - myKey			: Key to be searched for within Array.
 * - myValue		: Existing value to be returned if not found.
 * 
 * Return:
 * - Value found in Array or Existing if Key not found.
 */ 
function snc_getValueForKey ( $myArray, $myKey, $myValue = null ) {
	if ( is_array( $myArray ) && array_key_exists ( $myKey, $myArray ) ) {
		$myValue = $myArray[$myKey];
	}
	
	return $myValue;
}

/*
 * Append message to log file on server.
 *
 * Parameters:
 * - msg			: Message to log in file.
 * - filename		: Name of log file.
 */ 
function snc_setLogMessage ( $msg, $filename = "output.txt" ) {
	if ( SNC_LOG_FLAG ) {
		$fullFilename = str_replace( "includes/", "", SNC_BASE_PATH . $filename );
		$current = file_get_contents( $fullFilename );
		$current .= "\n" . $msg . "\n";
		file_put_contents( $fullFilename, $current );
	}
}

/*
 * Function to reset the Session messages - called after outputting them to the screen.
 */
function snc_doResetSessionMessages () {
	unset( $_SESSION['error'] );
	unset( $_SESSION['warning'] );
	unset( $_SESSION['message'] );
	unset( $_SESSION['returnMsg'] );
	unset( $_SESSION['debug'] );
}

/*
 * Empty log file on server.
 *
 * Parameters:
 * - filename		: Name of log file.
 */ 
function snc_doResetLog ( $filename = "output.txt" ) {
	file_put_contents( $filename, "" );
}

/*
 * Sanitizes a filename replacing whitespace with dashes.
 *
 * Removes special characters that are illegal in filenames on certain
 * operating systems and special characters requiring special escaping
 * to manipulate at the command line. Replaces spaces and consecutive
 * dashes with a single dash. Trim period, dash and underscore from beginning
 * and end of filename.
 * 
 * Parameters:
 * - filename		: The filename to be sanitized.
 * 
 * Returns:
 * - The sanitized filename.
 */ 
function snc_getSanitizeFileName ( $filename ) {
    $special_chars = array( "?", "[", "]", "\\", "=", "<", ">", ":", ";", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}" );
    $filename = str_replace( $special_chars, "", $filename );
    $filename = str_replace( ",", "-", $filename );
    $filename = preg_replace( "/[\s-]+/", "-", $filename );
    $filename = trim( $filename, ".-_" );
    return $filename;
}

/*
 * Parse raw HTTP headers and put them into a nested associative array.
 *
 * Parameters:
 * - raw_headers	: Raw HTTP headers of one per line.
 * 
 * Return:
 * - Associative array of HTTP header keys and value pairs.
 */ 
if ( !function_exists( 'http_parse_headers' ) ) {
    function http_parse_headers ( $raw_headers ) {
        $headers = array();
        $key = '';

        foreach( explode( "\n", $raw_headers ) as $i => $h ) {
            $h = explode( ':', $h, 2 );

            if ( isset( $h[1] ) ) {
                if ( !isset( $headers[$h[0]] ) ) {
                    $headers[$h[0]] = trim($h[1]);
				} elseif (is_array($headers[$h[0]])) {
                    $headers[$h[0]] = array_merge( $headers[$h[0]], array( trim( $h[1] ) ) );
                } else {
                    $headers[$h[0]] = array_merge( array( $headers[$h[0]] ), array( trim( $h[1] ) ) );
                }

                $key = $h[0];
            } else {
                if (substr($h[0], 0, 1) == "\t") {
                    $headers[$key] .= "\r\n\t".trim($h[0]);
				} elseif (!$key) {
                    $headers[0] = trim( $h[0] );
                    trim( $h[0] );
				}
            }
        }

        return $headers;
    }
}

/*
 * Modified 'substr_replace()' function to handle special characters.
 *
 * Parameters:
 * - string			: Original string to be modified.
 * - replacement	: Replacement string.
 * - start			: Starting position of string to be replaced.
 * - length			: Length of striong to be replaced.
 * 
 * Return:
 * - Modified string.
 */ 
if ( !function_exists( 'mb_substr_replace' ) ) {
	function mb_substr_replace ( $string, $replacement, $start, $length = 0 ) {
		if ( is_array( $string ) ) {
			foreach ( $string as $i => $val ) {
				$repl = is_array( $replacement ) ? $replacement[$i] : $replacement;
				$st   = is_array( $start ) ? $start[$i] : $start;
				$len  = is_array( $length ) ? $length[$i] : $length;
		
				$string[$i] = mb_substr_replace( $val, $repl, $st, $len );
			}
		
			return $string;
		}
		
		$result  = mb_substr( $string, 0, $start, 'UTF-8' );
		$result .= $replacement;
		
		if ( $length > 0 ) {
			$result .= mb_substr( $string, ( $start+$length ), mb_strlen( $string, 'UTF-8' ), 'UTF-8' );
		}
		
		return $result;
	}
}

/*
 * Dummy function to stop PHP warnings if unavailable in the associated server plugin.
 */ 
if ( !function_exists( "sncsa_getAccountsByEntity" ) ) {
	function sncsa_getAccountsByEntity ( $entityId, $networkIdList, $mode, $maxNum ) {
		return array();
	}
}

/*
 * Dummy function to stop PHP warnings if unavailable in the associated server plugin.
 */ 
if ( !function_exists( "sncsa_getAccountsByMember" ) ) {
	function sncsa_getAccountsByMember ( $memberId, $networkIdList, $maxNum ) {
		return array();
	}
}
