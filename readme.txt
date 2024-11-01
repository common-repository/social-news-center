=== Social News Center ===
Contributors: pgowling
Tags: social media, social networks, social posts, social news, facebook, instagram, twitter, display photos, display posts, display tweets
Requires at least: 4.0
Tested up to: 4.8
Stable tag: 0.0.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Display latest Posts from social media sites like Facebook, Instagram & Twitter. Perform actions such as view profiles, Like, Share, Favorite & Retweet.

== Description ==

Social News Center plugin displays the latest posts from social media sites such as Facebook, Instagram & Twitter for specified pages & accounts. Page and account profiles can also be viewed together with support for performing actions such as Like, Share, Favorite, Retweet and more.

Posts are displayed in item boxes which are sorted in date descending order based upon when they were posted, shared, or re-posted. The boxes are dynamically laid out within the web page based upon the width of the browser or section of the web page. The layout works on PC's, tablets and smartphones alike automatically adjusting to the browser or screensize. Each item box contains the following information about a post:

* Header:
 * Profile image for the social media account from which the post is being displayed.
 * Name of social media account which displays a popup profile if clicked.
 * Username of social media account which is also clickable (Instagram & Twitter only).
 * Social media network icon.
* Body:
 * Photo if available which displays a larger image or content (e.g. video) if clicked.
 * Textual post content with clickable links (e.g. Twitter hashtags display related posts in a popup).
* Footer:
 * Time when posted (i.e. the second, minutes, hours, days, months, or years ago).
 * Favorite or Like button with latest count.
 * 'view post' link (Facebook & Instagram only) which displays the full post in a pop-up window or a Reply button (Twitter only).
 * Share or Retweet button with latest count.

Note: most buttons within the Footer will show if they have already been used by the current user assuming this information is made available from the relevant social media network.

This plugin caches results from calls to social media networks in order to improve overall page load times and to better utilize the quotas placed on developer API keys. When a web site user logs in the plugin will where possible use the user's quota leaving the development quota as backup for those who do not login.

== Installation ==

This section describes how to install this plugin and get it working.

Steps:

1. Log into WordPress and access 'Plugins -> Add New' menu option via Dashboard.
2. Upload file 'social-news-center.zip' via 'Upload Plugins' menu option.
3. Activate the plugin through the 'Plugins' menu.
4. Configure plugin settings via 'Social News Center' menu option within Dashboard. Each social media network will need configuring to access the relevant API (see 'Admin Settings' section within 'readme.txt' file).
5. Add `[sncSocialMediaPosts]` shortcode on Page(s) and/or Post(s) on which social media posts are to be displayed.

== Frequently Asked Questions ==

= Which social media networks are supported? =

Currently Facebook, Instagram and Twitter are supported with more being added in due course.

= Why should users login to social media networks? =

Some Facebook pages and Instagram & Twitter accounts have restricted content which will not be displayed unless site visitors are logged into those respective social media networks.

Note: login is required via the Social News Center 'login' buttons i.e. being logged into the respective social media network within the same browser is not sufficient. 

= Does the plugin display posts from any social media accounts? =

Posts can be displayed from your personal accounts, your organization's accounts, or any unprotected/public accounts on the respective social media networks.

== Screenshots ==

