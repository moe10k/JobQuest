import pika, time, json, requests, os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()
api_key = os.getenv('API_KEY')  # Fetch API key from .env file

#RabbitMQ cluster nodes
rabbitmq_nodes = [
    '100.64.1.4', #rabbit@messaging - Master Node
    '100.64.1.5', #rabbit@andre - Slave node
    '100.64.1.2' #rabbit@database490 - Slave node
]

#Function to establish RabbitMQ connection with failover handling
def get_connection():
    for node in rabbitmq_nodes:
        try:
            connection = pika.BlockingConnection(pika.ConnectionParameters(host=node))
            print(f"Connected to RabbitMQ node: {node}")
            return connection
        except pika.exceptions.AMQPConnectionError:
            print(f"Failed to connect to RabbitMQ node: {node}. Trying next available node...")
    raise Exception("All RabbitMQ nodes are unreachable.")

# RabbitMQ Functions
def send_message(queue_name, message): #All queue_names and messages are declards inside app.py specific functions
    try:
        connection = get_connection()
        channel = connection.channel()
        channel.queue_declare(queue=queue_name, durable=True)
        channel.basic_publish(
            exchange='',
            routing_key=queue_name,
            body=message,
            properties=pika.BasicProperties(delivery_mode=2)  # Makes the message persistent
        )
        print(f"Message sent to queue '{queue_name}': {message}")
        connection.close()
    except Exception as e:
        print(f"Error sending message: {e}")

def consume_message(queue_name, delay=5): #All queue_names and messages are declards inside app.py specific functions
    try:
        connection = get_connection()
        channel = connection.channel()
        channel.queue_declare(queue=queue_name, durable=True)
        time.sleep(delay)  # Optional delay to allow message processing
        method_frame, header_frame, body = channel.basic_get(queue=queue_name, auto_ack=True)
        connection.close()
        if body:
            message = body.decode('utf-8')
            try:
                # Attempt to parse as JSON
                return json.loads(message)
            except json.JSONDecodeError:
                print(f"Warning: Message from queue '{queue_name}' is not valid JSON")
                # If not JSON, return as plain string
                return message
        return None
    except Exception as e:
        print(f"Error consuming message: {e}")
        return None

# External API Functions
def fetch_job_results(job_title, location):
    url = "https://api.apijobs.dev/v1/job/search"
    headers = {
        'apikey': api_key,
        'Content-Type': 'application/json'
    }
    payload = {
        'q': job_title,
        'country': location
    }
    
    response = requests.post(url, json=payload, headers=headers)

    if response.status_code == 200:
        job_results = response.json().get('hits', [])
        formatted_jobs = [
            {
                'title': job.get('title'),
                'company': job.get('hiringOrganizationName', 'Google'),
                'location': job.get('region', 'N/A'),
                'salary': job.get('baseSalaryMaxValue', '$75,000'),
                'job_url': job.get('url'),
                'description': job.get('description', 'Find out more on their website!'),
            } for job in job_results
        ]
        return formatted_jobs
    
    return []
