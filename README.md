# 🛡️ CyberCrime Hive

**A Comprehensive Cybercrime Reporting and Management Platform**

*Final Year BSc IT Project - New Life College, Tamale Campus*  
*Developed by: Abdul Rauf Abdul Rahaman*

---

## 🎯 Overview

CyberCrime Hive is a sophisticated web-based platform designed to streamline the reporting, tracking, and management of cybercrime incidents. Built as a final year project for BSc IT at New Life College, Tamale campus, this system provides a secure, user-friendly interface for both citizens to report cybercrimes and administrators to manage and investigate these reports effectively.

### 🎓 Academic Context
- **Institution**: New Life College, Tamale Campus
- **Program**: Bachelor of Science in Information Technology (BSc IT)
- **Project Type**: Final Year Project
- **Developer**: Abdul Rauf Abdul Rahaman
- **Year**: 2025

### 🌟 Project Objectives
- Create a centralized platform for cybercrime reporting
- Implement secure user authentication and role-based access control
- Provide real-time tracking and status updates for reported incidents
- Develop comprehensive analytics and reporting capabilities
- Ensure data security and privacy compliance
- Build a scalable and maintainable system architecture

---

## ✨ Features

### 🔐 Core Functionality
- **Incident Reporting**: Comprehensive form for reporting various types of cybercrimes
- **Real-time Tracking**: Unique tracking codes for monitoring report status
- **File Attachments**: Secure upload and storage of evidence files
- **Status Management**: Multi-stage workflow (Submitted → Under Review → In Investigation → Resolved)
- **Notification System**: Email and SMS notifications for status updates

### 👥 User Management
- **Role-based Access Control**: Separate interfaces for users and administrators
- **Secure Authentication**: Password hashing, session management, and 2FA support
- **User Profiles**: Comprehensive user information management
- **Account Security**: Password reset, account activation, and security logging

### 📊 Analytics & Reporting
- **Dashboard Analytics**: Real-time statistics and trend analysis
- **Export Capabilities**: CSV and PDF export functionality
- **Category Analytics**: Breakdown of incidents by type and severity
- **Performance Metrics**: Response times and resolution statistics

### 🎓 Knowledge Management
- **Knowledge Base**: Educational articles and cybersecurity resources
- **Content Management**: Admin-controlled article publishing system
- **SEO-friendly URLs**: Clean URL structure for better accessibility
- **Search Functionality**: Advanced search capabilities

### 💬 Feedback System
- **Secure Feedback Links**: Token-based feedback collection
- **Multi-dimensional Ratings**: Communication, speed, professionalism metrics
- **Analytics Integration**: Feedback analytics and response rate tracking
- **Follow-up Management**: Automated follow-up for unresolved issues

---

## 🛠️ Technology Stack

### Backend Technologies
- **PHP 8.0+**: Core server-side programming language
- **MySQL 8.0+**: Primary database management system
- **Apache/LiteSpeed**: Web server with mod_rewrite support

### Frontend Technologies
- **HTML5**: Semantic markup structure
- **CSS3**: Modern styling with responsive design
- **Bootstrap 5.3.0**: UI framework for responsive layouts
- **JavaScript (ES6+)**: Client-side interactivity
- **Chart.js**: Data visualization and analytics charts
- **Font Awesome**: Icon library for enhanced UI

### Development Tools & Libraries
- **Composer**: PHP dependency management
- **PHPMailer 6.10+**: Email functionality
- **DomPDF 3.1+**: PDF generation capabilities
- **Environment Management**: Multi-environment configuration support

### Security & Performance
- **CSRF Protection**: Cross-site request forgery prevention
- **SQL Injection Prevention**: Prepared statements and input validation
- **XSS Protection**: Output sanitization and content security policies
- **Session Security**: Secure session management with timeout controls
- **File Upload Security**: Type validation and secure storage

---

## 🏗️ System Architecture

