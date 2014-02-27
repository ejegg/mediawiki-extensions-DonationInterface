<?php

class AdyenDonationData extends BaseDonationData {
	function __construct() {
		$this->transformations = array(
			// These could alternatively be represented as declarative form and fed to the base class:
			//	transformations:
			//		- normalized:
			//			- staged:
			//				skinCode:
			//					config: SkinCode
			//				zip: zip
			//				hppSignature:
			//					callback: AdyenMessageSignatures::calculateHppSignature
			DataTransformer::fromInline( null, 'staged', function( $inData, &$outData ) {
				$outData->set( 'skinCode', $this->config->get( 'SkinCode' ) );
			} ),
			DataTransformer::fromInline( 'normalized', 'staged', function( $inData, &$outData ) {
				$outData->set( 'zip', $inData->get( 'zip' ) );
			} ),
			DataTransformer::fromInline( 'normalized', 'staged', function( $inData, &$outData ) {
				$outData->set( 'billingSignature',
					AdyenMessageSignatures::calculateBillingSignature( $inData, $this->config ) );
			} ),

			// Trixy.  Must be run after the base class normalization to override it,
			// if we were doing anything crazy like a different scaling factor.
			DataTransformer::fromInline( 'response', 'normalized', function( $inData, &$outData ) {
				$outData->set( 'amount', $inData->get( 'amount' ) / 100 );
			} ),

			// This one is purely validation, no setting performed.
			DataTransformer::fromInline( null, 'normalized', function( $inData, &$outData ) {
				$country = $inData->get( 'country' )
				$specialCurrencyValidations = array();
				switch ( $country ) {
				case 'AZ':
					// We have special rules about accepted currencies, in that case
					$specialCurrencyValidations = array(
						'AZR',
						'XHC',
					);
					break;
				default:
				}

				if ( $specialCurrencyValidations ) {
					if ( !in_array( $inData->get( 'currency_code' ), $specialCurrencyValidations ) ) {
						throw new NonnormalizableData( "You have bureaucrats." );
					}
				}
				// else we will be validated by base class logic
			} );
		);
	}
}

/**
 * Base class for managed data
 *
 * Builtin functionality should cover a large proportion of cases.  Inherit and extend
 * this class when you need special handling for a field or group of fields.
 */
class BaseDonationData {
}

class DataConstraint {
}

/**
 * Object which manages a field or group of fields throughout the donation and payment process
 *
 * The data is stored alongside functions used to transform it between forms.
 *
 * These objects may be used 
 */
class ManagedDonationData {
	/**
	 * @var $data LayeredData
	 */
	protected $data;

	/**
	 * @var $transformations array of DataTransformer
	 */
	protected $transformations;

	// TODO
	protected $config;

	/**
	 *	array(
	 *		'GET',
	 *		'POST',
	 *		'SESSION',
	 *		'user',
	 *		'response',
	 *		'staged',
	 *		'normalized',
	 *
	 *
	 *
	 * No thoughts about this yet.  I guess I was imagining we could suck data into normalized form, using whatever is available in any layer.
	 *
	 */
	protected $layerPriorities;
}

class DataTransformer {
	protected $inLayer;
	protected $outLayer;
	protected $func;

	/**
	 * Create a new DataTransformer object from parameters
	 *
	 * The transformer will only be used for the arc described by $inLayer -> $outLayer.
	 *
	 * Note the implication of $func: a DataTransformer can be extended in object-oriented
	 * style by extending the class, or you can get most of the same effects by
	 * using this inline creation method. For example:
	 *
	 *	$zipTransformer = DataTransformer::fromInline(
	 *		'response', 'normalized',
	 *		function( $inData, &$outData ) {
	 *			$outData->set( 'zip', $inData->get( 'zipCode' ) )
	 *		}
	 *	);
	 * This example, of course, would never be necessary because BaseDonationData can
	 * already handle simple mappings using the declarative gateway rules style.
	 *
	 * @param $inLayer string name of the source data layer
	 * @param $outLayer string name of the output layer
	 * @param $func callable
	 *
	 * @return DataTransformer
	 */
	static function fromInline( $inLayer, $outLayer, $func ) {
		$transformer = new DataTransformer();
		$transformer->inLayer = $inLayer;
		$transformer->outLayer = $outLayer;
		$transformer->func = $func;
	}

	/**
	 * @return bool
	 */
	function isMine( $inType, $outType ) {
		return ( ($this->inLayer and $this->inLayer === $inType)
			and ($this->outLayer and $this->outLayer === $outType) );
	}

	/**
	 * Munge data and write results to $dataOut
	 *
	 * Most transformers can safely ignore the type parameters, since the
	 * default behavior of isMine() will be to return false and skip the
	 * transformation if the input and desired output types do not match
	 * $this->inLayer and outLayer.
	 *
	 * Note: PHP does not actually enforce constness on the nonreference
	 * $inData parameter, but, just... don't.
	 *
	 * @param $inData DataSet
	 * @param $outData DataSet
	 *
	 * @param $inType string
	 * @param $outType string
	 */
	function transform( $inData, &$outData, $inType, $outType );
		if ( is_callable( $this->func ) ) {
			$this->func( $inData, &$outData, $inType, $outType );
		} else {
			// FIXME: crap, the stack trace does not expose the identity of this transformer
			throw new Exception( "No dice, your transformer is missing its point." );
		}
	}
}

/**
 * Object to hold data in multiple forms
 */
class LayeredData {
	/**
	 * @var $dataLayers array from layer name to dataset
	 */
	protected $dataLayers;

	/**
	 * Create a layered data object from a single layer.
	 */
	static function fromDataSet( DataSet $data ) {
		$out = new LayeredData();
		$out->dataLayers[$data->getType()] = $data;
		return $out;
	}

	// todo: GET POST SESSION param behavior needs to be regulated by the gateway.  in layers, or direct manipulation?
	//static function fromGPS

	function getLayer( $name ) {
		return $this->dataLayers[$name];
	}
}

/**
 * One layer of data, containing any number of fields
 *
 * There is an assumption that the data is a map at its highest level, but cell
 * contents are opaque.
 */
class DataSet {
	/**
	 * @var $layerName string Data is from this layer.
	 */
	protected $layerName;

	/**
	 * @var $data array simple map
	 */
	protected $data;

	function get( $key ) {
		return $data[$key];
	}

	function set( $key, $value ) {
		// TODO ability to enforce read-onlyness
		$data[$key] = $value;
	}

	/**
	 * Append and overwrite any existing keys
	 *
	 * @param $more DataSet a friend
	 */
	function update( DataSet $more ) {
		if ( $more->layerName !== $this->layerName ) {
			throw new Exception( "Attempted to merge dissimilar types of data" );
		}
		$this->data = $this->data + $more->data;
	}
}
