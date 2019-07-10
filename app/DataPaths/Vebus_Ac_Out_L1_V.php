<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class Vebus_Ac_Out_L1_V extends DataPath {
	const PATH = '/Ac/Out/L1/V';
	const MEASUREMENT = 'ac_out_l1_v';
	
	protected function _type() {
		return DataPath::TYPE_FLOAT;
	}
}