# JobQuest - Job Application Tracker

<div align="center">
  <h1><em>JobQuest</em></h1>
  <p><strong>Lost track of where you are? JobQuest is here for you!</strong></p>
</div>

## 📋 Overview

JobQuest is a comprehensive job application tracking platform designed to help users efficiently manage their job search process. The application provides tools for tracking job applications, managing professional networks, and staying organized throughout the job hunting journey.

### 🎯 Key Features

- **User Authentication & Profile Management**: Secure login/registration with profile customization
- **Job Application Tracking**: Monitor application status, interviews, and responses
- **Job Search Integration**: Search jobs using external APIs (LinkedIn)
- **Friend Network**: Connect with other users and build professional relationships
- **Dashboard Analytics**: Visual charts showing application statistics
- **Responsive Design**: Modern, mobile-friendly interface
- **High Availability**: Distributed architecture with failover capabilities

## 🏗️ Architecture

JobQuest follows a **microservices architecture** with the following components:

### Frontend (Flask/Python)
- **Location**: `frontend/`
- **Port**: 7012
- **Technology**: Flask, HTML/CSS/JavaScript, Chart.js
- **Features**: User interface, session management, API integration

### Backend Services (PHP)
- **Backend 1**: `backend1/` - User authentication and profile management
- **Backend 2**: `backend2/` - Friend system and social features
- **Technology**: PHP with Apache

### Database (MySQL)
- **Location**: `database/`
- **Schema**: User management, experience tracking, friend relationships
- **Tables**: User, Experience, User_info, Security_Questions, FriendRequests, Friends

### Message Queue (RabbitMQ)
- **Location**: `messaging/`
- **Purpose**: Inter-service communication and load balancing
- **Cluster**: Multi-node setup with failover capabilities

## 🚀 Quick Start

### Prerequisites

- **Python 3.8+**
- **PHP 7.4+**
- **MySQL 8.0+**
- **Apache/Nginx**
- **RabbitMQ 3.8+**

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd JobQuest
   ```

2. **Set up the database**
   ```bash
   # Connect to MySQL and run the setup script
   mysql -u root -p < database/setup.sql
   ```

3. **Configure database connection**
   ```bash
   # Edit database/db.php with your MySQL credentials
   $db_host = 'localhost';
   $db_user = 'your_username';
   $db_pass = 'your_password';
   $db_name = 'it490_db';
   ```

4. **Start the frontend**
   ```bash
   cd scripts
   chmod +x run_flask.sh
   ./run_flask.sh
   ```

5. **Start the backend services**
   ```bash
   chmod +x run_php_apache.sh
   ./run_php_apache.sh
   ```

6. **Configure RabbitMQ**
   ```bash
   # Update messaging/config.php with your RabbitMQ cluster details
   # Start RabbitMQ services on your cluster nodes
   ```

### Access the Application

- **Frontend**: http://localhost:7012
- **Backend APIs**: http://localhost (Apache default port)

## 🔧 Configuration

### Environment Variables

Create a `.env` file in the `frontend/` directory:

```env
API_KEY=your_linkedin_api_key
RABBITMQ_HOST=your_rabbitmq_host
RABBITMQ_PORT=5672
RABBITMQ_USERNAME=guest
RABBITMQ_PASSWORD=guest
```

### RabbitMQ Cluster Configuration

The application uses a multi-node RabbitMQ cluster for high availability:

```php
// messaging/config.php
$rabbitmqNodes = [
    [
        'host' => '100.64.1.4',  // Primary Node
        'node_name' => 'rabbit@messaging',
        'port' => 5672,
    ],
    [
        'host' => '100.64.1.5',  // Secondary Node
        'node_name' => 'rabbit@andre',
        'port' => 5672,
    ],
    [
        'host' => '100.64.1.2',  // Fallback Node
        'node_name' => 'rabbit@database490',
        'port' => 5672,
    ],
];
```

## 📊 Database Schema

### Core Tables

- **User**: User accounts and authentication
- **Experience**: Job history and work experience
- **User_info**: Personal information and education
- **Security_Questions**: Password recovery questions
- **FriendRequests**: Friend request management
- **Friends**: Established friend relationships

## 🔄 Message Queue Architecture

JobQuest uses RabbitMQ for inter-service communication:

### Request/Response Queues

- `registration_request_queue` ↔ `registration_response_queue`
- `login_request_queue` ↔ `login_response_queue`
- `popup_request_queue` ↔ `popup_response_queue`
- `resetpassword_request_queue` ↔ `resetpassword_response_queue`
- `friend_request_queue` ↔ `friend_response_queue`

### Message Flow

1. **Frontend** sends request to RabbitMQ queue
2. **Backend service** consumes message and processes request
3. **Database** operations are performed
4. **Response** is sent back through response queue
5. **Frontend** receives response and updates UI

## 🌐 API Integration

### LinkedIn Job Search API

The application integrates with LinkedIn's job search API to provide real-time job listings:

```python
# frontend/services.py
def fetch_job_results(job_title, location):
    url = "https://linkedin-data-api.p.rapidapi.com/search-jobs"
    headers = {
        "x-rapidapi-key": "your_api_key",
        "x-rapidapi-host": "linkedin-api8.p.rapidapi.com"
    }
    # Returns job listings with company info, descriptions, etc.
