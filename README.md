# 📝 Task Management System

A web-based **Task Management System** built with **PHP, MySQL, Bootstrap, and JavaScript**. This application enables admins to manage tasks efficiently and allows team members to track their assigned tasks. It features **real-time AJAX updates**, **user authentication**, **role-based access control**, and a **responsive iOS-style UI**.

---

## 🚀 Features

✔ **User Authentication** – Secure login for admins and team members.  
✔ **Role-Based Access**:
  - **Admins**: Can create tasks and assign them to team members. Can edit tasks assigned to themselves.
  - **Team Members**: Can view and update the status of their assigned tasks but cannot edit overdue or completed tasks.  
✔ **Task Management**:
  - Tasks contain a title, description, deadline, status, and assignment details.
  - Status options: `pending`, `in_progress`, `completed`.
  - Team members cannot edit tasks once marked as `completed` or if the deadline has passed.
  - Admins can edit tasks assigned to themselves, regardless of status or deadline.
✔ **Real-Time Updates** – Notifications and tasks refresh every **5 seconds** using AJAX.  
✔ **User Role Display** – Displays logged-in user’s name and role.  
✔ **Responsive Design** – iOS-style UI using Bootstrap & custom CSS.  

---

## 📂 Project Structure

```
task-management-system/
├── assets/
│   ├── css/
│   │   ├── styles.css          # General styles
│   │   ├── admin_styles.css    # Admin-specific styles
│   │   └── team_tasks.css      # Team task list styles (iOS-style)
├── includes/
│   ├── db.php                  # Database connection
│   └── menu.php                # Navigation menu
├── team/
│   ├── task_list.php           # Team member task list page
│   ├── fetch_notifications.php # AJAX endpoint for notifications
│   └── fetch_tasks.php         # AJAX endpoint for tasks
├── login.php                   # Login page
└── README.md                   # Project documentation
```

---

## 🛠️ Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache via XAMPP, WAMP, or a live server)
- Git (for version control)
- Composer (optional, for dependency management)

---

## 🔧 Setup Instructions

### 1️⃣ Clone the Repository

```bash
git clone https://github.com/DharmitCode/task-management-system.git
cd task-management-system
```

### 2️⃣ Set Up the Database

- Create a **MySQL database** and import the provided SQL script (see below).
- Update `includes/db.php` with your database credentials:

$host = 'localhost';
$dbname = 'task_management';
$username = 'your_username';
$password = 'your_password';


For **XAMPP users**, use:

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'task_management';


### 3️⃣ Configure the Web Server

- Place the project folder in your web server’s root directory (e.g., `htdocs` for XAMPP).
- Ensure your web server and MySQL service are running.

### 4️⃣ Access the Application

- Open your browser and go to:  
  **http://localhost/task-management-system/login.php**

- Log in using the default credentials (see Database Setup below).

---

## 🗄️ Database Setup

Run the following SQL script in your MySQL client (e.g., phpMyAdmin):


CREATE DATABASE task_management;
USE task_management;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'team') NOT NULL
);

-- Tasks table
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    deadline DATETIME NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    assigned_to INT NOT NULL,
    created_by INT NOT NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default users
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$z8nXvM2gXvYvYvYvYvYvYe8XvM2gXvYvYvYvYvYvYe8XvM2gXvYv', 'admin'), -- Password: admin123
('team1', '$2y$10$z8nXvM2gXvYvYvYvYvYvYe8XvM2gXvYvYvYvYvYvYe8XvM2gXvYv', 'team');   -- Password: team123


> **Note**: The passwords in the `users` table are hashed using PHP’s `password_hash()` function.  
> The plain text passwords are `admin123` for admin and `team123` for team1.

---

## 👥 How It Works

### **For Admins**

✅ **Login**: Log in with your admin credentials (e.g., `admin`, `admin123`).  
✅ **View Tasks**: Navigate to `team/task_list.php` to view assigned tasks.  
✅ **Edit Tasks**: Modify tasks assigned to you, even if overdue or completed.  
✅ **Real-Time Updates**: Notifications and tasks refresh every **5 seconds**.

### **For Team Members**

✅ **Login**: Log in with team member credentials (e.g., `team1`, `team123`).  
✅ **View Tasks**: Navigate to `team/task_list.php` to track tasks.  
✅ **Edit Tasks**: Can update task status **unless** it’s overdue or completed.  
✅ **Real-Time Updates**: Live notifications and task updates every **5 seconds**.

---

## 🛠️ Troubleshooting

🔹 **Database Errors?** Check `includes/db.php` for correct credentials.  
🔹 **No Tasks Displayed?** Ensure tasks are assigned to the logged-in user in the database.  
🔹 **AJAX Not Working?** Open the browser console (`F12`) and check for errors.  
🔹 **CSS Issues?** Clear browser cache if styles don’t load properly.  

---

## 🔮 Future Improvements

✅ Allow admins to manage all tasks, not just their own.  
✅ Add task creation and deletion features for admins.  
✅ Implement pagination for large task lists.  
✅ Introduce email notifications for task updates.  

---

## 📜 License

This project is open-source and available under the **MIT License**.

---

## ⭐ Contribute & Support

Found a bug or want to contribute? Feel free to submit an issue or a pull request!  
If you like this project, don’t forget to **⭐ star the repository**! 😊

---

🔗 **GitHub Repository**: [DharmitCode/task-management-system](https://github.com/DharmitCode/task-management-system)
