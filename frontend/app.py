from flask import Flask, render_template, request, redirect, url_for

app =  Flask (__name__) 

@app.route('/')
def home():
    return redirect('/login')


@app.route('/register', methods=['GET', 'POST'])
def registration():
    if request.method == 'POST':
        username = request.form['username']
        password = request.form['password']
        confirm_password = request.form['confirm_password']
        email = request.form['email']
        first_name = request.form['first_name']
        last_name = request.form['last_name']
        street = request.form['street']
        city = request.form['city']
        state = request.form['state']
        zip = request.form['zip']
        recent_job = request.form['job']
        school  = request.form['school']
        start_year = request.form['start_year']
        end_year = request.form['end_year']
        job_title = request.form['job_title']
        employment_type = request.form['employment_type']
        company_name = request.form['company_name']

        return redirect('/register')
    
    return render_template('registration.html')

if __name__ == "__main__":
    app.run(debug=True, port=7012)
