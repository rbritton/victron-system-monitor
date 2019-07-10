<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class System_Ac_Consumption_L1_Power extends DataPath {
	const PATH = '/Ac/Consumption/L1/Power';
	const MEASUREMENT = 'ac_consumption_l1_power';
	const CUMULATOR = 'cumulative_ac_consumption_l1_power';
	const COMBINATOR = 'combined_power_consumption';
	
	public function write($value) {
		$previous = parent::write($value);
		if ($previous) {
			$cumulativeValue = $previous['previous'] * $previous['delta'] / 3600;
			if ($cumulativeValue) {
				$points = [
					new Point(static::CUMULATOR, $cumulativeValue, ['system' => $this->_system, 'service' => $this->_service, 'device' => $this->_device], [], time()),
				];
				self::_database()->writePoints($points, Database::PRECISION_SECONDS);
				
				$points = [
					new Point(static::COMBINATOR, $cumulativeValue, ['system' => $this->_system, 'service' => $this->_service, 'device' => $this->_device], [], time() * 1000 + 100),
				];
				self::_database()->writePoints($points, Database::PRECISION_MILLISECONDS);
			}
		}
	}
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}