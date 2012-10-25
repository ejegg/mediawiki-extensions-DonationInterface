	
	/**
	 * wmfMessageExists returns true if a translatable message has been defined 
	 * for the string and language that have been passed in, false if none is 
	 * present. 
	 * @param string $msg_key The message string to look up.
	 * @param string $language A valid mediawiki language code.
	 * @return boolean - true if message exists, otherwise false.
	 */
	public static function wmfMessageExists( $msg_key, $language ){
		$language = strtolower( $language );
		if ( wfMessage( $msg_key )->inLanguage( $language )->exists() ){
			# if we are looking for English, we already know the answer
			if ( $language == 'en' ){
				return true;
			}

			# get the english version of the message
			$msg_en = wfMessage( $msg_key )->inLanguage( 'en' )->text();
			# attempt to get the message in the specified language
			$msg_lang = wfMessage( $msg_key )->inLanguage( $language )->text();

			# if the messages are the same, the message fellback to English, return false
			return strcmp( $msg_en, $msg_lang ) != 0;
		}
		return false;
	}

	/**
	 * wfLangSpecificFallback - returns the text of the first existant message
	 * in the requested language. If no messages are found in that language, the
	 * function returns the first existant fallback message.
	 *
	 * @param $language the requested language
	 * @return String the text of the first existant message
	 * @throws MWException if no message keys are specified
	 */
	public static function wfLangSpecificFallback( $language='en', $msg_keys=array() ){

		if ( count( $msg_keys ) < 1 ){
			throw new MWException( __FUNCTION__ . " BAD PROGRAMMER. No message keys given." );
		}

		# look for the first message that exists
		foreach ( $msg_keys as $m ){
			if ( self::wmfMessageExists( $m, $language) ){
				return wfMessage( $m )->inLanguage( $language )->text();
			}
		}

		# we found nothing in the requested language, return the first fallback message that exists
		foreach ( $msg_keys as $m ){
			if ( wfMessage( $m )->inLanguage( $language )->exists() ){
				return wfMessage( $m )->inLanguage( $language )->text();
			}
		}

		# somehow we still don't have a message, return a default error message
		return wfMessage( $msg_keys[0] )->text();
	}
