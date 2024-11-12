import pika, time, json, re, requests
import mysql.connector
from flask import Flask, flash, render_template, request, redirect, url_for, session
from flask_mail import Mail, Message
from itsdangerous import URLSafeTimedSerializer, SignatureExpired


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
resetpassword_request_queue = 'resetpassword_request_queue'
resetpassword_response_queue = 'resetpassword_response_queue'


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
   time.sleep(15)  # Wait x seconds before consuming the message


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


   time.sleep(15)  # Wait x seconds before consuming the message


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
       properties=pika.BasicProperties(delivery_mode=2)
   )


   print(f" [x] Sent popup data to RabbitMQ: {message}")
   connection.close()


def consume_popup_response():
   connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
   channel = connection.channel()
   channel.queue_declare(queue=popup_response_queue, durable=True)


   time.sleep(15)  # Delay to allow the response to be processed


   method_frame, header_frame, body = channel.basic_get(queue=popup_response_queue, auto_ack=True)


   channel.close()
   connection.close()


   if body:
       popup_response = body.decode('utf-8')
       print(f"Message received from popup2.php: {popup_response}")
       return popup_response
   else:
       return None



# Function to send resetpassword data to RabbitMQ
def send_resetpassword_rabbitmq(message):
   connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
   channel = connection.channel()
  
   # Declare a queue
   channel.queue_declare(queue=resetpassword_request_queue, durable=True)


   # Send the data to the queue
   channel.basic_publish(
       exchange='',
       routing_key=resetpassword_request_queue,
       body=message,
       properties=pika.BasicProperties(delivery_mode=2)  # Make message persistent
   )


   print(" [x] Sent reset password data to RabbitMQ:", message) #confirmation message if the frontend succesfully sent a message to rabbitMQ
   connection.close()


# Function to consume resetpassword response from RabbitMQ
def consume_resetpassword_response():
   connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
   channel = connection.channel()
   channel.queue_declare(queue=resetpassword_response_queue, durable=True)


   # Introduce a small delay to allow the message to reach the queue
   time.sleep(15)  # Wait x seconds before consuming the message


   method_frame, header_frame, body = channel.basic_get(queue=resetpassword_response_queue, auto_ack=True)


   channel.close()
   connection.close()


   if body:
       resetpassword_response = body.decode('utf-8')  # Decode the response
       print(f"Message received directly from register2.php: {resetpassword_response}")
       return resetpassword_response
   else:
       return None  # No message received




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
       confirm_password = request.form['confirm_password']


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
      
       # Validates password match
       if password != confirm_password:
           flash('Passwords do not match.', 'danger')
           print("Passwords do not match, Submission to RabitMQ failed.")
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


       print("Login Form Submitted, Validation Processing...")


       # Extract login form data
       email = request.form['email']
       password = request.form['password']


       #Validates Email using regex


       email_pattern = r'^[\w\.-]+@[\w\.-]+\.\w+$'
       if not re.match(email_pattern, email):
           flash('Invalid email address. Please provide a valid email.', 'danger')
           print("Invalid email address, Submission to RabitMQ failed.")
           return render_template('login.html')
       
       # Validates password length
       if len(password) < 6:
           flash('Password must be at least 6 characters long.', 'danger')
           print("Password is not long enough, Submission to RabitMQ failed.")
           return render_template('login.html')
      
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
               'username': response['username'],  # Store username in the session
               'user_id' : response['user_id'],
               'popup_enabled': response['popup_enabled']
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
   print("Form submitted")  # Debug print


