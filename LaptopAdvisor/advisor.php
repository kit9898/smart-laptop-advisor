<?php
require_once 'includes/auth_check.php';
include 'includes/header.php';

// --- Multi-Step Quiz Logic ---
$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;
$category = $_GET['category'] ?? 'Laptop'; // Default to Laptop if not set

// Determine Total Steps based on Category
if ($category === 'Accessories') {
    $total_steps = 4; // 1: Category, 2: Type, 3: Use Case, 4: Budget
} else {
    $total_steps = 6; // 1: Category, 2: Use Case, 3: Perf, 4: Screen, 5: Portability, 6: Budget
}

$accessory_type = $_GET['accessory_type'] ?? '';
$accessory_use_case = $_GET['accessory_use_case'] ?? '';
$use_case = $_GET['use_case'] ?? '';
$performance = $_GET['performance'] ?? '';
$screen_size = $_GET['screen_size'] ?? '';
$portability = $_GET['portability'] ?? '';
$budget = $_GET['budget'] ?? '1500';

$results = [];

if ($step > $total_steps) {
    // --- RECOMMENDATION ENGINE ---
    
    if ($category === 'Accessories') {
        // --- PROFESSIONAL ACCESSORY RECOMMENDATION ENGINE ---
        $sql = "SELECT p.*, 
                COALESCE(r.rating, 0) as user_rating,
                (SELECT COUNT(*) FROM recommendation_ratings rr WHERE rr.product_id = p.product_id AND rr.rating = 1) as positive_ratings,
                (SELECT COUNT(*) FROM recommendation_ratings rr WHERE rr.product_id = p.product_id) as total_ratings,
                (SELECT AVG(pr.rating) FROM product_reviews pr WHERE pr.product_id = p.product_id) as avg_review,
                (SELECT COUNT(*) FROM product_reviews pr WHERE pr.product_id = p.product_id) as review_count
                FROM products p 
                LEFT JOIN recommendation_ratings r ON p.product_id = r.product_id AND r.user_id = ?
                WHERE p.product_category != 'Laptop' AND p.is_active = 1
        ";
        
        $params = [$_SESSION['user_id']];
        $types = "i";

        // Filter by Type (Loose match to catch 'Gaming Mouse', 'Wireless Mouse' etc.)
        if (!empty($accessory_type)) {
            $sql .= " AND (p.product_category LIKE ? OR p.product_name LIKE ? OR p.description LIKE ?)";
            $type_param = "%" . $accessory_type . "%";
            $params[] = $type_param;
            $params[] = $type_param;
            $params[] = $type_param;
            $types .= "sss";
        }

        // Filter by Budget (allow 30% over budget to show premium options)
        $budget_limit = $budget * 1.30;
        $sql .= " AND p.price <= ?";
        $params[] = $budget_limit;
        $types .= "d";

        // Base ordering
        $sql .= " ORDER BY positive_ratings DESC, avg_review DESC, p.price ASC LIMIT 12";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result_set = $stmt->get_result();
        
        // Premium brand lists by category
        $premium_brands = [
            'Mouse' => ['Logitech', 'Razer', 'SteelSeries', 'Corsair', 'Zowie', 'Finalmouse', 'Pulsar'],
            'Keyboard' => ['Logitech', 'Razer', 'Corsair', 'Ducky', 'Keychron', 'SteelSeries', 'HyperX'],
            'Headset' => ['SteelSeries', 'HyperX', 'Logitech', 'Razer', 'Sony', 'Bose', 'Audio-Technica', 'Sennheiser'],
            'Monitor' => ['LG', 'Samsung', 'ASUS', 'Dell', 'BenQ', 'Acer', 'MSI', 'ViewSonic'],
            'default' => ['Logitech', 'Razer', 'SteelSeries', 'HyperX', 'Corsair', 'ASUS', 'ROG', 'Dell', 'LG', 'Samsung', 'Sony']
        ];
        
        // Use case keywords for matching
        $use_case_keywords = [
            'Gaming' => ['gaming', 'rgb', 'esports', 'fps', 'moba', 'mechanical', 'high dpi', 'low latency', '144hz', '240hz', 'refresh rate'],
            'Work' => ['ergonomic', 'office', 'wireless', 'quiet', 'silent', 'productivity', 'business', 'comfortable', 'bluetooth'],
            'Casual' => ['basic', 'simple', 'everyday', 'affordable', 'budget', 'compact', 'portable', 'easy'],
            'Creative' => ['precision', 'accurate', 'color', 'design', 'photo', 'video', 'editing', '4k', 'ips', 'creator']
        ];
        
        while($row = $result_set->fetch_assoc()) {
            // ===== PROFESSIONAL 7-FACTOR SCORING (100 pts max) =====
            
            // 1. Use Case Match Score (15 pts) - How well it matches selected use case
            $use_case_score = 0;
            $use_case_matched = false;
            if (!empty($accessory_use_case) && isset($use_case_keywords[$accessory_use_case])) {
                $keywords = $use_case_keywords[$accessory_use_case];
                $product_text = strtolower(($row['product_name'] ?? '') . ' ' . ($row['description'] ?? '') . ' ' . ($row['primary_use_case'] ?? ''));
                
                $matches = 0;
                foreach ($keywords as $keyword) {
                    if (stripos($product_text, $keyword) !== false) {
                        $matches++;
                    }
                }
                
                if ($matches >= 3) {
                    $use_case_score = 15;
                    $use_case_matched = true;
                } elseif ($matches >= 2) {
                    $use_case_score = 12;
                    $use_case_matched = true;
                } elseif ($matches >= 1) {
                    $use_case_score = 8;
                }
                
                // Check if primary_use_case matches directly
                if (stripos($row['primary_use_case'] ?? '', $accessory_use_case) !== false) {
                    $use_case_score = 15;
                    $use_case_matched = true;
                }
            }
            
            // 2. Type Match Score (10 pts) - How well it matches the selected type
            $type_score = 0;
            if (!empty($accessory_type)) {
                if (stripos($row['product_category'] ?? '', $accessory_type) !== false) {
                    $type_score = 10; // Exact category match
                } elseif (stripos($row['product_name'], $accessory_type) !== false) {
                    $type_score = 8; // Name match
                } else {
                    $type_score = 5; // Description match
                }
            } else {
                $type_score = 7; // No filter, neutral
            }
            
            // 3. Value Score (20 pts) - Budget fit with sweet spot logic
            $price_ratio = $row['price'] / max(1, $budget);
            if ($price_ratio >= 0.7 && $price_ratio <= 0.95) {
                $value_score = 20; // Sweet spot - great value
            } elseif ($price_ratio <= 0.7) {
                $value_score = 14; // Under budget - might be entry level
            } elseif ($price_ratio <= 1.0) {
                $value_score = 18; // Right at budget
            } elseif ($price_ratio <= 1.15) {
                $value_score = 12; // Slightly over - premium option
            } elseif ($price_ratio <= 1.30) {
                $value_score = 8; // Over budget
            } else {
                $value_score = 4;
            }
            
            // 4. Popularity Score (20 pts) - Community validation
            $pop_score = 0;
            $total_ratings = $row['total_ratings'] ?? 0;
            $positive_ratings = $row['positive_ratings'] ?? 0;
            if ($total_ratings > 0) {
                $positive_ratio = $positive_ratings / $total_ratings;
                $pop_score = min(20, round($positive_ratio * 15) + min(5, $total_ratings));
            }
            
            // 5. Review Score (15 pts) - Average rating with weight for review count
            $review_score = 0;
            $avg_review = $row['avg_review'] ?? 0;
            $review_count = $row['review_count'] ?? 0;
            if ($avg_review > 0) {
                $base_review_score = ($avg_review / 5) * 12;
                $count_bonus = min(3, $review_count * 0.3);
                $review_score = min(15, $base_review_score + $count_bonus);
            }
            
            // 6. Brand Reputation Score (15 pts) - Premium brand bonus
            $brand_score = 0;
            $is_premium_brand = false;
            $brands_for_type = $premium_brands[$accessory_type] ?? $premium_brands['default'];
            foreach ($brands_for_type as $brand) {
                if (stripos($row['brand'] ?? '', $brand) !== false || stripos($row['product_name'], $brand) !== false) {
                    $brand_score = 15;
                    $is_premium_brand = true;
                    break;
                }
            }
            // Second tier brands
            if ($brand_score == 0) {
                $mid_tier_brands = ['Anker', 'TP-Link', 'Redragon', 'Cooler Master', 'Xiaomi'];
                foreach ($mid_tier_brands as $brand) {
                    if (stripos($row['brand'] ?? '', $brand) !== false || stripos($row['product_name'], $brand) !== false) {
                        $brand_score = 8;
                        break;
                    }
                }
            }
            
            // 7. Stock Availability Score (5 pts)
            $stock_score = 0;
            $stock_qty = $row['stock_quantity'] ?? 0;
            if ($stock_qty >= 10) {
                $stock_score = 5;
            } elseif ($stock_qty >= 5) {
                $stock_score = 3;
            } elseif ($stock_qty > 0) {
                $stock_score = 1;
            }
            
            // Calculate total score
            $total_score = $use_case_score + $type_score + $value_score + $pop_score + $review_score + $brand_score + $stock_score;
            
            // Store all scores for display
            $row['total_score'] = min(100, $total_score);
            $row['use_case_score'] = $use_case_score;
            $row['use_case_matched'] = $use_case_matched;
            $row['is_premium_brand'] = $is_premium_brand;
            $row['type_score'] = $type_score;
            $row['value_score'] = $value_score;
            $row['popularity_score'] = $pop_score;
            $row['review_score'] = $review_score;
            $row['brand_score'] = $brand_score;
            $row['stock_score'] = $stock_score;
            $row['avg_review'] = $avg_review ?? 0;
            
            $results[] = $row;
        }
        
        // Sort results by total score descending, then by reviews
        usort($results, function($a, $b) {
            if ($b['total_score'] != $a['total_score']) {
                return $b['total_score'] - $a['total_score'];
            }
            return ($b['avg_review'] ?? 0) - ($a['avg_review'] ?? 0);
        });
        
        // Limit to top 8 results
        $results = array_slice($results, 0, 8);
        
        $stmt->close();

    } else {
        // --- LAPTOP LOGIC (Professional Multi-Factor Scoring) ---
        
        // Get user's persona from profile for better matching
        $user_persona = null;
        if (isset($_SESSION['user_id'])) {
            $persona_sql = "SELECT primary_use_case FROM users WHERE user_id = ?";
            $persona_stmt = $conn->prepare($persona_sql);
            $persona_stmt->bind_param("i", $_SESSION['user_id']);
            $persona_stmt->execute();
            $persona_result = $persona_stmt->get_result();
            if ($row = $persona_result->fetch_assoc()) {
                $user_persona = $row['primary_use_case'];
            }
            $persona_stmt->close();
        }
        
        // Define use case relationships for smarter matching
        $use_case_map = [
            'Gaming' => ['Gaming' => 100, 'Creative' => 60, 'Developer' => 50, 'Professional' => 30, 'Student' => 20, 'Home User' => 20, 'Gamer' => 100],
            'Creative' => ['Creative' => 100, 'Gaming' => 50, 'Developer' => 60, 'Professional' => 70, 'Student' => 40, 'Home User' => 30],
            'Developer' => ['Developer' => 100, 'Professional' => 80, 'Creative' => 50, 'Gaming' => 40, 'Student' => 60, 'Home User' => 30],
            'Professional' => ['Professional' => 100, 'Developer' => 70, 'Creative' => 50, 'Student' => 60, 'Home User' => 50, 'Business' => 100],
            'Student' => ['Student' => 100, 'Home User' => 80, 'Professional' => 50, 'Developer' => 40, 'Creative' => 30, 'Gaming' => 30],
            'Home User' => ['Home User' => 100, 'Student' => 80, 'Professional' => 50, 'Creative' => 30, 'Gaming' => 30],
            'Gamer' => ['Gamer' => 100, 'Gaming' => 100, 'Creative' => 50, 'Developer' => 40]
        ];
        
        // Base SQL with enhanced scoring
        $sql = "SELECT p.*, 
                COALESCE(r.rating, 0) as user_rating,
                
                -- 1. Use Case Match Score (25 points max)
                (CASE 
                    WHEN p.primary_use_case = ? THEN 25
                    WHEN p.primary_use_case IN ('Gaming', 'Gamer') AND ? IN ('Gaming', 'Gamer') THEN 25
                    WHEN p.primary_use_case = 'Creative' AND ? IN ('Gaming', 'Gamer', 'Creative') THEN 18
                    WHEN p.primary_use_case IN ('Gaming', 'Gamer') AND ? = 'Creative' THEN 15
                    WHEN p.primary_use_case = 'Developer' AND ? IN ('Professional', 'Developer', 'Creative') THEN 20
                    WHEN p.primary_use_case = 'Professional' AND ? IN ('Developer', 'Student', 'Home User') THEN 18
                    WHEN p.primary_use_case IN ('Student', 'Home User') AND ? IN ('Student', 'Home User', 'Professional') THEN 20
                    ELSE 5
                END) as use_case_score,

                -- 2. Performance/Specs Score (25 points max)
                (CASE 
                    WHEN ? = 'heavy' THEN 
                        (CASE 
                            WHEN p.ram_gb >= 32 AND (p.gpu LIKE '%RTX 40%' OR p.gpu LIKE '%RTX 30%' OR p.gpu LIKE '%M3%') THEN 25
                            WHEN p.ram_gb >= 16 AND (p.gpu LIKE '%RTX%' OR p.gpu LIKE '%M2%' OR p.gpu LIKE '%M3%') THEN 22
                            WHEN p.ram_gb >= 16 THEN 18
                            WHEN p.ram_gb >= 12 THEN 12
                            ELSE 5
                        END)
                    WHEN ? = 'everyday' THEN 
                        (CASE 
                            WHEN p.ram_gb >= 16 AND p.ram_gb <= 32 THEN 25
                            WHEN p.ram_gb >= 8 AND p.ram_gb < 16 THEN 22
                            WHEN p.ram_gb >= 8 THEN 20
                            ELSE 10
                        END)
                    WHEN ? = 'light' THEN 
                        (CASE 
                            WHEN p.ram_gb <= 8 THEN 25
                            WHEN p.ram_gb <= 16 THEN 20
                            ELSE 12
                        END)
                    ELSE 15
                END) as perf_score,

                -- 3. Screen/Portability Score (20 points max)
                (CASE 
                    WHEN ? = 'small' THEN 
                        (CASE 
                            WHEN p.display_size <= 13.5 THEN 20
                            WHEN p.display_size <= 14.5 THEN 15
                            WHEN p.display_size <= 15.6 THEN 8
                            ELSE 3
                        END)
                    WHEN ? = 'medium' THEN 
                        (CASE 
                            WHEN p.display_size >= 14 AND p.display_size <= 15.6 THEN 20
                            WHEN p.display_size >= 13 AND p.display_size < 17 THEN 15
                            ELSE 8
                        END)
                    WHEN ? = 'large' THEN 
                        (CASE 
                            WHEN p.display_size >= 17 THEN 20
                            WHEN p.display_size >= 16 THEN 18
                            WHEN p.display_size >= 15 THEN 14
                            ELSE 5
                        END)
                    ELSE 12
                END) as screen_score,

                -- 4. Value Score (20 points max) - Price relative to budget
                (CASE 
                    WHEN p.price <= ? * 0.7 THEN 12  -- Much cheaper, might lack features
                    WHEN p.price <= ? THEN 20       -- Within budget, best value
                    WHEN p.price <= ? * 1.15 THEN 15 -- Slightly over, still good
                    WHEN p.price <= ? * 1.3 THEN 8  -- Over budget but shown
                    ELSE 3
                END) as value_score,

                -- 5. Popularity & Reviews Score (10 points max)
                (SELECT LEAST(10, COUNT(*) * 2) FROM recommendation_ratings rr WHERE rr.product_id = p.product_id AND rr.rating = 1) as popularity_score,
                
                -- Review bonus
                COALESCE((SELECT AVG(pr.rating) FROM product_reviews pr WHERE pr.product_id = p.product_id), 0) as avg_review

                FROM products p 
                LEFT JOIN recommendation_ratings r ON p.product_id = r.product_id AND r.user_id = ?
                WHERE p.is_active = 1 AND p.product_category = 'laptop'
        ";

        // Params for the scoring logic
        $params = [
            $use_case, $use_case, $use_case, $use_case, $use_case, $use_case, $use_case, // Use Case (7x)
            $performance, $performance, $performance, // Performance (3x)
            $screen_size, $screen_size, $screen_size, // Screen (3x)
            $budget, $budget, $budget, $budget, // Value (4x)
            $_SESSION['user_id'] // User ID
        ];
        $types = "sssssssssssssddddi"; // 13s + 4d + 1i = 18 params

        // Expanded budget filter - show up to 40% over budget
        $sql .= " AND p.price <= ?";
        $params[] = $budget * 1.4;
        $types .= "d";

        // Order by total score (all components)
        $sql .= " ORDER BY (use_case_score + perf_score + screen_score + value_score + popularity_score) DESC, avg_review DESC, p.price ASC LIMIT 8";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result_set = $stmt->get_result();
        
        while($row = $result_set->fetch_assoc()) {
            $row['total_score'] = $row['use_case_score'] + $row['perf_score'] + $row['screen_score'] + $row['value_score'] + $row['popularity_score'];
            // Cap at 100
            $row['total_score'] = min(100, $row['total_score']);
            $results[] = $row;
        }
        $stmt->close();
    }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* Modern Design System */
:root {
    --primary: #4f46e5;
    --primary-hover: #4338ca;
    --bg-body: #f9fafb;
    --bg-card: #ffffff;
    --text-main: #111827;
    --text-muted: #6b7280;
    --border: #e5e7eb;
}

body {
    background-color: var(--bg-body);
    font-family: 'Inter', sans-serif;
    color: var(--text-main);
}

.advisor-wrapper {
    max-width: 1000px;
    margin: 40px auto;
    padding: 0 20px;
}

/* Progress Bar */
.progress-container {
    margin-bottom: 40px;
}

.progress-track {
    background: #e5e7eb;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    background: var(--primary);
    height: 100%;
    transition: width 0.4s ease;
}

.progress-labels {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 0.875rem;
    color: var(--text-muted);
    font-weight: 500;
}

/* Quiz Card */
.quiz-card {
    background: var(--bg-card);
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    padding: 40px;
    text-align: center;
    transition: all 0.3s ease;
}

.quiz-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 12px;
    color: var(--text-main);
}

