#!/bin/bash

# installs apache if not installed
if ! command -v apache2 &> /dev/null; then
    echo "Apache is not installed. Installing Apache..."
    sudo apt update
    sudo apt upgrade -y
    sudo apt install apache2 -y
else
    echo "Apache is already installed."
fi

# installs php if not installed
if ! command -v php &> /dev/null; then
    echo "PHP is not installed. Installing PHP..."
    sudo apt install php libapache2-mod-php -y
else
    echo "PHP is already installed."
fi

# checks the status, if not active then starts server
apache_status=$(sudo systemctl is-active apache2)

if [ "$apache_status" == "active" ]; then
    echo "Apache is running."
else
    echo "Apache is not running. Starting Apache service..."
    sudo systemctl start apache2
fi

# Create a basic index.php file if it doesn't exist but need to find out how to make the directory consistent with everyone else's VM
if [ ! -f "~/Capstone-Group-03/backend/index.php" ]; then
    echo "Creating index.php..."
    sudo bash -c 'cat <<EOF > ~/Capstone-Group-03/backend/index.php
<?php
    echo "Hello, World!";
?>
EOF'
    echo "index.php created"
else
    echo "index.php already exists"
fi

echo "The PHP backend is running on Apache."
