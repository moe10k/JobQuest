import pika, time, json, requests, os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()
rabbitmq_host = '10.147.17.228'  # Update this to your RabbitMQ server's address
api_key = os.getenv('API_KEY')  # Fetch API key from .env file

# RabbitMQ Functions
def send_message(queue_name, message): #All queue_names and messages are declards inside app.py specific functions
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
    channel = connection.channel()
    channel.queue_declare(queue=queue_name, durable=True)
    channel.basic_publish(
        exchange='',
        routing_key=queue_name,
        body=message,
        properties=pika.BasicProperties(delivery_mode=2)  # Makes the message persistent
    )
    connection.close()

def consume_message(queue_name, delay=5): #All queue_names and messages are declards inside app.py specific functions
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=rabbitmq_host))
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
            # If not JSON, return as plain string
            return message
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
                'job_url': job.get('url'),
                'company': job.get('hiringOrganizationName', 'N/A'),
                'location': job.get('region', 'N/A'),
                'description': job.get('description', 'N/A'),
            } for job in job_results
        ]
        return formatted_jobs
    
    return []