.quiz-subtitle {
    color: var(--text-muted);
    font-size: 1.1rem;
    margin-bottom: 40px;
}

/* Selection Grid */
.selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    text-align: left;
}

.option-card {
    position: relative;
    cursor: pointer;
}

.option-card input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.option-content {
    background: #ffffff;
    border: 2px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    height: 100%;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.option-card input:checked + .option-content {
    border-color: var(--primary);
    background: #eef2ff;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
}

.option-icon {
    font-size: 2.5rem;
    margin-bottom: 16px;
}

.option-title {
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 8px;
    color: var(--text-main);
}

.option-desc {
    font-size: 0.9rem;
    color: var(--text-muted);
    line-height: 1.4;
}

/* Range Slider */
.range-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px 0;
}

.range-value {
    font-size: 3rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 20px;
    display: block;
}

input[type=range] {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    outline: none;
    -webkit-appearance: none;
}

input[type=range]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 28px;
    height: 28px;
    background: var(--primary);
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    transition: transform 0.1s;
}

input[type=range]::-webkit-slider-thumb:hover {
    transform: scale(1.1);
}

/* Results Page */
.results-header {
    text-align: center;
    margin-bottom: 40px;
}

.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}

.result-card {
    background: var(--bg-card);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    transition: transform 0.3s ease;
    border: 1px solid var(--border);
    display: flex;
    flex-direction: column;
}