1. Page showing Facebook, Instagram and Twitter posts using the 'neutral' layout style. Individual boxes will dynamically rearrange according to the width of the browser or if the web page is viewed on a tablet or smartphone. Note: Login & Logout buttons default to the style of the installed WordPress theme.
2. Image displayed within a popup after clicking on the thumbnail or link within a post. Click the 'X' or background to close the popup, also pressing the ESC key will close the popup.
3. Video media (e.g. Amazon Video, YouTube etc) are playable within a popup after clicking on the link.
4. Tweets from Twitter displayed within a popup after clicking on a #HashTag link.
5. An individual Facebook post displayed together with some of the latest comments within a popup after clicking the 'view post' link.
6. A Facebook profile displayed within a popup after clicking on the account name in the header of a post.
7. An individual Instagram post displayed together with some of the latest comments within a popup after clicking the 'view post' link.
8. An Instagram profile displayed within a popup after clicking on the account name in the header of a post.
9. A Twitter profile displayed within a popup after clicking on the account name or username in the header of a post.
10. Adding the shortcode for the plugin to a WordPress page using the 'Text' mode to avoid HTML formatting being added and causing potential problems.
11. Editing the General settings section for the plugin. This screen is accessible via the Dashboard menu option 'Social News Center'.
12. Editing the Facebook & Instagram settings for the plugin. This screen is accessible via the Dashboard menu option 'Social News Center'.
13. Editing the Twitter settings for the plugin. This screen is accessible via the Dashboard menu option 'Social News Center'.
14. Page displaying Facebook, Instagram and Twitter posts using the 'white' layout style.
15. Page displaying Facebook, Instagram and Twitter posts using the 'black' layout style.
16. Page displaying Facebook, Instagram and Twitter posts using the 'red' layout style.
17. Page displaying Facebook, Instagram and Twitter posts using the 'green' layout style.
18. Page displaying Facebook, Instagram and Twitter posts using the 'blue' layout style.

== Changelog ==

= 0.0.8 =

* Ensure compatibility with WordPress V4.8.

= 0.0.7 =

* Ensure compatibility with WordPress V4.7.4.

= 0.0.6 =

* Ensure compatibility with WordPress V4.7.3.
* Remove unnecessary "require_once" statements for 'includes/config.php' & 'includes/functions.php' in 'facebook.php', 'instagram.php' and 'twitter.php'. These had been causing PHP errors in some instances.

= 0.0.5 =

* Ensure compatibility with WordPress V4.7.1.
* Addition of support for Instagram social network including relevant fields within Dashboard settings page and support within user-facing or client-side plugin. Note that user Login button is hidden until needed in future versions.
* Rename cookie 'snc_access_cookie' to 'snc_twitter_access_cookie' to allow additional social networks using OAuth authentication.
* Replace the ability to 'Like' Facebook posts with a non-clickable icon due to Facebook changing this functionality to only being able to like pages. Pages can still be liked when viewing a profile via the popup.
* Removal of 'liked' icon and associated count against list of Facebook comments within View Post popup due to Facebook no longer providing this information in the API.
* Display non-clickable 'Share' icon if Facebook post has already been shared due to Facebook changing how this functionality works.
* Minor bug fixes including layout of Login/Logout buttons spacing to makeway for Instagram support.

= 0.0.4 =

* Upgrade Facebook Graph API call to latest V2.7 in function snc_getFacebookPosts() within 'social-news-center.php'.
* Ensure compatibility with WordPress V4.6.1.
* Fix JavaScript error "SyntaxError: JSON.parse: unexpected end of data at line 1 column 1 of the JSON data" caused by characters that were non UTF-8 being returned from AJAX call to function snc_getFacebookPosts_callback() within 'social-news-center.php'.
* Other minor bug fixes.

= 0.0.3 =

* Minor bug fixes and ensure compatibility with WordPress V4.6.

= 0.0.2 =

* Fix to SQL update in function snc_setSiteCredentials() within 'includes/functions-wp.php' concerning the updating of site credentials. Resolves some Twitter login problems.

= 0.0.1 =

* Initial beta release.

== Upgrade Notice ==

None.

== Troubleshooting ==

= Parameters are specified within the shortcode but no posts are being displayed =

* Check that the parameters are enclosed within standard quotes and not fancy alternatives which some document editors can use e.g.
 * [sncSocialMediaPosts facebook="/Beer2Infinity" instagram="@wotvine" twitter="@beer2infinity"]

* Check that multiple accounts specified for a social media network are separated by semicolons (;) e.g.
 * [sncSocialMediaPosts facebook="/Beer2Infinity;/pgowling;/WOTvine"]

* Check that social media account names are prefixed with a '/' for Facebook, '@' for Instagram and '@' for Twitter e.g.
 * [sncSocialMediaPosts facebook="/Beer2Infinity" instagram="@wotvine" twitter="@beer2infinity"]

= Potential Conflicts =

