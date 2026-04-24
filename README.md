# Online Health Record Management System

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Python](https://img.shields.io/badge/Python-3.9+-blue.svg)](https://python.org)

## Overview

A web-based health record management system that empowers patients to take charge of their health by giving them access to their own medical histories. The system streamlines communication between patients, doctors, and hospital administration.

## Key Features

### For Patients
- View personal medical history
- Access health records anytime, anywhere
- Take proactive control over healthcare decisions

### For Doctors
- Add medical histories to patient records
- Complete disability assessment forms for patients undergoing life-altering events (bypassing separate, lengthy disability evaluations)

### For Administrators
- Conduct statistical research on hospital performance
- Generate relevant performance reports

## Why This Matters

Traditional disability assessments require patients to go through a separate, lengthy process after life-altering events. This system eliminates that wait by allowing doctors to document disabilities directly within the patient's medical record.

## Tech Stack

- **Backend:** [PHP]
- **Frontend:** [HTML, CSSS AND JAVASCRIPT]
- **Database:** [MySQL]
- **SERVER:** [XAMPP]

## Installation & Setup

```bash
# Clone the repository
git clone https://github.com/Aurie25/Online-Health-Record-Management-System.git

# Navigate to project folder
cd Online-Health-Record-Management-System

## Setup Instructions

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) installed
- PHP 7.4+ 
- MySQL 5.7+

### Step 1: Place the project files
Copy the entire project folder into:
C:\xampp\htdocs\ (Windows)
/Applications/XAMPP/htdocs/ (Mac)


### Step 2: Set up the database

1. Open **phpMyAdmin** (http://localhost/phpmyadmin)
2. Click on **SQL** tab
3. Copy and paste the SQL code from [`database.sql`](database.sql)
4. Click **Go** to execute

> **📁 The full SQL schema is available in:** `database requirements/database.sql`

### Step 3: Configure database connection
Update the database credentials in `config.php`:
```php
$db_host = "localhost";
$db_user = "root";
$db_password = "";
$db_name = "healthrecord_db";