.result-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.top-match-badge {
    background: #10b981;
    color: white;
    text-align: center;
    padding: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    letter-spacing: 0.05em;
}

.result-img {
    width: 100%;
    height: 220px;
    object-fit: contain;
    padding: 20px;
    background: #f9fafb;
}

.result-body {
    padding: 24px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.match-score {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 12px;
}

.score-high { background: #d1fae5; color: #065f46; }
.score-med { background: #e0e7ff; color: #3730a3; }

.result-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0 0 4px 0;
    color: var(--text-main);
}

.result-brand {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-bottom: 16px;
}

.specs-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
}

.spec-tag {
    background: #f3f4f6;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.8rem;
    color: #4b5563;
    font-weight: 500;
}

.result-price {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-main);
    margin-top: auto;
    margin-bottom: 16px;
}

.btn-view {
    display: block;
    width: 100%;
    padding: 12px;
    background: var(--primary);
    color: white;
    text-align: center;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-view:hover {
    background: var(--primary-hover);
}

.btn-restart {
    display: inline-block;
    margin-top: 40px;
    padding: 12px 30px;
    background: white;
    border: 1px solid var(--border);
    color: var(--text-main);
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-restart:hover {
    background: #f9fafb;
    border-color: #d1d5db;
}

/* Rationale Styles */
.rationale-box {
    background: #f3f4f6;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 16px;
}

.rationale-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-main);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.rationale-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.rationale-item {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-bottom: 4px;
    padding-left: 16px;
    position: relative;
}

.rationale-item::before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #10b981;
    font-weight: bold;
}
</style>

