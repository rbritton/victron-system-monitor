<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class System_SystemState_State extends DataPath {
	const PATH = '/SystemState/State';
	const MEASUREMENT = 'systemstate_state';
	
	protected function _type() {
		return DataPath::TYPE_INT;
	}
}