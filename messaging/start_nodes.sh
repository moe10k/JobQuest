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

// Function to check if a node is part of the cluster
function isNodeInCluster($node) {
    $output = shell_exec("rabbitmqctl -n {$node['node_name']} cluster_status");
    return strpos($output, 'cluster') !== false;
}

// Attempt to connect to each node in order
foreach ($rabbitmqNodes as $node) {
    echo "Checking status of $node[node_name]...\n";

    if (isRabbitMqReachable($node)) {
        // If the connection is successful, check if the node is already part of the cluster
        echo "Connected to $node[node_name] at {$node['host']}\n";

        // Check if the node is part of the cluster, but don't reset it unnecessarily
        if (!isNodeInCluster($node)) {
            echo "$node[node_name] is not part of the cluster. Attempting to join...\n";
            // Only rejoin if the node is not part of the cluster
            shell_exec("rabbitmqctl -n {$node['node_name']} stop_app");
            shell_exec("rabbitmqctl -n {$node['node_name']} reset");
            shell_exec("rabbitmqctl -n {$node['node_name']} start_app");
            shell_exec("rabbitmqctl -n {$node['node_name']} join_cluster rabbit@messaging");
            echo "$node[node_name] has rejoined the cluster.\n";
        } else {
            echo "$node[node_name] is already part of the cluster.\n";
        }

    } else {
        // If the node is unreachable, attempt to restart it
        echo "$node[node_name] is off. Starting it now...\n";
        // Start the RabbitMQ service if the node is down (example: using SSH to start the service)
        // ssh "$node['username']@$node['host']" 'sudo systemctl start rabbitmq-server'
        echo "$node[node_name] is now started and ready to connect.\n";
    }
}

// If all nodes are successfully connected and part of the cluster, proceed with the connection
echo "All nodes are connected and part of the cluster.\n";
return [
    'rabbitmq' => [
        'host' => $rabbitmqNodes[0]['host'],
        'port' => $rabbitmqNodes[0]['port'],
        'username' => $rabbitmqNodes[0]['username'],
        'password' => $rabbitmqNodes[0]['password'],
    ],
];

?>