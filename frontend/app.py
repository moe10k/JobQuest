import pika
from flask import Flask, flash, render_template, request, redirect, url_for

app =  Flask (__name__) 
app.secret_key = "secret_key" #Secret key for flashing messages
#Source: www.geeksforgeeks.org/flask-message-flashing/-->


# RabbitMQ connection details
rabbitmq_host = '192.168.1.227'  # I used my VM IP but Change this to your RabbitMQ server's address if needed
queue_name = 'registration_queue'


#Test user for authentication 
#test_user ={
#    "username" : "admin",
#    "password" : "admin123"
#}

# Function to send data to RabbitMQ
def send_to_rabbitmq(message):
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    
    # Declare a queue
    channel.queue_declare(queue=queue_name, durable=True)

    # Send a message to the queue
    channel.basic_publish(exchange='',
                          routing_key=queue_name,
                          body=message,
                          properties=pika.BasicProperties(
                              delivery_mode=2,  # Made message persistent
                          ))
    print(" [x] Sent to RabbitMQ:", message) #confirmation message if the frontend succesfully sent a message to rabbitMQ
    connection.close()

@app.route('/')
def home():
    return redirect('/login') #User is automatically redirected to the login screen when loading into our site

@app.route('/login', methods=['GET', 'POST'])
def login():
    return render_template('login.html')
    # if request.method == 'POST':
    #     username = request.form.get['username']
    #     password = request.form.get['password']

    # if not username or not password:
    #     flash('Please fill out all values!', 'danger')
    # elif username != test_user['username'] or password != test_user['password']:
    #     flash('Invalid Credentials. Please try again.', 'danger')
    # else:
    #     flash('You have been successfully logged in!', 'success')
    # return redirect('/home') # Redirect to home page if the user is authenticated


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
        send_to_rabbitmq(message)

        # Redirect to login screen after form submission
        return redirect('/login')

    # Render the registration form if it's a GET request
    return render_template('register.html')


@app.route('/dashboard')
def dashboard():
    return render_template('dashboard.html')



if __name__ == "__main__":
    app.run(debug=True, port=7012)