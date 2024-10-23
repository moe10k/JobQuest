import pika, time, json, re
from flask import Flask, flash, render_template, request, redirect, url_for, session

app =  Flask (__name__) 
app.secret_key = "secret_key" #Secret key for flashing messages

# RabbitMQ connection details
rabbitmq_host = '10.147.17.228'  # I used my VM IP but Change this to your RabbitMQ server's address if needed
registration_request_queue = 'registration_request_queue'
registration_response_queue = 'registration_response_queue'
login_request_queue = 'login_request_queue'
login_response_queue = 'login_response_queue'
popup_request_queue = 'popup_request_queue'
popup_response_queue = 'popup_response_queue'

def is_logged_in():
    if 'user' in session:
        return True
    else:
        return False

# Function to send registration data to RabbitMQ
def send_registration_rabbitmq(message):
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    
    # Declare a queue
    channel.queue_declare(queue=registration_request_queue, durable=True)

    # Send the registration data to the queue
    channel.basic_publish(
        exchange='',
        routing_key=registration_request_queue,
        body=message,
        properties=pika.BasicProperties(delivery_mode=2)  # Make message persistent
    )

    print(" [x] Sent registration data to RabbitMQ:", message) #confirmation message if the frontend succesfully sent a message to rabbitMQ
    connection.close()

# Function to consume registration response from RabbitMQ
def consume_registration_response():
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    channel.queue_declare(queue=registration_response_queue, durable=True)

    # Introduce a small delay to allow the message to reach the queue
    time.sleep(6)  # Wait x seconds before consuming the message

    method_frame, header_frame, body = channel.basic_get(queue=registration_response_queue, auto_ack=True)

    channel.close()
    connection.close()

    if body:
        registration_response = body.decode('utf-8')  # Decode the response
        print(f"Message received directly from register2.php: {registration_response}")
        return registration_response
    else:
        return None  # No message received

# Function to send login data to RabbitMQ
def send_login_rabbitmq(message):
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    
    # Declare a queue
    channel.queue_declare(queue=login_request_queue, durable=True)

    # Send the login data to the queue
    channel.basic_publish(
        exchange='',
        routing_key=login_request_queue,
        body=message,
        properties=pika.BasicProperties(delivery_mode=2)  # Make message persistent
    )

    print(" [x] Sent login data to RabbitMQ:", message)  # confirmation message if the frontend successfully sent a message to RabbitMQ
    connection.close()

# Function to consume login response from RabbitMQ
def consume_login_response():
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    channel.queue_declare(queue=login_response_queue, durable=True)

    time.sleep(6)  # Wait x seconds before consuming the message

    method_frame, header_frame, body = channel.basic_get(queue=login_response_queue, auto_ack=True)
    
    channel.close()
    connection.close()

    if body:
        response = json.loads(body.decode('utf-8'))  # Decode the JSON response
        return response  # Return the entire response (status and username)
    else:
        return None  # No message received
    

def send_popup_rabbitmq(message):
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    
    # Declare the popup request queue
    channel.queue_declare(queue=popup_request_queue, durable=True)

    # Send the form data to the queue
    channel.basic_publish(
        exchange='',
        routing_key=popup_request_queue,
        body=message,
        properties=pika.BasicProperties(delivery_mode=2)  # Make message persistent
    )

    print(f" [x] Sent popup data to RabbitMQ: {message}")
    connection.close()

def consume_popup_response():
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    channel.queue_declare(queue=popup_response_queue, durable=True)

    time.sleep(6)  # Delay to allow the response to be processed

    method_frame, header_frame, body = channel.basic_get(queue=popup_response_queue, auto_ack=True)

    channel.close()
    connection.close()

    if body:
        popup_response = body.decode('utf-8')
        print(f"Message received from popup2.php: {popup_response}")
        return popup_response
    else:
        return None



@app.route('/')
def landing():
    return render_template('landing.html') #User is automatically redirected to the landing screen when loading into our site


