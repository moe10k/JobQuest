#!/bin/bash

# Navigate to the frontend directory
cd ../frontend

# sudo apt install python3-venv

# Creates a virtual environment if it doesn't exist.
if [ ! -d "venv" ]; then 
    echo "Virtual environment not found! Creating it...."
    python3 -m venv venv
fi

# Activates the virtual environment
if [ -f "venv/bin/activate" ]; then
    source venv/bin/activate
else
    echo "Error: Failed to create virtual environment."
    exit 1
fi

# Install Flask if it is not installed 
pip show flask &> /dev/null
if [ $? -ne 0 ]; then 
     echo "Flask is not installed. Installing Flask...." 
     pip install flask
else
    echo "Flask is already installed."
fi

pip install requests #php
pip install pika #rabitmq
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

#Run the Flask app on port 7012
echo "Starting the  FLask app on port 7012..."
flask run --host=0.0.0.0 --port=7012
