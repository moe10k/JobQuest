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
    $python_cmd -m venv venv || { log_output "Error: Failed to create virtual environment."; exit 1; }
fi

# Activate virtual environment
log_output "Activating the virtual environment..."
source "$activate_venv" || { log_output "Error: Failed to activate virtual environment."; exit 1; }
log_output "Virtual environment activated successfully."

# Install required Python packages
log_output "Installing required Python packages..."
$python_cmd -m pip install -q requests pika Flask Flask-Mail mysql-connector-python itsdangerous gunicorn flask-cors python-dotenv || {
    log_output "Error: Failed to install required Python packages."; exit 1;
}

# Check if Gunicorn is installed and install it if necessary
if ! command -v gunicorn &> /dev/null; then
    log_output "Gunicorn not found. Installing Gunicorn..."
    pip install gunicorn || { log_output "Error: Failed to install Gunicorn."; exit 1; }
else
    log_output "Gunicorn is already installed."
fi

# Create app.py if it doesn't exist
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
fi

# Set Flask app environment variables
export FLASK_APP=app.py
export FLASK_ENV=production

# Stop any existing backend processes on port 7012
log_output "Stopping any existing processes on port 7012..."
sudo fuser -k 7012/tcp || true

# Get VPN IP dynamically (e.g., Tailscale IP)
VPN_INTERFACE="tailscale0"  
VM_IP=$(ifconfig "$VPN_INTERFACE" | grep 'inet ' | awk '{print $2}')

if [ -z "$VM_IP" ]; then
    log_output "Error: Unable to get VPN IP address for this VM."
    exit 1
fi
log_output "VM IP (tailscale): $VM_IP"

# Primary and secondary IPs
PRIMARY_IP="100.64.1.5"
SECONDARY_IP="100.64.1.4"

# Determine role based on IP
if [ "$VM_IP" == "$PRIMARY_IP" ]; then
    ROLE="primary"
elif [ "$VM_IP" == "$SECONDARY_IP" ]; then
    ROLE="backup"
else
    log_output "Error: This VM's IP does not match primary or secondary IP."
    exit 1
fi
log_output "Role set to: $ROLE"

start_gunicorn() {
    log_output "Starting Gunicorn on ${VM_IP}:7012 with a 60-second timeout..."
    gunicorn --bind "${VM_IP}:7012" --workers 4 --access-logfile "$log_file" --error-logfile "$log_file" app:app &
    export GUNICORN_PID=$!
}

# Start Gunicorn regardless of role
start_gunicorn

# Set up Nginx failover configuration
log_output "Setting up Nginx for frontend failover..."
sudo bash -c "cat <<'EOF' > /etc/nginx/sites-available/frontend_failover
upstream frontend_cluster {
    server $PRIMARY_IP:7012 max_fails=3 fail_timeout=10s;
    server $SECONDARY_IP:7012 backup;
}

server {
    listen 80;
    server_name frontend_cluster;

    location / {
        proxy_pass http://frontend_cluster;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        proxy_next_upstream error timeout http_502 http_503 http_504;
        proxy_connect_timeout 3s;
        proxy_read_timeout 10s;
        proxy_send_timeout 10s;
    }

    access_log /var/log/nginx/frontend_failover.log;
    error_log /var/log/nginx/frontend_failover_error.log;
}
EOF"

# Enable Nginx configuration
if [ ! -L /etc/nginx/sites-enabled/frontend_failover ]; then
    sudo ln -s /etc/nginx/sites-available/frontend_failover /etc/nginx/sites-enabled/
fi

# Start or restart Nginx service
log_output "Starting Nginx service..."
sudo systemctl start nginx || { log_output "Error: Failed to start Nginx."; exit 1; }
log_output "Nginx started successfully."

# Check and reload Nginx
log_output "Checking Nginx configuration..."
sudo nginx -t || { log_output "Error: Nginx configuration is invalid."; exit 1; }

log_output "Reloading Nginx..."
sudo systemctl reload nginx || { log_output "Error: Failed to reload Nginx."; exit 1; }

# Log the final status
log_output "Frontend services started successfully."
log_output "Gunicorn PID: $GUNICORN_PID"
log_output "Role: $ROLE"