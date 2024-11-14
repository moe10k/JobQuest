#!/bin/bash

# Function to log output to file
log_output() {
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" | tee -a frontend_backend_log.log
}

# Check and set VM's VPN IP dynamically (ZeroTier IP)
VPN_INTERFACE="ztbtoss2h4"  # Replace with your ZeroTier interface name
VM_IP=$(ifconfig "$VPN_INTERFACE" | grep 'inet ' | awk '{print $2}')  # Get VPN IP

if [ -z "$VM_IP" ]; then
    log_output "Error: Unable to get VPN IP address for this VM."
    exit 1
fi
log_output "VM IP (ZeroTier): $VM_IP"

# Set primary and secondary IPs
PRIMARY_IP="10.147.17.11"
SECONDARY_IP="10.147.17.65"

# Check if VM's IP matches primary or secondary IP
if [ "$VM_IP" == "$PRIMARY_IP" ]; then
    ROLE="primary"
elif [ "$VM_IP" == "$SECONDARY_IP" ]; then
    ROLE="backup"
else
    log_output "Error: This VM's IP does not match primary or secondary IP."
    exit 1
fi
log_output "Role set to: $ROLE"

# Define Gunicorn port
GUNICORN_PORT="7012"

# Find and kill the Gunicorn process running on the specified IP and port
GUNICORN_PID=$(ps aux | grep "gunicorn" | grep "$PRIMARY_IP:$GUNICORN_PORT" | awk '{print $2}')

if [ -z "$GUNICORN_PID" ]; then
    log_output "No Gunicorn process found on $PRIMARY_IP:$GUNICORN_PORT"
else
    log_output "Stopping Gunicorn process with PID: $GUNICORN_PID"
    kill $GUNICORN_PID
    log_output "Gunicorn process stopped."
fi

# Find and kill the Nginx process
NGINX_PID=$(ps aux | grep "nginx: worker" | awk '{print $2}')

if [ -z "$NGINX_PID" ]; then
    log_output "No Nginx process found."
else
    log_output "Stopping Nginx process with PID: $NGINX_PID"
    kill $NGINX_PID
    log_output "Nginx process stopped."
fi
