-- Create the database
CREATE DATABASE it490_db;

-- Use the database
USE it490_db;

-- Create the User table
CREATE TABLE User (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100),
    password VARCHAR(255),
    username VARCHAR(50),
    popup_enabled BOOLEAN DEFAULT FALSE,
    dark_mode_enabled BOOLEAN DEFAULT FALSE,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create the Experience table
CREATE TABLE Experience (
    exp_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    job_title VARCHAR(100),
    job_start_month VARCHAR(100),
    job_end_month VARCHAR(100),
    job_current BOOLEAN DEFAULT FALSE,
    bullet_points TEXT,
    FOREIGN KEY (user_id) REFERENCES User(user_id)
);

-- Create the User_info table
CREATE TABLE User_info (
    info_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    country VARCHAR(100),
    state VARCHAR(100),
    zip_code VARCHAR(10),
    school_name VARCHAR(100),
    school_start_month VARCHAR(20),
    school_end_month VARCHAR(20),
    school_current BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES User(user_id)
);

-- Create the Security_Questions table
CREATE TABLE Security_Questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    security_question_1 VARCHAR(255),
    security_question_2 VARCHAR(255),
    security_question_3 VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES User(user_id)
);

CREATE TABLE FriendRequests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    sender_email VARCHAR(255),
    receiver_id INT,
    receiver_email VARCHAR(255),
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES User(user_id),
    FOREIGN KEY (receiver_id) REFERENCES User(user_id)
);

CREATE TABLE Friends (
    friendship_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_email VARCHAR(255),
    friend_id INT,
    friend_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(user_id),
    FOREIGN KEY (friend_id) REFERENCES User(user_id)
);