<div class="advisor-wrapper">
    
    <?php if ($step <= $total_steps): ?>
        <!-- PROGRESS BAR -->
        <div class="progress-container">
            <div class="progress-track">
                <div class="progress-fill" style="width: <?php echo ($step / $total_steps) * 100; ?>%;"></div>
            </div>
            <div class="progress-labels">
                <span>Start</span>
                <span>Step <?php echo $step; ?> of <?php echo $total_steps; ?></span>
                <span>Results</span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($step == 0): ?>
        <!-- STEP 0: WELCOME -->
        <div class="quiz-card">
            <div style="font-size: 4rem; margin-bottom: 20px;">🤖</div>
            <h1 class="quiz-title">Let's Find Your Perfect Match</h1>
            <p class="quiz-subtitle">Answer a few quick questions and our AI-powered engine will find the best product for your needs.</p>
            
            <a href="?step=1" class="btn-view" style="max-width: 200px; margin: 0 auto; display: inline-block;">Start Advisor</a>
        </div>

    <?php elseif ($step == 1): ?>
        <!-- STEP 1: CATEGORY SELECTION -->
        <div class="quiz-card">
            <h2 class="quiz-title">What are you looking for?</h2>
            <p class="quiz-subtitle">We'll tailor the questions based on your product type.</p>
            
            <div class="selection-grid" style="justify-content: center;">
                <!-- Laptop Option -->
                <form action="advisor.php" method="GET" style="display: contents;">
                    <input type="hidden" name="step" value="2">
                    <input type="hidden" name="category" value="Laptop">
                    <label class="option-card">
                        <button type="submit" style="display: none;"></button>
                        <div class="option-content" onclick="this.previousElementSibling.click()">
                            <div class="option-icon">💻</div>
                            <div class="option-title">Laptop</div>
                            <div class="option-desc">I need a new computer for work, gaming, or school.</div>
                        </div>
                    </label>
                </form>

                <!-- Accessory Option -->
                <form action="advisor.php" method="GET" style="display: contents;">
                    <input type="hidden" name="step" value="2">
                    <input type="hidden" name="category" value="Accessories">
                    <label class="option-card">
                        <button type="submit" style="display: none;"></button>
                        <div class="option-content" onclick="this.previousElementSibling.click()">
                            <div class="option-icon">🖱️</div>
                            <div class="option-title">Accessories</div>
                            <div class="option-desc">I'm looking for peripherals like mice, keyboards, or headsets.</div>
                        </div>
                    </label>
                </form>
            </div>
        </div>

    <?php elseif ($step == 2 && $category === 'Accessories'): ?>
        <!-- STEP 2 (Accessories): TYPE -->
        <div class="quiz-card">
            <h2 class="quiz-title">What type of accessory?</h2>
            <p class="quiz-subtitle">Select the category you are interested in.</p>
            
            <form action="advisor.php" method="GET">
                <input type="hidden" name="step" value="3">
                <input type="hidden" name="category" value="Accessories">
                
                <div class="selection-grid">
                    <label class="option-card">
                        <input type="radio" name="accessory_type" value="Mouse" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🖱️</div>
                            <div class="option-title">Mouse</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="accessory_type" value="Keyboard" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">⌨️</div>
                            <div class="option-title">Keyboard</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="accessory_type" value="Headset" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🎧</div>
                            <div class="option-title">Headset</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="accessory_type" value="Monitor" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🖥️</div>
                            <div class="option-title">Monitor</div>
                        </div>
                    </label>
                </div>
            </form>
        </div>

    <?php elseif ($step == 3 && $category === 'Accessories'): ?>
        <!-- STEP 3 (Accessories): USE CASE -->
        <div class="quiz-card">
            <h2 class="quiz-title">What will you use it for?</h2>
            <p class="quiz-subtitle">We'll recommend the best <?php echo htmlspecialchars($accessory_type); ?> for your needs.</p>
            
            <form action="advisor.php" method="GET">
                <input type="hidden" name="step" value="4">
                <input type="hidden" name="category" value="Accessories">
                <input type="hidden" name="accessory_type" value="<?php echo htmlspecialchars($accessory_type); ?>">
                
                <div class="selection-grid">
                    <label class="option-card">
                        <input type="radio" name="accessory_use_case" value="Gaming" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🎮</div>
                            <div class="option-title">Gaming</div>
                            <div class="option-desc">High performance, fast response, RGB lighting</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="accessory_use_case" value="Work" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">💼</div>
                            <div class="option-title">Work / Office</div>
                            <div class="option-desc">Ergonomic, quiet, professional, reliable</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="accessory_use_case" value="Casual" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">☕</div>
                            <div class="option-title">Casual / Everyday</div>
                            <div class="option-desc">Simple, affordable, easy to use</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="accessory_use_case" value="Creative" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🎨</div>
                            <div class="option-title">Creative / Design</div>
                            <div class="option-desc">Precision, color accuracy, productivity</div>
                        </div>
                    </label>
                </div>
            </form>
        </div>

    <?php elseif ($step == 4 && $category === 'Accessories'): ?>
        <!-- STEP 4 (Accessories): BUDGET -->
        <div class="quiz-card">
            <h2 class="quiz-title">What's your budget?</h2>
            <p class="quiz-subtitle">We'll find the best <?php echo htmlspecialchars($accessory_type); ?> for <?php echo htmlspecialchars($accessory_use_case); ?> within your range.</p>
            
            <form action="advisor.php" method="GET" id="accessoryBudgetForm">
                <input type="hidden" name="step" value="5">
                <input type="hidden" name="category" value="Accessories">
                <input type="hidden" name="accessory_type" value="<?php echo htmlspecialchars($accessory_type); ?>">
                <input type="hidden" name="accessory_use_case" value="<?php echo htmlspecialchars($accessory_use_case); ?>">
                <input type="hidden" name="budget" id="accessoryBudgetHidden" value="100">
                
                <div class="range-container">
                    <span class="range-value" id="accessoryBudgetValue">$100</span>
                    <input type="range" id="accessoryBudgetRange" min="10" max="500" step="10" value="100">
                    <div style="display: flex; justify-content: space-between; color: var(--text-muted); margin-top: 10px;">
                        <span id="accessoryMinLabel">$10</span>
                        <span id="accessoryMaxLabel">$500+</span>
                    </div>
                </div>

                <button type="submit" class="btn-view" style="max-width: 300px; margin: 40px auto 0;">Show Accessories ✨</button>
            </form>
        </div>
        
        <script>
        (function() {
            // Currency-specific ranges for accessories
            const accessoryRanges = {
                'USD': { min: 10, max: 500, default: 100, step: 10, symbol: '$' },
                'MYR': { min: 50, max: 2000, default: 400, step: 50, symbol: 'RM' },
                'CNY': { min: 50, max: 3000, default: 600, step: 50, symbol: '¥' }
            };
            
            const rates = { USD: 1, MYR: 4.5, CNY: 7.2 };
            
            const slider = document.getElementById('accessoryBudgetRange');
            const display = document.getElementById('accessoryBudgetValue');
            const hidden = document.getElementById('accessoryBudgetHidden');
            const minLabel = document.getElementById('accessoryMinLabel');
            const maxLabel = document.getElementById('accessoryMaxLabel');
            const form = document.getElementById('accessoryBudgetForm');
            
            function getCurrency() {
                return localStorage.getItem('selected_currency') || 'USD';
            }
            
            function getRate(currency) {
                try {
                    const stored = JSON.parse(localStorage.getItem('currency_rates'));
                    if (stored && stored.rates && stored.rates[currency]) {
                        return stored.rates[currency];
                    }
                } catch(e) {}
                return rates[currency] || 1;
            }
            
            function updateSliderCurrency() {
                const currency = getCurrency();
                const range = accessoryRanges[currency] || accessoryRanges['USD'];
                const rate = getRate(currency);
                
                slider.min = range.min;
                slider.max = range.max;
                slider.step = range.step;
                slider.value = range.default;
                
                minLabel.textContent = range.symbol + range.min.toLocaleString();
                maxLabel.textContent = range.symbol + range.max.toLocaleString() + '+';
                display.textContent = range.symbol + range.default.toLocaleString();
                
                // Convert to USD for hidden field
                hidden.value = Math.round(range.default / rate);
            }
            
            slider.addEventListener('input', function() {
                const currency = getCurrency();
                const range = accessoryRanges[currency] || accessoryRanges['USD'];
                const rate = getRate(currency);
                
                display.textContent = range.symbol + parseInt(this.value).toLocaleString();
                hidden.value = Math.round(parseInt(this.value) / rate);
            });
            
            // Initialize
            updateSliderCurrency();
            
            // Update on currency change
            window.addEventListener('currencyChanged', updateSliderCurrency);
        })();
        </script>


    <?php elseif ($step == 2 && $category !== 'Accessories'): ?>
        <!-- STEP 2 (Laptop): USE CASE -->
        <div class="quiz-card">
            <h2 class="quiz-title">What will you use it for?</h2>
            <p class="quiz-subtitle">We'll prioritize features based on your primary activity.</p>
            
            <form action="advisor.php" method="GET">
                <input type="hidden" name="step" value="3">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <div class="selection-grid">
                    <label class="option-card">
                        <input type="radio" name="use_case" value="General Use" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🌐</div>
                            <div class="option-title">General Use</div>
                            <div class="option-desc">Web browsing, streaming, email, and light tasks.</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="use_case" value="Student" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">📚</div>
                            <div class="option-title">Student</div>
                            <div class="option-desc">Note-taking, research, assignments, and portability.</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="use_case" value="Business" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">💼</div>
                            <div class="option-title">Business</div>
                            <div class="option-desc">Productivity, multitasking, meetings, and reliability.</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="use_case" value="Gaming" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🎮</div>
                            <div class="option-title">Gaming</div>
                            <div class="option-desc">High-performance graphics for modern games.</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="use_case" value="Creative" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🎨</div>
                            <div class="option-title">Creative</div>
                            <div class="option-desc">Video editing, 3D rendering, and graphic design.</div>
                        </div>
                    </label>
                </div>
            </form>
        </div>

    <?php elseif ($step == 3 && $category !== 'Accessories'): ?>
        <!-- STEP 3 (Laptop): PERFORMANCE -->
        <div class="quiz-card">
            <h2 class="quiz-title">How much power do you need?</h2>
            <p class="quiz-subtitle">This determines the processor and memory requirements.</p>
            
            <form action="advisor.php" method="GET">
                <input type="hidden" name="step" value="4">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <input type="hidden" name="use_case" value="<?php echo htmlspecialchars($use_case); ?>">
                
                <div class="selection-grid">
                    <label class="option-card">
                        <input type="radio" name="performance" value="light" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🍃</div>
                            <div class="option-title">Light</div>
                            <div class="option-desc">Basic tasks. I don't run many apps at once.</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="performance" value="everyday" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">⚡</div>
                            <div class="option-title">Balanced</div>
                            <div class="option-desc">Smooth multitasking. I keep many tabs open.</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="performance" value="heavy" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🚀</div>
                            <div class="option-title">Heavy Duty</div>
                            <div class="option-desc">Intense workloads, large files, and pro software.</div>
                        </div>
                    </label>
                </div>
            </form>
        </div>

    <?php elseif ($step == 4 && $category !== 'Accessories'): ?>
        <!-- STEP 4 (Laptop): SCREEN SIZE -->
        <div class="quiz-card">
            <h2 class="quiz-title">Pick your preferred size</h2>
            <p class="quiz-subtitle">Balance between portability and screen real estate.</p>
            
            <form action="advisor.php" method="GET">
                <input type="hidden" name="step" value="5">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <input type="hidden" name="use_case" value="<?php echo htmlspecialchars($use_case); ?>">
                <input type="hidden" name="performance" value="<?php echo htmlspecialchars($performance); ?>">
                
                <div class="selection-grid">
                    <label class="option-card">
                        <input type="radio" name="screen_size" value="small" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">📱</div>
                            <div class="option-title">Compact (13-14")</div>
                            <div class="option-desc">Ultra-portable, fits in any bag. Great for travel.</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="screen_size" value="medium" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">💻</div>
                            <div class="option-title">Standard (15")</div>
                            <div class="option-desc">The sweet spot. Good screen size and portability.</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="screen_size" value="large" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🖥️</div>
                            <div class="option-title">Large (16"+)</div>
                            <div class="option-desc">Maximum workspace. Best for editing and gaming.</div>
                        </div>
                    </label>
                </div>
            </form>
        </div>

    <?php elseif ($step == 5 && $category !== 'Accessories'): ?>
        <!-- STEP 5 (Laptop): PORTABILITY -->
        <div class="quiz-card">
            <h2 class="quiz-title">How mobile are you?</h2>
            <p class="quiz-subtitle">We'll check the weight and battery life for you.</p>
            
            <form action="advisor.php" method="GET">
                <input type="hidden" name="step" value="6">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <input type="hidden" name="use_case" value="<?php echo htmlspecialchars($use_case); ?>">
                <input type="hidden" name="performance" value="<?php echo htmlspecialchars($performance); ?>">
                <input type="hidden" name="screen_size" value="<?php echo htmlspecialchars($screen_size); ?>">
                
                <div class="selection-grid">
                    <label class="option-card">
                        <input type="radio" name="portability" value="stationary" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🏠</div>
                            <div class="option-title">Mostly Stationary</div>
                            <div class="option-desc">It will mostly stay on a desk. Weight doesn't matter.</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="portability" value="occasional" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">🚶</div>
                            <div class="option-title">Occasional</div>
                            <div class="option-desc">I take it to coffee shops or meetings sometimes.</div>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="portability" value="frequent" required onchange="this.form.submit()">
                        <div class="option-content">
                            <div class="option-icon">✈️</div>
                            <div class="option-title">Road Warrior</div>
                            <div class="option-desc">I carry it everywhere, every day. Lightness is key.</div>
                        </div>
                    </label>
                </div>
            </form>
        </div>

    <?php elseif ($step == 6 && $category !== 'Accessories'): ?>
        <!-- STEP 6 (Laptop): BUDGET -->
        <div class="quiz-card">
            <h2 class="quiz-title">What's your budget?</h2>
            <p class="quiz-subtitle">We'll find the best value within your range.</p>
            
            <form action="advisor.php" method="GET">
                <input type="hidden" name="step" value="7">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <input type="hidden" name="use_case" value="<?php echo htmlspecialchars($use_case); ?>">
                <input type="hidden" name="performance" value="<?php echo htmlspecialchars($performance); ?>">
                <input type="hidden" name="screen_size" value="<?php echo htmlspecialchars($screen_size); ?>">
                <input type="hidden" name="portability" value="<?php echo htmlspecialchars($portability); ?>">
                
                <div class="range-container">
                    <span class="range-value" id="budgetValue">$1,000</span>
                    <input type="range" id="budgetSlider" min="0" max="100" step="1" value="30">
                    <input type="hidden" name="budget" id="budgetHidden" value="1000">
                    <div style="display: flex; justify-content: space-between; color: var(--text-muted); margin-top: 10px;">
                        <span id="budgetMin">$300</span>
                        <span id="budgetMax">$5,000+</span>
                    </div>
                </div>

                <button type="submit" class="btn-view" style="max-width: 300px; margin: 40px auto 0;">Reveal My Matches ✨</button>
            </form>
        </div>
        
        <script>
            // Currency-specific budget ranges (min, max, default) - all in local currency
            const budgetRanges = {
                'USD': { min: 300, max: 5000, default: 1000, symbol: '$' },
                'MYR': { min: 1500, max: 15000, default: 4000, symbol: 'RM' },
                'CNY': { min: 2000, max: 30000, default: 8000, symbol: '¥' }
            };
            
            const slider = document.getElementById('budgetSlider');
            const valueDisplay = document.getElementById('budgetValue');
            const hiddenInput = document.getElementById('budgetHidden');
            const minLabel = document.getElementById('budgetMin');
            const maxLabel = document.getElementById('budgetMax');
            
            // Get current currency and rate
            function getCurrencyInfo() {
                if (typeof CurrencyManager !== 'undefined' && CurrencyManager.selectedCurrency) {
                    const currency = CurrencyManager.selectedCurrency;
                    const rate = CurrencyManager.rates[currency] || 1;
                    return { currency, rate, ...budgetRanges[currency] || budgetRanges['USD'] };
                }
                return { currency: 'USD', rate: 1, ...budgetRanges['USD'] };
            }
            
            // Calculate actual budget from slider position (0-100)
            function sliderToBudget(sliderValue) {
                const info = getCurrencyInfo();
                const range = info.max - info.min;
                const localBudget = info.min + (sliderValue / 100 * range);
                return Math.round(localBudget / 50) * 50; // Round to nearest 50
            }
            
            // Convert local currency to USD for database query
            function localToUSD(localAmount) {
                const info = getCurrencyInfo();
                return Math.round(localAmount / info.rate);
            }
            
            // Update display based on slider
            function updateBudgetDisplay() {
                const info = getCurrencyInfo();
                const localBudget = sliderToBudget(slider.value);
                const usdBudget = localToUSD(localBudget);
                
                // Update display
                valueDisplay.textContent = info.symbol + localBudget.toLocaleString();
                
                // Update hidden field with USD value for backend
                hiddenInput.value = usdBudget;
                
                // Update min/max labels
                minLabel.textContent = info.symbol + info.min.toLocaleString();
                maxLabel.textContent = info.symbol + info.max.toLocaleString() + '+';
            }
            
            // Listen for slider changes
            slider.addEventListener('input', updateBudgetDisplay);
            
            // Initialize on page load
            document.addEventListener('DOMContentLoaded', () => {
                // Wait for CurrencyManager to initialize
                setTimeout(() => {
                    // Set slider to default position
                    const info = getCurrencyInfo();
                    const defaultPos = ((info.default - info.min) / (info.max - info.min)) * 100;
                    slider.value = defaultPos;
                    updateBudgetDisplay();
                }, 150);
            });
            
            // Listen for currency changes
            if (typeof MutationObserver !== 'undefined') {
                const observer = new MutationObserver(() => {
                    setTimeout(updateBudgetDisplay, 50);
                });
                document.querySelectorAll('.currency-selector').forEach(el => {
                    el.addEventListener('change', () => setTimeout(updateBudgetDisplay, 50));
                });
            }
        </script>

    <?php else: ?>
        <!-- RESULTS PAGE -->
        <div class="results-header">
            <h1 class="quiz-title">We Found <?php echo count($results); ?> Perfect Matches!</h1>
            
            <!-- User Choices Summary -->
            <?php if ($category !== 'Accessories'): ?>
            <div class="user-choices-summary">
                <h3 style="margin-bottom: 15px; font-size: 1.1rem; color: var(--text-muted);">📋 Your Preferences</h3>
                <div class="choices-grid">
                    <div class="choice-item">
                        <span class="choice-icon">🎯</span>
                        <span class="choice-label">Use Case</span>
                        <span class="choice-value"><?php echo htmlspecialchars($use_case); ?></span>
                    </div>
                    <div class="choice-item">
                        <span class="choice-icon">⚡</span>
                        <span class="choice-label">Performance</span>
                        <span class="choice-value"><?php echo ucfirst($performance); ?></span>
                    </div>
                    <div class="choice-item">
                        <span class="choice-icon">📱</span>
                        <span class="choice-label">Screen Size</span>
                        <span class="choice-value"><?php 
                            $screen_labels = ['small' => 'Compact (≤14")', 'medium' => 'Medium (14-15.6")', 'large' => 'Large (16"+)'];
                            echo $screen_labels[$screen_size] ?? ucfirst($screen_size); 
                        ?></span>
                    </div>
                    <div class="choice-item">
                        <span class="choice-icon">💰</span>
                        <span class="choice-label">Budget</span>
                        <span class="choice-value currency-price" data-base-price="<?php echo $budget; ?>">$<?php echo number_format($budget); ?></span>
                    </div>
                </div>
            </div>
            
            <style>
                .user-choices-summary {
                    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                    border-radius: 16px;
                    padding: 20px 25px;
                    margin: 25px 0;
                    border: 1px solid #e2e8f0;
                }
                .choices-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 15px;
                }
                .choice-item {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    text-align: center;
                    padding: 12px;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                }
                .choice-icon {
                    font-size: 1.5rem;
                    margin-bottom: 6px;
                }
                .choice-label {
                    font-size: 0.75rem;
                    color: var(--text-muted);
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 4px;
                }
                .choice-value {
                    font-size: 0.95rem;
                    font-weight: 600;
                    color: var(--primary);
                }
            </style>
            <?php else: ?>
                <!-- Accessories Preferences Summary -->
                <div class="user-choices-summary">
                    <h3 style="margin-bottom: 15px; font-size: 1.1rem; color: var(--text-muted);">📋 Your Preferences</h3>
                    <div class="choices-grid">
                        <div class="choice-item">
                            <span class="choice-icon"><?php 
                                echo match($accessory_type) {
                                    'Mouse' => '🖱️',
                                    'Keyboard' => '⌨️',
                                    'Headset' => '🎧',
                                    'Monitor' => '🖥️',
                                    default => '🔌'
                                };
                            ?></span>
                            <span class="choice-label">Type</span>
                            <span class="choice-value"><?php echo htmlspecialchars($accessory_type ?: 'All'); ?></span>
                        </div>
                        <div class="choice-item">
                            <span class="choice-icon"><?php 
                                echo match($accessory_use_case) {
                                    'Gaming' => '🎮',
                                    'Work' => '💼',
                                    'Casual' => '☕',
                                    'Creative' => '🎨',
                                    default => '🎯'
                                };
                            ?></span>
                            <span class="choice-label">Use Case</span>
                            <span class="choice-value"><?php echo htmlspecialchars($accessory_use_case ?: 'Any'); ?></span>
                        </div>
                        <div class="choice-item">
                            <span class="choice-icon">💰</span>
                            <span class="choice-label">Budget</span>
                            <span class="choice-value currency-price" data-base-price="<?php echo $budget; ?>">$<?php echo number_format($budget); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($results)): ?>
            <div class="quiz-card">
                <div style="font-size: 3rem; margin-bottom: 20px;">🔍</div>
                <h3>No exact matches found</h3>
                <p>Try increasing your budget or adjusting your filters to see more options.</p>
                <a href="advisor.php" class="btn-restart">Start Over</a>
            </div>
        <?php else: ?>
            <div class="results-grid">
                <?php foreach($results as $index => $row): ?>
                    <div class="result-card">
                        <?php if ($index === 0): ?>
                            <div class="top-match-badge">🏆 TOP RECOMMENDATION</div>
                        <?php endif; ?>
                        
                        <?php 
                        // Fix image path - remove 'LaptopAdvisor/' prefix if present
                        $img_url = $row['image_url'] ?? '';
                        if (strpos($img_url, 'LaptopAdvisor/') === 0) {
                            $img_url = substr($img_url, strlen('LaptopAdvisor/'));
                        }
                        ?>
                        <img src="<?php echo !empty($img_url) ? htmlspecialchars($img_url) : 'https://via.placeholder.com/300'; ?>" 
                             class="result-img" alt="<?php echo htmlspecialchars($row['product_name']); ?>">
                        
                        <div class="result-body">
                            <!-- Match Score for ALL categories -->
                            <div class="match-score <?php echo $row['total_score'] > 80 ? 'score-high' : ($row['total_score'] > 60 ? 'score-med' : 'score-low'); ?>">
                                <?php echo min(100, round($row['total_score'])); ?>% Match
                            </div>
                            
                            <h3 class="result-title"><?php echo htmlspecialchars($row['product_name']); ?></h3>
                            <div class="result-brand"><?php echo htmlspecialchars($row['brand']); ?></div>
                            
                            <?php if ($category !== 'Accessories'): ?>
                                <div class="specs-list">
                                    <span class="spec-tag"><?php echo $row['cpu']; ?></span>
                                    <span class="spec-tag"><?php echo $row['ram_gb']; ?>GB RAM</span>
                                    <span class="spec-tag"><?php echo $row['storage_gb']; ?>GB SSD</span>
                                    <span class="spec-tag"><?php echo $row['display_size']; ?>"</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="result-price currency-price" data-base-price="<?php echo $row['price']; ?>">$<?php echo number_format($row['price'], 2); ?></div>

                            <?php if ($category === 'Accessories'): ?>
                                <!-- Accessory Recommendation Reasons -->
                                <?php
                                $acc_reasons = [];
                                
                                // Use case match
                                if (!empty($row['use_case_matched']) && $row['use_case_matched']) {
                                    $use_case_msg = match($accessory_use_case) {
                                        'Gaming' => 'Perfect for <strong>gaming</strong> with high performance features',
                                        'Work' => 'Ideal for <strong>office work</strong> with ergonomic design',
                                        'Casual' => 'Great for <strong>everyday use</strong> - simple and reliable',
                                        'Creative' => 'Built for <strong>creative work</strong> with precision and accuracy',
                                        default => 'Matches your usage style'
                                    };
                                    $acc_reasons[] = $use_case_msg;
                                } elseif ($row['use_case_score'] >= 8) {
                                    $acc_reasons[] = 'Suitable for ' . htmlspecialchars($accessory_use_case) . ' use';
                                }
                                
                                // Value score
                                $price_percent = round(($row['price'] / max(1, $budget)) * 100);
                                if ($row['value_score'] >= 18) {
                                    if ($row['price'] <= $budget * 0.8) {
                                        $savings = $budget - $row['price'];
                                        $acc_reasons[] = '<strong>Great value</strong> - saves you <span class="currency-price" data-base-price="' . $savings . '">$' . number_format($savings) . '</span>';
                                    } else {
                                        $acc_reasons[] = '<strong>Excellent value</strong> for its features';
                                    }
                                } elseif ($row['value_score'] >= 12 && $row['price'] > $budget) {
                                    $acc_reasons[] = 'Premium option - slightly over budget but worth it';
                                }
                                
                                // Brand quality
                                if (!empty($row['is_premium_brand']) && $row['is_premium_brand']) {
                                    $acc_reasons[] = 'From a <strong>trusted premium brand</strong>';
                                }
                                
                                // Reviews
                                if ($row['review_score'] >= 10 && ($row['avg_review'] ?? 0) >= 4) {
                                    $acc_reasons[] = 'Highly rated (<strong>' . number_format($row['avg_review'], 1) . '★</strong> average)';
                                }
                                
                                // Popularity
                                if ($row['popularity_score'] >= 15) {
                                    $acc_reasons[] = 'Popular choice among buyers';
                                }
                                
                                // Limit to 4 reasons
                                $acc_reasons = array_slice($acc_reasons, 0, 4);
                                
                                if (!empty($acc_reasons)):
                                ?>
                                <div class="rationale-box">
                                    <div class="rationale-title">Why This <?php echo htmlspecialchars($accessory_type); ?>?</div>
                                    <ul class="rationale-list">
                                        <?php foreach($acc_reasons as $reason): ?>
                                            <li class="rationale-item"><?php echo $reason; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php
                            if ($category !== 'Accessories') {
                                // Generate Detailed Rationale for Laptops
                                $reasons = [];
                                
                                // Use Case - More specific
                                if ($row['use_case_score'] >= 25) {
                                    $use_case_detail = match($use_case) {
                                        'Gaming', 'Gamer' => "Built for gaming with powerful " . $row['gpu'],
                                        'Creative' => "Ideal for creative work with premium display and " . ($row['gpu'] ?: 'capable graphics'),
                                        'Developer' => "Developer-ready with " . $row['ram_gb'] . "GB RAM for multitasking",
                                        'Professional', 'Business' => "Business-class reliability and performance",
                                        'Student' => "Perfect for studying with balanced specs and portability",
                                        default => "Perfect match for <strong>" . htmlspecialchars($use_case) . "</strong>"
                                    };
                                    $reasons[] = $use_case_detail;
                                } elseif ($row['use_case_score'] >= 18) {
                                    $reasons[] = "Well-suited for " . htmlspecialchars($use_case) . " tasks";
                                }
                                
                                // Performance - Include actual specs
                                if ($row['perf_score'] >= 22) {
                                    $perf_detail = "<strong>" . $row['cpu'] . "</strong>";
                                    if (!empty($row['gpu']) && strpos($row['gpu'], 'Integrated') === false) {
                                        $perf_detail .= " + " . $row['gpu'];
                                    }
                                    $reasons[] = $perf_detail;
                                } elseif ($row['perf_score'] >= 18) {
                                    $reasons[] = $row['ram_gb'] . "GB RAM handles " . htmlspecialchars($performance) . " workloads smoothly";
                                }
                                
                                // Screen - Reference user choice
                                if ($row['screen_score'] >= 18) {
                                    $screen_desc = match($screen_size) {
                                        'small' => "Compact " . $row['display_size'] . "\" display - easy to carry",
                                        'medium' => "Perfect " . $row['display_size'] . "\" balance of screen & portability",
                                        'large' => "Immersive " . $row['display_size'] . "\" display for productivity",
                                        default => $row['display_size'] . "\" screen matches your preference"
                                    };
                                    $reasons[] = $screen_desc;
                                }
                                
                                // Value - Show price context
                                if ($row['value_score'] >= 20) {
                                    $price_diff = $budget - $row['price'];
                                    if ($price_diff > 100) {
                                        $reasons[] = "<strong>Under budget</strong> - saves you \$" . number_format($price_diff);
                                    } else {
                                        $reasons[] = "<strong>Best value</strong> within your budget range";
                                    }
                                } elseif ($row['value_score'] >= 15 && $row['price'] > $budget) {
                                    $over_pct = round((($row['price'] - $budget) / $budget) * 100);
                                    $reasons[] = "Just " . $over_pct . "% over budget - worth considering";
                                }
                                
                                // Popularity & Reviews
                                if ($row['popularity_score'] >= 6) {
                                    $reasons[] = "⭐ Highly rated by users";
                                } elseif ($row['avg_review'] >= 4) {
                                    $reasons[] = "⭐ " . number_format($row['avg_review'], 1) . " average rating";
                                }
                                
                                // Storage bonus
                                if ($row['storage_gb'] >= 1000) {
                                    $reasons[] = "Plenty of storage: " . ($row['storage_gb'] >= 1000 ? ($row['storage_gb']/1000) . "TB" : $row['storage_gb'] . "GB") . " SSD";
                                }
                                
                                // Limit to 4 reasons for better display
                                $reasons = array_slice($reasons, 0, 4);
                                
                                if (!empty($reasons)): ?>
                                    <div class="rationale-box">
                                        <div class="rationale-title">💡 Why This Laptop?</div>
                                        <ul class="rationale-list">
                                            <?php foreach ($reasons as $reason): ?>
                                                <li class="rationale-item"><?php echo $reason; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; 
                            }
                            ?>
                            
                            <a href="product_details.php?product_id=<?php echo $row['product_id']; ?>" class="btn-view">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center;">
                <a href="advisor.php" class="btn-restart">Start New Search</a>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>