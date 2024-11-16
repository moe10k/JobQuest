#!/bin/bash

# Set log file for traffic
log_file="frontend_backend_log.log"

# Function to log output to file
log_output() {
    export TZ="America/New_York"
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" | tee -a $log_file
    unset TZ
}

# Detect the operating system and set appropriate variables
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    activate_venv="../frontend/venv/bin/activate"  # Correct path for Linux/MacOS
    python_cmd="python3"
elif [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" || "$OSTYPE" == "win32" ]]; then
    activate_venv="../frontend/venv/Scripts/activate"  # Correct path for Windows
    python_cmd="python"
else
    log_output "Unsupported OS."
    exit 1
fi

# Navigate to the project directory
cd ../frontend || { log_output "Frontend directory not found"; exit 1; }

# Create virtual environment if it doesn't exist
if [ ! -d "venv" ]; then 
    log_output "Virtual environment not found! Creating it..."
    $python_cmd -m venv venv
    if [ $? -ne 0 ]; then
        log_output "Error: Failed to create virtual environment."
        exit 1
    fi
fi

# Activate virtual environment with the correct path
log_output "Attempting to activate the virtual environment..."
if ! source "$activate_venv"; then
    log_output "Error: Failed to activate virtual environment."
    exit 1
fi
log_output "Virtual environment activated successfully."

# Install required Python packages
log_output "Installing required Python packages..."
$python_cmd -m pip install -q requests pika Flask Flask-Mail mysql-connector-python itsdangerous gunicorn

# Check if Gunicorn is installed and install it if necessary
if ! command -v gunicorn &> /dev/null; then
    log_output "Gunicorn not found. Installing Gunicorn..."
    pip install gunicorn
    if [ $? -ne 0 ]; then
        log_output "Error: Failed to install Gunicorn."
        exit 1
    fi
else
    log_output "Gunicorn is already installed."
fi

# Create app.py if it doesn't exist for backend
if [ ! -f "app.py" ]; then
    log_output "Creating app.py for backend..."
    cat <<EOF > app.py
from flask import Flask, request
import logging

logging.basicConfig(
    filename='$log_file',  
    level=logging.INFO,  
    format='%(asctime)s - %(levelname)s - %(message)s',  
)

app = Flask(__name__)

@app.before_request
def log_request_info():
    app.logger.info('Request: %s %s', request.method, request.url)

@app.after_request
def log_response_info(response):
    app.logger.info('Response: %s %s', response.status, request.url)
    return response

@app.route('/')
def hello():
    return "Hello from the backend server!"

if __name__ == "__main__":
    app.run(debug=True, port=7012)  # Default port for Gunicorn
EOF
    log_output "app.py created successfully."
else
    log_output "app.py already exists."
fi

# Set Flask app environment variables
export FLASK_APP=app.py
export FLASK_ENV=production

# Stop any existing backend processes on port 7012
log_output "Stopping any existing processes on port 7012..."
sudo fuser -k 7012/tcp || true

# Check and set VM's VPN IP dynamically (ZeroTier IP)
VPN_INTERFACE="ztbtoss2h4"  
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
    log_output "Role set to: primary"
elif [ "$VM_IP" == "$SECONDARY_IP" ]; then
    ROLE="backup"
    log_output "Role set to: backup"
else
    log_output "Error: This VM's IP does not match primary or secondary IP."
    exit 1
fi

# Function to start Gunicorn
start_gunicorn() {
    log_output "Starting Gunicorn on ${VM_IP}:7012..."
    # Use full path to gunicorn if necessary
    GUNICORN_PATH=$(which gunicorn)
    if [ -z "$GUNICORN_PATH" ]; then
        log_output "Error: Gunicorn not found in virtual environment!"
        exit 1
    fi
    $GUNICORN_PATH --bind ${VM_IP}:7012 --workers 4 --access-logfile $log_file --error-logfile $log_file app:app &  # Run Gunicorn in the background
    export GUNICORN_PID=$!
}

# Function to stop Gunicorn if running
stop_gunicorn() {
    if [ -n "$GUNICORN_PID" ] && kill -0 "$GUNICORN_PID" 2>/dev/null; then
        log_output "Stopping Gunicorn..."
        kill "$GUNICORN_PID"
        unset GUNICORN_PID
    fi
}

# If the role is primary, start Gunicorn
if [ "$ROLE" == "primary" ]; then
    log_output "This is the primary node. Starting Gunicorn on ${VM_IP}:7012..."
    start_gunicorn
elif [ "$ROLE" == "backup" ]; then
    log_output "This is the backup node. Skipping Gunicorn start..."
else
    log_output "Error: Invalid ROLE. Set ROLE to 'primary' or 'backup'."
    exit 1
fi

# Failover monitoring for backup server
if [ "$ROLE" == "backup" ]; then
    log_output "Backup node detected. Monitoring primary server at ${PRIMARY_IP}..."

    primary_up_logged=false  # Track if the primary being online was logged

    while true; do
        # Check primary server status
        if curl -s --max-time 5 --head "http://${PRIMARY_IP}:7012" | grep "200 OK" > /dev/null; then
            if [ "$primary_up_logged" = false ]; then
                log_output "Primary server is online."
                primary_up_logged=true  # Prevent duplicate "online" logs
            fi
            stop_gunicorn  # Stop backup server if primary is online
        else
            log_output "Primary server is down! Activating backup server..."
            if [ -z "$GUNICORN_PID" ]; then
                start_gunicorn  # Start Gunicorn on backup
                log_output "Backup server activated."
            fi
            primary_up_logged=false  # Reset logging for the next recovery
        fi
        sleep 15  # Monitor every 15 seconds
    done
fi



# Install and set up Nginx for frontend failover
log_output "Setting up Nginx for frontend failover configuration..."

# Write the Nginx config to handle frontend failover
sudo bash -c "cat <<'EOF' > /etc/nginx/sites-available/frontend_failover
upstream frontend_cluster {
    server 10.147.17.11:7012 max_fails=3 fail_timeout=10s;  # Primary IP
    server 10.147.17.65:7012 backup;                        # Backup IP
}

server {
    listen 80;
    server_name frontend_cluster;

    location / {
        proxy_pass http://frontend_cluster;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        # Retry the next server if there's a failure
        proxy_next_upstream error timeout http_502 http_503 http_504;
        proxy_connect_timeout 3s;
        proxy_read_timeout 10s;
        proxy_send_timeout 10s;
    }

    access_log /var/log/nginx/frontend_failover.log;
    error_log /var/log/nginx/frontend_failover_error.log;
}
EOF"

# Create symbolic link to enable the configuration
if [ ! -L /etc/nginx/sites-enabled/frontend_failover ]; then
    sudo ln -s /etc/nginx/sites-available/frontend_failover /etc/nginx/sites-enabled/
fi

# Check Nginx configuration syntax before restarting
log_output "Checking Nginx configuration syntax..."
sudo nginx -t
sudo ufw allow 'Nginx Full'  # Allow Nginx through the firewall
sudo ufw allow 7012
sudo ufw allow 80
sudo ufw allow 15672
sudo ufw allow 5672 # Allow RabbitMQ ports
sudo ufw reload  # Reload the firewall rules

# Restart Nginx if it is the backup node
if [ "$ROLE" == "backup" ]; then
    log_output "Restarting Nginx to apply failover and firewall configuration..."
    sudo nginx -t
    sudo ufw allow 'Nginx Full'  # Allow Nginx through the firewall
    sudo ufw allow 7012
    sudo ufw allow 80
    sudo ufw allow 15672
    sudo ufw allow 5672 # Allow RabbitMQ ports
    sudo ufw reload  # Reload the firewall rules
    sudo systemctl restart nginx
fi

# Log the final status
log_output "Frontend services started successfully."