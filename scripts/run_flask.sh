#!/bin/bash

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
    echo "Unsupported OS."
    exit 1
fi

# Navigate to the frontend directory
cd ../frontend || { echo "Frontend directory not found"; exit 1; }

# Create virtual environment if it doesn't exist
if [ ! -d "venv" ]; then 
    echo "Virtual environment not found! Creating it..."
    $python_cmd -m venv venv
fi

# Activate the virtual environment
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    # Source for Linux (Ubuntu)
    if [ -f "venv/bin/activate" ]; then
        source venv/bin/activate
    else
        echo "Error: Failed to create virtual environment on Linux."
        exit 1
    fi
else
    # Call activate.bat for Windows
    if [ -f "venv\\Scripts\\activate" ]; then
        # Windows-compatible activation
        source venv\\Scripts\\activate || { echo "Error: Failed to activate virtual environment on Windows."; exit 1; }
    else
        echo "Error: Failed to create virtual environment on Windows."
        exit 1
    fi
fi

# Verify activation by checking if pip is available
if ! command -v pip &> /dev/null; then
    echo "Error: Virtual environment activation failed."
    exit 1
fi

# Install Flask if it is not installed
if ! pip show flask &>/dev/null; then 
    echo "Flask is not installed. Installing Flask..."
    pip install flask
else
    echo "Flask is already installed."
fi

# Install other necessary packages
pip install requests pika  # installs requests and pika packages

# Install mail package
pip install Flask-Mail
pip install mysql-connector-python
pip install mysql-connector-python pika
pip install itsdangerous
pip install gunicorn

# Install Nginx
echo "Installing Nginx..."
sudo apt update
sudo apt install -y nginx

# Create app.py if it doesn't exist
if [ ! -f "app.py" ]; then
    echo "Creating app.py..."
    cat <<EOF > app.py
from flask import Flask

app = Flask(__name__)

@app.route('/')
def hello():
    return "Hello, World!"

if __name__ == "__main__":
    app.run(debug=True, port=7012)
EOF
    echo "app.py created successfully."
else
    echo "app.py already exists."
fi

# Set Flask app environment variables
export FLASK_APP=app.py
export FLASK_ENV=production

# Start Gunicorn with the app running on 7012, with the specified IP and workers
echo "Starting Gunicorn on 10.147.17.11:8000..."
gunicorn app:app --bind 10.147.17.11:8000 --workers 4 &

# Set up Nginx configuration
echo "Setting up Nginx reverse proxy configuration..."

# Check if symlink already exists and remove it
if [ -L /etc/nginx/sites-enabled/IT490 ]; then
    echo "Symbolic link for IT490 already exists, removing it..."
    sudo rm /etc/nginx/sites-enabled/IT490
fi

# Create the symbolic link
sudo ln -s /etc/nginx/sites-available/IT490 /etc/nginx/sites-enabled/

# Write Nginx configuration
cat <<EOF | sudo tee /etc/nginx/sites-available/IT490 > /dev/null
server {
    listen 81;
    server_name 10.147.17.11;

    access_log /var/log/nginx/IT490.log;

    location / {
        proxy_pass http://10.147.17.11:8000;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF

# Restart Nginx
echo "Restarting Nginx..."
sudo systemctl restart nginx

# Ensure Firewall is open for port 81 (HTTP) and 7012 (Gunicorn)
echo "Configuring firewall rules..."
sudo ufw default deny incoming

# Allow HTTP (port 80) and Gunicorn (port 7012) traffic
sudo ufw allow 81/tcp
sudo ufw allow 7012/tcp

# Reload UFW to apply the changes
sudo ufw reload

echo "Setup completed! Gunicorn is running on 10.147.17.11:8000, Nginx is proxying on port 80."