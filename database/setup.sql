-- Create the database
CREATE DATABASE it490_db;

-- Use the database
USE it490_db;

-- Create the User table
CREATE TABLE User (
    CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(100),
    education TEXT,
    experience TEXT,
    profile_picture VARCHAR(255),
    resume_url VARCHAR(255),
    biography TEXT,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

);
