<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class Solarcharger_Pv_V extends DataPath {
	const PATH = '/Pv/V';
	const MEASUREMENT = 'pv_v';
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}