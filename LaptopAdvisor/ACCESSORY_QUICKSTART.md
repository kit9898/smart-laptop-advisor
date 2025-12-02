# ✅ AI-Powered Accessory Recommendations - READY TO USE

## Summary

Successfully implemented intelligent accessory recommendations using **hybrid AI algorithms** (content-based + collaborative filtering). The system learns from user behavior and product features to suggest relevant accessories.

---

## ✅ What's Complete

### 1. Database (✅ Already Inserted by You)
- 30+ accessories across all categories (Gaming, Business, Creative, Student)
- Product categorization system (laptops vs accessories)
- Linking system (accessories → laptop categories)

### 2. Python ML Backend (✅ Fixed & Ready)
Files:
- `recommendation_engine/accessory_recommender.py` - AI algorithms
- `recommendation_engine/api.py` - REST API (syntax fixed ✅)

**AI Algorithms**:
- **Content-Based**: Matches by features, price range, brand (60% weight)
- **Collaborative**: Analyzes purchase patterns (40% weight)  
- **Hybrid**: Combines both for best results

### 3. Ready to Integrate

---

## 🚀 Quick Start Guide

### Step 1: Start the Python API Server

```bash
cd c:\xampp\htdocs\LaptopAdvisor\recommendation_engine
python api.py
```

You should see:
```
╔════════════════════════════════════════════════════════╗
║   LaptopAdvisor Recommendation Engine API Server      ║
║   Running on: http://127.0.0.1:5000                   ║
╚════════════════════════════════════════════════════════╝
```

Keep this terminal open!

---

### Step 2: Add to `product_details.php`

Add this code after the "Similar Products" section (around line 850):

```php
<?php
// ==========================================
// AI-POWERED ACCESSORY RECOMMENDATIONS
// ==========================================

// Only show accessories for laptops, not for accessories themselves
if ($product['product_category'] == 'laptop'):
    
// Call Python ML API for intelligent recommendations
$accessory_recommendations = [];
$api_url = 'http://127.0.0.1:5000/api/accessory-recommendations';

$post_data = json_encode([
    'user_id' => $_SESSION['user_id'] ?? 1,
    'laptop_id' => $product['product_id'],
    'use_case' => $product['primary_use_case'],
    'limit' => 4,
    'method' => 'hybrid'  // Uses AI hybrid algorithm
]);

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);  // 3 second timeout
$api_response = curl_exec($ch);
$api_error = curl_error($ch);
curl_close($ch);

// Parse API response
if ($api_response && !$api_error) {
    $api_data = json_decode($api_response, true);
    
    if ($api_data['success'] && !empty($api_data['recommendations'])) {
        // Get product IDs from recommendations
        $accessory_ids = array_column($api_data['recommendations'], 'product_id');
        
        // Fetch full accessory details from database
        if (!empty($accessory_ids)) {
            $placeholders = implode(',', array_fill(0, count($accessory_ids), '?'));
            $stmt = $conn->prepare("SELECT * FROM products WHERE product_id IN ($placeholders) LIMIT 4");
            $stmt->bind_param(str_repeat('i', count($accessory_ids)), ...$accessory_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $accessory_recommendations[] = $row;
            }
            $stmt->close();
        }
    }
}
?>

<!-- Display Accessories Section -->
<?php if (!empty($accessory_recommendations)): ?>
<div class="accessories-recommendation-section" style="margin-top: 50px; padding-top: 40px; border-top: 2px solid #e9ecef;">
    <div style="margin-bottom: 25px;">
        <h3 style="font-size: 1.8rem; margin-bottom: 10px;">✨ You Might Also Like</h3>
        <p style="color: #666; font-size: 1rem;">
            AI-recommended accessories for your <?php echo htmlspecialchars($product['primary_use_case']); ?> laptop
        </p>
    </div>
    
    <div class="accessory-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px;">
        <?php foreach ($accessory_recommendations as $accessory): ?>
        <div class="accessory-card" style="background: white; border: 2px solid #e9ecef; border-radius: 12px; padding: 20px; transition: all 0.3s; cursor: pointer;" 
             onmouseover="this.style.borderColor='#667eea'; this.style.transform='translateY(-5px)';"
             onmouseout="this.style.borderColor='#e9ecef'; this.style.transform='translateY(0)';">
            
            <!-- Category Badge -->
            <div style="display: inline-block; padding: 4px 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; font-size: 0.7rem; font-weight: 600; margin-bottom: 12px;">
                <?php 
                $category_icons = [
                    'mouse' => '🖱️',
                    'keyboard' => '⌨️',
                    'headset' => '🎧',
                    'monitor' => '🖥️',
                    'bag' => '🎒',
                    'webcam' => '📷',
                    'mousepad' => '⬛'
                ];
                echo ($category_icons[$accessory['product_category']] ?? '🛍️') . ' ';
                echo ucfirst($accessory['product_category']);
                ?>
            </div>
            
            <!-- Product Name -->
            <h4 style="font-size: 1rem; margin: 10px 0 8px 0; color: #1a1a1a; line-height: 1.4; min-height: 40px;">
                <?php echo htmlspecialchars($accessory['product_name']); ?>
            </h4>
            
            <!-- Brand -->
            <p style="font-size: 0.85rem; color: #888; margin-bottom: 10px;">
                <?php echo htmlspecialchars($accessory['brand']); ?>
            </p>
            
            <!-- Price -->
            <p style="font-size: 1.3rem; font-weight: 700; color: #667eea; margin: 12px 0;">
                $<?php echo number_format($accessory['price'], 2); ?>
            </p>
            
            <!-- Add to Cart Button -->
            <button onclick="quickAddToCart(<?php echo $accessory['product_id']; ?>)" 
                    style="width: 100%; padding: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s;"
                    onmouseover="this.style.opacity='0.9'"
                    onmouseout="this.style.opacity='1'">
                🛒 Add to Cart
            </button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; // End if laptop ?>
```