### Directory Structure
```
cybercrime_hive/
├── admin/                  # Administrative interface
│   ├── dashboard.php      # Main admin dashboard
│   ├── reports.php        # Report management
│   ├── users.php          # User management
│   ├── feedback.php       # Feedback analytics
│   └── knowledge_cms.php  # Content management
├── api/                   # RESTful API endpoints
│   └── index.php          # API router and handlers
├── assets/                # Static resources
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript files
│   └── images/           # Image assets
├── auth/                  # Authentication system
│   ├── login.php         # User login
│   ├── register.php      # User registration
│   └── forgot_password.php # Password recovery
├── classes/               # PHP class definitions
├── config/                # Configuration files
│   ├── config.php        # Application settings
│   ├── database.php      # Database connection
│   ├── environment.php   # Environment management
│   └── init_db.php       # Database initialization
├── database/              # Database schemas
│   ├── feedback_system.sql # Feedback system tables
│   └── create_knowledge_base.sql # Knowledge base schema
├── feedback/              # Feedback collection system
├── includes/              # Shared components
│   ├── header.php        # Common header
│   ├── footer.php        # Common footer
│   ├── functions.php     # Utility functions
│   └── EmailService.php  # Email service class
├── knowledge/             # Knowledge base system
├── reports/               # Report submission and tracking
│   ├── submit.php        # Report submission form
│   └── track.php         # Report tracking interface
├── user/                  # User dashboard and profile
└── vendor/                # Composer dependencies
```

### Database Architecture
The system uses a normalized MySQL database with the following key entities:
- **Users**: User accounts and authentication
- **Reports**: Cybercrime incident reports
- **Attachments**: File uploads and evidence
- **Notifications**: Communication logs
- **Feedback**: User satisfaction surveys
- **Knowledge Base**: Educational content
- **Admin Logs**: Administrative action tracking

---

## 🚀 Installation

### Prerequisites
- **Web Server**: Apache 2.4+ or LiteSpeed with mod_rewrite
- **PHP**: Version 8.0 or higher
- **MySQL**: Version 8.0 or higher
- **Composer**: For dependency management

### Step-by-Step Installation

1. **Clone the Repository**
   ```bash
   git clone https://github.com/yourusername/cybercrime-hive.git
   cd cybercrime-hive
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Database Setup**
   ```sql
   CREATE DATABASE cybercrime_hive;
   ```

4. **Environment Configuration**
   ```bash
   cp .env.example .env
   # Edit .env with your database and SMTP settings
   ```

5. **Initialize Database**
   ```bash
   php config/init_db.php
   ```

6. **Set Permissions**
   ```bash
   chmod 755 uploads/
   chmod 644 .htaccess
   ```

7. **Configure Web Server**
   - Ensure mod_rewrite is enabled
   - Set document root to the project directory
   - Configure SSL certificate (recommended)

### Docker Installation (Alternative)
```bash
docker-compose up -d
```

---

## ⚙️ Configuration

### Environment Variables
Create a `.env` file in the root directory:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=cybercrime_hive
DB_USER=your_username
DB_PASS=your_password

# SMTP Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password

# Application Settings
SITE_URL=https://yourdomain.com
ADMIN_EMAIL=admin@yourdomain.com
APP_ENV=production
```

### Security Configuration
- Update default admin credentials
- Configure CSRF tokens
- Set up SSL certificates
- Configure firewall rules
- Enable security headers

---

## 🗄️ Database Schema

### Core Tables

#### Users Table
```sql
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    phone VARCHAR(20),
    registered_at DATETIME,
    role ENUM('user', 'admin') DEFAULT 'user'
);
```

#### Reports Table
```sql
CREATE TABLE reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('Online Banking Fraud', 'Phishing', 'Hacking', ...) NOT NULL,
    incident_date DATETIME NOT NULL,
    status ENUM('Submitted', 'Under Review', 'In Investigation', 'Resolved') DEFAULT 'Submitted',
    tracking_code VARCHAR(20) UNIQUE NOT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);
```

### Cybercrime Categories
The system supports 50+ cybercrime categories including:
- **Fraud & Financial**: Online Banking Fraud, Credit Card Fraud, Identity Theft
- **Email & Communication**: Phishing, Email Spoofing, SMS Phishing
- **Hacking & Access**: Unauthorized Access, Malware Distribution, Network Intrusion
- **Harassment & Abuse**: Cyberbullying, Cyberstalking, Online Defamation
- **And many more...**

---

## 🔌 API Documentation

### Base URL
```
https://yourdomain.com/api/
```

### Authentication
API endpoints require authentication via session or API key.

### Endpoints

#### Get Public Statistics
```http
GET /api/stats
```

#### List Reports
```http
GET /api/reports
Authorization: Bearer {token}
```

#### Get Specific Report
```http
GET /api/reports/{tracking_code}
Authorization: Bearer {token}
```

#### Submit Report
```http
POST /api/submit
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "Report Title",
    "description": "Detailed description",
    "category": "Phishing",
    "incident_date": "2024-01-15 10:30:00"
}
```

#### Get Categories
```http
GET /api/categories
Authorization: Bearer {token}
```

---

## 👤 User Roles & Permissions

