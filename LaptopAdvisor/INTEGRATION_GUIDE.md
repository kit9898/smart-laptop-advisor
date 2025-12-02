# LaptopAdvisor - Recommendation Engine Integration Guide

## Overview
This guide explains how to integrate the Python-based Machine Learning recommendation engine with your PHP application.

## Architecture

```
┌─────────────────┐        HTTP API         ┌──────────────────────┐
│   PHP Frontend  │ ◄─────────────────────► │  Python Flask API    │
│  (products.php) │     JSON Requests        │    (Port 5000)       │
└─────────────────┘                          └──────────────────────┘
         │                                              │
         │                                              │
         ▼                                              ▼
  ┌──────────────┐                            ┌──────────────────┐
  │  MySQL DB    │ ◄─────────────────────────►│  ML Models       │
  │              │     Training Data           │  (Pickle Files)  │
  └──────────────┘                            └──────────────────┘
```

## Setup Steps

### 1. Start the Python Recommendation API

#### Option A: Using the Batch File (Recommended for Windows)
1. Open Command Prompt
2. Navigate to the recommendation_engine folder:
   ```bash
   cd c:\xampp\htdocs\LaptopAdvisor\recommendation_engine
   ```
3. Run the start script:
   ```bash
   start_api.bat
   ```

#### Option B: Manual Start
```bash
cd c:\xampp\htdocs\LaptopAdvisor\recommendation_engine
python api.py
```

The API will start on `http://127.0.0.1:5000`

### 2. Verify API is Running

Open a web browser and visit:
```
http://127.0.0.1:5000/api/health
```

You should see:
```json
{
  "status": "healthy",
  "model_loaded": true,
  "timestamp": 1234567890.123
}
```

### 3. Test the Integration

The `products.php` file is already configured to use both SQL and ML recommendations:

1. **SQL Recommendations** (Fallback): Fast, rule-based scoring
2. **ML Recommendations** (Primary): Machine learning-based collaborative filtering

## How It Works

### Recommendation Flow

1. **User visits Recommendations tab** (`?view=recommendations`)
2. PHP checks if Python API is available (`$api->healthCheck()`)
3. **If API is available:**
   - ML recommendations are fetched
   - Combined with SQL recommendations
   - Re-ranked using hybrid scoring
4. **If API is unavailable:**
   - Falls back to SQL-only recommendations
   - User experience is not affected

### Code Integration in products.php

```php
// Load ML API wrapper
require_once 'includes/recommendation_api.php';

// Initialize API client
$ml_api = new RecommendationAPI();

// Get ML recommendations
$ml_recommendations = null;
if ($ml_api->healthCheck()) {
    $ml_recommendations = $ml_api->getRecommendations(
        $user_id, 
        $user_pref, 
        20  // Get more for better hybrid results
    );
}

// Combine SQL and ML recommendations
if ($ml_recommendations) {
    $combined = combineRecommendations(
        $sql_results,
        $ml_recommendations,
        0.6  // 60% weight to ML, 40% to SQL
    );
}
```

## API Endpoints

### Health Check
```bash
GET /api/health
```

### Get Recommendations
```bash
POST /api/recommendations
Content-Type: application/json

{
  "user_id": 1,
  "use_case": "Gaming",
  "limit": 10
}
```

### Get Similar Products
```bash
POST /api/similar-products
Content-Type: application/json

{
  "product_id": 5,
  "limit": 10
}
```

### Train Model
```bash
POST /api/train
Content-Type: application/json

{
  "async": true
}
```

### Get Statistics
```bash
GET /api/stats
```

## Configuration

### PHP Configuration (recommendation_api.php)

```php
// Change API host/port if needed
$ml_api = new RecommendationAPI('127.0.0.1', 5000);

// Adjust cache duration (default: 1 hour)
$ml_api->cache_duration = 3600;

// Disable caching
$ml_api->cache_enabled = false;
```

### Python Configuration (.env)

Create `.env` file in `recommendation_engine` folder:

```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=laptop_advisor_db

FLASK_HOST=127.0.0.1
FLASK_PORT=5000
FLASK_DEBUG=False
```

## Model Training

