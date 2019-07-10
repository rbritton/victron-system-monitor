<?php
namespace App;

use PhpParser\Node\Expr\AssignOp\Mod;

class ModbusRegister
{
	const TYPE_INT16 = 's'; //`pack` doesn't have a big endian signed short, but since we're running this on a big endian platform, we can use the machine order one
	const TYPE_UINT16 = 'n';
	const TYPE_INT32 = 'l'; //`pack` doesn't have a big endian signed long, but since we're running this on a big endian platform, we can use the machine order one
	const TYPE_UINT32 = 'N';
	const TYPE_STRING = ' ';
	
	const UNITS_NONE = '';
	const UNITS_AC_V = 'V AC';
	const UNITS_AC_A = 'A AC';
	const UNITS_DC_V = 'V DC';
	const UNITS_DC_A = 'A DC';
	const UNITS_A = 'A';
	const UNITS_VA = 'VA';
	const UNITS_W = 'W';
	const UNITS_AH = 'Ah';
	const UNITS_KWH = 'kWh';
	const UNITS_HZ = 'Hz';
	const UNITS_PERCENT = '%';
	const UNITS_CELSIUS = 'C';
	const UNITS_SECONDS = 's';
	const UNITS_RPM = 'RPM';
	const UNITS_DEGREES = 'deg';
	const UNITS_MPS = 'm/s';
	const UNITS_M3 = 'm3';
	
	protected $_template;
	protected $_value;
	
	public static function parse($data, $start, $count) {
		if (!defined('ENV_BIG_ENDIAN')) {
			list($endiantest) = array_values(unpack('L1L', pack('V', 1)));
			define('ENV_BIG_ENDIAN', ($endiantest !== 1));
			if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) { echo 'Environment: ' . (ENV_BIG_ENDIAN ? 'Big Endian' : 'Little Endian') . "\n"; }
		}
		
