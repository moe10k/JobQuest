#!/bin/bash

#Defining master and slave nodes
MASTER_NODE="rabbbit@messaging"
SLAVE_NODES=("rabbit@andre" "rabbit@database490")
SLAVE_IPS=("10.147.17.11" "10.147.17.146")
SSH_USERS=("awh9" "jose")

check_node_status() {
    local node=$1
    rabbitmqctl -n "$node" status > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "Node $node is running."
        return 0
    else
        echo "Node $node is NOT running."
        return 1
    fi
}

# Function to start RabbitMQ service on a remote slave node
start_slave_node() {
    local ip=$1
    local user=$2
    echo "Starting RabbitMQ on $ip..."
    ssh "$user@$ip" 'sudo systemctl start rabbitmq-server'
}

# Function to join a node to the cluster if it's not part of it
join_cluster() {
    local slave_node=$1
    echo "Joining $slave_node to the cluster..."
    rabbitmqctl -n "$MASTER_NODE" stop_app
    rabbitmqctl -n "$MASTER_NODE" reset
    rabbitmqctl -n "$MASTER_NODE" start_app
    rabbitmqctl -n "$slave_node" join_cluster "$MASTER_NODE"
    rabbitmqctl -n "$slave_node" start_app
}

# Iterate through the slave nodes
for i in "${!SLAVE_NODES[@]}"; do
    slave_node="${SLAVE_NODES[$i]}"
    slave_ip="${SLAVE_IPS[$i]}"
    ssh_user="${SSH_USERS[$i]}"

    # Check if the slave node is running
    if ! check_node_status "$slave_node"; then
        # If the slave node is not running, start it
        start_slave_node "$slave_ip" "$ssh_user"
        
        # Wait for the node to start (adjust as needed)
        sleep 5
        
        # Check if the slave node is now part of the cluster
        if ! check_node_status "$slave_node"; then
            echo "Node $slave_node is still not available after starting the service."
        else
            # Join the node to the cluster if it isn't already
            join_cluster "$slave_node"
        fi
    fi
done
