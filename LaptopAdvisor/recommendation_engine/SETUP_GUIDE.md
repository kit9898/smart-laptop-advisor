# LaptopAdvisor Python Recommendation Engine - Setup Guide

## Quick Start

### Step 1: Install Python Dependencies

Open Command Prompt in the `recommendation_engine` folder:

```bash
cd c:\xampp\htdocs\LaptopAdvisor\recommendation_engine
pip install -r requirements.txt
```

### Step 2: Configure Database Connection

1. Copy `.env.example` to `.env`:
```bash
copy .env.example .env
```

2. Edit `.env` with your database credentials (use Notepad or any text editor)

### Step 3: Start the API Server

Double-click `start_api.bat` or run:
```bash
start_api.bat
```

The API will start on `http://127.0.0.1:5000`

## Testing the API

### Test with cURL (in Command Prompt):

```bash
# Health check
curl http://127.0.0.1:5000/api/health

# Get recommendations
curl -X POST http://127.0.0.1:5000/api/recommendations ^
  -H "Content-Type: application/json" ^
  -d "{\"user_id\": 1, \"limit\": 5}"

# Get similar products
curl -X POST http://127.0.0.1:5000/api/similar-products ^
  -H "Content-Type: application/json" ^
  -d "{\"product_id\": 5, \"limit\": 5}"
```

## Using in PHP

Add to your PHP file:

```php
require_once 'includes/recommendation_api.php';

$api = new RecommendationAPI();

// Check if API is running
if ($api->healthCheck()) {
    // Get ML recommendations
    $ml_recs = $api->getRecommendations($user_id, $use_case, 10);
    
    if ($ml_recs) {
        foreach ($ml_recs as $rec) {
            echo "Product ID: {$rec['product_id']}, Score: {$rec['score']}\n";
        }
    }
}
```

## Troubleshooting

**API won't start:**
- Make sure Python is installed: `python --version`
- Check if port 5000 is available
- Review error messages in console

**No recommendations returned:**
- Ensure database has rating data
- Check database connection in `.env`
- Train the model: `python recommender.py`

**PHP can't connect:**
- Verify Flask API is running
- Check firewall settings
- Test with cURL first

## Running as Background Service (Optional)

For production, use a process manager like `nssm` or run Flask with Gunicorn.

## Updating the Model

The model should be retrained periodically:

```bash
# Manual training
python recommender.py

# Or via API
curl -X POST http://127.0.0.1:5000/api/train -H "Content-Type: application/json" -d "{\"async\": true}"
```

## Performance Tips

- Model is cached for 24 hours
- PHP responses are cached for 1 hour
- Clear cache: `$api->clearCache()`
- Monitor with: `curl http://127.0.0.1:5000/api/stats`
