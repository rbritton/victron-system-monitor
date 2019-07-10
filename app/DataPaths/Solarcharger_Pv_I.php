<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class Solarcharger_Pv_I extends DataPath {
	const PATH = '/Pv/I';
	const MEASUREMENT = 'pv_i';
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}