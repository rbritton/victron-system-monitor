<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class System_Dc_Battery_ConsumedAmphours extends DataPath {
	const PATH = '/Dc/Battery/ConsumedAmphours';
	const MEASUREMENT = 'dc_battery_consumedamphours';
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}