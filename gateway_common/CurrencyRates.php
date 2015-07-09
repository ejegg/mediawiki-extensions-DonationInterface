<?php

class CurrencyRates {
	/**
	 * FIXME: script to generate regularly
	 * Supplies rough (not up-to-date) conversion rates for currencies
	 */

	static public $lastUpdated = '2015-07-08';

	static public function getCurrencyRates() {
		// If you change these, make sure to also update any JS validation scripts
		// For now I'm not rounding numbers under 1 because I don't think that's a big issue and could cause issues with the max check.
		$currencyRates = array(
			'ADF' => 5.95,
			'ADP' => 150.87,
			'AED' => 3.67,
			'AFA' => 60.47,
			'AFN' => 60.47,
			'ALL' => 124.74,
			'AMD' => 473.47,
			'ANG' => 1.82,
			'AOA' => 122.35,
			'AON' => 122.35,
			'ARS' => 9.1,
			'ATS' => 12.48,
			'AUD' => 1.35,
			'AWG' => 1.79,
			'AZM' => 3926.19,
			'AZN' => 1.05,
			'BAM' => 1.77,
			'BBD' => 2,
			'BDT' => 76.04,
			'BEF' => 36.58,
			'BGL' => 1.77,
			'BGN' => 1.77,
			'BHD' => 0.37,
			'BIF' => 1599.65,
			'BMD' => 1,
			'BND' => 1.33,
			'BOB' => 6.71,
			'BRL' => 3.2,
			'BSD' => 0.99,
			'BTN' => 63.52,
			'BWP' => 9.89,
			'BYR' => 15378.7,
			'BZD' => 1.96,
			'CAD' => 1.27,
			'CDF' => 912.52,
			'CHF' => 0.95,
			'CLP' => 651.51,
			'CNY' => 6.09,
			'COP' => 2648.2,
			'CRC' => 523.08,
			'CUC' => 1,
			'CUP' => 23.15,
			'CVE' => 99.81,
			'CYP' => 0.53,
			'CZK' => 24.59,
			'DEM' => 1.77,
			'DJF' => 177.72,
			'DKK' => 6.77,
			'DOP' => 44.46,
			'DZD' => 99.04,
			'ECS' => 25588.54,
			'EEK' => 14.19,
			'EGP' => 7.81,
			'ESP' => 150.87,
			'ETB' => 20.5,
			'EUR' => 0.91,
			'FIM' => 5.39,
			'FJD' => 2.11,
			'FKP' => 0.65,
			'FRF' => 5.95,
			'GBP' => 0.65,
			'GEL' => 2.25,
			'GHC' => 37964.2,
			'GHS' => 3.8,
			'GIP' => 0.65,
			'GMD' => 38.45,
			'GNF' => 7251.79,
			'GRD' => 308.97,
			'GTQ' => 7.43,
			'GYD' => 197.27,
			'HKD' => 7.75,
			'HNL' => 21.27,
			'HRK' => 6.87,
			'HTG' => 52.41,
			'HUF' => 287.8,
			'IDR' => 13333.3,
			'IEP' => 0.71,
			'ILS' => 3.79,
			'INR' => 63.39,
			'IQD' => 1141.95,
			'IRR' => 29360,
			'ISK' => 133.46,
			'ITL' => 1755.66,
			'JMD' => 113.71,
			'JOD' => 0.71,
			'JPY' => 121.58,
			'KES' => 98.48,
			'KGS' => 62.13,
			'KHR' => 3995.54,
			'KMF' => 445,
			'KPW' => 135.01,
			'KRW' => 1134.04,
			'KWD' => 0.3,
			'KYD' => 0.82,
			'KZT' => 183.31,
			'LAK' => 7923.05,
			'LBP' => 1480.9,
			'LKR' => 130.3,
			'LRD' => 85,
			'LSL' => 12.5,
			'LTL' => 3.13,
			'LUF' => 36.58,
			'LVL' => 0.64,
			'LYD' => 1.34,
			'MAD' => 9.77,
			'MDL' => 18.71,
			'MGA' => 3252.88,
			'MGF' => 9149.13,
			'MKD' => 55.45,
			'MMK' => 1111.03,
			'MNT' => 1958,
			'MOP' => 7.79,
			'MRO' => 320.47,
			'MTL' => 0.39,
			'MUR' => 34.3,
			'MVR' => 14.97,
			'MWK' => 441.83,
			'MXN' => 15.82,
			'MYR' => 3.8,
			'MZM' => 38000,
			'MZN' => 38,
			'NAD' => 12.5,
			'NGN' => 196.44,
			'NIO' => 26.24,
			'NLG' => 2,
			'NOK' => 8.2,
			'NPR' => 99.74,
			'NZD' => 1.5,
			'OMR' => 0.38,
			'PAB' => 1,
			'PEN' => 3.12,
			'PGK' => 2.68,
			'PHP' => 45.15,
			'PKR' => 100.4,
			'PLN' => 3.83,
			'PTE' => 181.78,
			'PYG' => 5065.03,
			'QAR' => 3.64,
			'ROL' => 40637.3,
			'RON' => 4.06,
			'RSD' => 108.68,
			'RUB' => 57.16,
			'RWF' => 721.7,
			'SAR' => 3.75,
			'SBD' => 7.83,
			'SCR' => 12.33,
			'SDD' => 592.4,
			'SDG' => 5.92,
			'SDP' => 2272.21,
			'SEK' => 8.5,
			'SGD' => 1.35,
			'SHP' => 0.62,
			'SIT' => 217.29,
			'SKK' => 27.32,
			'SLL' => 4158,
			'SOS' => 673,
			'SRD' => 3.24,
			'SRG' => 3240,
			'STD' => 22115.7,
			'SVC' => 8.54,
			'SYP' => 217.65,
			'SZL' => 12.5,
			'THB' => 33.94,
			'TJS' => 6.26,
			'TMM' => 14285.71,
			'TMT' => 2.85,
			'TND' => 1.97,
			'TOP' => 2.16,
			'TRL' => 2687990,
			'TRY' => 2.69,
			'TTD' => 6.25,
			'TWD' => 31.04,
			'TZS' => 2185.91,
			'UAH' => 21.25,
			'UGX' => 3454.24,
			'USD' => 1,
			'UYU' => 26.49,
			'UZS' => 2541,
			'VEB' => 6290.36,
			'VEF' => 6.29,
			'VND' => 21482.8,
			'VUV' => 104.5,
			'WST' => 2.36,
			'XAF' => 595.84,
			'XAG' => 0.07,
			'XAU' => 0,
			'XCD' => 2.69,
			'XEU' => 0.91,
			'XOF' => 595.83,
			'XPD' => 0,
			'XPF' => 108.27,
			'XPT' => 0,
			'YER' => 214.8,
			'YUN' => 108.68,
			'ZAR' => 12.5,
			'ZMK' => 5327.65,
			'ZWD' => 376.36,
		);
		
		return $currencyRates;
	}
}
