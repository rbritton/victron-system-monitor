<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class Solarcharger_Yield_MaxPower extends DataPath {
	const PATH = '/History/Daily/0/MaxPower';
	const MEASUREMENT = 'yield_maxpower';
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}