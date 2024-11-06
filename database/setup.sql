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
   country VARCHAR(100),
   state VARCHAR(100),
   zip_code VARCHAR(100),
   job_title VARCHAR(100),
   job_start_month VARCHAR(1000),
   job_end_month VARCHAR(100),
   job_current BOOLEAN DEFAULT FALSE,
   school_name VARCHAR(100),
   school_start_month VARCHAR(100),
   school_end_month VARCHAR(100),
   school_current BOOLEAN DEFAULT FALSE,
   security_question_1 VARCHAR(255),
   security_question_2 VARCHAR(255),
   security_question_3 VARCHAR(255),
   name VARCHAR(100),
   education TEXT,
   experience TEXT,
   profile_picture VARCHAR(255),
   resume_url VARCHAR(255),
   biography TEXT,
   date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



