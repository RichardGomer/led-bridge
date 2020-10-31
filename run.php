<?php

use BinSoul\Net\Mqtt\Client\React\ReactMqttClient;
use BinSoul\Net\Mqtt\Connection;
use BinSoul\Net\Mqtt\DefaultMessage;
use BinSoul\Net\Mqtt\DefaultSubscription;
use BinSoul\Net\Mqtt\Message;
use BinSoul\Net\Mqtt\Subscription;
use React\Socket\TcpConnector;

include 'vendor/autoload.php';

$MQTTHOST = '10.0.0.8';
$TOPIC = "led1";

// Setup client
$loop = \React\EventLoop\Factory::create();
$connector = new TcpConnector($loop); // This doesn't do DNS resolution so must connect via
$mqclient = new ReactMqttClient($connector, $loop);

// Bind to events
$mqclient->on('open', function () use ($mqclient) {
    // Network connection established
    echo sprintf("MQTT Open: %s:%d\n", $mqclient->getHost(), $mqclient->getPort());
});

$mqclient->on('close', function () use ($mqclient, $loop) {
    // Network connection closed
    echo sprintf("MQTT Close: %s:%d\n", $mqclient->getHost(), $mqclient->getPort());

    $loop->stop();
});

$mqclient->on('connect', function (Connection $connection) {
    // Broker connected
    echo sprintf("MQTT Connect: client=%s\n", $connection->getClientID());
});

$mqclient->on('disconnect', function (Connection $connection) {
    // Broker disconnected
    echo sprintf("MQTT Disconnect: client=%s\n", $connection->getClientID());
});


// Connect to broker
$mqclient->connect($MQTTHOST)->then(
    function () use ($mqclient, $loop) {

        // Set up UDP client
        $factory = new React\Datagram\Factory($loop);
        $factory->createServer('127.0.0.1:1446')->then(function (React\Datagram\Socket $udserver) use ($mqclient, $loop) {
            echo "UDP Listening on ".$udserver->getLocalAddress()."\n";

            $lastpacket = 0;
            $timeout = 3; // Timeout after inactivity
            $loop->addPeriodicTimer(1, function() use (&$lastpacket, $timeout, $mqclient) {
                if($lastpacket > 0 && $lastpacket < time() - $timeout) {
                    echo "No packets, switching off\n";
                    $mqclient->publish(new DefaultMessage('cmnd/led1/Power0', 'off'));
                    $lastpacket = 0;
                }
            });

            $udserver->on('message', function($message, $address, $udserver) use ($mqclient, &$lastpacket) {

                // Parse the UDP packet
                list($r, $g, $b) = str_split($message, 1);

                $r = ord($r);
                $g = ord($g);
                $b = ord($b);

                $msg = "$r,$g,$b";

                $lastpacket = time();

                // Push to MQTT
                $mqclient->publish(new DefaultMessage('cmnd/led1/Color1', $msg))
                    ->then(function (Message $message) {
                        echo sprintf("%s => %s\n", $message->getTopic(), $message->getPayload());
                    })
                    ->otherwise(function (\Exception $e) {
                        echo sprintf("MQTT Error: %s\n", $e->getMessage());
                    });
            });
        });
    }
);

$loop->run();
