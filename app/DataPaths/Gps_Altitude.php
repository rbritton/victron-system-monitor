<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class Gps_Altitude extends DataPath {
	const PATH = '/Altitude';
	const MEASUREMENT = 'altitude';
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}