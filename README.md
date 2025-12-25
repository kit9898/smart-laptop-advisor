# Smart Laptop Advisor (FYP)

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-00000F?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-563D7C?style=for-the-badge&logo=bootstrap&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

## Project Overview
**Smart Laptop Advisor** is a sophisticated web-based application designed to bridge the gap between complex technical specifications and user needs. It utilizes an intelligent recommendation engine and interactive chatbot to help users (Students, Gamers, Professionals) find their perfect laptop. The system features a robust admin panel for comprehensive e-commerce management, analytics, and AI configuration.

## Key Features

### 🛍️ User Frontend (`/LaptopAdvisor`)
- **🤖 AI Recommendation Engine**: Rule-based and ML-powered suggestions tailored to user personas and usage patterns.
- **💬 Intelligent Chatbot**: Interactive assistant capable of answering natural language queries and guiding users.
- **🏷️ Coupon System**: Apply discount codes at checkout for promotional savings.
- **📦 Product Catalog & Filtering**: Advanced search with filters for brand, specs, and price categories.
- **⚖️ Product Comparison**: Side-by-side comparison tool for technical specifications.
- **🛒 E-commerce Features**: Full cart functionality, secure checkout, and order history tracking.

### 🛠️ Admin Panel (`/admin`)
- **📊 Dynamic Dashboard**:
    - Real-time widgets for Revenue, Orders, and Active Users.
    - **Recent Reviews** and AI performance monitoring.
- **🎟️ Coupon Management**:
    - Full CRUD support for discount codes.
    - Set validity periods, usage limits, and discount types.
- **📈 Advanced Reporting System**:
    - Detailed visual reports for **Sales**, **Products**, **Customers**, **AI Analysis**, and **Inventory**.
    - Export capabilities to PDF and CSV.
- **⭐ Review Management**: Monitor and respond to user product reviews.
- **📝 Product & Order Management**: Comprehensive tools for catalog and order lifecycle management.
- **🤖 AI Configuration**: Fine-tune weighting algorithms and chatbot intent responses.

## Technology Stack
- **Backend**: PHP (Native), MySQL
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript
- **Database**: MySQL
- **Libraries**:
    - **Chart.js / ApexCharts**: For interactive data visualization.
    - **jsPDF & html2canvas**: For client-side report generation.
    - **PHPMailer**: For transactional emails.

## Installation & Setup

1.  **Clone the Repository**
    ```bash
    git clone https://github.com/kit9898/fyp.git
    ```

2.  **Database Setup**
    - Locate the latest SQL dump file: `laptop_advisor_db (13).sql` in the root directory.
    - Import this file into your local MySQL server (using phpMyAdmin or CLI).
    - **Verify Credentials**:
        - Check `admin/includes/db_connect.php`
        - Check `LaptopAdvisor/includes/db_connect.php`
        - *Ensure username/password matches your local environment (default: root/empty).*

3.  **Run the Application**
    - Host the project on a local server like XAMPP or WAMP.
    - **Frontend**: Navigate to `http://localhost/fyp/LaptopAdvisor`
    - **Admin Panel**: Navigate to `http://localhost/fyp/admin`

4.  **Admin Login** (Default)
    - **Email**: `admin@laptopadvisor.com` (Check database for actual admin email)
    - **Password**: *As set in database hash*

## Folder Structure
- `admin/`: Backend administration logic, reports, and AI management.
- `LaptopAdvisor/`: Customer-facing storefront and recommendation tools.
- `api/`: AJAX endpoints and chatbot logic.
- `uploads/`: Product images and user assets.

## License
This project is developed for educational purposes as a Final Year Project.
