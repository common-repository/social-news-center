/*****************************************
 * TWITTER SPECIFIC JAVASCRIPT FUNCTIONS *
 *****************************************/

/*
 * Function to render a Login or Logout button for Twitter.
 * 
 * Parameters:
 * - target			: HTML element to hold the Login/Logout button.
 * - loggedInFlag	: Indicates whether or not the User is logged in.
 */
function snc_doInitTwitterLogin ( target, loggedInFlag ) {

	//alert( "path=" + path + ", target=" + target + ", loggedInFlag=" + loggedInFlag );
	
	// Initialize variables
	var returnUrl = window.location.href;
		
	if ( document.getElementById( target ) != null ) {
		if ( loggedInFlag == "Y" ) {
			document.getElementById( target ).innerHTML = "Twitter: <input id=\"sncTwLogButton\" type=\"button\" value=\"Logout\" onclick=\"snc_doLogoutTwUser('" + returnUrl + "');\" />";
		} else {
			document.getElementById( target ).innerHTML = "Twitter: <input id=\"sncTwLogButton\" type=\"button\" value=\"Login\" onclick=\"snc_doLoginTwUser('" + returnUrl + "');\" />";
		}
	}
}

!function(d,s,id){
	var js,
		fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';
	if(!d.getElementById(id)){
		js=d.createElement(s);
		js.id=id;
		js.src=p+'://platform.twitter.com/widgets.js';
		fjs.parentNode.insertBefore(js,fjs);
	}
}(document, 'script', 'twitter-wjs');
