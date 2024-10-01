from flask import Flask, flash, render_template, request, redirect, url_for

app =  Flask (__name__) 
app.secret_key = "secret_key" #Secret key for flashing messages
#Source: www.geeksforgeeks.org/flask-message-flashing/-->

#Test user for authentication 
test_user ={
    "username" : "admin",
    "password" : "admin123"
}

@app.route('/')
def home():
    return redirect('/login')

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


@app.route('/register', methods=['GET', 'POST']) #GET for form display, POST for form submission
def register():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form['password']
        confirm_password = request.form['confirm_password']
        email = request.form['email']
        first_name = request.form['first_name']
        last_name = request.form['last_name']
        address= request.form['address']
        city = request.form['city']
        state = request.form['state']
        zip = request.form['zip']
        recent_job = request.form['job']
        school  = request.form['school']
        major = request.form['major']
        start_year = request.form['start_year']
        end_year = request.form['end_year']
        job_title = request.form['job_title']
        employment_type = request.form['employment_type']
        company_name = request.form['company']

        return redirect('/register.html')
    
    return render_template('register.html')

@app.route('/dashboard')
def dashboard():
    return render_template('dashboard.html')



if __name__ == "__main__":
    app.run(debug=True, port=7012)