<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

abstract class DataPath {
	const TYPE_INT = 'int';
	const TYPE_FLOAT = 'float';
	
	const PATH = '/';
	const MEASUREMENT = '';
	const PATHS = [
		'system' => [
			'/SystemState/State' => 'System_SystemState_State',
			
			// AC-related Paths
			'/Ac/ConsumptionOnInput/L1/Power' => 'System_Ac_ConsumptionOnInput_L1_Power',
			'/Ac/Consumption/L1/Power' => 'System_Ac_Consumption_L1_Power',
			'/Ac/Grid/L1/Power' => 'System_Ac_Grid_L1_Power',
			
			// DC-related Paths
			'/Dc/Battery/Current' => 'System_Dc_Battery_Current',
			'/Dc/Battery/Power' => 'System_Dc_Battery_Power',
			'/Dc/Battery/Soc' => 'System_Dc_Battery_Soc',
			'/Dc/Battery/Temperature' => 'System_Dc_Battery_Temperature',
			'/Dc/Battery/TimeToGo' => 'System_Dc_Battery_TimeToGo',
			'/Dc/Battery/Voltage' => 'System_Dc_Battery_Voltage',
			'/Dc/Battery/ConsumedAmphours' => 'System_Dc_Battery_ConsumedAmphours',
			'/Dc/System/Power' => 'System_Dc_System_Power',
		],
		'solarcharger' => [
			'/Pv/V' => 'Solarcharger_Pv_V',
			'/Pv/I' => 'Solarcharger_Pv_I',
			'/Yield/Power' => 'Solarcharger_Yield_Power',
			'/History/Daily/0/MaxPower' => 'Solarcharger_Yield_MaxPower',
		],
		'gps' => [
			'/Position/Longitude' => 'Gps_Position_Longitude',
			'/Position/Latitude' => 'Gps_Position_Latitude',
			'/Altitude' => 'Gps_Altitude',
			'/NrOfSatellites' => 'Gps_NrOfSatellites',
		],
		'vebus' => [
			'/State' => 'System_SystemState_State',
			'/Ac/ActiveIn/L1/V' => 'Vebus_Ac_ActiveIn_L1_V',
			'/Ac/ActiveIn/L1/F' => 'Vebus_Ac_ActiveIn_L1_F',
			'/Ac/Out/L1/V' => 'Vebus_Ac_Out_L1_V',
			'/Ac/Out/L1/F' => 'Vebus_Ac_Out_L1_F',
		],
		'battery' => [
			'/Dc/0/Temperature' => 'System_Dc_Battery_Temperature',
		],
	];
	
	protected $_system = null;
	protected $_service = null;
	protected $_device = 0;
	protected $_cache = false;
	
	/**
	 * @param string $service
	 * @param string $path
	 * @return DataPath|null
	 */
	public static function instance($system, $service, $path, $device = 0, $cache = false) {
		if (isset(self::PATHS[$service])) {
			if (isset(self::PATHS[$service][$path])) {
				$className = '\\App\\DataPaths\\' . self::PATHS[$service][$path];
				if (class_exists($className)) {
					return new $className($system, $service, $device, $cache);
				}
			}
		}
		return null;
	}
	
	protected static function _client() {
		static $_cached = null;
		if ($_cached === null) {
			$_cached = new Client('localhost');
		}
		return $_cached;
	}
	
	protected static function _database() {
		static $_cached = null;
		if ($_cached === null) {
			$_cached = self::_client()->selectDB('monitor');
		}
		return $_cached;
	}
	
	protected function __construct($system, $service, $device = 0, $cache = false) {
		$this->_system = $system;
		$this->_service = $service;
		$this->_device = $device;
		$this->_cache = $cache;
	}
	
	protected static function _cacheKey($system, $service, $device, $path) {
		return "dbus:{$system}:{$service}:{$device}:{$path}";
	}
	
	/**
	 * @param $value
	 * @return null|array The time delta between now and when the previous value was written and the previous value.
	 */
	public function write($value) {
		$previous = $this->_previous($this->_system);
		if (!$this->_cache || self::_floatCompare($previous, $value, 0.01) !== 0) {
			$castValue = $this->_castValue($value);
			$previousTime = (int) Redis::get(self::_cacheKey($this->_system, $this->_service, $this->_device, static::PATH) . ':time');
			$time = time();
			$points = [
				new Point(static::MEASUREMENT, $castValue, ['system' => $this->_system, 'service' => $this->_service, 'device' => $this->_device], [], $time * 1000 + (int) $this->_device),
			];
			if ($this->_validate($castValue)) {
				self::_database()->writePoints($points, Database::PRECISION_MILLISECONDS);
				Redis::set(self::_cacheKey($this->_system, $this->_service, $this->_device, static::PATH), $value, 300);
				Redis::set(self::_cacheKey($this->_system, $this->_service, $this->_device, static::PATH) . ':time', $time, 300);
			}
			return ['delta' => ($previousTime ? ($time - $previousTime) : 0), 'previous' => $previous];
		}
		//return null;
	}
	
	protected function _castValue($value) {
		switch ($this->_type()) {
			case self::TYPE_INT:
				return (int) $value;
			case self::TYPE_FLOAT:
				return (float) $value;
		}
		return $value;
	}
	
	protected function _previous($system) {
		return Redis::get(self::_cacheKey($system, $this->_service, $this->_device, static::PATH));
	}
	
	abstract protected function _type();
	
	protected function _validate($value) {
		return true;
	}
	
	protected static function _floatCompare($a, $b, $delta = 0.0001) {
		if (abs($a - $b) <= $delta) { return 0; }
		else if ($a > $b) { return -1; }
		return 1;
	}
}
