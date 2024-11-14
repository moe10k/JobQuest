#!/bin/bash

# Set log file for traffic
log_file="frontend_backend_log.log"

# Function to log output to file
log_output() {
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" | tee -a $log_file
}

# Define primary and secondary IPs
PRIMARY_IP="10.147.17.11"
SECONDARY_IP="10.147.17.65"

# Get the VM's IP address
VM_IP=$(hostname -I | awk '{print $1}')

# Determine the role based on the VM's IP address
if [ "$VM_IP" == "$PRIMARY_IP" ]; then
    ROLE="primary"
elif [ "$VM_IP" == "$SECONDARY_IP" ]; then
    ROLE="backup"
else
    log_output "Error: VM IP $VM_IP does not match primary or secondary IP. Exiting."
    exit 1
fi

# Log the IPs and role for confirmation
log_output "Primary IP: $PRIMARY_IP"
log_output "Secondary IP: $SECONDARY_IP"
log_output "This VM IP: $VM_IP"
log_output "Assigned Role: $ROLE"

# Export the variables for use in the script
export PRIMARY_IP
export SECONDARY_IP
export ROLE

# Detect the operating system and set appropriate variables
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    activate_venv="source venv/bin/activate"
    python_cmd="python3"
elif [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" || "$OSTYPE" == "win32" ]]; then
    activate_venv="venv\\Scripts\\activate"
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

# Verify the activate script exists and is executable
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    if [ ! -f "venv/bin/activate" ] || [ ! -x "venv/bin/activate" ]; then
        log_output "Error: 'venv/bin/activate' is missing or not executable."
        exit 1
    fi
elif [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" || "$OSTYPE" == "win32" ]]; then
    if [ ! -f "venv\\Scripts\\activate" ]; then
        log_output "Error: 'venv\\Scripts\\activate' is missing on Windows."
        exit 1
    fi
fi

# Attempt to activate the virtual environment
log_output "Attempting to activate the virtual environment..."
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    source venv/bin/activate
else
    source venv\\Scripts\\activate
fi

# Check if activation was successful
if ! command -v pip &> /dev/null; then
    log_output "Error: Virtual environment activation failed; pip is not available."
    exit 1
else
    log_output "Virtual environment activated successfully."
fi

# Install required Python packages
log_output "Installing required Python packages..."
pip install -q requests pika Flask Flask-Mail mysql-connector-python itsdangerous gunicorn

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
    app.run(debug=True, port=7012)
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

# Function to start Gunicorn
start_gunicorn() {
    log_output "Starting Gunicorn on ${VM_IP}:7012..."
    gunicorn --bind ${VM_IP}:7012 --workers 4 --access-logfile $log_file --error-logfile $log_file app:app &
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

# Start Gunicorn if this VM is the primary node
if [ "$ROLE" == "primary" ]; then
    log_output "This is the primary node. Starting Gunicorn on ${VM_IP}:7012..."
    start_gunicorn
elif [ "$ROLE" == "backup" ]; then
    log_output "This is the backup node. Skipping Gunicorn start..."
fi

# Failover monitoring for backup server
if [ "$ROLE" == "backup" ]; then
    log_output "Backup node detected. Monitoring primary server at ${PRIMARY_IP}..."

    # Monitoring loop
    while true; do
        if curl -s --max-time 5 --head "http://${PRIMARY_IP}:7012" | grep "200 OK" > /dev/null; then
            log_output "Primary server is online."
            stop_gunicorn
        else
            log_output "Primary server is down! Activating backup server..."
            if [ -z "$GUNICORN_PID" ]; then
                start_gunicorn
            fi
        fi
        sleep 15
    done
fi

# Install and set up Nginx for frontend failover
log_output "Setting up Nginx for frontend failover configuration..."

# Write Nginx config
sudo bash -c 'cat <<EOF > /etc/nginx/sites-available/frontend_failover
upstream frontend_cluster {
    server 10.147.17.11:7012 max_fails=3 fail_timeout=30s;
    server 10.147.17.65:7012 backup;
}

server {
    listen 8000;
    server_name frontend_cluster;

    location / {
        proxy_pass http://frontend_cluster;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    access_log /var/log/nginx/frontend_failover.log;
    error_log /var/log/nginx/frontend_failover_error.log;
}
EOF'

# Enable config, check Nginx, restart it
if [ ! -L /etc/nginx/sites-enabled/frontend_failover ]; then
    sudo ln -s /etc/nginx/sites-available/frontend_failover /etc/nginx/sites-enabled/
fi
log_output "Checking Nginx configuration syntax..."
sudo nginx -t
log_output "Restarting Nginx..."
sudo systemctl restart nginx

# Configure firewall rules
log_output "Configuring firewall rules..."
sudo ufw allow 7012/tcp
sudo ufw allow 8000/tcp
sudo ufw allow 5672/tcp
sudo ufw allow 4369/tcp
sudo ufw allow 25672/tcp
sudo ufw reload

log_output "Setup completed! Gunicorn backend is running on ${VM_IP}:7012, and Nginx frontend failover is set up on port 8000."
log_output "Logs are available in $log_file."
