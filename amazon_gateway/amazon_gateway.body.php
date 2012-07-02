<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */

/**
 * GlobalCollectGateway
 *
 */
class AmazonGateway extends GatewayForm {

	/**
	 * Constructor - set up the new special page
	 */
	public function __construct() {
		$this->adapter = new AmazonAdapter();
		parent::__construct(); //the next layer up will know who we are.
	}

	/**
	 * Show the special page
	 *
	 * @todo
	 * - Finish error handling
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		global $wgRequest, $wgOut, $wgExtensionAssetsPath;
		$CSSVersion = $this->adapter->getGlobal( 'CSSVersion' );

		$wgOut->allowClickjacking();

		$wgOut->addExtensionStyle(
			$wgExtensionAssetsPath . '/DonationInterface/gateway_forms/css/gateway.css?284' .
			$CSSVersion );

		// Hide unneeded interface elements
		$wgOut->addModules( 'donationInterface.skinOverride' );

		// Make the wiki logo not clickable.
		// @fixme can this be moved into the form generators?
		$js = <<<EOT
<script type="text/javascript">
jQuery(document).ready(function() {
	jQuery("div#p-logo a").attr("href","#");
});
</script>
EOT;
		$wgOut->addHeadItem( 'logolinkoverride', $js );

		$this->setHeaders();

		if ( $wgRequest->getText( 'PaypalRedirect', 0 ) ) {
			$this->amazonRedirect();
			return;
		}

		$this->displayForm();
	}

	protected function amazonRedirect()
	{
		global $wgRequest;
		$amount = $wgRequest->getText("amount");
		$secret_key = $this->adapter->getGlobal("SecretKey");
		$gateway_uri = $this->adapter->getGlobal("URL");

		$params = array(
			"accessKey" => $this->adapter->getGlobal("AccessKey"),
			"amazonPaymentsAccountId" => $this->adapter->getGlobal("PaymentsAccountId"),
			"amount" => $amount,
			"cobrandingStyle" => "logo",
			"collectShippingAddress" => "0",
			"description" => "Donation to Wikimedia Foundation",
			"immediateReturn" => "1",
			"isDonationWidget" => "1",
			"processImmediate" => "1",
			"signatureMethod" => "HmacSHA256",
			"signatureVersion" => "2",
		);

		ksort($params);
		foreach ($params as $key => $value)
		{
			$encoded = str_replace("%7E", "~", rawurlencode($value));
			$query[] = $key . "=" . $encoded;
		}
		$query_str = implode("&", $query);
error_log($query_str);

		$parsed_uri = parse_url($gateway_uri);
		$path_encoded = str_replace("%2F", "/", rawurlencode($parsed_uri['path']));

		$message = "GET\n{$parsed_uri['host']}\n{$path_encoded}\n{$query_str}";

		$signature = rawurlencode(base64_encode(hash_hmac('sha256', $message, $secret_key, TRUE)));

		$this->adapter->storeLimbo();

		header("Location: {$gateway_uri}?{$query_str}&signature={$signature}");
	}
}

// end class
