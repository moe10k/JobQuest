#!/bin/bash

#Start RabbitMQ service on each node
sudo systemctl start rabbitmq-server

#Check and join cluster if necessary
CLUSTER_STATUS=$(rabbitmqctl cluster_status | grep rabbit@messaging)
if [[ -z $CLUSTER_STATUS ]]; then
    echo "Joining cluster . . ."
    rabbitmqctl stop_app
    rabbitmqctl join_cluster rabbit@messaging
    rabbitmqctl start_app
fi

#Check status after startup
rabbitmqctl cluster_status

#Sends a message to indicate cluster is up
echo "RabbitMQ cluster is running."