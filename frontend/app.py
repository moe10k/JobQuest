from flask import Flask, flash, render_template, request, redirect, url_for, session
import pika
import requests

app =  Flask (__name__) 
app.secret_key = "secret_key" #Secret key for flashing messages
#Source: www.geeksforgeeks.org/flask-message-flashing/-->


# RabbitMQ connection details
rabbitmq_host = '192.168.1.227'  # I used my VM IP but Change this to your RabbitMQ server's address if needed

login_queue = 'login_queue'
login_response_queue = 'login_response_queue'

php_login_url = "http://10.147.17.228:80/login.php"
php_register_url = "http://10.147.17.228:80/register.php"

#Test user for authentication 
#test_user ={
#    "username" : "admin",
#    "password" : "admin123"
#}

# Function to send registration data to RabbitMQ
def send_registration_data_rabbitmq(message):
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    
    # Declare a queue
    channel.queue_declare(queue='registration_queue', durable=True)

    # Send a message to the queue
    channel.basic_publish(exchange='',
                          routing_key='registration_queue',
                          body=message,
                          properties=pika.BasicProperties(
                              delivery_mode=2,  # Made message persistent
                          ))
    print(" [x] Sent registration message to RabbitMQ:", message) #confirmation message if the frontend succesfully sent a message to rabbitMQ
    connection.close()

# Function to send login data to RabbitMQ
def send_login_to_rabbitmq(message):
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    
    # Declare a queue
    channel.queue_declare(queue=login_queue, durable=True)

    # Send the message to the queue
    channel.basic_publish(exchange='',
                          routing_key=login_queue,
                          body=message,
                          properties=pika.BasicProperties(
                              delivery_mode=2,  # Make message persistent
                          ))
    print(" [x] Sent login data to RabbitMQ:", message)  # confirmation message if the frontend successfully sent a message to RabbitMQ
    connection.close()

# Function to consume response from RabbitMQ
def consume_login_response():
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    channel.queue_declare(queue=login_response_queue, durable=True)

    method_frame, header_frame, body = channel.basic_get(queue=login_response_queue, auto_ack=True)
    channel.close()
    connection.close()

    if body:
        return body.decode('utf-8')  # Decode the response
    else:
        return None  # No message received


@app.route('/')
def home():
    return redirect('/login') #User is automatically redirected to the login screen when loading into our site

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        # Extract login form data
        email = request.form['email']
        password = request.form['password']
        
        # Create a message to send to RabbitMQ
        message = f"{email},{password}"
        
        # Send the login data to RabbitMQ
        send_login_to_rabbitmq(message)

        # Wait for the response from RabbitMQ
        response = consume_login_response()
        if response == 'success':
            session['user'] = email
            flash('Login successful!', 'success')
            return redirect('/dashboard')
        else:
            flash('Invalid credentials. Please try again.', 'danger')
            return redirect('/login')
    
    # Render the login form if it's a GET request
    return render_template('login.html')


@app.route('/register', methods=['GET', 'POST'])
def register():
    if request.method == 'POST':
        # Extract registration form data
        username = request.form['username']
        email = request.form['email']
        password = request.form['password']
        
        # Create a dictionary with form data to send to PHP backend
        data = {
            'username': username,
            'email': email,
            'password': password
        }

        # Send the registration data to the PHP backend via POST
        try:
            response = requests.post(php_register_url, data=data)
            response.raise_for_status()  # Check if the request was successful

            # Process the PHP backend response
            if response.text == 'success':  # Based on what your PHP returns
                flash('Registration successful! Please log in.', 'success')
                return redirect('/login')
            else:
                flash('Registration failed. Please try again.', 'danger')
                return redirect('/register')

        except requests.exceptions.RequestException as e:
            return f"Error communicating with PHP backend: {e}", 500

    # Render the registration form if it's a GET request
    return render_template('register.html')



@app.route('/dashboard')
def dashboard():
    if 'user' in session:
        return f"Welcome {session['user']}!"
    else:
        return redirect('/login')



if __name__ == "__main__":
    app.run(debug=True, port=7012)