---

### Step 3: Test It!

1. **Start Python API** (from Step 1)
2. **Open a gaming laptop** in your browser:
   - Go to `product_details.php?product_id=3` (Razer Blade 15)
3. **Scroll down** - you should see "✨ You Might Also Like"
4. **Verify** gaming accessories appear (gaming mouse, keyboard, headset)

---

## 🧪 Testing Different Categories

- **Gaming Laptop** (ID 3): Should show gaming peripherals
- **Business Laptop** (ID 6): Should show professional accessories  
- **Creative Laptop** (ID 1): Should show Audio-Technica headphones, BenQ monitor, etc.

---

## 📊 How the AI Works

Example for a **gaming laptop**:

1. **Content-Based** (60%):
   - Laptop category: "Gaming"
   - ✅ Match accessories with `related_to_category='Gaming'`
   - ✅ Price compatibility (high-end laptop → premium accessories)
   - ✅ Brand synergy bonus (same brand gets +20%)

2. **Collaborative** (40%):
   - Find users who bought same/similar gaming laptops
   - See what accessories they purchased
   - Recommend popular choices

3. **Final Score** = (Content × 0.6) + (Collaborative × 0.4)

---

## 🎯 Next Steps (Optional Enhancements)

1. **Add to `products.php`**: Show accessory recommendations at bottom
2. **Bundle Deals**: Suggest "laptop + accessories" bundles
3. **Smart Sorting**: Sort accessories by AI score
4. **A/B Testing**: Compare AI vs random recommendations

---

## 🛠️ Troubleshooting

**Python API not starting?**
```bash
# Install dependencies first
cd c:\xampp\htdocs\LaptopAdvisor\recommendation_engine
pip install -r requirements.txt
```

**No accessories showing?**
- Check if Python API is running (http://127.0.0.1:5000/api/health)
- Verify database has accessories (`SELECT * FROM products WHERE product_category != 'laptop'`)
- Check PHP  error logs

**Wrong accessories recommended?**
- The AI learns from purchase patterns - need more order data for better collaborative filtering
- Content-based filtering works immediately with just product features

---

## 📁 Files Reference

**Database**: `add_accessory_support.sql` ✅  
**Python ML Backend**: `recommendation_engine/accessory_recommender.py` ✅  
**API Server**: `recommendation_engine/api.py` ✅ (syntax fixed)  
**PHP Integration**: Copy code above into `product_details.php` ⏳

---

**🎉 You're all set! The AI recommendation system is ready to use.**
