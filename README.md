# Victron Venus Data Monitor

This code is an implementation of two data monitoring options for devices running Victron's Venus OS. Data is ingested, processed, and inserted into a local instance of InfluxDB for the purpose of presenting via a Grafana dashboard. It is packaged as a Laravel app to leverage the CLI framework and potentially eventually support a web-based management UI.

## Requirements

- PHP 7.1+
- InfluxDB 1.1.1+
- Redis (tested on 4.0.9)

## Usage

Dependencies to this are managed by [Composer](https://getcomposer.org). Upon initial installation, they must be installed via `composer install`. Following that, the two commands may be used:

`php artisan modbus:query {host} {--port=502} {--daemon} {--interval=5}`

Runs the ModbusTCP query, inserting all new records into InfluxDB. If `--daemon` is given, it will loop rather than exiting after the first query. The interval may also be customized from the default 5 seconds by providing `--interval=<seconds>`.

`php artisan mqtt:monitor {host} {--port=1883}`

Starts the MQTT subscriber, inserting all new records into InfluxDB. Because MQTT functions on a notification pattern rather than polling, behavior of this is equivalent to the ModBusTCP command above with `--daemon` set.

In the `services` directory, a systemd service definition is provided for each command for those wanting it to start with the system. These can be installed in `/etc/systemd/system/`, systemd reloaded via `systemctl daemon-reload`, and then started/stopped/etc via the usual commands (e.g., `systemctl start venus-modbus`, `systemctl stop venus-modbus`, `systemctl enable venus-modbus`, etc).

## Implementation Details

### ModbusTCP

One of the protocols implemented is ModbusTCP. In general, this is the most efficient method of pulling data out of the system for frequently-updated statistics, but it lacks exposure of some values (e.g., GPS altitude) and requires polling. That said, the majority of the data collection is routed through this protocol (though either would work).

Victron provides a register map [here](https://github.com/victronenergy/dbus_modbustcp/blob/master/CCGX-Modbus-TCP-register-list.xlsx).

### MQTT

Venus OS includes an MQTT broker, which can be turned on if wanted. When enabled, a service that rebroadcasts the system's D-Bus to the MQTT broker activates. [This repository](https://github.com/victronenergy/dbus-mqtt) contains more information, and a list of available paths can be found [here](https://github.com/victronenergy/venus/wiki/dbus).

In actual use, the MQTT broker is not my first choice. When trying to subscribe to more than a small number of specific notifications, it will stop sending any at all. The only workaround I've found is to subscribe to every notification and filter it within a custom listener (part of this implementation). That said, it is the only source for some values.

## Debugging

The constant `MONITOR_DEBUG` may be defined to cause the commands out log verbose info to the console.