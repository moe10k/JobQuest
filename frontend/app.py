import pika
from flask import Flask, flash, render_template, request, redirect, url_for, session

app =  Flask (__name__) 
app.secret_key = "secret_key" #Secret key for flashing messages
#Source: www.geeksforgeeks.org/flask-message-flashing/-->


# RabbitMQ connection details
rabbitmq_host = '10.147.17.228'  # I used my VM IP but Change this to your RabbitMQ server's address if needed

registration_queue = 'registration_queue'
login_request_queue = 'login_request_queue'
login_response_queue = 'login_response_queue'

# Function to send registration data to RabbitMQ
def send_registration_data_rabbitmq(message):
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    
    # Declare a queue
    channel.queue_declare(queue=registration_queue, durable=True)

    # Send a message to the queue
    channel.basic_publish(exchange='',
                          routing_key=registration_queue,
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
    channel.queue_declare(queue=login_request_queue, durable=True)

    # Send the message to the queue
    channel.basic_publish(exchange='',
                          routing_key=login_request_queue,
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
    if request.method == 'POST': #once the user clicks submit, the following will happen
        # Extract form data
        username = request.form['username']
        email = request.form['email']
        password = request.form['password']
        
        # Create a message to send to RabbitMQ, can learn to use JSON later if we want
        message = f"{username},{email},{password}"
        
        # Send the message to RabbitMQ
        send_registration_data_rabbitmq(message)

        # Redirect to login screen after form submission
        return redirect('/login')

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