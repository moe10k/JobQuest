<?php

// Define RabbitMQ nodes in order of priority
$rabbitmqNodes = [
    [
        'host' => '10.147.17.228',  // Primary Node (Nadia)
        'node_name' => 'rabbit@messaging',  // Node identifier
        'port' => 5672,
        'username' => 'guest',
        'password' => 'guest',
    ],
    [
        'host' => '10.147.17.11',   // Secondary Node (Andre)
        'node_name' => 'rabbit@andre',  // Node identifier
        'port' => 5672,
        'username' => 'guest',
        'password' => 'guest',
    ],
    [
        'host' => '10.147.17.146',  // Fallback Node (Jose)
        'node_name' => 'rabbit@database490',  // Node identifier
        'port' => 5672,
        'username' => 'guest',
        'password' => 'guest',
    ],
];

// Function to check if RabbitMQ node is reachable
function isRabbitMqReachable($node) {
    $connection = @fsockopen($node['host'], $node['port'], $errno, $errstr, 5);
    if ($connection) {
        fclose($connection);
        return true;
    }
    return false;
}

// Attempt to connect to each node in order
foreach ($rabbitmqNodes as $node) {
    if (isRabbitMqReachable($node)) {
        // If the connection is successful, return the connection details for use
        return [
            'rabbitmq' => [
                'host' => $node['host'],
                'port' => $node['port'],
                'username' => $node['username'],
                'password' => $node['password'],
            ],
        ];
    }
}

// If no nodes are reachable, return null
return null;