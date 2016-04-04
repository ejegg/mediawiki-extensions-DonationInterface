<?php

/**
 * Strip any punctuation from fiscal number before submitting
 */
class FiscalNumber implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		if ( empty( $unstagedData['fiscal_number'] ) ) {
			if ( isset($unstagedData['country']) &&
				$unstagedData['country'] === 'MX' &&
				$adapter->getIdentifier() === 'astropay'
			) {
				// Not validated, but currently required by the AstroPay API
				// Needs to be 13 digits
				// TODO: Remove this when they fix it
				$stagedData['fiscal_number'] = '1111222233334';
			}
		} else {
			$stagedData['fiscal_number'] = preg_replace( '/[^a-zA-Z0-9]/', '', $unstagedData['fiscal_number'] );
		}
	}
}
