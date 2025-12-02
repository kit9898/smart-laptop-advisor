# Fix: Undefined Variable $performance_score Error

## Problem
Lines 581-590 in `product_details.php` have misplaced code causing errors:
```
Warning: Undefined variable $performance_score
```

## Root Cause
My previous edit accidentally inserted Performance Rating code in the wrong location (after the price section instead of being part of the complete Performance Rating section).

## Quick Fix

### Step 1: Find and Delete These Lines (around line 581-610)

Look for this **BROKEN CODE** and **DELETE IT**:

```php
                    <div class="meter-label">
                        <span>Performance Rating</span>
                        <span><?php echo $performance_score; ?></span>
                    </div>
                    <div class="meter-bar">
                        <div class="meter-fill" style="width: <?php echo $performance_score; ?>%"></div>
                    </div>
                    <p style="margin-top: 8px; font-size: 0.85rem; color: #666;">
                        <?php 
                        if ($performance_score >= 80) echo "⚡ Exceptional performance for demanding tasks";
                        elseif ($performance_score >= 60) echo "✓ Great performance for most applications";
                        elseif ($performance_score >= 40) echo "→ Good for everyday computing";
                        else echo "→ Suitable for basic tasks";
                        ?>
                    </p>
                </div>
```

### Step 2: Add the COMPLETE Performance Rating Section

Add this **AFTER the Key Specifications section** and **BEFORE the Add to Cart section**:

```php
                <!-- Performance Rating -->
                <?php if (isset($product['product_category']) && $product['product_category'] == 'laptop'): ?>
                <?php $performance_score = getPerformanceRating($product['ram_gb'], $product['gpu']); ?>
                <div class="performance-meter">
                    <div class="meter-label">
                        <span>Performance Rating</span>
                        <span><?php echo $performance_score; ?>/100</span>
                    </div>
                    <div class="meter-bar">
                        <div class="meter-fill" style="width: <?php echo $performance_score; ?>%"></div>
                    </div>
                    <p style="margin-top: 8px; font-size: 0.85rem; color: #666;">
                        <?php 
                        if ($performance_score >= 80) echo "⚡ Exceptional performance for demanding tasks";
                        elseif ($performance_score >= 60) echo "✓ Great performance for most applications";
                        elseif ($performance_score >= 40) echo "→ Good for everyday computing";
                        else echo "→ Suitable for basic tasks";
                        ?>
                    </p>
                </div>
                <?php endif; ?>
```

## Key Changes:
1. **Added condition**: Only show for laptops (`if product_category == 'laptop'`)
2. **Define variable first**: `$performance_score = getPerformanceRating(...)`
3. **Complete structure**: Has opening `<div class="performance-meter">` and closing `</div>`
4. **Proper placement**: After Key Specifications, before Add to Cart

## Result
- ✅ No more undefined variable errors
- ✅ Performance Rating only shows for laptops (not accessories)
- ✅ Properly formatted with complete HTML structure

---

**Location Reference**:
- Around line **605-625** (after the Key Specifications `</div>`)
- Before `<!-- Add to Cart Section -->`
