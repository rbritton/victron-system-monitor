<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class System_Ac_Grid_L1_Power extends DataPath {
	const PATH = '/Ac/Grid/L1/Power';
	const MEASUREMENT = 'ac_grid_l1_power';
	
	protected function _type() {
		return DataPath::TYPE_INT;
	}
}