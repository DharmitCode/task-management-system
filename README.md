# Task Management System

A simple web application for task management, built with HTML, CSS (Bootstrap), JavaScript, PHP, and MySQL. It allows admins to assign tasks to team members, who can then view and update their tasks.

## Features
- **User Authentication**: Admins and team members can log in with role-based access.
- **Admin Dashboard**: Admins can assign tasks, view task statistics, and see a list of assigned tasks.
- **Team Task List**: Team members can view tasks assigned to them, update task status, and add remarks.
- **Notifications**: Team members receive notifications when tasks are assigned.
- **Responsive Design**: The app is mobile-friendly using Bootstrap.

## Setup Instructions
1. **Install XAMPP**:
   - Download and install XAMPP from [apachefriends.org](https://www.apachefriends.org/).
   - Start Apache and MySQL in the XAMPP Control Panel.

2. **Clone the Repository**:
	git clone https://github.com/yourusername/task-management-system.git


3. **Move to `htdocs`**:
- Copy the `task-management-system` folder to `C:\xampp\htdocs`.

4. **Set Up the Database**:
- Open `http://localhost/phpmyadmin`.
- Create a database named `task_management`.
- Run the following SQL to create tables:
  ```sql
  CREATE TABLE users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(50) NOT NULL UNIQUE,
      password VARCHAR(255) NOT NULL,
      role ENUM('admin', 'team') NOT NULL,
      email VARCHAR(100) NOT NULL
  );

  CREATE TABLE tasks (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      description TEXT,
      deadline DATETIME,
      assigned_to INT,
      created_by INT,
      status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      remarks TEXT, -- Added for team member remarks
      FOREIGN KEY (assigned_to) REFERENCES users(id),
      FOREIGN KEY (created_by) REFERENCES users(id)
  );

  CREATE TABLE notifications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT,
      message TEXT NOT NULL,
      is_read BOOLEAN DEFAULT FALSE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES users(id)
  );

Add test users:

INSERT INTO users (username, password, role, email)
VALUES ('admin1', '$2y$10$YOUR_HASH_HERE', 'admin', 'admin@example.com'),
       ('team1', '$2y$10$YOUR_HASH_HERE', 'team', 'team1@example.com');

Generate password hashes using:

echo password_hash("admin123", PASSWORD_DEFAULT);
echo password_hash("team123", PASSWORD_DEFAULT);

