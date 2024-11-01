/*******************************************
 * INSTAGRAM SPECIFIC JAVASCRIPT FUNCTIONS *
 *******************************************/

/*
 * Function to render a Login or Logout button for Instagram.
 * 
 * Parameters:
 * - target			: HTML element to hold the Login/Logout button.
 * - loggedInFlag	: Indicates whether or not the User is logged in.
 */
function snc_doInitInstagramLogin ( target, loggedInFlag ) {

	//alert( "path=" + path + ", target=" + target + ", loggedInFlag=" + loggedInFlag );
	
	// Initialize variables
	var returnUrl = window.location.href;
		
	if ( document.getElementById( target ) != null ) {
		if ( loggedInFlag == "Y" ) {
			document.getElementById( target ).innerHTML = "Instagram: <input id=\"sncInLogButton\" type=\"button\" value=\"Logout\" onclick=\"snc_doLogoutInUser('" + returnUrl + "');\" />";
		} else {
			document.getElementById( target ).innerHTML = "Instagram: <input id=\"sncInLogButton\" type=\"button\" value=\"Login\" onclick=\"snc_doLoginInUser('" + returnUrl + "');\" />";
		}
	}
}
