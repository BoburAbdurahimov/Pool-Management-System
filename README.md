🏊‍♂️ Pool Management System

A backend-focused PHP & MySQL – based management system designed to store, manage, and report data related to pool membership, attendance, activities, and billing. Built with emphasis on clean architecture, RESTful API design, and database integrity.

📌 Table of Contents

💡 Overview

🛠️ Tech Stack

🚀 Features

🗂️ Architecture

🧠 Database Schema

⚙️ Installation & Setup

🧪 API Endpoints

🧾 Example Requests

📈 Future Improvements

📄 License

💡 Overview

The Pool Management System is designed for administrators to efficiently manage:

Members and their subscriptions

Pool attendance and schedules

Billing and membership status

Activities and history tracking

It exposes clean backend APIs for future frontend integration (mobile app, admin dashboard, etc.).

🛠️ Tech Stack
Layer	Technology
Backend	PHP
Database	MySQL
API Style	RESTful
Version Control	Git & GitHub
🚀 Core Features

✔ Member registration and management
✔ Multiple subscription types
✔ Attendance logging
✔ Billing and status reports
✔ Secure backend logic
✔ Well-structured database design

🗂 Architecture

This project follows a backend-centric structure, focusing on:

Modular controllers

Database abstraction

Clear request/response flow

Scalability for future frontend integration

🧠 Database Schema

The system uses MySQL with tables including:

members

subscriptions

attendance

billing

These tables are linked via relational keys to enforce consistency.

(Detailed schema diagrams and migrations can be added later.)

⚙️ Installation & Setup
1. Clone the Repo
git clone https://github.com/BoburAbdurahimov/Pool-Management-System.git
cd Pool-Management-System
2. Install Dependencies

Make sure you have PHP & Composer installed:

composer install
3. Setup Database

Create a database:

CREATE DATABASE pool_management;

Import structure:

mysql -u root -p pool_management < database.sql

Configure DB connection in your .env or config file:

DB_HOST=127.0.0.1
DB_NAME=pool_management
DB_USER=root
DB_PASS=secret
4. Run the Application

Depending on setup:

php -S localhost:8000

Visit:
👉 http://localhost:8000

🧪 API Endpoints
Method	URL	Description
GET	/members	Get all members
POST	/members	Add a new member
GET	/attendance	List attendance
POST	/attendance	Log attendance
GET	/billing	Billing overview

(Replace with real endpoints if different.)

🧾 Example Requests
Add a Member
curl -X POST http://localhost:8000/members \
     -H "Content-Type: application/json" \
     -d '{ "name": "John Doe", "email": "john@example.com" }'
Log Attendance
curl -X POST http://localhost:8000/attendance \
     -H "Content-Type: application/json" \
     -d '{ "member_id": 1, "status": "present" }'
📈 Future Improvements

✨ Add token-based authentication (JWT)
✨ Full admin panel UI (Vue.js)
✨ Dockerized deployment
✨ Unit tests & CI/CD
✨ Pagination and filters for APIs

📄 License

This project is open-source — feel free to use it and improve it.
![image](https://github.com/user-attachments/assets/49f76816-3a7d-42ad-b57d-08966eeb0527)
![image](https://github.com/user-attachments/assets/fbb1d251-6226-47a3-91f9-b5d8371cc2b5)
![image](https://github.com/user-attachments/assets/a290c2b6-d9d2-4145-ab75-0238ac9e133d)
![image](https://github.com/user-attachments/assets/fb9f08e5-4451-4a83-8fde-e620ee3f3beb)
![image](https://github.com/user-attachments/assets/2dfdce2a-26fc-42a9-9e24-f528782045af)
![image](https://github.com/user-attachments/assets/0256f1cb-1fc1-4dc6-be34-c7aeb7d7078e)
![image](https://github.com/user-attachments/assets/3c72acd5-4a30-4b06-895c-d9cecea79ad1)
![image](https://github.com/user-attachments/assets/3a2848a5-b0df-4007-94db-a86d1d4bde73)







