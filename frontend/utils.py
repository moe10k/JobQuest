import re
from flask import session

# Validation Functions
def validate_email(email):
    email_pattern = r'^[\w\.-]+@[\w\.-]+\.\w+$'
    return re.match(email_pattern, email)

def validate_password(password, min_length=6):
    return len(password) >= min_length

def match_passwords(password, confirm_password):
    return password == confirm_password

# Session Utilities
def is_logged_in():
    return 'user' in session
