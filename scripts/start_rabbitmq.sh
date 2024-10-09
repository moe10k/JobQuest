#!/bin/bash

#Code checks if Erlang is installed
if dpkg -l | grep -q erlang; then
    echo "Erlang already installed"
else
    echo "Erlang not installed. Installing Erlang now.."

    #update package list
    sudo apt-get update

    #install erlang
    sudo apt-get install -y erlang

    #message if successful
    echo "Erlang has been successfully installed"
fi

#Check to see if RabbitMQ is installed
if dpkg -l | grep -q rabbitmq-server; then
    echo "RabbitMQ already installed"
else
    echo "RabbitMQ not installed. Installing RabbitMQ now.."
    
    #update package list
    sudo apt-get update

    #install rabbitmq
    sudo apt-get install -y rabbitmq-server

    echo "RabbitMQ has been successfully installed"
fi

#Enable RabbitMQ to start on boot
sudo systemctl enable rabbitmq-server

#Start rabbitmq
sudo systemctl start rabbitmq-server

echo "RabbitMQ service has been enabled and started, run sudo systemctl status rabbitmq-server to check status"
#!/bin/bash

#Code checks if Erlang is installed
if dpkg -l | grep -q erlang; then
    echo "Erlang already installed"
else
    echo "Erlang not installed. Installing Erlang now.."

    #update package list
    sudo apt-get update

    #install erlang
    sudo apt-get install -y erlang

    #message if successful
    echo "Erlang has been successfully installed"
fi

#Check to see if RabbitMQ is installed
if dpkg -l | grep -q rabbitmq-server; then
    echo "RabbitMQ already installed"
else
    echo "RabbitMQ not installed. Installing RabbitMQ now.."
    
    #update package list
    sudo apt-get update

    #install rabbitmq
    sudo apt-get install -y rabbitmq-server

    echo "RabbitMQ has been successfully installed"
fi

#Enable RabbitMQ to start on boot
sudo systemctl enable rabbitmq-server

#Start rabbitmq
sudo systemctl start rabbitmq-server

echo "RabbitMQ service has been enabled and started, run sudo systemctl status rabbitmq-server to check status"