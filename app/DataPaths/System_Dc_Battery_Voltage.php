<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class System_Dc_Battery_Voltage extends DataPath {
	const PATH = '/Dc/Battery/Voltage';
	const MEASUREMENT = 'dc_battery_voltage';
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}