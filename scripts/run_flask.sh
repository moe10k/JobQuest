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
export FLASK_ENV=development

# Run the Flask app on port 7012 using `python -m flask`
echo "Starting the Flask app on port 7012..."
$python_cmd -m flask run --host=0.0.0.0 --port=7012