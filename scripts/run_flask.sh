#!/bin/bash

# Set log file for traffic
log_file="frontend_backend_log.log"

# Function to log output to file
log_output() {
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" | tee -a $log_file
}

# Detect the operating system
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
fi

# Activate the virtual environment
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    if [ -f "venv/bin/activate" ]; then
        source venv/bin/activate
    else
        log_output "Error: Failed to activate virtual environment on Linux."
        exit 1
    fi
else
    if [ -f "venv\\Scripts\\activate" ]; then
        source venv\\Scripts\\activate || { log_output "Error: Failed to activate virtual environment on Windows."; exit 1; }
    else
        log_output "Error: Failed to activate virtual environment on Windows."
        exit 1
    fi
fi

# Verify activation by checking if pip is available
if ! command -v pip &> /dev/null; then
    log_output "Error: Virtual environment activation failed."
    exit 1
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

# Configure this VM's IP
vm_ip="10.147.17.11"  # Replace with actual IP

# Start Gunicorn for backend with access and error logging
log_output "Starting Gunicorn on ${vm_ip}:7012 for backend..."
gunicorn --bind ${vm_ip}:7012 --workers 4 --access-logfile $log_file --error-logfile $log_file app:app &

# Install and set up Nginx for frontend failover
log_output "Setting up Nginx for frontend failover configuration..."

# Write the Nginx config to handle frontend failover with both nodes
sudo bash -c 'cat <<EOF > /etc/nginx/sites-available/frontend_failover
upstream frontend_cluster {
    server 10.147.17.11:7012 max_fails=3 fail_timeout=30s;  # Primary frontend node
    server 10.147.17.65:7012 backup;                       # Secondary frontend node
}

server {
    listen 80;
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

# Create symbolic link to enable the configuration
if [ ! -L /etc/nginx/sites-enabled/frontend_failover ]; then
    sudo ln -s /etc/nginx/sites-available/frontend_failover /etc/nginx/sites-enabled/
fi

# Check Nginx configuration syntax before restarting
log_output "Checking Nginx configuration syntax..."
sudo nginx -t

# Restart Nginx
log_output "Restarting Nginx..."
sudo systemctl restart nginx

# Configure UFW firewall rules for ports 80 (Nginx) and 7012 (Gunicorn)
log_output "Configuring firewall rules..."
sudo ufw allow 80/tcp  # Allow HTTP traffic for Nginx on port 80
sudo ufw allow 7012/tcp  # Allow traffic to Gunicorn on port 7012
sudo ufw enable
sudo ufw reload  # Reload UFW to apply the changes

log_output "Setup completed! Gunicorn backend is running on ${vm_ip}:7012, and Nginx frontend failover is set up on port 80."
log_output "Check the logs in $log_file for more details."
