from flask import Flask, render_template, request, redirect, session, flash
from services import send_message, consume_message, fetch_job_results
from utils import validate_email, validate_password, match_passwords, is_logged_in

app = Flask(__name__)
app.secret_key = "secret_key"  # Secret key for session management

@app.route('/') #Default page where every will see - must be logged out
def landing():
    if is_logged_in(): #Checks if User is logged in and if true - redirect to Dashboard page
        flash('You must logout first.', 'danger')
        return redirect('/dashboard')
    
    return render_template('landing.html')

@app.route('/register', methods=['GET', 'POST']) #Register Page
def register():
    if is_logged_in(): #Checks if User is logged in and if true - redirect to Dashboard page
        flash('You must logout first.', 'danger')
        return redirect('/dashboard')
    
    if request.method == 'POST': #If form is submitted - grab usernme, email, password, confirm_password
        username = request.form['username']
        email = request.form['email']
        password = request.form['password']
        confirm_password = request.form['confirm_password']
        
        if not validate_email(email): #All 3 checks are in utils.py
            flash('Invalid email address.', 'danger')
            return render_template('register.html')
        if not validate_password(password):
            flash('Password must be at least 6 characters long.', 'danger')
            return render_template('register.html')
        if not match_passwords(password, confirm_password):
            flash('Passwords do not match.', 'danger')
            return render_template('register.html')
        
        message = f"{username},{email},{password}" #Put all variables in a message for rabbitmq

        send_message('registration_request_queue', message) #both rabbitmq functions are in services.py
        response = consume_message('registration_response_queue')

        if response == 'success':
            flash('Registration Successful!', 'success')
            return redirect('/login')
        else:
            flash('Registration Failed.', 'danger')

    return render_template('register.html')

@app.route('/login', methods=['GET', 'POST']) #Login Page
def login():
    if is_logged_in(): #Checks if User is logged in and if true - redirect to Dashboard page
        flash('You must logout first.', 'danger')
        return redirect('/dashboard')
    
    if request.method == 'POST': #If form is submitted - grab email and password
        email = request.form['email']
        password = request.form['password']

        if not validate_email(email): #Both checks are in utils.py
            flash('Invalid email address.', 'danger')
            return render_template('login.html')
        if not validate_password(password):
            flash('Password must be at least 6 characters long.', 'danger')
            return render_template('login.html')
        
        message = f"{email},{password}" #Put all variables in a message for rabbitmq

        send_message('login_request_queue', message) #both rabbitmq functions are in services.py
        response = consume_message('login_response_queue')

        if response['status'] == 'success':
            session['user'] = {
                'email': email,
                'username': response['username'],
                'user_id' : response['user_id'],
                'popup_enabled': response['popup_enabled']
            }
            flash('Login Successful!', 'success')
            return redirect('/dashboard')
        else:
            flash('Invalid credentials.', 'danger')

    return render_template('login.html')

@app.route('/dashboard') #Dashboard Page - Must be logged in
def dashboard():
    if is_logged_in(): #Checks if User is logged in and if true - grabs session variables
        user = session['user']
        user_id = user.get('user_id')
        show_popup = True if user.get('popup_enabled') == 0 else False
        return render_template('dashboard.html', user=user, user_id=user_id, show_popup=show_popup)
    flash('You must login first.', 'danger') #If not logged in - redirect to Login Page
    return redirect('/login')


@app.route('/submit_popup', methods=['POST']) #Popup Page
def submit_popup():
    if not is_logged_in(): #If not logged in - redirect to Login Page
       flash('You must login first', 'danger')
       return redirect('/login')
   
    #Once submitted - Grab all of these variables
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
    user_id = session.get('user', {}).get('user_id')

    #put all variables in a message for rabbitmq
    message = f"{first_name},{last_name},{country},{state},{zip_code},{job_title},{job_start_month},{job_end_month},{job_current},{school_name},{school_start_month},{school_end_month},{school_current},{security_question_1},{security_question_2},{security_question_3},{popup_enabled},{user_id}"

    send_message('popup_request_queue', message) #both rabbitmq functions are in services.py
    response = consume_message('popup_response_queue')

    if response == 'success':
        session['show_popup'] = False
        session['user']['popup_enabled'] = 1
        flash('Additional Information Submitted Successfully!', 'success')
    else:
        flash('Error Submitting - Please Try again later', 'danger')

    return redirect('/dashboard')

@app.route('/resetpassword', methods=['GET', 'POST']) #Reset Password Page
def resetpassword():
    if not is_logged_in(): #If not logged in - redirect to Login Page
       flash('You must login first', 'danger')
       return redirect('/login')
  
    if request.method == 'POST': #If form is submitted - Grab these variables
        question1 = request.form.get('question1')
        question2 = request.form.get('question2')
        question3 = request.form.get('question3')
        newpassword = request.form.get('newpassword')
        
        if not validate_password(newpassword): #Check is in utils.py
                flash('Password must be at least 6 characters long.', 'danger')
                return render_template('resetpassword.html')

        user_id = session.get('user', {}).get('user_id') #user id needed for updating correct row in table

        message = f"{user_id},{question1},{question2},{question3},{newpassword}" #Put all variables in a message for rabbitmq

        send_message('resetpassword_request_queue', message) #both rabbitmq functions are in services.py
        response = consume_message('resetpassword_response_queue')
        
        if response == 'success':
            flash('Password reset successful!', 'success')
            return redirect('/login')
        else:
            flash('Password reset failed. Please try again.', 'danger')
            return render_template('resetpassword.html')

    return render_template('resetpassword.html') 

