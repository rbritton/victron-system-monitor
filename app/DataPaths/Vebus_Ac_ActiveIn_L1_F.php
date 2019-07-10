<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class Vebus_Ac_ActiveIn_L1_F extends DataPath {
	const PATH = '/Ac/ActiveIn/L1/F';
	const MEASUREMENT = 'ac_activein_l1_f';
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}