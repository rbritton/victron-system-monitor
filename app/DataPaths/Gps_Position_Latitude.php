<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class Gps_Position_Latitude extends DataPath {
	const PATH = '/Latitude';
	const MEASUREMENT = 'latitude';
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}