@app.route('/search', methods=['GET', 'POST']) #Search Page
def search_jobs():
    if not is_logged_in(): #If not logged in - redirect to Login Page
       flash('You must login first', 'danger')
       return redirect('/login')
    
    if request.method == 'POST': #If form submitted - Grab all these variables
        job_title = request.form.get('job_title')
        location = request.form.get('location')
        job_results = fetch_job_results(job_title, location)
        return render_template('search.html', jobs=job_results)
    
    return render_template('search.html')

@app.route('/tracker', methods=['GET'])
def track_jobs():
    # Mock data for testing purposes
    applications = [
        {
            "job_name": "Software Engineer",
            "date_applied": "2024-11-01",
            "response_received": "Yes",
            "response_method": "Email",
            "interview_scheduled": "Yes",
            "number_of_interviews": 2,
            "offer_received": "Yes",
            "date_received": "2024-11-15"
        },
        {
            "job_name": "Data Analyst",
            "date_applied": "2024-10-20",
            "response_received": "No",
            "response_method": "-",
            "interview_scheduled": "-",
            "number_of_interviews": "-",
            "offer_received": "No",
            "date_received": "-"
        }
    ]

    # Pass mock data to the template
    return render_template('tracker.html', applications=applications)

@app.route('/friends', methods=['GET', 'POST'])
def friends():
    if not is_logged_in():  # Ensure user is logged in
        flash('You must login first', 'danger')
        return redirect('/login')

    # Handle friend request submission
    if request.method == 'POST':
        email = request.form.get('email')  # Get the friend's email from the form
        if not validate_email(email):
            flash('Invalid email address.', 'danger')
            return redirect('/friends')

        # Send friend request to RabbitMQ
        user_id = session.get('user', {}).get('user_id')  # Current user's ID
        message = f"{user_id},{email}"
        send_message('send_friendrequest_request_queue', message)
        response = consume_message('send_friendrequest_response_queue')

        if response == 'success':
            flash('Friend request sent successfully!', 'success')
        else:
            flash('Error sending friend request. Please try again.', 'danger')

    return render_template('friends.html') 

@app.route('/api/friends_list', methods=['GET'])
def api_friends_list():
    if not is_logged_in():
        return {'error': 'Not logged in'}, 401

    user_email = session.get('user', {}).get('email')
    send_message('fetch_friendslist_request_queue', user_email)
    friends_list = consume_message('fetch_friendslist_response_queue') or []
    return {'friends': friends_list}

@app.route('/api/pending_requests', methods=['GET'])
def api_pending_requests():
    if not is_logged_in():
        return {'error': 'Not logged in'}, 401

    user_id = session.get('user', {}).get('user_id')
    send_message('fetch_pending_friendrequest_request_queue', str(user_id))
    pending_requests = consume_message('fetch_pending_friendrequest_response_queue') or []
    return {'requests': [{'email': email} for email in pending_requests]}

@app.route('/handle_friend_request', methods=['POST'])
def handle_friend_request():
    if not is_logged_in():  # Ensure user is logged in
        flash('You must login first', 'danger')
        return redirect('/login')

    data = request.get_json()  # Parse JSON data
    friend_email = data.get('email')  # Corresponding email
    action = data.get('action') #accept or reject

    user_id = session.get('user', {}).get('user_id')  # Current user's ID
    user_email = session.get('user', {}).get('email')  # Current user's email

    # Handle the action
    message = f"{user_id},{user_email},{friend_email},{action}"
    send_message('pending_friendrequest_request_queue', message)
    response = consume_message('pending_friendrequest_response_queue')

    if response == 'success':
        flash(f"Friend request for {friend_email} successfully processed!", 'success')
    else:
        flash(f"Error processing friend request for {friend_email}. Please try again.", 'danger')

    return redirect('/friends')


@app.route('/profile')
def profile():
    # Pass user details dynamically
    #user = get_user_details()  # Replace with actual user data retrieval logic
    #return render_template('profile.html', user=user)
    return render_template('profile.html')


@app.route('/logout')
def logout(): #If not logged in - redirect to Login Page
    if not is_logged_in():
       flash('You must login first', 'danger')
       return redirect('/login')
    
    session.clear()
    flash('You have been logged out.', 'success')
    return redirect('/login')

@app.route('/comingsoon')
def coming_soon():
    return render_template('partials/comingsoon.html')

@app.context_processor
def inject_is_logged_in():
   return dict(is_logged_in=is_logged_in())

if __name__ == "__main__":
    app.run(debug=True, port=7012)