### When to Train
- After adding new products
- After collecting new user ratings
- Weekly for best results

### How to Train

#### Method 1: Via API (Recommended)
```bash
curl -X POST http://127.0.0.1:5000/api/train \
  -H "Content-Type: application/json" \
  -d "{\"async\": true}"
```

#### Method 2: Direct Python Script
```bash
cd c:\xampp\htdocs\LaptopAdvisor\recommendation_engine
python recommender.py
```

#### Method 3: Via PHP
```php
require_once 'includes/recommendation_api.php';
$ml_api = new RecommendationAPI();
$ml_api->trainModel(true);  // async=true
```

## Troubleshooting

### API Not Starting
**Problem:** `python api.py` fails

**Solutions:**
1. Check Python installation: `python --version` (needs 3.7+)
2. Install dependencies: `pip install -r requirements.txt`
3. Check database connection in `.env`
4. Verify port 5000 is available

### No Recommendations Returned
**Problem:** API returns empty array

**Solutions:**
1. Train the model: `python recommender.py`
2. Check database has ratings data
3. Verify user has a use_case set in profile
4. Check minimum ratings threshold (default: 5)

### PHP Can't Connect to API
**Problem:** `healthCheck()` returns false

**Solutions:**
1. Verify Flask API is running
2. Check Windows Firewall settings
3. Test with cURL first: `curl http://127.0.0.1:5000/api/health`
4. Review PHP error logs for connection errors

### Slow Performance
**Problem:** Recommendations take too long

**Solutions:**
1. Enable caching (enabled by default)
2. Train model to cache similarity matrix
3. Reduce `limit` parameter
4. Use async training

## Performance Optimization

### Caching Strategy
- **PHP Cache**: 1 hour (file-based)
- **Python Model Cache**: 24 hours (pickle file)
- Clear cache after training: `$ml_api->clearCache()`

### Best Practices
1. **Always enable fallback** to SQL recommendations
2. **Use async training** to avoid blocking requests
3. **Monitor API health** with periodic health checks
4. **Set appropriate timeouts** (default: 5 seconds)
5. **Train during off-peak hours**

## Monitoring

### Check API Status
```php
$stats = $ml_api->getStats();
print_r($stats);
```

### Monitor Logs
- Python API logs: Check console where `api.py` is running
- PHP error logs: Check Apache error logs
- Database queries: Enable slow query log

## Production Deployment

### Running API as Service

#### Option 1: Using NSSM (Windows Service)
```bash
nssm install LaptopAdvisorAPI
nssm set LaptopAdvisorAPI Application "C:\Python\python.exe"
nssm set LaptopAdvisorAPI AppDirectory "c:\xampp\htdocs\LaptopAdvisor\recommendation_engine"
nssm set LaptopAdvisorAPI AppParameters "api.py"
nssm start LaptopAdvisorAPI
```

#### Option 2: Using Gunicorn (Better Performance)
```bash
pip install gunicorn
gunicorn -w 4 -b 127.0.0.1:5000 api:app
```

### Security Considerations
1. **Don't expose API** publicly (use 127.0.0.1 only)
2. **Add API key authentication** if needed
3. **Rate limit** requests to prevent abuse
4. **Sanitize** all user inputs
5. **Use HTTPS** in production

## Maintenance

### Weekly Tasks
- Train model with new data
- Clear old cache files
- Review API logs

### Monthly Tasks
- Update Python dependencies
- Backup trained models
- Analyze recommendation quality

## Support

For issues or questions:
1. Check this guide
2. Review `SETUP_GUIDE.md` in `recommendation_engine` folder
3. Check Python API console for errors
4. Review PHP error logs

## Quick Reference Card

```bash
# Start API
cd c:\xampp\htdocs\LaptopAdvisor\recommendation_engine
start_api.bat

# Health Check
curl http://127.0.0.1:5000/api/health

# Train Model
curl -X POST http://127.0.0.1:5000/api/train -H "Content-Type: application/json" -d "{\"async\": true}"

# Get Stats
curl http://127.0.0.1:5000/api/stats

# Clear PHP Cache
# In PHP:
$ml_api->clearCache();
```