		$map = self::map();
		$registers = [];
		for ($i = $start; $i < $start + $count; $i++) {
			if (!isset($map[$i])) { continue; }
			
			$length = 0;
			$flip = false;
			switch ($map[$i]['type']) {
				case self::TYPE_INT16:
					$flip = !ENV_BIG_ENDIAN;
					$length = 2;
					break;
				case self::TYPE_UINT16:
					$length = 2;
					break;
				case self::TYPE_INT32:
					$flip = !ENV_BIG_ENDIAN;
					$length = 4;
					break;
				case self::TYPE_UINT32:
					$length = 4;
					break;
				case self::TYPE_STRING:
					$length = $map[$i]['length'] * 2; //2 bytes per register, length refers to the number of registers spanned
					break;
			}
			if ($length == 0) { continue; }
			if ($length > strlen($data)) { return false; }
			
			$value = substr($data, 0, $length);
			if ($flip) {
				$value = strrev($value);
			}
			
			$data = substr($data, $length);
			if ($map[$i]['type'] != self::TYPE_STRING) {
				list($value) = array_values(unpack($map[$i]['type'], $value));
			}
			$registers[$i] = new ModbusRegister($map[$i], $value);
		}
		return $registers;
	}
	
	public function __construct($template, $value) {
		$this->_template = $template;
		$this->_value = $value;
	}
	
	public function __get($key) {
		switch ($key) {
			case 'service':
				return $this->_template['service'];
			case 'register':
				return $this->_template['register'];
			case 'path':
				return $this->_template['path'];
			case 'writable':
				return $this->_template['writable'];
			case 'value':
				if ($this->_template['type'] == self::TYPE_STRING) {
					return $this->_value;
				}
				return $this->_value / $this->_template['scale'];
			case 'unit':
				return $this->_template['unit'];
		}
		
		throw new \OutOfBoundsException('Unsupported key: ' . $key);
	}
	
	public function __toString() {
		return "({$this->service}) {$this->path}: {$this->value} {$this->unit}"; 
	}
	
	public static function length($start, $count) {
		$map = self::map();
		$total = 0;
		for ($i = $start; $i < $start + $count; $i++) {
			if (!isset($map[$i])) { continue; }
			
			$length = 0;
			switch ($map[$i]['type']) {
				case self::TYPE_INT16:
				case self::TYPE_UINT16:
					$length = 2;
					break;
				case self::TYPE_INT32:
				case self::TYPE_UINT32:
					$length = 4;
					break;
				case self::TYPE_STRING:
					$length = $map[$i]['length'] * 2; //2 bytes per register, length refers to the number of registers spanned
					break;
			}
			if ($length == 0) { continue; }
			$total += $length;
		}
		return $total;
	}
	
	public static function map() {
		return [
			3 => [ /* Input voltage phase 1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 3,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/ActiveIn/L1/V',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			4 => [ /* Input voltage phase 2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 4,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/ActiveIn/L2/V',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			5 => [ /* Input voltage phase 3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 5,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/ActiveIn/L3/V',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			6 => [ /* Input current phase 1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 6,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/ActiveIn/L1/I',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			], /* Positive: current flowing from mains to Multi. Negative: current flowing from Multi to mains. */
			
			7 => [ /* Input current phase 2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 7,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/ActiveIn/L2/I',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			], /* Positive: current flowing from mains to Multi. Negative: current flowing from Multi to mains. */
			
			8 => [ /* Input current phase 3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 8,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/ActiveIn/L3/I',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			], /* Positive: current flowing from mains to Multi. Negative: current flowing from Multi to mains. */
			
			9 => [ /* Input frequency 1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 9,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 100,
				'path' => '/Ac/ActiveIn/L1/F',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_HZ,
			],
			
			10 => [ /* Input frequency 2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 10,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 100,
				'path' => '/Ac/ActiveIn/L2/F',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_HZ,
			],
			
			11 => [ /* Input frequency 3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 11,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 100,
				'path' => '/Ac/ActiveIn/L3/F',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_HZ,
			],
			
			12 => [ /* Input power 1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 12,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 0.1,
				'path' => '/Ac/ActiveIn/L1/P',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_VA,
			], /* Sign meaning equal to Input current */
			
			13 => [ /* Input power 2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 13,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 0.1,
				'path' => '/Ac/ActiveIn/L2/P',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_VA,
			], /* Sign meaning equal to Input current */
			
			14 => [ /* Input power 3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 14,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 0.1,
				'path' => '/Ac/ActiveIn/L3/P',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_VA,
			], /* Sign meaning eaqual to Input current */
			
			15 => [ /* Output voltage phase 1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 15,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/Out/L1/V',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			16 => [ /* Output voltage phase 2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 16,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/Out/L2/V',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			17 => [ /* Output voltage phase 3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 17,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/Out/L3/V',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			18 => [ /* Output current phase 1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 18,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/Out/L1/I',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			], /* Postive: current flowing from Multi to the load. Negative: current flowing from load to the Multi. */
			
			19 => [ /* Output current phase 2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 19,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/Out/L2/I',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			], /* Postive: current flowing from Multi to the load. Negative: current flowing from load to the Multi. */
			
			20 => [ /* Output current phase 3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 20,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/Out/L3/I',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			], /* Postive: current flowing from Multi to the load. Negative: current flowing from load to the Multi. */
			
			21 => [ /* Output frequency */
				'service' => 'com.victronenergy.vebus',
				'register' => 21,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 100,
				'path' => '/Ac/Out/L1/F',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_HZ,
			],
			
			22 => [ /* Active input current limit */
				'service' => 'com.victronenergy.vebus',
				'register' => 22,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/ActiveIn/CurrentLimit',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_A,
			], /* See Venus-OS manual for limitations, for example when VE.Bus BMS or DMC is installed. */
			
			23 => [ /* Output power 1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 23,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 0.1,
				'path' => '/Ac/Out/L1/P',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_VA,
			], /* Sign meaning equal to Output current */
			
			24 => [ /* Output power 2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 24,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 0.1,
				'path' => '/Ac/Out/L2/P',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_VA,
			], /* Sign meaning equal to Output current */
			
			25 => [ /* Output power 3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 25,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 0.1,
				'path' => '/Ac/Out/L3/P',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_VA,
			], /* Sign meaning equal to Output current */
			
			26 => [ /* Battery voltage */
				'service' => 'com.victronenergy.vebus',
				'register' => 26,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Dc/0/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			27 => [ /* Battery current */
				'service' => 'com.victronenergy.vebus',
				'register' => 27,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/0/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			], /* Positive: current flowing from the Multi to the dc system. Negative: the other way around. */
			
			28 => [ /* Phase count */
				'service' => 'com.victronenergy.vebus',
				'register' => 28,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/NumberOfPhases',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			29 => [ /* Active input */
				'service' => 'com.victronenergy.vebus',
				'register' => 29,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/ActiveIn/ActiveInput',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=AC Input 1;1=AC Input 2;240=Disconnected */
			
			30 => [ /* VE.Bus state of charge */
				'service' => 'com.victronenergy.vebus',
				'register' => 30,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Soc',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_PERCENT,
			],
			
			31 => [ /* VE.Bus state */
				'service' => 'com.victronenergy.vebus',
				'register' => 31,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/State',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Off;1=Low Power;2=Fault;3=Bulk;4=Absorption;5=Float;6=Storage;7=Equalize;8=Passthru;9=Inverting;10=Power assist;11=Power supply;252=Bulk protection */
			
			32 => [ /* VE.Bus Error */
				'service' => 'com.victronenergy.vebus',
				'register' => 32,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/VebusError',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No error;1=VE.Bus Error 1: Device is switched off because one of the other phases in the system has switched off;2=VE.Bus Error 2: New and old types MK2 are mixed in the system;3=VE.Bus Error 3: Not all- or more than- the expected devices were found in the system;4=VE.Bus Error 4: No other device whatsoever detected;5=VE.Bus Error 5: Overvoltage on AC-out;6=VE.Bus Error 6: Error in DDC Program;7=VE.Bus BMS connected- which requires an Assistant- but no assistant found;10=VE.Bus Error 10: System time synchronisation problem occurred;14=VE.Bus Error 14: Device cannot transmit data;16=VE.Bus Error 16: Dongle missing;17=VE.Bus Error 17: One of the devices assumed master status because the original master failed;18=VE.Bus Error 18: AC Overvoltage on the output of a slave has occurred while already switched off;22=VE.Bus Error 22: This device cannot function as slave;24=VE.Bus Error 24: Switch-over system protection initiated;25=VE.Bus Error 25: Firmware incompatibility. The firmware of one of the connected device is not sufficiently up to date to operate in conjunction with this device;26=VE.Bus Error 26: Internal error */
			
			33 => [ /* Switch Position */
				'service' => 'com.victronenergy.vebus',
				'register' => 33,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Mode',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 1=Charger Only;2=Inverter Only;3=On;4=Off -- See Venus-OS manual for limitations, for example when VE.Bus BMS or DMC is installed. */
			
			34 => [ /* Temperature alarm */
				'service' => 'com.victronenergy.vebus',
				'register' => 34,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			35 => [ /* Low battery alarm */
				'service' => 'com.victronenergy.vebus',
				'register' => 35,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowBattery',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			36 => [ /* Overload alarm */
				'service' => 'com.victronenergy.vebus',
				'register' => 36,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/Overload',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			37 => [ /* ESS power setpoint phase 1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 37,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Hub4/L1/AcPowerSetpoint',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_W,
			], /* ESS Mode 3 - Instructs the multi to charge/discharge with giving power. Negative = discharge. Used by the control loop in grid-parallel systems. */
			
			38 => [ /* ESS disable charge flag phase */
				'service' => 'com.victronenergy.vebus',
				'register' => 38,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Hub4/DisableCharge',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Charge allowed;1=Charge disabled -- ESS Mode 3 - Enables/Disables charge (0=enabled, 1=disabled). Note that power setpoint will yield to this setting */
			
			39 => [ /* ESS disable feedback flag phase */
				'service' => 'com.victronenergy.vebus',
				'register' => 39,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Hub4/DisableFeedIn',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Feed in allowed;1=Feed in disabled -- ESS Mode 3 - Enables/Disables feedback (0=enabled, 1=disabled). Note that power setpoint will yield to this setting */
			
			40 => [ /* ESS power setpoint phase 2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 40,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Hub4/L2/AcPowerSetpoint',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_W,
			], /* ESS Mode 3 - Instructs the multi to charge/discharge with giving power. Negative = discharge. Used by the control loop in grid-parallel systems. */
			
			41 => [ /* ESS power setpoint phase 3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 41,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Hub4/L3/AcPowerSetpoint',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_W,
			], /* ESS Mode 3 - Instructs the multi to charge/discharge with giving power. Negative = discharge. Used by the control loop in grid-parallel systems. */
			
			42 => [ /* Temperatur sensor alarm */
				'service' => 'com.victronenergy.vebus',
				'register' => 42,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/TemperatureSensor',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			43 => [ /* Voltage sensor alarm */
				'service' => 'com.victronenergy.vebus',
				'register' => 43,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/VoltageSensor',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			44 => [ /* Temperature alarm L1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 44,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L1/HighTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			45 => [ /* Low battery alarm L1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 45,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L1/LowBattery',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			46 => [ /* Overload alarm L1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 46,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L1/Overload',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			47 => [ /* Ripple alarm L1 */
				'service' => 'com.victronenergy.vebus',
				'register' => 47,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L1/Ripple',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			48 => [ /* Temperature alarm L2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 48,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L2/HighTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			49 => [ /* Low battery alarm L2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 49,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L2/LowBattery',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			50 => [ /* Overload alarm L2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 50,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L2/Overload',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			51 => [ /* Ripple alarm L2 */
				'service' => 'com.victronenergy.vebus',
				'register' => 51,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L2/Ripple',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			52 => [ /* Temperature alarm L3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 52,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L3/HighTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			53 => [ /* Low battery alarm L3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 53,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L3/LowBattery',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			54 => [ /* Overload alarm L3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 54,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L3/Overload',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			55 => [ /* Ripple alarm L3 */
				'service' => 'com.victronenergy.vebus',
				'register' => 55,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/L3/Ripple',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Ok;1=Warning;2=Alarm */
			
			56 => [ /* Disable PV inverter */
				'service' => 'com.victronenergy.vebus',
				'register' => 56,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/PvInverter/Disable',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=PV enabled;1=PV disabled -- Disable PV inverter on AC out (using frequency shifting). Only works when vebus device is in inverter mode. Needs ESS or PV inverter assistant */
			
			57 => [ /* VE.Bus BMS allows battery to be charged */
				'service' => 'com.victronenergy.vebus',
				'register' => 57,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Bms/AllowToCharge',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No;1=Yes -- VE.Bus BMS allows the battery to be charged */
			
			58 => [ /* VE.Bus BMS allows battery to be discharged */
				'service' => 'com.victronenergy.vebus',
				'register' => 58,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Bms/AllowToDischarge',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No;1=Yes -- VE.Bus BMS allows the battery to be discharged */
			
			59 => [ /* VE.Bus BMS is expected */
				'service' => 'com.victronenergy.vebus',
				'register' => 59,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Bms/BmsExpected',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No;1=Yes -- Presence of VE.Bus BMS is expected based on vebus settings (presence of ESS or BMS assistant) */
			
			60 => [ /* VE.Bus BMS error */
				'service' => 'com.victronenergy.vebus',
				'register' => 60,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Bms/Error',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No;1=Yes */
			
			259 => [ /* Battery voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 259,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Dc/0/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			260 => [ /* Starter battery voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 260,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Dc/1/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			261 => [ /* Current */
				'service' => 'com.victronenergy.battery',
				'register' => 261,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/0/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			], /* Postive: battery begin charged. Negative: battery being discharged */
			
			262 => [ /* Battery temperature */
				'service' => 'com.victronenergy.battery',
				'register' => 262,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/0/Temperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_CELSIUS,
			], /* In degrees Celsius */
			
			263 => [ /* Mid-point voltage of the battery bank */
				'service' => 'com.victronenergy.battery',
				'register' => 263,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Dc/0/MidVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			264 => [ /* Mid-point deviation of the battery bank */
				'service' => 'com.victronenergy.battery',
				'register' => 264,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Dc/0/MidVoltageDeviation',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_PERCENT,
			],
			
			265 => [ /* Consumed Amphours */
				'service' => 'com.victronenergy.battery',
				'register' => 265,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => -10,
				'path' => '/ConsumedAmphours',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AH,
			], /* Always negative (to have the same sign as the current). */
			
			266 => [ /* State of charge */
				'service' => 'com.victronenergy.battery',
				'register' => 266,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Soc',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_PERCENT,
			],
			
			267 => [ /* Alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 267,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/Alarm',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm -- 2015-01-19: Deprecated for CCGX. Value is always 0. */
			
			268 => [ /* Low voltage alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 268,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			269 => [ /* High voltage alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 269,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			270 => [ /* Low starter-voltage alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 270,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowStarterVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			271 => [ /* High starter-voltage alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 271,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighStarterVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			272 => [ /* Low State-of-charge alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 272,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowSoc',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			273 => [ /* Low temperature alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 273,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			274 => [ /* High temperature alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 274,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			275 => [ /* Mid-voltage alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 275,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/MidVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			276 => [ /* Low fused-voltage alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 276,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowFusedVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm -- 2014-12-13: Deprecated because over-engineered. Value is always 0. */
			
			277 => [ /* High fused-voltage alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 277,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighFusedVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm -- 2014-12-13: Deprecated because over-engineered. Value is always 0. */
			
			278 => [ /* Fuse blown alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 278,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/FuseBlown',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			279 => [ /* High internal-temperature alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 279,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighInternalTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			280 => [ /* Relay status */
				'service' => 'com.victronenergy.battery',
				'register' => 280,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Relay/0/State',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Open;1=Closed -- Not supported by CAN.Bus BMS batteries. */
			
			281 => [ /* Deepest discharge */
				'service' => 'com.victronenergy.battery',
				'register' => 281,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => -10,
				'path' => '/History/DeepestDischarge',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AH,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			282 => [ /* Last discharge */
				'service' => 'com.victronenergy.battery',
				'register' => 282,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => -10,
				'path' => '/History/LastDischarge',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AH,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			283 => [ /* Average discharge */
				'service' => 'com.victronenergy.battery',
				'register' => 283,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => -10,
				'path' => '/History/AverageDischarge',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AH,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			284 => [ /* Charge cycles */
				'service' => 'com.victronenergy.battery',
				'register' => 284,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/History/ChargeCycles',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			285 => [ /* Full discharges */
				'service' => 'com.victronenergy.battery',
				'register' => 285,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/History/FullDischarges',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			286 => [ /* Total Ah drawn */
				'service' => 'com.victronenergy.battery',
				'register' => 286,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => -10,
				'path' => '/History/TotalAhDrawn',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AH,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			287 => [ /* Minimum voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 287,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/History/MinimumVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			288 => [ /* Maximum voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 288,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/History/MaximumVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			289 => [ /* Time since last full charge */
				'service' => 'com.victronenergy.battery',
				'register' => 289,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 0.01,
				'path' => '/History/TimeSinceLastFullCharge',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_SECONDS,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			290 => [ /* Automatic syncs */
				'service' => 'com.victronenergy.battery',
				'register' => 290,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/History/AutomaticSyncs',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			291 => [ /* Low voltage alarms */
				'service' => 'com.victronenergy.battery',
				'register' => 291,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/History/LowVoltageAlarms',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			292 => [ /* High voltage alarms */
				'service' => 'com.victronenergy.battery',
				'register' => 292,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/History/HighVoltageAlarms',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			293 => [ /* Low starter voltage alarms */
				'service' => 'com.victronenergy.battery',
				'register' => 293,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/History/LowStarterVoltageAlarms',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			294 => [ /* High starter voltage alarms */
				'service' => 'com.victronenergy.battery',
				'register' => 294,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/History/HighStarterVoltageAlarms',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			295 => [ /* Minimum starter voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 295,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/History/MinimumStarterVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			296 => [ /* Maximum starter voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 296,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/History/MaximumStarterVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			297 => [ /* Low fused-voltage alarms */
				'service' => 'com.victronenergy.battery',
				'register' => 297,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/History/LowFusedVoltageAlarms',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 2014-12-13: Deprecated because over-engineered. Value is always 0. */
			
			298 => [ /* High fused-voltage alarms */
				'service' => 'com.victronenergy.battery',
				'register' => 298,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/History/HighFusedVoltageAlarms',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 2014-12-13: Deprecated because over-engineered. Value is always 0. */
			
			299 => [ /* Minimum fused voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 299,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/History/MinimumFusedVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			], /* 2014-12-13: Deprecated because over-engineered. Value is always 0. */
			
			300 => [ /* Maximum fused voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 300,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/History/MaximumFusedVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			], /* 2014-12-13: Deprecated because over-engineered. Value is always 0. */
			
			301 => [ /* Discharged Energy */
				'service' => 'com.victronenergy.battery',
				'register' => 301,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/History/DischargedEnergy',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			302 => [ /* Charged Energy */
				'service' => 'com.victronenergy.battery',
				'register' => 302,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/History/ChargedEnergy',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			], /* Not supported by CAN.Bus BMS batteries. */
			
			303 => [ /* Time to go */
				'service' => 'com.victronenergy.battery',
				'register' => 303,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 0.01,
				'path' => '/TimeToGo',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_SECONDS,
			], /* Special value: 0 = charging. Not supported by CAN.Bus BMS batteries. */
			
			304 => [ /* State of health */
				'service' => 'com.victronenergy.battery',
				'register' => 304,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Soh',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_PERCENT,
			], /* Not supported by Victron products. Supported by CAN.Bus batteries. */
			
			305 => [ /* Max charge voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 305,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Info/MaxChargeVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			], /* Not supported by Victron products. Supported by CAN.Bus batteries. */
			
			306 => [ /* Min discharge voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 306,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Info/BatteryLowVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			], /* Not supported by Victron products. Supported by CAN.Bus batteries. */
			
			307 => [ /* Max charge current */
				'service' => 'com.victronenergy.battery',
				'register' => 307,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Info/MaxChargeCurrent',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			], /* Not supported by Victron products. Supported by CAN.Bus batteries. */
			
			308 => [ /* Max discharge current */
				'service' => 'com.victronenergy.battery',
				'register' => 308,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Info/MaxDischargeCurrent',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			], /* Not supported by Victron products. Supported by CAN.Bus batteries. */
			
			309 => [ /* Capacity */
				'service' => 'com.victronenergy.battery',
				'register' => 309,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Capacity',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AH,
			],
			
			310 => [ /* Diagnostics; 1st last error timestamp */
				'service' => 'com.victronenergy.battery',
				'register' => 310,
				'type' => ModbusRegister::TYPE_INT32,
				'scale' => 1,
				'path' => '/Diagnostics/LastErrors/1/Time',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			312 => [ /* Diagnostics; 2nd last error timestamp */
				'service' => 'com.victronenergy.battery',
				'register' => 312,
				'type' => ModbusRegister::TYPE_INT32,
				'scale' => 1,
				'path' => '/Diagnostics/LastErrors/2/Time',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			314 => [ /* Diagnostics; 3rd last error timestamp */
				'service' => 'com.victronenergy.battery',
				'register' => 314,
				'type' => ModbusRegister::TYPE_INT32,
				'scale' => 1,
				'path' => '/Diagnostics/LastErrors/3/Time',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			316 => [ /* Diagnostics; 4th last error timestamp */
				'service' => 'com.victronenergy.battery',
				'register' => 316,
				'type' => ModbusRegister::TYPE_INT32,
				'scale' => 1,
				'path' => '/Diagnostics/LastErrors/4/Time',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			318 => [ /* Minimum cell temperature */
				'service' => 'com.victronenergy.battery',
				'register' => 318,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/System/MinCellTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_CELSIUS,
			],
			
			319 => [ /* Maximum cell temperature */
				'service' => 'com.victronenergy.battery',
				'register' => 319,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/System/MaxCellTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_CELSIUS,
			],
			
			320 => [ /* High charge current alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 320,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighChargeCurrent',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			321 => [ /* High discharge current alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 321,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighDischargeCurrent',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			322 => [ /* Cell imbalance alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 322,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/CellImbalance',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			323 => [ /* Internal failure alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 323,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/InternalFailure',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			324 => [ /* High charge temperature alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 324,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighChargeTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			325 => [ /* Low charge temperature alarm */
				'service' => 'com.victronenergy.battery',
				'register' => 325,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowChargeTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			771 => [ /* Battery voltage */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 771,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Dc/0/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			772 => [ /* Battery current */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 772,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/0/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			],
			
			773 => [ /* Battery temperature */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 773,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/0/Temperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_CELSIUS,
			], /* VE.Can MPPTs only */
			
			774 => [ /* Charger on/off */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 774,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Mode',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 1=On;4=Off -- VE.Can MPPTs only */
			
			775 => [ /* Charge state */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 775,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/State',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Off;2=Fault;3=Bulk;4=Absorption;5=Float;6=Storage;7=Equalize;11=Other (Hub-1);252=Hub-1 */
			
			776 => [ /* PV voltage */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 776,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Pv/V',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			], /* Not available if multiple VE.Can chargers are combined */
			
			777 => [ /* PV current */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 777,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Pv/I',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			], /* Not available if multiple VE.Can chargers are combined */
			
			778 => [ /* Equalization pending */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 778,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Equalization/Pending',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No;1=Yes;2=Error;3=Unavailable- Unknown */
			
			779 => [ /* Equalization time remaining */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 779,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Equalization/TimeRemaining',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_SECONDS,
			],
			
			780 => [ /* Relay on the charger */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 780,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Relay/0/State',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Open;1=Closed */
			
			[
				'service' => 'com.victronenergy.solarcharger',
				'register' => 781,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/Alarm',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm -- Deprecated. Value is always 0 */
			
			782 => [ /* Low batt. voltage alarm */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 782,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			783 => [ /* High batt. voltage alarm */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 783,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			784 => [ /* Yield today */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 784,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/History/Daily/0/Yield',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			], /* Today's yield */
			
			785 => [ /* Maximum charge power today */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 785,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/History/Daily/0/MaxPower',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Today's maximum power */
			
			786 => [ /* Yield yesterday */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 786,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/History/Daily/1/Yield',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			], /* Yesterday's yield */
			
			787 => [ /* Maximum charge power yesterday */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 787,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/History/Daily/1/MaxPower',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Yesterday's maximum power */
			
			788 => [ /* Error code */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 788,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/ErrorCode',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No error;1=Battery temperature too high;2=Battery voltage too high;3=Battery temperature sensor miswired (+);4=Battery temperature sensor miswired (-);5=Battery temperature sensor disconnected;6=Battery voltage sense miswired (+);7=Battery voltage sense miswired (-);8=Battery voltage sense disconnected;9=Battery voltage wire losses too high;17=Charger temperature too high;18=Charger over-current;19=Charger current polarity reversed;20=Bulk time limit reached;22=Charger temperature sensor miswired;23=Charger temperature sensor disconnected;34=Input current too high */
			
			789 => [ /* PV power */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 789,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Yield/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			790 => [ /* User yield */
				'service' => 'com.victronenergy.solarcharger',
				'register' => 790,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Yield/User',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			], /* Energy generated by the solarcharger since last user reset */
			
			800 => [ /* Serial (System) */
				'service' => 'com.victronenergy.system',
				'register' => 800,
				'type' => ModbusRegister::TYPE_STRING,
				'length' => 6,
				'scale' => 1,
				'path' => '/Serial',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* System value -> MAC address of CCGX (represented as string) */
			
			806 => [ /* CCGX Relay 1 state */
				'service' => 'com.victronenergy.system',
				'register' => 806,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Relay/0/State',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Open;1=Closed */
			
			807 => [ /* CCGX Relay 2 state */
				'service' => 'com.victronenergy.system',
				'register' => 807,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Relay/1/State',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Open;1=Closed -- Relay 1 is available on Venus GX only. */
			
			808 => [ /* PV - AC-coupled on output L1 */
				'service' => 'com.victronenergy.system',
				'register' => 808,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/PvOnOutput/L1/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Summation of all AC-Coupled PV Inverters on the output */
			
			809 => [ /* PV - AC-coupled on output L2 */
				'service' => 'com.victronenergy.system',
				'register' => 809,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/PvOnOutput/L2/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			810 => [ /* PV - AC-coupled on output L3 */
				'service' => 'com.victronenergy.system',
				'register' => 810,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/PvOnOutput/L3/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			811 => [ /* PV - AC-coupled on input L1 */
				'service' => 'com.victronenergy.system',
				'register' => 811,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/PvOnGrid/L1/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Summation of all AC-Coupled PV Inverters on the input */
			
			812 => [ /* PV - AC-coupled on input L2 */
				'service' => 'com.victronenergy.system',
				'register' => 812,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/PvOnGrid/L2/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			813 => [ /* PV - AC-coupled on input L3 */
				'service' => 'com.victronenergy.system',
				'register' => 813,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/PvOnGrid/L3/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			814 => [ /* PV - AC-coupled on generator L1 */
				'service' => 'com.victronenergy.system',
				'register' => 814,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/PvOnGenset/L1/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Summation of all AC-Coupled PV Inverters on a generator. Bit theoretic; this will never be used. */
			
			815 => [ /* PV - AC-coupled on generator L2 */
				'service' => 'com.victronenergy.system',
				'register' => 815,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/PvOnGenset/L2/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			816 => [ /* PV - AC-coupled on generator L3 */
				'service' => 'com.victronenergy.system',
				'register' => 816,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/PvOnGenset/L3/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			817 => [ /* AC Consumption L1 */
				'service' => 'com.victronenergy.system',
				'register' => 817,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/Consumption/L1/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Power supplied by system to loads. */
			
			818 => [ /* AC Consumption L2 */
				'service' => 'com.victronenergy.system',
				'register' => 818,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/Consumption/L2/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			819 => [ /* AC Consumption L3 */
				'service' => 'com.victronenergy.system',
				'register' => 819,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/Consumption/L3/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			820 => [ /* Grid L1 */
				'service' => 'com.victronenergy.system',
				'register' => 820,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/Grid/L1/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Power supplied by Grid to system. */
			
			821 => [ /* Grid L2 */
				'service' => 'com.victronenergy.system',
				'register' => 821,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/Grid/L2/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			822 => [ /* Grid L3 */
				'service' => 'com.victronenergy.system',
				'register' => 822,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/Grid/L3/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			823 => [ /* Genset L1 */
				'service' => 'com.victronenergy.system',
				'register' => 823,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/Genset/L1/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Power supplied by Genset tot system. */
			
			824 => [ /* Genset L2 */
				'service' => 'com.victronenergy.system',
				'register' => 824,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/Genset/L2/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			825 => [ /* Genset L3 */
				'service' => 'com.victronenergy.system',
				'register' => 825,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/Genset/L3/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			826 => [ /* Active input source */
				'service' => 'com.victronenergy.system',
				'register' => 826,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/ActiveIn/Source',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Not available;1=Grid;2=Generator;3=Shore power;240=Not connected */
			
			840 => [ /* Battery Voltage (System) */
				'service' => 'com.victronenergy.system',
				'register' => 840,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Dc/Battery/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			], /* Battery Voltage determined from different measurements. In order of preference: BMV-voltage (V), Multi-DC-Voltage (CV), MPPT-DC-Voltage (ScV), Charger voltage */
			
			841 => [ /* Battery Current (System) */
				'service' => 'com.victronenergy.system',
				'register' => 841,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/Battery/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			], /* Postive: battery begin charged. Negative: battery being discharged */
			
			842 => [ /* Battery Power (System) */
				'service' => 'com.victronenergy.system',
				'register' => 842,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Dc/Battery/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Postive: battery begin charged. Negative: battery being discharged */
			
			843 => [ /* Battery State of Charge (System) */
				'service' => 'com.victronenergy.system',
				'register' => 843,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Dc/Battery/Soc',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_PERCENT,
			], /* Best battery state of charge, determined from different measurements. */
			
			844 => [ /* Battery state (System) */
				'service' => 'com.victronenergy.system',
				'register' => 844,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Dc/Battery/State',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=idle;1=charging;2=discharging */
			
			845 => [ /* Battery Consumed Amphours (System) */
				'service' => 'com.victronenergy.system',
				'register' => 845,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => -10,
				'path' => '/Dc/Battery/ConsumedAmphours',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AH,
			],
			
			846 => [ /* Battery Time to Go (System) */
				'service' => 'com.victronenergy.system',
				'register' => 846,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 0.01,
				'path' => '/Dc/Battery/TimeToGo',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_SECONDS,
			], /* Special value: 0 = charging */
			
			850 => [ /* PV - DC-coupled power */
				'service' => 'com.victronenergy.system',
				'register' => 850,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Dc/Pv/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Summation of output power of all connected Solar Chargers */
			
			851 => [ /* PV - DC-coupled current */
				'service' => 'com.victronenergy.system',
				'register' => 851,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/Pv/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			], /* Summation of output current of all connected Solar Chargers */
			
			855 => [ /* Charger power */
				'service' => 'com.victronenergy.system',
				'register' => 855,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Dc/Charger/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			860 => [ /* DC System Power */
				'service' => 'com.victronenergy.system',
				'register' => 860,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Dc/System/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Power supplied by Battery to system. */
			
			865 => [ /* VE.Bus charge current (System) */
				'service' => 'com.victronenergy.system',
				'register' => 865,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/Vebus/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			], /* Current flowing from the Multi to the dc system. Negative: the other way around. */
			
			866 => [ /* VE.Bus charge power (System) */
				'service' => 'com.victronenergy.system',
				'register' => 866,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Dc/Vebus/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* System value etc. AND: Positive: power flowing from the Multi to the dc system. Negative: the other way around. */
			
			1026 => [ /* Position */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1026,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Position',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=AC input 1;1=AC output;2=AC input 2 */
			
			1027 => [ /* L1 Voltage */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1027,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/L1/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			1028 => [ /* L1 Current */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1028,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/L1/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			1029 => [ /* L1 Power */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1029,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/L1/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			1030 => [ /* L1 Energy */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1030,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L1/Energy/Forward',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			1031 => [ /* L2 Voltage */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1031,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/L2/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			1032 => [ /* L2 Current */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1032,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/L2/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			1033 => [ /* L2 Power */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1033,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/L2/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			1034 => [ /* L2 Energy */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1034,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L2/Energy/Forward',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			1035 => [ /* L3 Voltage */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1035,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/L3/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			1036 => [ /* L3 Current */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1036,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/L3/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			1037 => [ /* L3 Power */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1037,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/L3/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			1038 => [ /* L3 Energy */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1038,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L3/Energy/Forward',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			1039 => [ /* Serial */
				'service' => 'com.victronenergy.pvinverter',
				'register' => 1039,
				'type' => ModbusRegister::TYPE_STRING,
				'length' => 7,
				'scale' => 1,
				'path' => '/Serial',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* The system serial as string (MSB of first register: first character, LSB of last register: last character). */
			
			1282 => [ /* State */
				'service' => 'com.victronenergy.battery',
				'register' => 1282,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/State',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Initializing (Wait start);1=Initializing (before boot);2=Initializing (Before boot delay);3=Initializing (Wait boot);4=Initializing;5=Initializing (Measure battery voltage);6=Initializing (Calculate battery voltage);7=Initializing (Wait bus voltage);8=Initializing (Wait for lynx shunt);9=Running;10=Error (10);11=Error (11) */
			
			1283 => [ /* Error */
				'service' => 'com.victronenergy.battery',
				'register' => 1283,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/ErrorCode',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No error;1=Battery initialization error;2=No batteries connected;3=Unknown battery connected;4=Different battery type;5=Number of batteries incorrect;6=Lynx Shunt not found;7=Battery measure error;8=Internal calculation error;9=Batteries in series not ok;10=Number of batteries incorrect;11=Hardware error;12=Watchdog error;13=Over voltage;14=Under voltage;15=Over temperature;16=Under temperature;17=Hardware fault;18=Standby shutdown;19=Pre-charge charge error;20=Safety contactor check error;21=Pre-charge discharge error;22=ADC error;23=Slave error;24=Slave warning;25=Pre-charge error;26=Safety contactor error;27=Over current;28=Slave update failed;29=Slave update unavailable */
			
			1284 => [ /* System-switch */
				'service' => 'com.victronenergy.battery',
				'register' => 1284,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/SystemSwitch',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Disabled;1=Enabled */
			
			1285 => [ /* Balancing */
				'service' => 'com.victronenergy.battery',
				'register' => 1285,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Balancing',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Inactive;1=Active */
			
			1286 => [ /* System; number of batteries */
				'service' => 'com.victronenergy.battery',
				'register' => 1286,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/System/NrOfBatteries',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			1287 => [ /* System; batteries parallel */
				'service' => 'com.victronenergy.battery',
				'register' => 1287,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/System/BatteriesParallel',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			1288 => [ /* System; batteries series */
				'service' => 'com.victronenergy.battery',
				'register' => 1288,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/System/BatteriesSeries',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			1289 => [ /* System; number of cells per battery */
				'service' => 'com.victronenergy.battery',
				'register' => 1289,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/System/NrOfCellsPerBattery',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			1290 => [ /* System; minimum cell voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 1290,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/System/MinCellVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			1291 => [ /* System; maximum cell voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 1291,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/System/MaxCellVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			1292 => [ /* Diagnostics; shutdowns due to error */
				'service' => 'com.victronenergy.battery',
				'register' => 1292,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Diagnostics/ShutDownsDueError',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			1293 => [ /* Diagnostics; 1st last error */
				'service' => 'com.victronenergy.battery',
				'register' => 1293,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Diagnostics/LastErrors/1/Error',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No error;1=Battery initialization error;2=No batteries connected;3=Unknown battery connected;4=Different battery type;5=Number of batteries incorrect;6=Lynx Shunt not found;7=Battery measure error;8=Internal calculation error;9=Batteries in series not ok;10=Number of batteries incorrect;11=Hardware error;12=Watchdog error;13=Over voltage;14=Under voltage;15=Over temperature;16=Under temperature;17=Hardware fault;18=Standby shutdown;19=Pre-charge charge error;20=Safety contactor check error;21=Pre-charge discharge error;22=ADC error;23=Slave error;24=Slave warning;25=Pre-charge error;26=Safety contactor error;27=Over current;28=Slave update failed;29=Slave update unavailable */
			
			1294 => [ /* Diagnostics; 2nd last error */
				'service' => 'com.victronenergy.battery',
				'register' => 1294,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Diagnostics/LastErrors/2/Error',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No error;1=Battery initialization error;2=No batteries connected;3=Unknown battery connected;4=Different battery type;5=Number of batteries incorrect;6=Lynx Shunt not found;7=Battery measure error;8=Internal calculation error;9=Batteries in series not ok;10=Number of batteries incorrect;11=Hardware error;12=Watchdog error;13=Over voltage;14=Under voltage;15=Over temperature;16=Under temperature;17=Hardware fault;18=Standby shutdown;19=Pre-charge charge error;20=Safety contactor check error;21=Pre-charge discharge error;22=ADC error;23=Slave error;24=Slave warning;25=Pre-charge error;26=Safety contactor error;27=Over current;28=Slave update failed;29=Slave update unavailable */
			
			1295 => [ /* Diagnostics; 3rd last error */
				'service' => 'com.victronenergy.battery',
				'register' => 1295,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Diagnostics/LastErrors/3/Error',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No error;1=Battery initialization error;2=No batteries connected;3=Unknown battery connected;4=Different battery type;5=Number of batteries incorrect;6=Lynx Shunt not found;7=Battery measure error;8=Internal calculation error;9=Batteries in series not ok;10=Number of batteries incorrect;11=Hardware error;12=Watchdog error;13=Over voltage;14=Under voltage;15=Over temperature;16=Under temperature;17=Hardware fault;18=Standby shutdown;19=Pre-charge charge error;20=Safety contactor check error;21=Pre-charge discharge error;22=ADC error;23=Slave error;24=Slave warning;25=Pre-charge error;26=Safety contactor error;27=Over current;28=Slave update failed;29=Slave update unavailable */
			
			1296 => [ /* Diagnostics; 4th last error */
				'service' => 'com.victronenergy.battery',
				'register' => 1296,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Diagnostics/LastErrors/4/Error',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No error;1=Battery initialization error;2=No batteries connected;3=Unknown battery connected;4=Different battery type;5=Number of batteries incorrect;6=Lynx Shunt not found;7=Battery measure error;8=Internal calculation error;9=Batteries in series not ok;10=Number of batteries incorrect;11=Hardware error;12=Watchdog error;13=Over voltage;14=Under voltage;15=Over temperature;16=Under temperature;17=Hardware fault;18=Standby shutdown;19=Pre-charge charge error;20=Safety contactor check error;21=Pre-charge discharge error;22=ADC error;23=Slave error;24=Slave warning;25=Pre-charge error;26=Safety contactor error;27=Over current;28=Slave update failed;29=Slave update unavailable */
			
			1297 => [ /* IO; allow to charge */
				'service' => 'com.victronenergy.battery',
				'register' => 1297,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Io/AllowToCharge',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No;1=Yes */
			
			1298 => [ /* IO; allow to discharge */
				'service' => 'com.victronenergy.battery',
				'register' => 1298,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Io/AllowToDischarge',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No;1=Yes */
			
			1299 => [ /* IO; external relay */
				'service' => 'com.victronenergy.battery',
				'register' => 1299,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Io/ExternalRelay',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Inactive;1=Active */
			
			1300 => [ /* History; Min cell-voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 1300,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/History/MinimumCellVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			1301 => [ /* History; Max cell-voltage */
				'service' => 'com.victronenergy.battery',
				'register' => 1301,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/History/MaximumCellVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			2048 => [ /* Motor RPM */
				'service' => 'com.victronenergy.motordrive',
				'register' => 2048,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Motor/RPM',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_RPM,
			],
			
			2049 => [ /* Motor temperature */
				'service' => 'com.victronenergy.motordrive',
				'register' => 2049,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Motor/Temperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_CELSIUS,
			],
			
			2050 => [ /* Controller DC Voltage */
				'service' => 'com.victronenergy.motordrive',
				'register' => 2050,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Dc/0/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			2051 => [ /* Controller DC Current */
				'service' => 'com.victronenergy.motordrive',
				'register' => 2051,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/0/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			],
			
			2052 => [ /* Controller DC Power */
				'service' => 'com.victronenergy.motordrive',
				'register' => 2052,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/0/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			], /* Positive = being powered from battery, Negative is charging battery (regeneration) */
			
			2053 => [ /* Controller Temperature */
				'service' => 'com.victronenergy.motordrive',
				'register' => 2053,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Controller/Temperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_CELSIUS,
			],
			
			2307 => [ /* Output 1 - voltage */
				'service' => 'com.victronenergy.charger',
				'register' => 2307,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Dc/0/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			2308 => [ /* Output 1 - current */
				'service' => 'com.victronenergy.charger',
				'register' => 2308,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/0/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			],
			
			2309 => [ /* Output 1 - temperature */
				'service' => 'com.victronenergy.charger',
				'register' => 2309,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/0/Temperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_CELSIUS,
			],
			
			2310 => [ /* Output 2 - voltage */
				'service' => 'com.victronenergy.charger',
				'register' => 2310,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Dc/1/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			2311 => [ /* Output 2 - current */
				'service' => 'com.victronenergy.charger',
				'register' => 2311,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/1/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			],
			
			2312 => [ /* Output 3 - voltage */
				'service' => 'com.victronenergy.charger',
				'register' => 2312,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Dc/2/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			2313 => [ /* Output 3 - current */
				'service' => 'com.victronenergy.charger',
				'register' => 2313,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Dc/2/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_A,
			],
			
			2314 => [ /* AC Current */
				'service' => 'com.victronenergy.charger',
				'register' => 2314,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/In/L1/I',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			2315 => [ /* AC Power */
				'service' => 'com.victronenergy.charger',
				'register' => 2315,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Ac/In/L1/P',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			2316 => [ /* AC Current limit */
				'service' => 'com.victronenergy.charger',
				'register' => 2316,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/In/CurrentLimit',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			2317 => [ /* Charger on/off */
				'service' => 'com.victronenergy.charger',
				'register' => 2317,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Mode',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Off;1=On;2=Error;3=Unavailable- Unknown */
			
			2318 => [ /* Charge state */
				'service' => 'com.victronenergy.charger',
				'register' => 2318,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/State',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Off;1=Low Power Mode;2=Fault;3=Bulk;4=Absorption;5=Float;6=Storage;7=Equalize;8=Passthru;9=Inverting;10=Power assist;11=Power supply mode;252=Bulk protection */
			
			2319 => [ /* Error code */
				'service' => 'com.victronenergy.charger',
				'register' => 2319,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/ErrorCode',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No error;1=Battery temperature too high;2=Battery voltage too high;3=Battery temperature sensor miswired (+);4=Battery temperature sensor miswired (-);5=Battery temperature sensor disconnected;6=Battery voltage sense miswired (+);7=Battery voltage sense miswired (-);8=Battery voltage sense disconnected;9=Battery voltage wire losses too high;17=Charger temperature too high;18=Charger over-current;19=Charger current polarity reversed;20=Bulk time limit reached;22=Charger temperature sensor miswired;23=Charger temperature sensor disconnected;34=Input current too high */
			
			2320 => [ /* Relay on the charger */
				'service' => 'com.victronenergy.charger',
				'register' => 2320,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Relay/0/State',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Open;1=Closed */
			
			2321 => [ /* Low voltage alarm */
				'service' => 'com.victronenergy.charger',
				'register' => 2321,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			2322 => [ /* High voltage alarm */
				'service' => 'com.victronenergy.charger',
				'register' => 2322,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			2600 => [ /* Grid L1 - Power */
				'service' => 'com.victronenergy.grid',
				'register' => 2600,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/L1/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			2601 => [ /* Grid L2 - Power */
				'service' => 'com.victronenergy.grid',
				'register' => 2601,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/L2/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			2602 => [ /* Grid L3 - Power */
				'service' => 'com.victronenergy.grid',
				'register' => 2602,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/L3/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			2603 => [ /* Grid L1 - Energy from net */
				'service' => 'com.victronenergy.grid',
				'register' => 2603,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L1/Energy/Forward',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2604 => [ /* Grid L2 - Energy from net */
				'service' => 'com.victronenergy.grid',
				'register' => 2604,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L2/Energy/Forward',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2605 => [ /* Grid L3 - Energy from net */
				'service' => 'com.victronenergy.grid',
				'register' => 2605,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L3/Energy/Forward',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2606 => [ /* Grid L1 - Energy to net */
				'service' => 'com.victronenergy.grid',
				'register' => 2606,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L1/Energy/Reverse',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2607 => [ /* Grid L2 - Energy to net */
				'service' => 'com.victronenergy.grid',
				'register' => 2607,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L2/Energy/Reverse',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2608 => [ /* Grid L3 - Energy to net */
				'service' => 'com.victronenergy.grid',
				'register' => 2608,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L3/Energy/Reverse',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2609 => [ /* Serial */
				'service' => 'com.victronenergy.grid',
				'register' => 2609,
				'type' => ModbusRegister::TYPE_STRING,
				'length' => 7,
				'scale' => 0,
				'path' => '/Serial',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* The grid meter serial as string (MSB of first register: first character, LSB of last register: last character). */
			
			2616 => [ /* Grid L1 – Voltage */
				'service' => 'com.victronenergy.grid',
				'register' => 2616,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/L1/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			2617 => [ /* Grid L1 – Current */
				'service' => 'com.victronenergy.grid',
				'register' => 2617,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/L1/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			2618 => [ /* Grid L2 – Voltage */
				'service' => 'com.victronenergy.grid',
				'register' => 2618,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/L2/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			2619 => [ /* Grid L2 – Current */
				'service' => 'com.victronenergy.grid',
				'register' => 2619,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/L2/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			2620 => [ /* Grid L3 – Voltage */
				'service' => 'com.victronenergy.grid',
				'register' => 2620,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/L3/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			2621 => [ /* Grid L3 – Current */
				'service' => 'com.victronenergy.grid',
				'register' => 2621,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/L3/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			2622 => [ /* Grid L1 - Energy from net */
				'service' => 'com.victronenergy.grid',
				'register' => 2622,
				'type' => ModbusRegister::TYPE_UINT32,
				'scale' => 100,
				'path' => '/Ac/L1/Energy/Forward',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2624 => [ /* Grid L2 - Energy from net */
				'service' => 'com.victronenergy.grid',
				'register' => 2624,
				'type' => ModbusRegister::TYPE_UINT32,
				'scale' => 100,
				'path' => '/Ac/L2/Energy/Forward',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2626 => [ /* Grid L3 - Energy from net */
				'service' => 'com.victronenergy.grid',
				'register' => 2626,
				'type' => ModbusRegister::TYPE_UINT32,
				'scale' => 100,
				'path' => '/Ac/L3/Energy/Forward',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2628 => [ /* Grid L1 - Energy to net */
				'service' => 'com.victronenergy.grid',
				'register' => 2628,
				'type' => ModbusRegister::TYPE_UINT32,
				'scale' => 100,
				'path' => '/Ac/L1/Energy/Reverse',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2630 => [ /* Grid L2 - Energy to net */
				'service' => 'com.victronenergy.grid',
				'register' => 2630,
				'type' => ModbusRegister::TYPE_UINT32,
				'scale' => 100,
				'path' => '/Ac/L2/Energy/Reverse',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2632 => [ /* Grid L3 - Energy to net */
				'service' => 'com.victronenergy.grid',
				'register' => 2632,
				'type' => ModbusRegister::TYPE_UINT32,
				'scale' => 100,
				'path' => '/Ac/L3/Energy/Reverse',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_KWH,
			],
			
			2700 => [ /* ESS control loop setpoint */
				'service' => 'com.victronenergy.hub4',
				'register' => 2700,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/AcPowerSetpoint',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_W,
			], /* ESS Mode 2 - Setpoint for the ESS control-loop in the CCGX. The control-loop will increase/decrease the Multi charge/discharge power to get the grid L1 reading at J224this setpoint */
			
			2701 => [ /* ESS max charge current (fractional) */
				'service' => 'com.victronenergy.hub4',
				'register' => 2701,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/MaxChargePercentage',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_PERCENT,
			], /* ESS Mode 2 - Max charge current for ESS control-loop. The control-loop will use this value to limit the multi power setpoint. */
			
			2702 => [ /* ESS max discharge current (fractional) */
				'service' => 'com.victronenergy.hub4',
				'register' => 2702,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/MaxDischargePercentage',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_PERCENT,
			], /* ESS Mode 2 - Max discharge current for ESS control-loop. The control-loop will use this value to limit the multi power setpoint. Currently a value < 50% will disable discharge completely. >=50% allows maximum discharge */
			
			2703 => [ /* ESS control loop setpoint */
				'service' => 'com.victronenergy.hub4',
				'register' => 2703,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 0.01,
				'path' => '/AcPowerSetpoint',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			2800 => [ /* Latitude */
				'service' => 'com.victronenergy.gps',
				'register' => 2800,
				'type' => ModbusRegister::TYPE_INT32,
				'scale' => 10000000,
				'path' => '/Position/Latitude',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DEGREES,
			],
			
			2802 => [ /* Longitude */
				'service' => 'com.victronenergy.gps',
				'register' => 2802,
				'type' => ModbusRegister::TYPE_INT32,
				'scale' => 10000000,
				'path' => '/Position/Longitude',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DEGREES,
			],
			
			2804 => [ /* Course */
				'service' => 'com.victronenergy.gps',
				'register' => 2804,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Course',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DEGREES,
			], /* Direction of movement 0-360 degrees */
			
			2805 => [ /* Speed */
				'service' => 'com.victronenergy.gps',
				'register' => 2805,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Speed',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_MPS,
			], /* Speed in m/s */
			
			2806 => [ /* GPS fix */
				'service' => 'com.victronenergy.gps',
				'register' => 2806,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Fix',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0: no fix, 1: fix */
			
			2807 => [ /* GPS number of satellites */
				'service' => 'com.victronenergy.gps',
				'register' => 2807,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/NrOfSatellites',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			2900 => [ /* ESS BatteryLife state */
				'service' => 'com.victronenergy.settings',
				'register' => 2900,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Settings/CGwacs/BatteryLife/State',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=External control or BL disabled;1=Restarting;2=Self-consumption;3=Self-consumption;4=Self-consumption;5=Discharge disabled;6=Force charge;7=Sustain;9=Keep batteries charged;10=BL Disabled;11=BL Disabled (Low SoC) -- Use value 0 (disable) and 1(enable) for writing only */
			
			2901 => [ /* ESS Minimum SoC (unless grid fails) */
				'service' => 'com.victronenergy.settings',
				'register' => 2901,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Settings/CGwacs/BatteryLife/MinimumSocLimit',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_PERCENT,
			], /* Same as the setting in the GUI */
			
			3000 => [ /* Product ID */
				'service' => 'com.victronenergy.tank',
				'register' => 3000,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/ProductId',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			3001 => [ /* Tank capacity */
				'service' => 'com.victronenergy.tank',
				'register' => 3001,
				'type' => ModbusRegister::TYPE_UINT32,
				'scale' => 10000,
				'path' => '/Capacity',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_M3,
			],
			
			3003 => [ /* Tank fluid type */
				'service' => 'com.victronenergy.tank',
				'register' => 3003,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/FluidType',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Fuel;1=Fresh water;2=Waste water;3=Live well;4=Oil;5=Black water (sewage) */
			
			3004 => [ /* Tank level */
				'service' => 'com.victronenergy.tank',
				'register' => 3004,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Level',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_PERCENT,
			],
			
			3005 => [ /* Tank remaining fluid */
				'service' => 'com.victronenergy.tank',
				'register' => 3005,
				'type' => ModbusRegister::TYPE_UINT32,
				'scale' => 10000,
				'path' => '/Remaining',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_M3,
			],
			
			3007 => [ /* Tank status */
				'service' => 'com.victronenergy.tank',
				'register' => 3007,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Status',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=OK;1=Disconnected;2=Short circuited;3=Reverse Polarity;4=Unknown */
			
			3100 => [ /* Output current */
				'service' => 'com.victronenergy.inverter',
				'register' => 3100,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/Out/L1/I',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			3101 => [ /* Output voltage */
				'service' => 'com.victronenergy.inverter',
				'register' => 3101,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/Out/L1/V',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			3105 => [ /* Battery voltage */
				'service' => 'com.victronenergy.inverter',
				'register' => 3105,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Dc/0/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			3110 => [ /* High temperature alarm */
				'service' => 'com.victronenergy.inverter',
				'register' => 3110,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;1=Warning;2=Alarm */
			
			3111 => [ /* High battery voltage alarm */
				'service' => 'com.victronenergy.inverter',
				'register' => 3111,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;1=Warning;2=Alarm */
			
			3112 => [ /* High AC-Out voltage alarm */
				'service' => 'com.victronenergy.inverter',
				'register' => 3112,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/HighVoltageAcOut',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;1=Warning;2=Alarm */
			
			3113 => [ /* Low temperature alarm */
				'service' => 'com.victronenergy.inverter',
				'register' => 3113,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;1=Warning;2=Alarm */
			
			3114 => [ /* Low battery voltage alarm */
				'service' => 'com.victronenergy.inverter',
				'register' => 3114,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;1=Warning;2=Alarm */
			
			3115 => [ /* Low AC-Out voltage alarm */
				'service' => 'com.victronenergy.inverter',
				'register' => 3115,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/LowVoltageAcOut',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;1=Warning;2=Alarm */
			
			3116 => [ /* Overload alarm */
				'service' => 'com.victronenergy.inverter',
				'register' => 3116,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/Overload',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;1=Warning;2=Alarm */
			
			3117 => [ /* Ripple alarm */
				'service' => 'com.victronenergy.inverter',
				'register' => 3117,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarms/Ripple',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;1=Warning;2=Alarm */
			
			3125 => [ /* Firmware version */
				'service' => 'com.victronenergy.inverter',
				'register' => 3125,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/FirmwareVersion',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			3126 => [ /* Inverter on/off/eco */
				'service' => 'com.victronenergy.inverter',
				'register' => 3126,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Mode',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 2=On;4=Off;5=Eco */
			
			3127 => [ /* Inverter model */
				'service' => 'com.victronenergy.inverter',
				'register' => 3127,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/ProductId',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			3128 => [ /* Inverter state */
				'service' => 'com.victronenergy.inverter',
				'register' => 3128,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/State',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Off;1=Low power mode (search mode);2=Fault;9=Inverting (on) */
			
			3200 => [ /* Phase 1 voltage */
				'service' => 'com.victronenergy.genset',
				'register' => 3200,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/L1/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			3201 => [ /* Phase 2 voltage */
				'service' => 'com.victronenergy.genset',
				'register' => 3201,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/L2/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			3202 => [ /* Phase 3 voltage */
				'service' => 'com.victronenergy.genset',
				'register' => 3202,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 10,
				'path' => '/Ac/L3/Voltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_V,
			],
			
			3203 => [ /* Phase 1 current */
				'service' => 'com.victronenergy.genset',
				'register' => 3203,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/L1/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			3204 => [ /* Phase 2 current */
				'service' => 'com.victronenergy.genset',
				'register' => 3204,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/L2/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			3205 => [ /* Phase 3 current */
				'service' => 'com.victronenergy.genset',
				'register' => 3205,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Ac/L3/Current',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_AC_A,
			],
			
			3206 => [ /* Phase 1 power */
				'service' => 'com.victronenergy.genset',
				'register' => 3206,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/L1/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			3207 => [ /* Phase 2 power */
				'service' => 'com.victronenergy.genset',
				'register' => 3207,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/L2/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			3208 => [ /* Phase 3 power */
				'service' => 'com.victronenergy.genset',
				'register' => 3208,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 1,
				'path' => '/Ac/L3/Power',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_W,
			],
			
			3209 => [ /* Phase 1 frequency */
				'service' => 'com.victronenergy.genset',
				'register' => 3209,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L1/Frequency',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_HZ,
			],
			
			3210 => [ /* Phase 2 frequency */
				'service' => 'com.victronenergy.genset',
				'register' => 3210,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L2/Frequency',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_HZ,
			],
			
			3211 => [ /* Phase 3 frequency */
				'service' => 'com.victronenergy.genset',
				'register' => 3211,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Ac/L3/Frequency',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_HZ,
			],
			
			3212 => [ /* Generator model */
				'service' => 'com.victronenergy.genset',
				'register' => 3212,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/ProductId',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			3213 => [ /* Status */
				'service' => 'com.victronenergy.genset',
				'register' => 3213,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/StatusCode',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Standby;1=Startup 1;2=Startup 2;3=Startup 3;4=Startup 4;5=Startup 5;6=Startup 6;7=Startup 7;8=Running;9=Stopping;10=Error */
			
			3214 => [ /* Error */
				'service' => 'com.victronenergy.genset',
				'register' => 3214,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/ErrorCode',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No error;1=AC voltage L1 too low;2=AC frequency L1 too low;3=AC current too low;4=AC power too low;5=Emergency stop;6=Servo current too low;7=Oil pressure too low;8=Engine temperature too low;9=Winding temperature too low;10=Exhaust temperature too low;13=Starter current too low;14=Glow current too low;15=Glow current too low;16=Fuel holding magnet current too low;17=Stop solenoid hold coil current too low;18=Stop solenoid pull coil current too low;19=Optional DC out current too low;20=5V output voltage too low;21=Boost output current too low;22=Panel supply current too high;25=Starter battery voltage too low;26=Startup aborted (rotation too low);28=Rotation too low;29=Power contactor current too low;30=AC voltage L2 too low;31=AC frequency L2 too low;32=AC current L2 too low;33=AC power L2 too low;34=AC voltage L3 too low;35=AC frequency L3 too low;36=AC current L3 too low;37=AC power L3 too low;62=Fuel temperature too low;63=Fuel level too low;65=AC voltage L1 too high;66=AC frequency too high;67=AC current too high;68=AC power too high;70=Servo current too high;71=Oil pressure too high;72=Engine temperature too high;73=Winding temperature too high;74=Exhaust temperature too low;77=Starter current too low;78=Glow current too high;79=Glow current too high;80=Fuel holding magnet current too high;81=Stop solenoid hold coil current too high;82=Stop solenoid pull coil current too high;83=Optional DC out current too high;84=5V output voltage too high;85=Boost output current too high;89=Starter battery voltage too high;90=Startup aborted (rotation too high);92=Rotation too high;93=Power contactor current too high;94=AC voltage L2 too high;95=AC frequency L2 too high;96=AC current L2 too high;97=AC power L2 too high;98=AC voltage L3 too high;99=AC frequency L3 too high;100=AC current L3 too high;101=AC power L3 too high;126=Fuel temperature too high;127=Fuel level too high;130=Lost control unit;131=Lost panel;132=Service needed;133=Lost 3-phase module;134=Lost AGT module;135=Synchronization failure;137=Intake airfilter;139=Lost sync. module;140=Load-balance failed;141=Sync-mode deactivated;142=Engine controller;148=Rotating field wrong;149=Fuel level sensor lost;150=Init failed;151=Watchdog;152=Out: winding;153=Out: exhaust;154=Out: Cyl. head;155=Inverter over temperature;156=Inverter overload;157=Inverter communication lost;158=Inverter sync failed;159=CAN communication lost;160=L1 overload;161=L2 overload;162=L3 overload;163=DC overload;164=DC overvoltage;165=Emergency stop;166=No connection */
			
			3215 => [ /* Auto start */
				'service' => 'com.victronenergy.genset',
				'register' => 3215,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/AutoStart',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Disabled;1=Enabled */
			
			3216 => [ /* Engine load */
				'service' => 'com.victronenergy.genset',
				'register' => 3216,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Engine/Load',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_PERCENT,
			],
			
			3217 => [ /* Engine speed */
				'service' => 'com.victronenergy.genset',
				'register' => 3217,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Engine/Speed',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_RPM,
			],
			
			3218 => [ /* Engine operating hours */
				'service' => 'com.victronenergy.genset',
				'register' => 3218,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 0.01,
				'path' => '/Engine/OperatingHours',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_SECONDS,
			],
			
			3219 => [ /* Engine coolant temperature */
				'service' => 'com.victronenergy.genset',
				'register' => 3219,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Engine/CoolantTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_CELSIUS,
			],
			
			3220 => [ /* Engine winding temperature */
				'service' => 'com.victronenergy.genset',
				'register' => 3220,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Engine/WindingTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_CELSIUS,
			],
			
			3221 => [ /* Engine exhaust temperature */
				'service' => 'com.victronenergy.genset',
				'register' => 3221,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 10,
				'path' => '/Engine/ExaustTemperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_CELSIUS,
			],
			
			3222 => [ /* Starter voltage */
				'service' => 'com.victronenergy.genset',
				'register' => 3222,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/StarterVoltage',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_DC_V,
			],
			
			3223 => [ /* Start generator */
				'service' => 'com.victronenergy.genset',
				'register' => 3223,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Start',
				'writable' => true,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Stop;1=Start */
			
			3300 => [ /* Product ID */
				'service' => 'com.victronenergy.temperature',
				'register' => 3300,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/ProductId',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			3301 => [ /* Temperature scale factor */
				'service' => 'com.victronenergy.temperature',
				'register' => 3301,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 100,
				'path' => '/Scale',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			3302 => [ /* Temperature offset */
				'service' => 'com.victronenergy.temperature',
				'register' => 3302,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 100,
				'path' => '/Offset',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			3303 => [ /* Temperature type */
				'service' => 'com.victronenergy.temperature',
				'register' => 3303,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/TemperatureType',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Battery;1=Fridge;2=Generic */
			
			3304 => [ /* Temperature */
				'service' => 'com.victronenergy.temperature',
				'register' => 3304,
				'type' => ModbusRegister::TYPE_INT16,
				'scale' => 100,
				'path' => '/Temperature',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_CELSIUS,
			],
			
			3305 => [ /* Temperature status */
				'service' => 'com.victronenergy.temperature',
				'register' => 3305,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Status',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=OK;1=Disconnected;2=Short circuited;3=Reverse Polarity;4=Unknown */
			
			3400 => [ /* Aggregate (measured value) */
				'service' => 'com.victronenergy.pulsemeter',
				'register' => 3400,
				'type' => ModbusRegister::TYPE_UINT32,
				'scale' => 1,
				'path' => '/Aggregate',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_M3,
			],
			
			3402 => [ /* Count (number of pulses on meter) */
				'service' => 'com.victronenergy.pulsemeter',
				'register' => 3402,
				'type' => ModbusRegister::TYPE_UINT32,
				'scale' => 1,
				'path' => '/Count',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			3420 => [ /* Count */
				'service' => 'com.victronenergy.digitalinput',
				'register' => 3420,
				'type' => ModbusRegister::TYPE_UINT32,
				'scale' => 1,
				'path' => '/Count',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			],
			
			3422 => [ /* State */
				'service' => 'com.victronenergy.digitalinput',
				'register' => 3422,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/State',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=Low;1=High;2=Off;3=On;4=No;5=Yes;6=Open;7=Closed;8=Alarm;9=OK;10=Running;11=Stopped */
			
			3423 => [ /* Alarm */
				'service' => 'com.victronenergy.digitalinput',
				'register' => 3423,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Alarm',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 0=No alarm;2=Alarm */
			
			3424 => [ /* Type */
				'service' => 'com.victronenergy.digitalinput',
				'register' => 3424,
				'type' => ModbusRegister::TYPE_UINT16,
				'scale' => 1,
				'path' => '/Type',
				'writable' => false,
				'unit' => ModbusRegister::UNITS_NONE,
			], /* 2=Door;3=Bilge pump;4=Bilge alarm;5=Burglar alarm;6=Smoke alarm;7=Fire alarm;8=CO2 alarm */
		];
	}
}