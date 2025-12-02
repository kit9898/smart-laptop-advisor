# 💻 LaptopAdvisor - E-Commerce Platform with ML Recommendations

A full-featured laptop e-commerce website with intelligent product recommendations powered by machine learning.

## 🚀 Quick Start

**New to this project?** Follow our complete setup guide:

📖 **[SETUP_GUIDE.md](SETUP_GUIDE.md)** - Comprehensive installation instructions

## ✨ Features

### Customer Features
- 🛍️ **Product Catalog** - Browse laptops with detailed specifications
- 🤖 **ML-Powered Recommendations** - Personalized product suggestions
- 🛒 **Shopping Cart** - Add to cart with quantity management
- 💳 **Checkout System** - Complete order processing
- 👤 **User Profiles** - Manage personal information and default shipping address
- 📦 **Order History** - Track past purchases
- ⭐ **Product Ratings** - Rate and review products
- 🎟️ **Coupon System** - Apply discount codes

### Technical Features
- 🐘 **PHP Backend** - Server-side logic with MySQL database
- 🐍 **Python ML Engine** - Flask API for recommendation algorithms
- 📊 **Collaborative Filtering** - User-based recommendation system
- 🔐 **User Authentication** - Secure login and session management
- 📱 **Responsive Design** - Mobile-friendly interface

## 🛠️ Technology Stack

### Frontend
- HTML5, CSS3, JavaScript
- Responsive design

### Backend
- **PHP** 7.4+
- **MySQL** database
- **Apache** web server (via XAMPP)

### ML Recommendation Engine
- **Python** 3.8+
- **Flask** - REST API server
- **scikit-learn** - Machine learning algorithms
- **pandas** - Data processing
- **NumPy** - Numerical computing

## 📂 Project Structure

```
LaptopAdvisor/
├── SETUP_GUIDE.md          # Complete setup instructions
├── index.php               # Homepage
├── products.php            # Product listing with ML recommendations
├── cart.php                # Shopping cart
├── checkout.php            # Checkout page
├── profile.php             # User profile
├── edit_profile.php        # Profile editing with address management
├── includes/               # Shared PHP files
│   ├── db.php             # Database connection
│   ├── auth_check.php     # Authentication
│   ├── header.php         # Site header
│   └── recommendation_api.php  # Python API client
├── recommendation_engine/  # Python ML system
│   ├── api.py             # Flask REST API
│   ├── recommender.py     # ML model training
│   ├── requirements.txt   # Python dependencies
│   ├── .env               # Database configuration
│   └── start_api.bat      # Quick start script
├── uploads/               # User uploaded images
└── css/                   # Stylesheets
```

## 🎯 Getting Started

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Python 3.8 or higher
- Web browser

### Installation

**Step-by-step guide:** See [SETUP_GUIDE.md](SETUP_GUIDE.md)

**Quick version:**
1. Install XAMPP and Python
2. Import database: `laptop_advisor_db (1).sql`
3. Run migrations for address fields
4. Install Python packages: `pip install -r recommendation_engine/requirements.txt`
5. Start XAMPP (Apache + MySQL)
6. Start Python API: `python recommendation_engine/api.py`
7. Open: `http://localhost/LaptopAdvisor`

## 🔧 Configuration

### Database Connection
Edit `includes/db.php`:
```php
$host = 'localhost';
$db_name = 'laptop_advisor_db';
$username = 'root';
$password = '';
```

### Python API
Edit `recommendation_engine/.env`:
```
DB_HOST=localhost
DB_NAME=laptop_advisor_db
DB_USER=root
DB_PASSWORD=
```

## 🧪 Testing

### Test Users
After setup, create test accounts or use sample data

### Test ML Recommendations
1. Add ratings for products
2. Retrain model: `python recommendation_engine/recommender.py`
3. Visit products page to see recommendations

## 📚 Documentation

- [SETUP_GUIDE.md](SETUP_GUIDE.md) - Complete installation guide
- [recommendation_engine/README.md](recommendation_engine/README.md) - ML engine details
- [recommendation_engine/SETUP_GUIDE.md](recommendation_engine/SETUP_GUIDE.md) - Python API setup

## 🐛 Troubleshooting

**Common issues and solutions are in [SETUP_GUIDE.md](SETUP_GUIDE.md#troubleshooting)**

Quick fixes:
- **Apache won't start**: Port 80 conflict → Change to port 8080
- **Python API errors**: Check `.env` configuration
- **No recommendations**: Train model with `python recommender.py`

## 🔄 Workflow

### Daily Development
1. Start XAMPP (Apache + MySQL)
2. Start Python API: `python recommendation_engine/api.py`
3. Code and test
4. Stop services when done

### After Adding Ratings
Retrain ML model:
```bash
cd recommendation_engine
python recommender.py
```

## 🎨 Key Features Detail

### Address Management
Users can save a default shipping address in their profile that auto-fills at checkout.

### ML Recommendations
- Collaborative filtering based on user ratings
- Content-based filtering using product specs
- Hybrid approach for best results
- Real-time API integration with PHP frontend

### Checkout Process
1. View cart
2. Proceed to checkout
3. Address auto-fills from profile (or last order)
4. Enter payment details (simulated)
5. Place order

## ⚠️ Security Notes

**This is a development setup**

For production deployment:
- Change default MySQL password
- Enable HTTPS
- Implement proper error handling
- Secure API endpoints
- Add rate limiting
- Validate all user inputs
- Use prepared statements (already implemented)

## 📝 License

Educational project - feel free to use and modify

## 🤝 Contributing

This is a learning project. Feel free to fork and experiment!

---

**Need help?** Start with [SETUP_GUIDE.md](SETUP_GUIDE.md) for detailed instructions.
