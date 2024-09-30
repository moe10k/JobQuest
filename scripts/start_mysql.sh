#!/bin/bash

#Start MySQL Workbench
mysql-workbench-community &

#Start MySQL Server
echo "Starting MySQL"
sudo service mysql start
sudo mysql

