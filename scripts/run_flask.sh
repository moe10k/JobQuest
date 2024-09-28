#!/bin/bash
if [ ! -d "venv" ]; then 
    echo "Virtual environment not found!Create it using "p>
    python3 -venv venv
fi

source venv/bin/activate

if ! command -v flask &> /dev/null; then 
     echo "Flask is not installed. Installing flask...." 
     pip install flask
fi

if [ ! -f "app.py" ]; then
     echo "Creating app.py..."
     cat <<EOF > app.py
from flask import Flask

app = Flask(__name__)

@app.route('/')
def hello():
    return "Hello, World!"

if __name__ == "__main__":
    app.run(debug=True)
EOF
   echo "app.py created successfully."
else
   echo "app.py already exists."
fi

export FLASK_APP=app.py

export FLASK_ENV=development

echo "starting the  FLask app..."
flask run
