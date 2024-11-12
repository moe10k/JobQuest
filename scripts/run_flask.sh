#!/bin/bash

# Set log file for traffic
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

# Create app.py if it doesn't exist
if [ ! -f "app.py" ]; then
    log_output "Creating app.py..."
    cat <<EOF > app.py
from flask import Flask
import logging

# Set up logging to file for Flask traffic
logging.basicConfig(
    filename='backend_log.log',  # Log file where server traffic will be stored
    level=logging.INFO,  # Log level (INFO to capture general traffic, DEBUG for more detailed logs)
    format='%(asctime)s - %(levelname)s - %(message)s',  # Log format with timestamp, log level, and message
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

# Stop any existing processes on port 7012
log_output "Stopping any existing processes on port 7012..."
sudo fuser -k 7012/tcp || true

# Start Gunicorn with the app running on 7012, with the specified IP and workers
log_output "Starting Gunicorn on 10.147.17.11:7012..."
gunicorn --bind 10.147.17.11:7012 --workers 4 --access-logfile backend_log.log --error-logfile backend_log.log app:app &

log_output "Setup completed! Gunicorn is running on 10.147.17.11:7012, and logging traffic to backend_log.log."

