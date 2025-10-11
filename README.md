## **Swachh – Waste Segregation Monitoring System**



HTML | CSS | JavaScript | PHP | MySQL



Swachh is a smart waste segregation monitoring platform designed for residential societies.  

It enables residents to track their waste segregation habits and allows waste collection workers to report the status of each apartment.  

The system promotes sustainable waste management through data-driven insights, credits, and accountability.



##### ✨ Features

👷 **Worker Dashboard** - View societies, mark apartments as segregated / partially / not segregated / no waste

🏠 **Resident Dashboard** - Track segregation status and performance reports

🔐 **Secure Login System** - Separate roles for workers and residents

📊 **Analytics** - Weekly and monthly segregation summaries

💰 **Credits \& Penaltie**s - Reward segregation consistency, apply fines for low performance

📅 **Auto Logs** - Each day’s report stored with timestamp

🚫 **No Waste Option** - Workers can mark apartments with no waste





##### 📂 Folder Structure

Swachh/

├── api/              *# Backend PHP endpoints (login, save\_report, logout)*

├── workers/          *# Worker interface pages*

├── residents/        *# Resident interface pages*

├── css/              *# Styling (style.css)*

├── js/               *# Frontend JavaScript*

├── db.php            *# Database connection file*

├── index.php         *# Login and role selection*

├── schema.sql        *# Database schema and seed data*

└── README.txt        *# Project documentation*





##### ⚙️ Setup Instructions

**1. Requirements**

&nbsp;  - XAMPP or WAMP (PHP 7.2+ and MySQL)

&nbsp;  - Web browser (Chrome recommended)



**2. Installation**

&nbsp;  - Copy the entire 'Swachh' folder to:

&nbsp;    C:\\xampp\\htdocs\\

&nbsp;  - Start Apache and MySQL from XAMPP Control Panel.



**3. Database Setup**

&nbsp;  - Open phpMyAdmin → Import → Choose 'schema.sql'

&nbsp;  - Click 'Go' to create all tables and sample data.



**4. Run the Project**

&nbsp;  - Open browser and visit:

&nbsp;    http://localhost/Swachh/



**5. Login Credentials**

&nbsp;   Role     | Email                | Password  

&nbsp;   Worker   | worker@example.com   | 12345    

&nbsp;   Resident | resident@example.com | 12345   





##### 🚀 Usage

* &nbsp;Workers log in to view assigned societies and update segregation status.
* &nbsp;Residents log in to view reports, performance charts, and credits.
* &nbsp;Each status update is instantly stored in the MySQL database.
* &nbsp;Credits adjust automatically based on segregation frequency.
* &nbsp;Low credits trigger a penalty warning message.



##### 💾 Database Structure

**Tables:**

* users                → Stores user details (worker/resident)
* societies            → Societies monitored
* blocks               → Blocks within each society
* apartments           → Apartments linked to residents
* segregation\_reports  → Daily reports for each apartment
* monthly\_challenges   → Monthly challenges for residents to complete
* notifications	→ Notifications from admin to workers and residents 	
* reward\_redemptions   → Residents can redeem rewards based on credits

&nbsp;	 	



##### 🤖 Future Enhancements

* Admin Panel for municipal authorities
* Email/SMS alerts for residents
* Penalty payment gateway
* Graphical dashboards with charts.js
* Smart bin IoT integration for automated detection





##### 📜 License

**Source Code:** MIT License  

Developed as an academic project for sustainable waste management.



##### 

##### 👨‍💻 Credits

Developed by **Aastha Adesara** (IU2341230148) and **Maharshi Gajjar** (IU2341230145)

Indus University  





##### 📬 For issues, feedback, or contributions –  

**contact:** aastha.adesara05@gmail.com



