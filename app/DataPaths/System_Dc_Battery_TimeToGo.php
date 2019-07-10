<?php

namespace App\DataPaths;

use App\DataPath;
use Illuminate\Support\Facades\Redis;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Point;

class System_Dc_Battery_TimeToGo extends DataPath {
	const PATH = '/Dc/Battery/TimeToGo';
	const MEASUREMENT = 'dc_battery_timetogo';
	
	protected function _type() {
		return DataPath::TYPE_INT;
	}
	
	protected function _validate($value) {
		return ($value > 0); //This measurement sometimes erroneously returns 0, so we just discard those. A true 0 would mean there's no power to even run the system.
	}
}