#!/bin/bash

# Function to log output to file
log_output() {
    export TZ="America/New_York"
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" | tee -a stop_services_log.log # Log output to file
    unset TZ
}

# Check and set VM's VPN IP dynamically (Tailscale IP)
VPN_INTERFACE="tailscale0"  
VM_IP=$(ifconfig "$VPN_INTERFACE" | grep 'inet ' | awk '{print $2}')  # Get VPN IP

if [ -z "$VM_IP" ]; then
    log_output "Error: Unable to get VPN IP address for this VM."
    exit 1
fi
log_output "VM IP (tailscale): $VM_IP"

# Set primary and secondary IPs
PRIMARY_IP="100.77.152.51"
SECONDARY_IP="100.64.1.4"

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

# Stop the Nginx service using systemctl
log_output "Stopping Nginx service using systemctl."
sudo systemctl stop nginx
log_output "Nginx service stopped."

# Print a simple message to the terminal
echo "Processes stopped."
