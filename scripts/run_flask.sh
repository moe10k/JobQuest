#!/bin/bash

# Detect the operating system
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    # Linux (Ubuntu)
    activate_venv="source venv/bin/activate"
    python_cmd="python3"
    # Start Gunicorn for Linux
    server_cmd="gunicorn app:app --bind 0.0.0.0:7012"
elif [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" || "$OSTYPE" == "win32" ]]; then
    # Windows (Git Bash/WSL/Native Bash on Windows)
    activate_venv="venv\\Scripts\\activate"
    python_cmd="python"
    # Start Waitress or Flask's built-in server for Windows
    server_cmd="python app.py"  # This uses Waitress if defined in app.py
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

# Install necessary packages
pip install flask requests pika Flask-Mail mysql-connector-python gunicorn waitress

# Start the server
echo "Starting the Flask app..."
$server_cmd