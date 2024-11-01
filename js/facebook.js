/******************************************
 * FACEBOOK SPECIFIC JAVASCRIPT FUNCTIONS *
 ******************************************/

/*
 * Function to check the result of the user status and display login button if necessary.
 * 
 * Parameters:
 * - response		: Result of the OAuth login dialog.
 */
function snc_doCheckLoginStatus ( response ) {

	var displayStyle = "none",
		displayText = "",
		loginText = "Facebook: <input id=\"sncFbLogButton\" type=\"button\" value=\"Login\" onclick=\"snc_doLoginFbUser();\" />";

	// Check result of Login status
	if ( response && response.status == 'connected' ) {
		//displayStyle = "none";
		displayStyle = "block";
		displayText = "";
		loginText = "Facebook: <input id=\"sncFbLogButton\" type=\"button\" value=\"Logout\" onclick=\"snc_doLogoutFbUser();\" />";
          
		// Now Personalize the User Experience
		//console.log( "Access Token: " + response.authResponse.accessToken );
	} else if ( response && response.status == 'not_authorized' ) {
		displayStyle = "block";
		displayText = "Not authorized for this site so Posts shown may be limited";
	} else if ( document.getElementById( "sncFbLoginArea" ) != null ) { // No point showing this error if user unable to login
		displayStyle = "block";
		displayText = "Not logged into Facebook so Posts shown may be limited";
	}
	
	// Check if a warning message needs displaying
	if ( displayText != "" ) {
		displayText = "<strong>Warning</strong>: " + displayText + ".";
		document.getElementById( "sncMsgTxt" ).innerHTML = displayText;
	}
	
	// Update Login/Logout button
	if ( document.getElementById( "sncFbLoginArea" ) != null ) {
		document.getElementById( "sncFbLoginArea" ).style.display = displayStyle;
		document.getElementById( "sncFbLoginArea" ).innerHTML = loginText;
	}
}

/*
 * Function to Login in the current user via Facebook and ask for email permission.
 */
function snc_doLoginFbUser () {
	FB.login( snc_doCheckLoginStatus(), {scope:'email'} );
	
	// Reload page once User is logged-in
	FB.Event.subscribe( 'auth.login', function() {
		window.location.reload();
	});
}

/*
 * Function to Logout the current user from Facebook and reload page.
 */
function snc_doLogoutFbUser () {
	FB.logout(
		function(response) {
			window.location.reload();
		}
	);
}

(function(d, s, id){
	var js, fjs = d.getElementsByTagName(s)[0];
	if (d.getElementById(id)) {return;}
	js = d.createElement(s); js.id = id;
	js.src = "//connect.facebook.net/en_US/sdk.js";
	fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));
