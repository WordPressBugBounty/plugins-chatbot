<?php
/**
 * Unit Converter Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Unit_Converter_Actions
 */
class Unit_Converter_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'unit_converter';
		$this->group = __( 'Unit Converter', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( empty( $action_id ) ) {
			return false;
		}

		$config = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$value  = isset( $config['value'] ) ? floatval( $this->parse_tokens( $config['value'], $trigger_data ) ) : 0;
		$from   = isset( $config['from'] ) ? $config['from'] : '';
		$to     = isset( $config['to'] ) ? $config['to'] : '';

		switch ( $action_id ) {
			case 'length_conversion':
				return $this->convert_length( $value, $from, $to );
			case 'weight_conversion':
				return $this->convert_weight( $value, $from, $to );
			case 'temperature_conversion':
				return $this->convert_temperature( $value, $from, $to );
			case 'volume_conversion':
				return $this->convert_volume( $value, $from, $to );
			case 'area_conversion':
				return $this->convert_area( $value, $from, $to );
			case 'speed_conversion':
				return $this->convert_speed( $value, $from, $to );
			case 'pressure_conversion':
				return $this->convert_pressure( $value, $from, $to );
			case 'energy_conversion':
				return $this->convert_energy( $value, $from, $to );
			case 'power_conversion':
				return $this->convert_power( $value, $from, $to );
			case 'data_size_conversion':
				return $this->convert_data_size( $value, $from, $to );
			case 'angle_conversion':
				return $this->convert_angle( $value, $from, $to );
			case 'frequency_conversion':
				return $this->convert_frequency( $value, $from, $to );
			case 'fuel_economy_conversion':
				return $this->convert_fuel_economy( $value, $from, $to );
			default:
				return false;
		}
	}

	/**
	 * Length Conversion
	 */
	private function convert_length( $value, $from, $to ) {
		$units = array(
			'mm'  => 0.001,
			'cm'  => 0.01,
			'm'   => 1,
			'km'  => 1000,
			'in'  => 0.0254,
			'ft'  => 0.3048,
			'yd'  => 0.9144,
			'mi'  => 1609.344,
		);

		if ( ! isset( $units[ $from ] ) || ! isset( $units[ $to ] ) ) {
			return array( 'success' => false, 'message' => 'Invalid units.' );
		}

		$result = $value * ( $units[ $from ] / $units[ $to ] );
		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Weight Conversion
	 */
	private function convert_weight( $value, $from, $to ) {
		$units = array(
			'mg'  => 0.000001,
			'g'   => 0.001,
			'kg'  => 1,
			'ton' => 1000,
			'oz'  => 0.0283495,
			'lb'  => 0.453592,
		);

		if ( ! isset( $units[ $from ] ) || ! isset( $units[ $to ] ) ) {
			return array( 'success' => false, 'message' => 'Invalid units.' );
		}

		$result = $value * ( $units[ $from ] / $units[ $to ] );
		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Temperature Conversion
	 */
	private function convert_temperature( $value, $from, $to ) {
		if ( $from === $to ) {
			return array( 'success' => true, 'result' => $value );
		}

		// Convert to Celsius first
		$celsius = 0;
		if ( $from === 'c' ) {
			$celsius = $value;
		} elseif ( $from === 'f' ) {
			$celsius = ( $value - 32 ) * 5 / 9;
		} elseif ( $from === 'k' ) {
			$celsius = $value - 273.15;
		}

		// Convert from Celsius to target
		$result = 0;
		if ( $to === 'c' ) {
			$result = $celsius;
		} elseif ( $to === 'f' ) {
			$result = ( $celsius * 9 / 5 ) + 32;
		} elseif ( $to === 'k' ) {
			$result = $celsius + 273.15;
		}

		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Volume Conversion
	 */
	private function convert_volume( $value, $from, $to ) {
		$units = array(
			'ml'   => 0.001,
			'l'    => 1,
			'gal'  => 3.78541,
			'floz' => 0.0295735,
			'cup'  => 0.236588,
			'pt'   => 0.473176,
			'qt'   => 0.946353,
		);

		if ( ! isset( $units[ $from ] ) || ! isset( $units[ $to ] ) ) {
			return array( 'success' => false, 'message' => 'Invalid units.' );
		}

		$result = $value * ( $units[ $from ] / $units[ $to ] );
		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Area Conversion
	 */
	private function convert_area( $value, $from, $to ) {
		$units = array(
			'sqm'  => 1,
			'sqft' => 0.092903,
			'ac'   => 4046.86,
			'ha'   => 10000,
			'sqkm' => 1000000,
			'sqmi' => 2589988.11,
		);

		if ( ! isset( $units[ $from ] ) || ! isset( $units[ $to ] ) ) {
			return array( 'success' => false, 'message' => 'Invalid units.' );
		}

		$result = $value * ( $units[ $from ] / $units[ $to ] );
		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Speed Conversion
	 */
	private function convert_speed( $value, $from, $to ) {
		$units = array(
			'kmh'  => 1 / 3.6,
			'mph'  => 0.44704,
			'ms'   => 1,
			'knot' => 0.514444,
			'fts'  => 0.3048,
		);

		if ( ! isset( $units[ $from ] ) || ! isset( $units[ $to ] ) ) {
			return array( 'success' => false, 'message' => 'Invalid units.' );
		}

		$result = $value * ( $units[ $from ] / $units[ $to ] );
		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Pressure Conversion
	 */
	private function convert_pressure( $value, $from, $to ) {
		$units = array(
			'pa'  => 1,
			'bar' => 100000,
			'psi' => 6894.76,
			'atm' => 101325,
			'torr'=> 133.322,
		);

		if ( ! isset( $units[ $from ] ) || ! isset( $units[ $to ] ) ) {
			return array( 'success' => false, 'message' => 'Invalid units.' );
		}

		$result = $value * ( $units[ $from ] / $units[ $to ] );
		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Energy Conversion
	 */
	private function convert_energy( $value, $from, $to ) {
		$units = array(
			'j'   => 1,
			'cal' => 4.184,
			'kwh' => 3600000,
			'btu' => 1055.06,
			'ev'  => 1.60218e-19,
		);

		if ( ! isset( $units[ $from ] ) || ! isset( $units[ $to ] ) ) {
			return array( 'success' => false, 'message' => 'Invalid units.' );
		}

		$result = $value * ( $units[ $from ] / $units[ $to ] );
		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Power Conversion
	 */
	private function convert_power( $value, $from, $to ) {
		$units = array(
			'w'   => 1,
			'kw'  => 1000,
			'hp'  => 745.7,
			'btuh'=> 0.293071,
		);

		if ( ! isset( $units[ $from ] ) || ! isset( $units[ $to ] ) ) {
			return array( 'success' => false, 'message' => 'Invalid units.' );
		}

		$result = $value * ( $units[ $from ] / $units[ $to ] );
		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Data Size Conversion
	 */
	private function convert_data_size( $value, $from, $to ) {
		$units = array(
			'bit' => 0.125,
			'b'   => 1,
			'kb'  => 1024,
			'mb'  => pow( 1024, 2 ),
			'gb'  => pow( 1024, 3 ),
			'tb'  => pow( 1024, 4 ),
			'pb'  => pow( 1024, 5 ),
		);

		if ( ! isset( $units[ $from ] ) || ! isset( $units[ $to ] ) ) {
			return array( 'success' => false, 'message' => 'Invalid units.' );
		}

		$result = $value * ( $units[ $from ] / $units[ $to ] );
		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Angle Conversion
	 */
	private function convert_angle( $value, $from, $to ) {
		$units = array(
			'deg' => 1,
			'rad' => 180 / M_PI,
			'grad'=> 0.9,
			'min' => 1 / 60,
			'sec' => 1 / 3600,
		);

		if ( ! isset( $units[ $from ] ) || ! isset( $units[ $to ] ) ) {
			return array( 'success' => false, 'message' => 'Invalid units.' );
		}

		$result = $value * ( $units[ $from ] / $units[ $to ] );
		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Frequency Conversion
	 */
	private function convert_frequency( $value, $from, $to ) {
		$units = array(
			'hz'  => 1,
			'khz' => 1000,
			'mhz' => 1000000,
			'ghz' => 1000000000,
			'rpm' => 1 / 60,
		);

		if ( ! isset( $units[ $from ] ) || ! isset( $units[ $to ] ) ) {
			return array( 'success' => false, 'message' => 'Invalid units.' );
		}

		$result = $value * ( $units[ $from ] / $units[ $to ] );
		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}

	/**
	 * Fuel Economy Conversion
	 */
	private function convert_fuel_economy( $value, $from, $to ) {
		if ( $from === $to ) {
			return array( 'success' => true, 'result' => $value );
		}

		// Specialized conversion because L/100km is inverse
		// Convert everything to MPG first
		$mpg = 0;
		if ( $from === 'mpg' ) {
			$mpg = $value;
		} elseif ( $from === 'l100' ) {
			$mpg = 235.215 / $value;
		} elseif ( $from === 'kml' ) {
			$mpg = $value * 2.35215;
		} elseif ( $from === 'mpg_imp' ) {
			$mpg = $value / 1.20095;
		}

		// Convert from MPG to target
		$result = 0;
		if ( $to === 'mpg' ) {
			$result = $mpg;
		} elseif ( $to === 'l100' ) {
			$result = 235.215 / $mpg;
		} elseif ( $to === 'kml' ) {
			$result = $mpg / 2.35215;
		} elseif ( $to === 'mpg_imp' ) {
			$result = $mpg * 1.20095;
		}

		return array( 'success' => true, 'result' => $result, 'message' => 'Converted successfully.' );
	}
}
