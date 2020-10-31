LED Bridge
==========

A really simple bridge to convert raw UDP packets from (e.g.) Hyperion-NG into
commands for a Tasmota device.

Use a Tasmota LED strip as bias lighting.

Just `php run.php` to listen; and set up a udpraw LED device in Hyperion.

Address of the MQTT server, and the channel name, are hardcoded in run.php but
simple enough to edit.
