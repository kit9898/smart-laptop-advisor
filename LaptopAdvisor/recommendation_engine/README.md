# LaptopAdvisor Python Recommendation Engine

A machine learning-based recommendation system for the LaptopAdvisor application.

## Features

- **Collaborative Filtering**: User-based recommendations using KNN
- **Content-Based Filtering**: Product similarity using cosine similarity
- **Hybrid Approach**: Combines both methods for better accuracy
- **REST API**: Flask-based API for PHP integration
- **Caching**: Automatic caching of recommendations
- **Model Persistence**: Save and load trained models

## Installation

### 1. Install Python Dependencies

```bash
cd c:\xampp\htdocs\LaptopAdvisor\recommendation_engine
pip install -r requirements.txt
```

### 2. Configure Environment

Copy `.env.example` to `.env` and update with your database credentials:

```bash
copy .env.example .env
```

Edit `.env`:
```
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=laptop_advisor_db
```

### 3. Train the Model

```bash
python recommender.py
```

This will:
- Load product and rating data from the database
- Prepare feature matrices
- Calculate similarity scores
- Save the trained model

### 4. Start the API Server

```bash
python api.py
```

The API will be available at `http://127.0.0.1:5000`

## API Endpoints

### Health Check
```
GET /api/health
```

### Get Recommendations
```
POST /api/recommendations
Content-Type: application/json

{
    "user_id": 1,
    "use_case": "Gaming",
    "limit": 10
}
```

### Get Similar Products
```
POST /api/similar-products
Content-Type: application/json

{
    "product_id": 5,
    "limit": 10
}
```

### Get Popular Products
```
POST /api/popular
Content-Type: application/json

{
    "use_case": "Gaming",
    "limit": 10
}
```

### Train Model
```
POST /api/train
Content-Type: application/json

{
    "async": true
}
```

### Get Statistics
```
GET /api/stats
```

## PHP Integration

Include the API client in your PHP code:

```php
require_once 'includes/recommendation_api.php';

// Create API client
$api = new RecommendationAPI();

// Check if API is available
if ($api->healthCheck()) {
    // Get recommendations
    $recommendations = $api->getRecommendations($user_id, $use_case, 10);
    
    // Get similar products
    $similar = $api->getSimilarProducts($product_id, 5);
}
```

## Architecture

```
┌─────────────────┐
│   PHP Frontend  │
│  (products.php) │
└────────┬────────┘
         │ HTTP Request
         ▼
┌─────────────────┐
│  Flask API      │
│   (api.py)      │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  ML Engine      │
│ (recommender.py)│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  MySQL Database │
└─────────────────┘
```

## Algorithms

### Collaborative Filtering
- Uses K-Nearest Neighbors (KNN) with cosine similarity
- Finds users with similar rating patterns
- Recommends products liked by similar users
- Handles cold start with content-based fallback

### Content-Based Filtering
- Extracts product features (RAM, CPU, GPU, price, etc.)
- Calculates cosine similarity between products
- Recommends similar products based on features
- Works even for new users

### Hybrid Approach
- Combines collaborative (60%) and content-based (40%)
- Provides balanced recommendations
- Better accuracy than either method alone

## Model Training

The model should be retrained periodically to incorporate new data:

```bash
# Manual training
python recommender.py

# Or via API
curl -X POST http://127.0.0.1:5000/api/train \
  -H "Content-Type: application/json" \
  -d '{"async": true}'
```

## Troubleshooting

### API Not Responding
- Check if Flask server is running
- Verify firewall settings
- Check logs for errors

### No Recommendations
- Ensure sufficient rating data exists
- Check if model is trained
- Verify database connection

### Poor Recommendations
- Retrain model with more data
- Adjust weights in `config.py`
- Check data quality

## Performance

- Recommendations are cached for 1 hour
- Model is loaded once on startup
- Typical response time: < 100ms
- Supports concurrent requests

## License

Part of the LaptopAdvisor project.
