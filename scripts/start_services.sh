#!/bin/bash

sudo systemctl start rabbitmq-server

sudo systemctl start mysql

./run_php_apache.sh

./run_flask.sh