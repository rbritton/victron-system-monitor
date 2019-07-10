<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class Solarcharger_Yield_Power extends DataPath {
	const PATH = '/Yield/Power';
	const MEASUREMENT = 'yield_power';
	const CUMULATOR = 'cumulative_yield_power';
	
	public function write($value) {
		$previous = parent::write($value);
		if ($previous) {
			$cumulativeValue = $previous['previous'] * $previous['delta'] / 3600;
			if ($cumulativeValue) {
				$points = [
					new Point(static::CUMULATOR, $cumulativeValue, ['system' => $this->_system, 'service' => $this->_service, 'device' => $this->_device], [], time()),
				];
				self::_database()->writePoints($points, Database::PRECISION_SECONDS);
			}
		}
	}
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}