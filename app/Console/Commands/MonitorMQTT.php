<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Bluerhinos\phpMQTT;
use App\DataPath;

class MonitorMQTT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:monitor {host} {--port=1883}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitors the Venus MQTT feed';
    
    private $_portalID = null;
    private $_keepalive = 0;

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
		$port = $this->hasArgument('port') ? $this->argument('port') : 1883;
		$username = '';
		$password = '';
		$client_id = uniqid();
	
		if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) { echo "Starting Daemon\n"; }
		
		do {
			try {
				$mqtt = new phpMQTT($server, $port, $client_id);
				if (!$mqtt->connect(true, NULL, $username, $password)) {
					throw new \Exception('Connection failed, sleeping and retrying');
				}
				
				// Portal ID
				if (empty($this->_portalID)) {
					$topics['N/+/system/0/Serial'] = array("qos" => 1, "function" => array($this, '_messageHandler'));
					$mqtt->subscribe($topics, 1);
					
					while($mqtt->proc()) {
						if (!empty($this->_portalID)) {
							$this->_keepalive = time();
							break;
						}
					}
					
					if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) { echo "Portal ID: {$this->_portalID}\n"; }
				}
				
				/*
				 * A large number of specific topics will result in no notifications being sent, so it's generally better
				 * to implement the selective listening in code if needing a lot of different values and subscribe to
				 * everything.
				 */
				$topics = [
					"N/{$this->_portalID}/gps/0/Altitude" => ['qos' => 1, 'function' => [$this, '_messageHandler']],
					//Uncomment to subscribe to everything
					//"N/{$this->_portalID}/#" => ['qos' => 1, 'function' => [$this, '_messageHandler']],
				];
				$mqtt->subscribe($topics, 1);
				while($mqtt->proc()) {
					if ($this->_keepalive + 50 < time()) {
						$mqtt->publish("R/{$this->_portalID}/system/0/Serial", '');
						$this->_keepalive = time();
					}
				}
				
				$mqtt->close();
			}
			catch (\Exception $e) {
				if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) { echo "\n" . 'Exception: ' . $e->getMessage(); }
				sleep(10);
			}
		} while (true);
		
		return true;
    }
    
    public function _messageHandler($topic, $message)
	{
		try {
			if (preg_match('~^([NRW])/[^/]+/([a-z]+)/(\d+)(/.+)$~', $topic, $matches)) {
				$json = \GuzzleHttp\json_decode($message, true);
				if (isset($json['value'])) {
					list(, $action, $service, $device, $path) = $matches;
					if ($service == 'system' && $path == '/Serial') {
						$this->_portalID = $json['value'];
					}
					
					if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) { echo "\n{$path} (device {$device} on {$service}): {$json['value']}"; }
					$instance = DataPath::instance($this->_portalID, $service, $path, $device);
					if ($instance) {
						$instance->write($json['value']);
					}
				}
			}
		}
		catch (\Exception $e) {
			if (defined('MONITOR_DEBUG') && MONITOR_DEBUG) { echo "\n" . 'Exception: ' . $e->getMessage(); }
		}
	}
}