```

## 🎨 User Interface

### Key Pages

- **Landing Page**: Welcome screen with login/register options
- **Dashboard**: Main hub with analytics and quick actions
- **Profile**: User information and settings
- **Job Search**: Search and browse job opportunities
- **Job Tracker**: Monitor application status and progress
- **Friends**: Manage professional network
- **Settings**: Account preferences and security

### Features

- **Responsive Design**: Works on desktop, tablet, and mobile
- **Dark Mode**: Toggle between light and dark themes
- **Real-time Updates**: Live status updates via WebSocket
- **Interactive Charts**: Visual analytics using Chart.js
- **Flash Messages**: User feedback and notifications

## 🔒 Security Features

- **Password Hashing**: Secure password storage
- **Session Management**: Flask session-based authentication
- **Input Validation**: Server-side validation for all forms
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Output sanitization

## 🚀 Deployment

### Production Setup

1. **Web Server**: Configure Apache/Nginx with SSL
2. **Load Balancer**: Set up load balancing for high availability
3. **Database**: Use MySQL cluster for redundancy
4. **Message Queue**: Deploy RabbitMQ cluster across multiple nodes
5. **Monitoring**: Set up logging and monitoring tools

### Docker Support

```dockerfile
# Example Dockerfile for frontend
FROM python:3.9-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt
COPY . .
EXPOSE 7012
CMD ["python", "app.py"]
```

## 🧪 Testing

### Running Tests

```bash
# Frontend tests
cd frontend
python -m pytest tests/

# Backend tests
cd backend1
php vendor/bin/phpunit tests/
```

## 📝 API Documentation

### Authentication Endpoints

- `POST /register` - User registration
- `POST /login` - User authentication
- `POST /resetpassword` - Password reset

### Job Management Endpoints

- `GET /search` - Search for jobs
- `GET /tracker` - View application tracker
- `POST /submit_popup` - Submit profile information

### Social Features

- `GET /friends` - View friends list
- `POST /add_friends` - Send friend request
- `POST /handle_friend_request` - Accept/reject requests

## 🤝 Contributing

### Development Workflow

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

### Code Standards

- **Python**: Follow PEP 8 guidelines
- **PHP**: Follow PSR-12 standards
- **JavaScript**: Use ESLint configuration
- **HTML/CSS**: Validate markup and styles

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check MySQL service status
   - Verify database credentials in `database/db.php`

2. **RabbitMQ Connection Failed**
   - Ensure RabbitMQ service is running
   - Check cluster node availability
   - Verify connection parameters

3. **Frontend Not Loading**
   - Check Flask app is running on port 7012
   - Verify virtual environment is activated
   - Check for missing dependencies

### Logs

- **Frontend logs**: `frontend/frontend_backend_log.log`
- **Backend logs**: Check Apache error logs
- **RabbitMQ logs**: Check RabbitMQ management interface

## 📞 Support

For technical support or questions:

- **Email**: support@jobquest.com
- **Documentation**: [Wiki Link]
- **Issues**: GitHub Issues page

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

<div align="center">
  <p><strong>Built with ❤️ by Team JobQuest</strong></p>
  <p>Jose Arbelo (Database) | Kevin Burgos (Backend 2) | Nadia Manoppo (RabbitMQ) | Mohammad Kiyam (Backend 1) | Andre Henry (Frontend)</p>
</div>
