#!/bin/bash

# Set log file
log_file="backend_log.log"

# Function to log output to file
log_output() {
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" | tee -a $log_file
}

# Detect the operating system
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    # Linux (Ubuntu)
    activate_venv="source venv/bin/activate"
    python_cmd="python3"
elif [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" || "$OSTYPE" == "win32" ]]; then
    # Windows (Git Bash/WSL/Native Bash on Windows)
    activate_venv="venv\\Scripts\\activate"
    python_cmd="python"
else
    log_output "Unsupported OS."
    exit 1
fi

# Navigate to the frontend directory
cd ../frontend || { log_output "Frontend directory not found"; exit 1; }

# Create virtual environment if it doesn't exist
if [ ! -d "venv" ]; then 
    log_output "Virtual environment not found! Creating it..."
    $python_cmd -m venv venv
fi

# Activate the virtual environment
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    # Source for Linux (Ubuntu)
    if [ -f "venv/bin/activate" ]; then
        source venv/bin/activate
    else
        log_output "Error: Failed to create virtual environment on Linux."
        exit 1
    fi
else
    # Call activate for Windows
    if [ -f "venv\\Scripts\\activate" ]; then
        source venv\\Scripts\\activate || { log_output "Error: Failed to activate virtual environment on Windows."; exit 1; }
    else
        log_output "Error: Failed to create virtual environment on Windows."
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

# Install Nginx (Linux only)
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    log_output "Installing Nginx..."
    sudo apt update
    sudo apt install -y nginx
fi

# Create app.py if it doesn't exist
if [ ! -f "app.py" ]; then
    log_output "Creating app.py..."
    cat <<EOF > app.py
from flask import Flask

app = Flask(__name__)

@app.route('/')
def hello():
    return "Hello, World!"

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

# Stop any existing processes on port 7012 or 80
log_output "Stopping any existing processes on ports 7012 and 80..."
sudo fuser -k 7012/tcp || true
sudo fuser -k 80/tcp || true

# Start Gunicorn with the app running on 7012, with the specified IP and workers
log_output "Starting Gunicorn on 10.147.17.11:7012..."
gunicorn app:app --bind 10.147.17.11:7012 --workers 4 &

# Set up Nginx configuration
log_output "Setting up Nginx reverse proxy configuration..."

# Remove existing symlink for Nginx config if it exists
if [ -L /etc/nginx/sites-enabled/IT490 ]; then
    log_output "Removing existing symbolic link for IT490..."
    sudo rm /etc/nginx/sites-enabled/IT490
fi

# Write Nginx configuration
cat <<EOF | sudo tee /etc/nginx/sites-available/IT490 > /dev/null
server {
    listen 80;
    server_name 10.147.17.11;

    access_log /var/log/nginx/IT490.log;

    location / {
        proxy_pass http://10.147.17.11:7012;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF

# Create the symbolic link if it doesn't exist
if [ ! -L /etc/nginx/sites-enabled/IT490 ]; then
    sudo ln -s /etc/nginx/sites-available/IT490 /etc/nginx/sites-enabled/
fi

# Check Nginx configuration syntax before restarting
log_output "Checking Nginx configuration syntax..."
sudo nginx -t

# Restart Nginx
log_output "Restarting Nginx..."
sudo systemctl restart nginx

log_output "Setup completed! Gunicorn is running on 10.147.17.11:7012, and Nginx is proxying on port 80."
