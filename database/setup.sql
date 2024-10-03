-- Create the database
CREATE DATABASE it490_db;

-- Use the database
USE it490_db;

-- Create the User table
CREATE TABLE User (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL
);
