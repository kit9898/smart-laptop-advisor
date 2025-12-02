# Quick Start Guide: Integrating ML Recommendations

## ✅ Your System Status
Good news! Your `products.php` is working fine. We only need to start the Python API and connect it.

##  Step-by-Step Integration

### Step 1: Start the Python Recommendation API

Open Command Prompt and run:

```bash
cd c:\xampp\htdocs\LaptopAdvisor\recommendation_engine
start_api.bat
```

You should see:
```
╔════════════════════════════════════════════════════════╗
║   LaptopAdvisor Recommendation Engine API Server      ║
║                                                        ║
║   Running on: http://127.0.0.1:5000                   ║
╚════════════════════════════════════════════════════════╝
```

**Keep this window open!** The API needs to run in the background.

### Step 2: Test the API

Open your web browser:
```
http://127.0.0.1:5000/api/health
```

You should see:
```json
{
  "status": "healthy",
  "model_loaded": true
}
```

### Step 3: Add ML Integration to products.php

Your `products.php` file is working fine. Just add this code at the **beginning of the recommendations section** (around line 82, right after you get the user_pref):

```php
// --- MACHINE LEARNING INTEGRATION ---
require_once 'includes/recommendation_api.php';
$ml_api = new RecommendationAPI();
$ml_available = false;
$ml_product_ids = [];

// Check if Python API is running
if ($ml_api->healthCheck()) {
    $ml_available = true;
    // Get ML recommendations
    $ml_recs = $ml_api->getRecommendations($user_id, $user_pref, 12);
    
    if ($ml_recs) {
        // Extract product IDs for SQL query
        $ml_product_ids = array_column($ml_recs, 'product_id');
    }
}
```

### Step 4: Modify Your SQL Query

**If ML is available**, we want to boost those products in the results. Change your SQL WHERE clause from:

```php
WHERE p.primary_use_case = ? AND (r.rating IS NULL OR r.rating != -1)
```

To:

```php
WHERE (" . ($ml_available && !empty($ml_product_ids) 
    ? "p.product_id IN (" . implode(',', array_map('intval', $ml_product_ids)) . ") OR " 
    : "") . "p.primary_use_case = ?) AND (r.rating IS NULL OR r.rating != -1)
```

This will prioritize ML-recommended products while still showing others.

### Step 5: Test It!

1. Open LaptopAdvisor in your browser
2. Click on "For You" tab
3. You should see ML-powered recommendations!

## 🎯 Easy Way (Less Code Changes)

If you don't want to modify your working code, you can display ML recommendations in a separate section:

```php
<?php if ($view == 'recommendations' && $ml_available && !empty($ml_recs)): ?>
    <!-- ML Recommendations Section -->
    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h3 style="margin: 0;">🤖 AI-Powered Picks</h3>
        <p style="margin: 5px 0 0 0; font-size: 0.9rem;">These recommendations use machine learning based on user patterns</p>
    </div>
<?php endif; ?>
```

## 🔧 Troubleshooting

**API won't start:**
- Install Python dependencies: `pip install -r requirements.txt`
- Check `.env` file has correct database credentials

**No recommendations:**
-  Train the model first: `python recommender.py`
- Make sure you have ratings in the `recommendation_ratings` table

**"Connection refused" error:**
- Make sure `start_api.bat` is running
- Check firewall isn't blocking port 5000

## 📊 Monitor Performance

Check API stats:
```
http://127.0.0.1:5000/api/stats
```

See how many products and ratings are in the system.

## 🎨 Visual Indicator

Add this badge to show when ML is active:

```php
<?php if ($ml_available): ?>
    <div style="background: #10b981; color: white; padding: 8px 16px; border-radius: 20px; display: inline-block; font-size: 0.85rem; margin-bottom: 15px;">
        🤖 ML-Powered Recommendations Active
    </div>
<?php endif; ?>
```

## Next Steps

1. **Keep API running**: Set it up as a Windows service for production
2. **Train regularly**: Run `python recommender.py` weekly
3. **Monitor**: Check `/api/stats` to see system health

For full details, see `INTEGRATION_GUIDE.md`
