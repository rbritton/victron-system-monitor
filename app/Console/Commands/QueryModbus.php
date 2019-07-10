<?php

namespace App\Console\Commands;

use App\ModbusRegister;
use App\DataPath;
use Illuminate\Console\Command;

class QueryModbus extends Command
{
	const UNIT_SYSTEM = 100;
	const UNIT_INVERTER = 246;
	const UNIT_SOLAR = 245;
	const UNIT_BMV = 247;
	
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'modbus:query {host} {--port=502} {--daemon} {--interval=5}'; 
	
	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Queries the Venus ModbusTCP feed';
	
	private $_message = 1;
	private $_portalID = null;
	
	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		$server = $this->argument('host');
		$port = $this->hasOption('port') ? (int) $this->option('port') : 502;
		$daemon = $this->hasOption('daemon');
		$interval = $this->hasOption('interval') ? (int) $this->option('interval') : 5;
		
		if ($daemon) {
			if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) { echo "Starting Daemon\n"; }
			while (true) {
				$success = $this->_query($server, $port, $interval);
				if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) { echo "\n\n===============\n\n"; }
				sleep($success ? $interval : $interval * 3);
			}
		}
		else {
			$this->_query($server, $port, $interval);
		}
	}
	
	private function _query($server, $port, $interval) {
		try {
			$socket = stream_socket_client("tcp://" . $server . ":" . $port, $errno, $errstr, 60, STREAM_CLIENT_CONNECT);
			if (!$socket) {
				error_log("stream_socket_create() $errno, $errstr \n");
				return false;
			}
			
			stream_set_timeout($socket, $interval);
			stream_set_blocking($socket, 0);
		}
		catch (\Exception $e) {
			if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) { echo $e->getMessage(); }
			return false;
		}
		
		try {
			$registers = $this->_fetch($socket, self::UNIT_SYSTEM, 800, 27);
			$this->_process($registers);
			
			$registers = $this->_fetch($socket, self::UNIT_SYSTEM, 840, 7);
			$this->_process($registers);
			
			$registers = $this->_fetch($socket, self::UNIT_SYSTEM, 860, 1);
			$this->_process($registers);
			
			$registers = $this->_fetch($socket, self::UNIT_SYSTEM, 2800, 8); //GPS
			$this->_process($registers);
			
			$registers = $this->_fetch($socket, self::UNIT_SOLAR, 771, 20);
			$this->_process($registers);
			
			$registers = $this->_fetch($socket, self::UNIT_BMV, 259, 20);
			$this->_process($registers);
			
			$registers = $this->_fetch($socket, self::UNIT_INVERTER, 3, 31);
			$this->_process($registers);
		}
		catch (\Exception $e) {
			echo $e->getMessage() . "\n";
			error_log($e);
			stream_socket_shutdown($socket, STREAM_SHUT_WR);
			return false;
		}
		
		stream_socket_shutdown($socket, STREAM_SHUT_WR);
		return true;
	}
	
	private function _process($registers) {
		$this->_debug($registers);
		if ($registers) {
			foreach ($registers as $id => $r) {
				if ($id == 800) {
					$this->_portalID = $r->value;
				}
				else {
					$instance = DataPath::instance($this->_portalID, preg_replace('/^com\.victronenergy\./i', '', $r->service), $r->path, $r->unit);
					if ($instance) {
						$instance->write($r->value);
					}
				}
			}
		}
	}
	
	private function _debug($registers) {
		if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) {
			if ($registers) {
				foreach ($registers as $r) {
					echo $r . "\n";
				}
			}
		}
	}
	
	private function _fetch($socket, $unit, $start, $count) {
		if ($this->_request($socket, $unit, 3, pack('n', $start) . pack('n', $count))) {
			$expected = ModbusRegister::length($start, $count);
			$response = $this->_read($socket, 9 + $expected);
			if ($response !== false) {
				$data = substr($response, 9); //Strip headers 
				$registers = ModbusRegister::parse($data, $start, $count);
				return $registers;
			}
		}
		return false;
	}
	
	/**
	 * Writes a ModbusTCP request to $socket.
	 * 
	 * $unit may be one of:
	 * - 100 (the core system, any value of service name com.victronenergy.system)
	 * - 246 (CCGX VE.Bus port)
	 * - 247 (CCGX VE.Direct 1 port)
	 * - 245 (CCGX VE.Direct 2 port/Venus GX VE.Direct 1 port)
	 * - 243 (Venus GX VE.Direct 2 port)
	 * - 242 (Venus GX VE.Bus port)
	 * 
	 * $function may be one of:
	 * - 3 (ReadHoldingRegisters)
	 * - 4 (ReadInputRegisters, identical to ReadHoldingRegisters)
	 * - 6 (WriteSingleRegister)
	 * - 16 (WriteMultipleRegisters)
	 * 
	 * Packet structure for reading is <2 bytes: message ID><2 bytes: protocol><2 bytes: remaining data length><1 byte: unit identifier><1 byte: function><2 bytes: start register><2 bytes: register count>
	 * 
	 * @param resource $socket
	 * @param int $unit
	 * @param int $function
	 * @param string $data
	 */
	private function _request($socket, $unit, $function, $data) {
		$length = 8 + strlen($data);
		$payload = pack('n', $this->_message /* ID number for the message */) . pack('n', 0 /* protocol identifier for ModbusTCP */) . pack('n', $length - 6 /* length of the remaining request */) . pack('C', $unit) . pack('C', $function) . $data;
		if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) { echo 'Wrote ' . implode(' ', str_split(bin2hex($payload), 2)) . "\n"; }
		$written = fwrite($socket, $payload, $length);
		$this->_message++;
		return ($length === $written);
	}
	
	/**
	 * Packet structure for reading is <2 bytes: message ID of request><2 bytes: protocol><2 bytes: remaining data length><1 byte: unit identifier><1 byte: function><1 byte: checksum><remaining: contents of registers>
	 * 
	 * @param $socket
	 * @param $length
	 * @return bool|string
	 */
	private function _read($socket, $length) {
		$start = microtime(true);
		$timeout = ini_get('default_socket_timeout');
		$string = '';
		$remaining = $length;
		while (!$this->_feof($socket) && (microtime(true) - $start) < $timeout && $remaining > 0) {
			$chunk = fread($socket, $remaining);
			if ($chunk === false) {
				return false;
			}
			
			$string .= $chunk;
			$remaining = $length - strlen($string);
		}
		if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) { echo 'Received ' . implode(' ', str_split(bin2hex($string), 2)) . "\n"; }
		if ($remaining == 0) {
			return $string;
		}
		throw new \RuntimeException('Connection interrupted');
	}
	
	private function _feof($fp, &$start = NULL) {
		$start = microtime(true);
		return feof($fp);
	}
}