* "LightBoxes" - there can be potential conflicts between popups with this plugin and other plugins or themes that use LightBox or similar functionality e.g. clicking on an image can trigger two popups to overlay each other. Resolution is to disable LightBox or popup functionlity within one of the plugins (if possible) or deactivate the conflicting plugin and if needed find an alternative.

= Instagram 'Login' button does not work within Dashboard Admin settings page =

* Force reload of page using SHIFT + Refresh so that the Javascript script cached by your browser is updated.

== Admin settings ==

Accessible via the 'Social Media Center' menu on the left side within the admin Dashboard.

= General =

* "Posts per Account (main)" - maximum number of Posts displayed per social media Account (or Page) on the main screen i.e. WordPress Page or Post.
* "Posts per Account (popup)" - maximum number of Posts displayed per social media Account (or Page) on a popup e.g. when a Twitter hashtag (#abc) is clicked on.
* "Maximum posts displayed" - maximum number of Posts displayed on a WordPress Page or Post for all social Media Accounts (or Pages).
* "Cache limit (minutes)" - time for a cached item to expire i.e. the social media network will be requeried to update the specified item at this point.
* "Cache reset (minutes)" - time for an item to be reset or deleted from the cache. This value must be a greater value than the Cache limit in order to have an effect.
* "Cookie timeout (seconds)" - cookies will expire after this amount of time such as Filter settings (Country, State, City) e.g. 86400 = 60 secs x 60 mins x 24 hrs.
* "Layout style" - color scheme used to display the item boxes containing Posts. Basic version of the plugin includes White, Neutral, Black, Red, Green, and Blue.

= Facebook =

* "Status" - to Enable or Disable the displaying of Facebook Posts.
* "App Id" - FACEBOOK_APP_ID setting to access the Facebook API
 * e.g. 667778899223981.
* "Secret" - FACEBOOK_SECRET setting to access the Facebook API
 * e.g. abc9d4563232a12345dd6f321ab99778.

	"App Id" & "Secret" settings can be created or retrieved via https://developers.facebook.com/apps

= Instagram =

* "Status" - to Enable or Disable the displaying of Instagram Posts.
* "Client Id" - 'Client ID' setting to access the Instagram API
 * e.g. 29ab5bd42e8g123dd88fa5bc3456dd4a.
* "Client Secret" - 'Client Secret' setting to access the Instagram API
 * e.g. def3a1234567b54321ee4d222ed98765.
* "Access Token" - 'Access Token' setting to access the Instagram API, generated by clicking the 'Generate' button
 * e.g. 2323318765.45ee4bd.ddabc1665c12349d654d443a4dfd2d6b.
* "Access Token validity" - 'Access Token validity' setting to show whether the 'Access Token' is 'valid' or 'invalid', generated as a result of clicking the 'Generate' button
 * e.g. valid.

	"Client Id" & "Client Secret" settings can be created or retrieved via https://www.instagram.com/developer

	Note: Field 'Valid redirect URIs' under 'Security' tab needs setting to include the following two URIs whilst replacing '<DOMAIN NAME>' with your web site domain name e.g. www.mywebsite.com, if the web site is in a sub-folder then include this too e.g. www.mywebsite.com/wordpress
	
		http://<DOMAIN NAME>/wp-admin/admin-ajax.php?action=snc_get_instagram_code
		
			It is important to use 'https' rather than 'http' in the above URI if the admin area of your WordPress installation is SSL protected. The port number may also need including e.g. ':443'.
	
		http://<DOMAIN NAME>/wp-admin/admin-ajax.php?action=snc_instagram_oauth

			It is important to use 'https' rather than 'http' in the above URI if the user facing pages of your WordPress installation are SSL protected. The port number may also need including e.g. ':443', in most web sites SSL is not used for performance purposes.

			Additionally 'Client Status' can be left as 'Sandbox Mode' within Instagram Developer.

= Twitter =

* "Status" - to Enable or Disable the displaying of Twitter Posts.
* "Consumer Key" - 'consumer_key' setting to access the Twitter API
 * e.g. WD6SoDad6dsKO2FIkjghf.
* "Consumer Secret" - 'consumer_secret' setting to access the Twitter API
 * e.g. FdLSWdD6RAufrmFjuyhjfm7AFvt4dr9FRvghD1L5M.
* "OAuth Access Token" - 'oauth_access_token' setting to access the Twitter API
 * e.g. 879666148-uWWoWSdsG6VKNXdWaQSc8NnVGHrdCo34xOrRuNbl.
* "OAuth Access Token Secret" - 'oauth_access_token_secret' setting to access the Twitter API
 * e.g. KJHmPdTsw8JTSWDArQWl2ERrsBmyLKbPV62cxS54DB.
* "Replies" - to Include or Exclude replies to posts.
* "Retweets" - to Include or Exclude retweets of posts.

	"consumer_key", "consumer_secret", "oauth_access_token" & "oauth_access_token_secret" settings can be created or retrieved via https://apps.twitter.com
	
== Shortcode Options ==

* Place the following shortcode on any Page or Post within your WordPress website:
 * [sncSocialMediaPosts]

	(Note that one or more social media networks with account(s) or profile(s) need specifying in order for posts to be displayed)

* To display posts from the 'Beer Infinity' Facebook page:
 * [sncSocialMediaPosts facebook="/Beer2Infinity"]
  	
	This will show a Facebook Login button too and is the same as the following:
 * [sncSocialMediaPosts facebook="/Beer2Infinity" header="Y"]

* Login/logout buttons can be suppressed as follows:
 * [sncSocialMediaPosts facebook="/Beer2Infinity" header="N"]

	(Note the need to preceed the Facebook account name with a '/')

* To display posts from the 'Beer Infinity' Facebook, Instagram & Twitter pages:
 * [sncSocialMediaPosts facebook="/Beer2Infinity" instagram="@beer2infinity" twitter="@beer2infinity"]

	(Note the need to preceed the Instagram & Twitter account names with an '@')

* To display posts from multiple Facebook, Instagram & Twitter pages:
 * [sncSocialMediaPosts facebook="/Beer2Infinity;/pgowling" instagram="@placeboworld;@thewho" twitter="@beer2infinity;@pgowling"]

	(Note the need to separate each Facebook, Instagram & Twitter Account names with a ';')

== Additional Info ==

This section includes some points to note about the behaviour of social media posts being returned by this plugin:

* Actions - some actions performed on posts will not always be displayed immediately e.g. clicking Favoriting or Retweeting a Twitter post displayed by the Social News Center plugin. These actions will be displayed once the current cached item has expired or the cache has been manually emptied within the settings page.

* Caching - in certain situations it is possible for protected posts to be seen after a user has logged out of a social media network due to the results still being cached. Once the cached items have expired then this will no longer be the case. Note: this does not mean that a user can see posts that they did not previously have access to!

* Login/logout - there are differences between different social media networks with how Login/Logout works e.g.

 * Facebook - if login/logout is carried out directly within the Facebook website then the Social News Center plugin will automatically pick this up when the page is refreshed or alternatively it is possible to login/logout of Facebook within the plugin.

 * Instagram - login/logout on the Instagram website is not detected by the Social News Center plugin and that an additional Authorization is required within the plugin via the 'Login' button.

 * Twitter - login/logout on the Twitter website is not detected by the Social News Center plugin and that an additional Authorization is required within the plugin via the 'Login' button.

* Partial results - please note that the social network API's used by this plugin do not provide access to all posts and data e.g. Twitter quotes "Please note that Twitter’s search service and, by extension, the Search API is not meant to be an exhaustive source of Tweets. Not all Tweets will be indexed or made available via the search interface.".

* Posts per Account - changing this setting can sometimes not have an immediate effect due to results being cached. Reducing the value is impacted less than increasing the value as only the cached results will be displayed until it either expires or is manually emptied via the settings page.
 
* Shortcode - the WordPress shortcode placed with Pages or Posts should only be edited in 'Text' mode as 'Visual' mode can add extra HTML coding that stops affects the shortcode syntax.

* Update delays - sometimes there can be delays in posts, comments and actions becoming available through API's which can vary between social media networks and times of the day.