@app.route('/register', methods=['GET', 'POST'])
def register():
    if is_logged_in():
        return redirect('/dashboard') #checks if user is in session and will redirect to dashboard
    
    if request.method == 'POST': #once the user clicks submit, the following will happen
        
        print("Registration Form Submitted, Validation Processing...") #message that registration form was submitted

        # Extract form data
        username = request.form['username']
        email = request.form['email']
        password = request.form['password']

        #Validates Email using regex

        email_pattern = r'^[\w\.-]+@[\w\.-]+\.\w+$'
        if not re.match(email_pattern, email):
            flash('Invalid email address. Please provide a valid email.', 'danger')
            print("Invalid email address, Submission to RabitMQ failed.")
            return render_template('register.html')
         
        # Validates password length
        if len(password) < 6:
            flash('Password must be at least 6 characters long.', 'danger')
            print("Password is not long enough, Submission to RabitMQ failed.")
            return render_template('register.html')
        

        message = f"{username},{email},{password}" #message that data is sent to queue
        
        # Send the message to RabbitMQ
        send_registration_rabbitmq(message)
        response = consume_registration_response() 
        print(f"Message recieved from the consume registration response function: {response}") #checking what message it received from backend
        if response == 'success':
            flash('Registration Successful!', 'success')
            print("Successfully Registered!")
            return render_template('login.html')
        else:
            flash('Invalid Registration. Please try again.', 'danger')
            print("Registration Failed - Account Already Exists!")
            return render_template('register.html')

    # Render the registration form if it's a GET request
    return render_template('register.html')


@app.route('/login', methods=['GET', 'POST'])
def login():
    if is_logged_in():
        return redirect('/dashboard') #checks if user is in session and will redirect to dashboard
    
    if request.method == 'POST':

        print("Login Form Submitted, Processing...")

        # Extract login form data
        email = request.form['email']
        password = request.form['password']
        
        # Create a message to send to RabbitMQ
        message = f"{email},{password}"
        
        # Send the login data to RabbitMQ
        send_login_rabbitmq(message)

        # Wait for the response from RabbitMQ
        response = consume_login_response() 
        print(f"Message recieved: {response}")#Checking what messaged was received from backend
        if response['status'] == 'success':
            session['user'] = {
                'email': email,
                'username': response['username']  # Store username in the session
            }
            flash('Login successful!', 'success')
            print("Login Successful!")
            return redirect('/dashboard')
        else:
            flash('Invalid credentials. Please try again.', 'danger')
            print("Invalid Credentials")
            return render_template('login.html')
    
    # Render the login form if it's a GET request
    return render_template('login.html')


@app.route('/submit_popup', methods=['POST'])
def submit_popup():
    first_name = request.form.get('firstName')
    last_name = request.form.get('lastName')
    country = request.form.get('country')
    state = request.form.get('state')
    zip_code = request.form.get('zip')
    job_title = request.form.get('jobTitle')
    job_start_month = request.form.get('jobStartMonth')
    job_end_month = request.form.get('jobEndMonth')
    job_current = request.form.get('jobCurrent')  # This will be 'on' if checked, or None if unchecked
    school_name = request.form.get('schoolName')
    school_start_month = request.form.get('schoolStartMonth')
    school_end_month = request.form.get('schoolEndMonth')
    school_current = request.form.get('schoolCurrent')  # Same as job_current

    message = json.dumps({
        "first_name": first_name,
        "last_name": last_name,
        "country": country,
        "state": state,
        "zip_code": zip_code,
        "job_title": job_title,
        "job_start_month": job_start_month,
        "job_end_month": job_end_month,
        "job_current": job_current,
        "school_name": school_name,
        "school_start_month": school_start_month,
        "school_end_month": school_end_month,
        "school_current": school_current
    })

    send_popup_rabbitmq(message)

    response = consume_popup_response()

    if response == 'success':
        flash('Additional information submitted successfully!', 'success')
    else:
        flash('Error - Please Try again later', 'danger')

    flash('Additional information submitted successfully!', 'success')
    return redirect('/dashboard')


@app.route('/dashboard')
def dashboard():
    if is_logged_in():
        return render_template('dashboard.html', user=session['user'])
    else:
        flash('You must login first', 'danger')
        return redirect('/login')
    

@app.route('/logout')
def logout():
    if is_logged_in():
        session.clear()  # Clear the entire session
        flash('You have been logged out.', 'success')
        return redirect('/login')
    else:
        flash('You must login first', 'danger')
        return redirect('/login')

@app.context_processor
def inject_is_logged_in():
    return dict(is_logged_in=is_logged_in())


if __name__ == "__main__":
    app.run(debug=True, port=7012)
