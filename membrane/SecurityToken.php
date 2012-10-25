<?php

class SecurityToken {
	/**
	 * token_checkTokens
	 * The main function to check the salted and MD5'd token we should have 
	 * saved and gathered from $wgRequest, against the clear-text token we 
	 * should have saved to the user's session. 
	 * token_getSaltedSessionToken() will start off the process if this is a 
	 * first load, and there's no saved token in the session yet. 
	 * @global Webrequest $wgRequest
	 * @staticvar string $match
	 * @return type 
	 */
	public function token_checkTokens() {
		global $wgRequest;
		static $match = null; //because we only want to do this once per load.

		if ( $match === null ) {
			if ( $this->isCaching() ){
				//This makes sense.
				//If all three conditions for caching are currently true, the 
				//last thing we want to do is screw it up by setting a session 
				//token before the page loads, because sessions break caching. 
				//The API will set the session and form token values immediately 
				//after that first page load, which is all we care about saving 
				//in the cache anyway. 
				return true;
			}

			// establish the edit token to prevent csrf
			$token = $this->token_getSaltedSessionToken();

			$this->log( $this->getAnnoyingOrderIDLogLinePrefix() . ' editToken: ' . $token, LOG_DEBUG );

			// match token			
			if ( !$this->isSomething( 'token' ) ){
				$this->setVal( 'token', $token );				
			}
			$token_check = $this->getVal( 'token' );
			
			$match = $this->token_matchEditToken( $token_check );
			if ( $wgRequest->wasPosted() ) {
				$this->log( $this->getAnnoyingOrderIDLogLinePrefix() . ' Submitted edit token: ' . $this->getVal( 'token' ), LOG_DEBUG );
				$this->log( $this->getAnnoyingOrderIDLogLinePrefix() . ' Token match: ' . ($match ? 'true' : 'false' ), LOG_DEBUG );
			}
		}

		return $match;
	}


	/**
	 * Establish an 'edit' token to help prevent CSRF, etc.
	 *
	 * We use this in place of $wgUser->editToken() b/c currently
	 * $wgUser->editToken() is broken (apparently by design) for
	 * anonymous users.  Using $wgUser->editToken() currently exposes
	 * a security risk for non-authenticated users.  Until this is
	 * resolved in $wgUser, we'll use our own methods for token
	 * handling.
	 * 
	 * Public so the api can get to it. 
	 *
	 * @return string
	 */
	public function token_getSaltedSessionToken() {

		// make sure we have a session open for tracking a CSRF-prevention token
		self::ensureSession();

		$gateway_ident = $this->gatewayID;

		if ( !isset( $_SESSION[$gateway_ident . 'EditToken'] ) ) {
			// generate unsalted token to place in the session
			$token = self::token_generateToken();
			$_SESSION[$gateway_ident . 'EditToken'] = $token;
		} else {
			$token = $_SESSION[$gateway_ident . 'EditToken'];
		}

		return $this->token_applyMD5AndSalt( $token );
	}
	
	/**
	 * token_refreshAllTokenEverything
	 * In the case where we have an expired session (token mismatch), we go 
	 * ahead and fix it for 'em for their next post. We do this by refreshing 
	 * everything that has to do with the edit token.
	 */
	protected function token_refreshAllTokenEverything(){
		$unsalted = self::token_generateToken();	
		$gateway_ident = $this->gatewayID;
		self::ensureSession();
		$_SESSION[$gateway_ident . 'EditToken'] = $unsalted;
		$salted = $this->token_getSaltedSessionToken();
		$this->setVal( 'token', $salted );
	}
	
	/**
	 * token_applyMD5AndSalt
	 * Takes a clear-text token, and returns the MD5'd result of the token plus 
	 * the configured gateway salt.
	 * @param string $clear_token The original, unsalted, unencoded edit token. 
	 * @return string The salted and MD5'd token. 
	 */
	protected function token_applyMD5AndSalt( $clear_token ){
		$salt = $this->getGatewayGlobal( 'Salt' );
		
		if ( is_array( $salt ) ) {
			$salt = implode( "|", $salt );
		}
		
		$salted = md5( $clear_token . $salt ) . EDIT_TOKEN_SUFFIX;
		return $salted;
	}


	/**
	 * token_generateToken
	 * Generate a random string to be used as an edit token. 
	 * @var string $padding A string with which we could pad out the random hex 
	 * further. 
	 * @return string
	 */
	public static function token_generateToken( $padding = '' ) {
		$token = dechex( mt_rand() ) . dechex( mt_rand() );
		return md5( $token . $padding );
	}

	/**
	 * token_matchEditToken
	 * Determine the validity of a token by checking it against the salted 
	 * version of the clear-text token we have already stored in the session. 
	 * On failure, it resets the edit token both in the session and in the form, 
	 * so they will match on the user's next load. 
	 *
	 * @var string $val
	 * @return bool
	 */
	protected function token_matchEditToken( $val ) {
		// fetch a salted version of the session token
		$sessionSaltedToken = $this->token_getSaltedSessionToken();
		if ( $val != $sessionSaltedToken ) {
			wfDebug( "DonationData::matchEditToken: broken session data\n" );
			//and reset the token for next time. 
			$this->token_refreshAllTokenEverything();
		}
		return $val == $sessionSaltedToken;
	}

