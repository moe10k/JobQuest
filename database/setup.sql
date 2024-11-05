-- Create the database
CREATE DATABASE it490_db;

-- Use the database
USE it490_db;

-- Create the User table
CREATE TABLE User (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    username VARCHAR(50),
    password VARCHAR(255),
    email VARCHAR(100),
    country VARCHAR(100), -- New field
    state VARCHAR(100), -- New field
    zip_code VARCHAR(100), -- New field
    job_title VARCHAR(100), -- New field
    job_start_month VARCHAR(1000), -- New field
    job_end_month VARCHAR(100), -- New field
    job_current BOOLEAN DEFAULT FALSE, -- New field
    school_name VARCHAR(100), -- New field
    school_start_month VARCHAR(100), -- New field
    school_end_month VARCHAR(100), -- New field
    school_current BOOLEAN DEFAULT FALSE, -- New field
    security_question_1 VARCHAR(255), -- New field
    security_question_2 VARCHAR(255), -- New field
    security_question_3 VARCHAR(255), -- New field
    name VARCHAR(100),
    education TEXT,
    experience TEXT,
    profile_picture VARCHAR(255),
    resume_url VARCHAR(255),
    biography TEXT,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