### Regular Users
- **Report Submission**: Create and submit cybercrime reports
- **Report Tracking**: Track status of submitted reports
- **Profile Management**: Update personal information
- **Feedback Submission**: Provide feedback on resolved cases
- **Knowledge Base Access**: Read educational articles

### Administrators
- **Full User Access**: All regular user capabilities
- **Report Management**: View, update, and manage all reports
- **User Management**: Create, modify, and manage user accounts
- **Analytics Dashboard**: Access to comprehensive analytics
- **Content Management**: Manage knowledge base articles
- **System Configuration**: Modify system settings and configurations
- **Export Capabilities**: Generate reports in various formats

### Security Features
- **Two-Factor Authentication**: Optional 2FA for admin accounts
- **Session Management**: Automatic session timeout and security
- **Audit Logging**: Comprehensive logging of administrative actions
- **Role-based Access Control**: Granular permission system

---

## 🔒 Security Features

### Authentication & Authorization
- **Secure Password Hashing**: bcrypt with salt
- **Session Security**: Secure session handling with timeout
- **CSRF Protection**: Cross-site request forgery prevention
- **Role-based Access**: Granular permission system

### Data Protection
- **Input Validation**: Comprehensive server-side validation
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Output sanitization
- **File Upload Security**: Type and size validation

### Infrastructure Security
- **HTTPS Enforcement**: SSL/TLS encryption
- **Security Headers**: Content Security Policy, X-Frame-Options
- **Environment Detection**: Automatic security configuration
- **Error Handling**: Secure error reporting

---

## 📖 Usage Guide

### For Citizens (Regular Users)

1. **Registration**
   - Visit the registration page
   - Provide required information
   - Verify email address
   - Login with credentials

2. **Reporting a Cybercrime**
   - Navigate to "Submit Report"
   - Fill out the comprehensive form
   - Upload supporting evidence
   - Receive tracking code

3. **Tracking Reports**
   - Use tracking code to monitor progress
   - Receive email/SMS notifications
   - View detailed status updates

### For Administrators

1. **Dashboard Overview**
   - Monitor system statistics
   - View recent reports and activities
   - Access quick action items

2. **Report Management**
   - Review submitted reports
   - Update investigation status
   - Assign cases to investigators
   - Generate reports

3. **User Management**
   - Manage user accounts
   - Reset passwords
   - Monitor user activities

---

## 📸 Screenshots

### User Interface
- **Homepage**: Modern, responsive design with cybersecurity focus
- **Report Submission**: Intuitive multi-step form with validation
- **Tracking Interface**: Real-time status updates with timeline view
- **User Dashboard**: Personalized dashboard with report history

### Administrative Interface
- **Admin Dashboard**: Comprehensive analytics and system overview
- **Report Management**: Advanced filtering and bulk operations
- **User Management**: Complete user lifecycle management
- **Analytics**: Interactive charts and export capabilities

---

## 🤝 Contributing

This project was developed as an academic capstone project. While it's primarily for educational purposes, contributions and suggestions are welcome for learning and improvement.

### Development Guidelines
1. Follow PSR-12 coding standards
2. Write comprehensive comments
3. Include unit tests for new features
4. Update documentation for changes
5. Follow security best practices

### Reporting Issues
Please report any security vulnerabilities privately to the developer.

---

## 📄 License

This project is developed for academic purposes as part of a BSc IT final year project at New Life College, Tamale Campus. 

**Academic Use**: This project is submitted as partial fulfillment of the requirements for the Bachelor of Science in Information Technology degree.

**Educational Purpose**: The code and documentation are made available for educational and learning purposes.

---

## 📞 Contact

### Developer Information
- **Name**: Abdul Rauf Abdul Rahaman
- **Institution**: New Life College, Tamale Campus
- **Program**: BSc Information Technology
- **Email**: [niendoo2@gmail.com]
- **LinkedIn**: [[LinkedIn Profile](https://www.linkedin.com/in/abdul-rauf-abdul-rahaman-255209106/)]


### Project Repository
- **GitHub**: [[Repository URL](https://github.com/niendoo/cybercrime_hive/)]
- **Documentation**: [[Documentation URL](https://github.com/niendoo/cybercrime_hive/readme.me)]

---

## 🙏 Acknowledgments

- **New Life College, Tamale Campus** - For providing the academic framework and support
- **IT Department Faculty** - For guidance and mentorship throughout the project
- **Open Source Community** - For the tools and libraries that made this project possible
- **Cybersecurity Professionals** - For insights into real-world cybercrime reporting needs


