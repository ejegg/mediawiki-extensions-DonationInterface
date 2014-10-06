<?php

class WmfFramework {
	static function debugLog( $identifier, $msg ) {
		wfDebugLog( $identifier, $msg );
	}

	static function getIP() {
		global $wgRequest;
		return $wgRequest->getIP();
	}

	static function getHostname() {
		return wfHostname();
	}

	static function formatMessage($message_identifier /*, ... */ ) {
		return call_user_func_array( 'wfMessage', func_get_args() )->text();
	}

	static function runHooks($func, $args) {
		return wfRunHooks($func, $args);
	}

	static function getLanguageCode() {
		global $wgLang;
		return $wgLang->getCode();
	}

	static function isUseSquid() {
		global $wgUseSquid;
		return $wgUseSquid;
	}

	static function setupSession($sessionId=false) {
		wfSetupSession();
	}

	static function validateIP($ip) {
		return IP::isValid( $ip );
	}

	static function isValidBuiltInLanguageCode( $code ) {
		return Language::isValidBuiltInCode( $code );
	}

	static function validateEmail( $email ) {
		return Sanitizer::validateEmail( $email );
	}

	/**
	 * wmfMessageExists returns true if a translatable message has been defined
	 * for the string and language that have been passed in, false if none is
	 * present.
	 * @param string $msg_key The message string to look up.
	 * @param string $language A valid mediawiki language code.
	 * @return boolean - true if message exists, otherwise false.
	 */
	public static function messageExists( $msg_key, $language ) {
		return wfMessage( $msg_key )->inLanguage( $language )->exists();
	}

	static function getUserAgent() {
		return Http::userAgent();
	}

	static function isPosted() {
		global $wgRequest;
		$wgRequest->wasPosted();
	}
}
