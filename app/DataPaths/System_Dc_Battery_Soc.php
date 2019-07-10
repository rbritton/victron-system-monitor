<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class System_Dc_Battery_Soc extends DataPath {
	const PATH = '/Dc/Battery/Soc';
	const MEASUREMENT = 'dc_battery_soc';
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}