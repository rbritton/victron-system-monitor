<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class Gps_NrOfSatellites extends DataPath {
	const PATH = '/NrOfSatellites';
	const MEASUREMENT = 'nrofsatellites';
	
	protected function _type() {
		return DataPath::TYPE_INT;
	}
}