# Extract form data
   first_name = request.form.get('firstName')
   last_name = request.form.get('lastName')
   country = request.form.get('country')
   state = request.form.get('state')
   zip_code = request.form.get('zip')
   job_title = request.form.get('jobTitle')
   job_start_month = request.form.get('jobStartMonth')
   job_end_month = request.form.get('jobEndMonth')
   job_current = 'True' if request.form.get('jobCurrent') else 'False'
   school_name = request.form.get('schoolName')
   school_start_month = request.form.get('schoolStartMonth')
   school_end_month = request.form.get('schoolEndMonth')
   school_current = 'True' if request.form.get('schoolCurrent') else 'False'
   security_question_1 = request.form.get('securityQuestion1')
   security_question_2 = request.form.get('securityQuestion2')
   security_question_3 = request.form.get('securityQuestion3')
   popup_enabled = 1
   user_id = session.get('user', {}).get('user_id')#will be used to update the correct row in table


   # Create the message in the same format as register
   message = f"{first_name},{last_name},{country},{state},{zip_code},{job_title},{job_start_month},{job_end_month},{job_current},{school_name},{school_start_month},{school_end_month},{school_current},{security_question_1},{security_question_2},{security_question_3},{popup_enabled},{user_id}"


   send_popup_rabbitmq(message)


   response = consume_popup_response()


   if response == 'success':
       session['show_popup'] = False  # thisll make sure that the popup doesn't show up again
       session['user']['popup_enabled'] = 1
       flash('Additional information submitted successfully!', 'success')
   else:
       flash('Error - Please Try again later', 'danger')


   return redirect('/dashboard')



@app.route('/dashboard')
def dashboard():
    if is_logged_in():
        user = session['user']
        user_id = user.get('user_id')  # Retrieve the user_id from the session for debugging purposes

        # Check if we need to show the popup based on the popup_enabled status from the session
        show_popup = True if user.get('popup_enabled') == 0 else False

        return render_template('dashboard.html', user=user, user_id=user_id, show_popup=show_popup)
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
  
  
@app.route('/resetpassword', methods=['GET', 'POST'])
def resetpassword():
   if not is_logged_in():
       flash('You must login first', 'danger')
       return redirect('/login')
  
   if request.method == 'POST':
       question1 = request.form.get('question1')
       question2 = request.form.get('question2')
       question3 = request.form.get('question3')
       newpassword = request.form.get('newpassword')
      
       if len(newpassword) < 6: #same validation as password registration
           flash('New password must be at least 6 characters long.', 'danger')
           return render_template('resetpassword.html')


       user_id = session.get('user', {}).get('user_id') #user id needed for updating correct row in table
       message = f"{user_id},{question1},{question2},{question3},{newpassword}"


       # Send the reset password data to RabbitMQ
       send_resetpassword_rabbitmq(message)


       # Process response from RabbitMQ
       response = consume_resetpassword_response()
      
       if response == 'success':
           flash('Password reset successful!', 'success')
           return redirect('/login')
       else:
           flash('Password reset failed. Please try again.', 'danger')
           return render_template('resetpassword.html')


   # Render the reset password form if it's a GET request
   return render_template('resetpassword.html')


@app.route('/search', methods=['GET', 'POST'])
def search_jobs():
    job_results = None  # Default to None to avoid showing results on the initial load
    if request.method == 'POST':
        # Extract form data for job search
        job_title = request.form.get('job_title')
        location = request.form.get('location')

        # API info - hardcoded for now, need to create .env later
        url = "https://api.apijobs.dev/v1/job/search"
        headers = {
            'apikey': '9765c07340ff178432353d35e4658525d3bedd8e74cce25f3d982f2e24ac669e',
            'Content-Type': 'application/json'
        }
        payload = {
            'q': job_title,
            'country': location
        }

        # Send POST request
        response = requests.post(url, json=payload, headers=headers)

        # Print the status code and response content for debugging
        print("Status Code:", response.status_code)

        # Check the response status
        if response.status_code == 200:
            job_results = response.json().get('hits', [])
            
            if job_results:
                # Print the first job for debugging
                print("First Job Details:", job_results[0])

                # Extract specific job details for display
                formatted_jobs = []
                for job in job_results:
                    formatted_jobs.append({
                        'title': job.get('title'),
                        'job_url': job.get('url'),
                        'company': job.get('hiringOrganizationName', 'N/A'),
                        'location': job.get('region', 'N/A'),
                        'description': job.get('description', 'N/A')
                    })

                return render_template('search.html', jobs=formatted_jobs)

            else:
                print("No job data found.")
                flash('No job results found.', 'info')
                return render_template('search.html')

        else:
            flash('Failed to fetch job data. Please try again later.', 'danger')
            return render_template('search.html')

    # Render the search page with job results (if any)
    return render_template('search.html', jobs=job_results)





@app.context_processor
def inject_is_logged_in():
   return dict(is_logged_in=is_logged_in())




if __name__ == "__main__":
   app.run(debug=True, port=7012)