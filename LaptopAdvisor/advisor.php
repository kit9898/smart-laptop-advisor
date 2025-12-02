<?php
require_once 'includes/auth_check.php';
include 'includes/header.php';

// --- Multi-Step Quiz Logic ---
$step = isset($_GET['step']) ? (int)$_GET['step'] : 0;
$total_steps = 5; // Changed to 5 steps

$use_case = $_GET['use_case'] ?? '';
$performance = $_GET['performance'] ?? '';
$screen_size = $_GET['screen_size'] ?? '';
$portability = $_GET['portability'] ?? '';
$budget = $_GET['budget'] ?? '1500';

$results = [];

if ($step > $total_steps) {
    // Try exact match first
    $sql = "SELECT *, 0 as match_score FROM products WHERE 1=1";
    $params = [];
    $types = '';
    $is_relaxed_search = false;
    
    if (!empty($budget)) { 
        $sql .= " AND price <= ?"; 
        $params[] = $budget; 
        $types .= 'd'; 
    }
    
    if (!empty($use_case)) { 
        $sql .= " AND primary_use_case = ?"; 
        $params[] = $use_case; 
        $types .= 's'; 
    }
    
    if (!empty($performance)) {
        if ($performance == 'light') $sql .= " AND ram_gb <= 8";
        if ($performance == 'everyday') $sql .= " AND ram_gb >= 8 AND ram_gb <= 16";
        if ($performance == 'heavy') $sql .= " AND ram_gb >= 16";
    }
    
    if (!empty($screen_size)) {
        if ($screen_size == 'small') $sql .= " AND display_size < 14";
        if ($screen_size == 'medium') $sql .= " AND display_size >= 14 AND display_size < 16";
        if ($screen_size == 'large') $sql .= " AND display_size >= 16";
    }
    
    $sql .= " ORDER BY price DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) { 
        $stmt->bind_param($types, ...$params); 
    }
    $stmt->execute();
    $result_set = $stmt->get_result();
    
    // Calculate match scores for exact matches
    while($row = $result_set->fetch_assoc()) {
        $match_score = 0;
        
        // Performance match (0-30 points)
        if ($performance == 'heavy' && $row['ram_gb'] >= 16) $match_score += 30;
        elseif ($performance == 'everyday' && $row['ram_gb'] >= 8 && $row['ram_gb'] <= 16) $match_score += 30;
        elseif ($performance == 'light' && $row['ram_gb'] <= 8) $match_score += 30;
        else $match_score += 15;
        
        // Screen size match (0-20 points)
        if ($screen_size == 'large' && $row['display_size'] >= 16) $match_score += 20;
        elseif ($screen_size == 'small' && $row['display_size'] < 14) $match_score += 20;
        elseif ($screen_size == 'medium' && $row['display_size'] >= 14 && $row['display_size'] < 16) $match_score += 20;
        else $match_score += 10;
        
        // Use case match (0-30 points)
        if ($row['primary_use_case'] == $use_case) $match_score += 30;
        else $match_score += 10;
        
        // Budget efficiency (0-20 points)
        $price_ratio = $row['price'] / $budget;
        if ($price_ratio >= 0.8 && $price_ratio <= 1.0) $match_score += 20;
        elseif ($price_ratio >= 0.6 && $price_ratio < 0.8) $match_score += 15;
        else $match_score += 10;
        
        $row['match_score'] = $match_score;
        $results[] = $row;
    }
    $stmt->close();
    
    // If no exact matches found, do relaxed search
    if (empty($results)) {
        $is_relaxed_search = true;
        
        // Get all products and score them by similarity
        $sql = "SELECT * FROM products WHERE 1=1";
        
        // Keep budget as hard constraint (increase by 20%)
        if (!empty($budget)) {
            $relaxed_budget = $budget * 1.2;
            $sql .= " AND price <= " . $relaxed_budget;
        }
        
        $result_set = $conn->query($sql);
        
        while($row = $result_set->fetch_assoc()) {
            $match_score = 0;
            
            // Performance match with partial credit
            if ($performance == 'heavy') {
                if ($row['ram_gb'] >= 16) $match_score += 30;
                elseif ($row['ram_gb'] >= 12) $match_score += 20;
                else $match_score += 10;
            } elseif ($performance == 'everyday') {
                if ($row['ram_gb'] >= 8 && $row['ram_gb'] <= 16) $match_score += 30;
                elseif ($row['ram_gb'] >= 8 || $row['ram_gb'] >= 6) $match_score += 20;
                else $match_score += 10;
            } elseif ($performance == 'light') {
                if ($row['ram_gb'] <= 8) $match_score += 30;
                elseif ($row['ram_gb'] <= 12) $match_score += 20;
                else $match_score += 10;
            }
            
            // Screen size with tolerance
            $display_diff = 0;
            if ($screen_size == 'large') {
                if ($row['display_size'] >= 16) $match_score += 20;
                elseif ($row['display_size'] >= 15) $match_score += 15;
                else $match_score += 8;
            } elseif ($screen_size == 'small') {
                if ($row['display_size'] < 14) $match_score += 20;
                elseif ($row['display_size'] <= 14.5) $match_score += 15;
                else $match_score += 8;
            } elseif ($screen_size == 'medium') {
                if ($row['display_size'] >= 14 && $row['display_size'] < 16) $match_score += 20;
                elseif ($row['display_size'] >= 13.5 && $row['display_size'] < 16.5) $match_score += 15;
                else $match_score += 8;
            }
            
            // Use case match with partial credit
            if ($row['primary_use_case'] == $use_case) {
                $match_score += 30;
            } else {
                // Give partial credit for similar use cases
                if (($use_case == 'Gaming' && $row['primary_use_case'] == 'Creative') ||
                    ($use_case == 'Creative' && $row['primary_use_case'] == 'Gaming')) {
                    $match_score += 15;
                } elseif (($use_case == 'Business' && $row['primary_use_case'] == 'General Use') ||
                          ($use_case == 'General Use' && $row['primary_use_case'] == 'Business')) {
                    $match_score += 15;
                } elseif (($use_case == 'Student' && ($row['primary_use_case'] == 'General Use' || $row['primary_use_case'] == 'Business'))) {
                    $match_score += 15;
                } else {
                    $match_score += 8;
                }
            }
            
            // Budget scoring
            $price_ratio = $row['price'] / $budget;
            if ($price_ratio >= 0.8 && $price_ratio <= 1.0) $match_score += 20;
            elseif ($price_ratio >= 0.6 && $price_ratio <= 1.2) $match_score += 15;
            else $match_score += 8;
            
            $row['match_score'] = $match_score;
            $row['is_alternative'] = true; // Flag as alternative match
            $results[] = $row;
        }
    }
    
    // Sort by match score
    usort($results, function($a, $b) {
        return $b['match_score'] - $a['match_score'];
    });
    
    // Limit to top 6
    $results = array_slice($results, 0, 6);
}

// Helper function for match percentage
function getMatchPercentage($score) {
    return min(100, round($score));
}
?>

<style>
.match-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 10px;
}
.match-excellent { background: #10b981; color: white; }
.match-good { background: #3b82f6; color: white; }
.match-fair { background: #f59e0b; color: white; }

.recommendation-rationale {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}

.recommendation-rationale h4 {
    font-size: 0.95rem;
    margin-bottom: 10px;
    color: #333;
}

.recommendation-rationale ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.recommendation-rationale li {
    padding: 6px 0;
    font-size: 0.9rem;
    color: #555;
    position: relative;
    padding-left: 20px;
}

.recommendation-rationale li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #10b981;
    font-weight: bold;
}

.no-results-container {
    text-align: center;
    padding: 40px 20px;
    background: #f8f9fa;
    border-radius: 12px;
    margin: 20px 0;
}

.alternative-recommendations {
    margin-top: 30px;
    padding: 20px;
    background: #fff3cd;
    border-radius: 8px;
    border-left: 4px solid #ffc107;
}

.quiz-step-subtitle {
    color: #666;
    font-size: 0.95rem;
    margin-top: 10px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}
</style>

<h2>Smart Laptop Advisor</h2>

<div class="advisor-container">
    <?php if ($step > $total_steps): // --- FINAL RESULTS VIEW --- ?>
        <div class="quiz-step">
            <h2>Your Personalized Recommendations</h2>
            <div class="summary-box">
                <h4>Your Preferences:</h4>
                <ul>
                    <li><strong>Use Case:</strong> <span><?php echo htmlspecialchars($use_case); ?></span></li>
                    <li><strong>Performance Needs:</strong> <span><?php echo htmlspecialchars(ucfirst($performance)); ?></span></li>
                    <li><strong>Screen Size:</strong> <span><?php echo htmlspecialchars(ucfirst($screen_size)); ?></span></li>
                    <li><strong>Portability:</strong> <span><?php echo htmlspecialchars(ucfirst($portability)); ?></span></li>
                    <li><strong>Budget:</strong> <span>Up to $<?php echo number_format((float)$budget, 2); ?></span></li>
                </ul>
            </div>
            
            <?php if (!empty($results)): ?>
                <?php if ($is_relaxed_search): ?>
                    <div class="alternative-recommendations" style="margin: 20px auto; max-width: 700px;">
                        <h4>⚠️ No Exact Matches Found</h4>
                        <p style="margin: 10px 0;">We couldn't find laptops that match all your criteria perfectly, but here are the <strong>closest alternatives</strong> based on your preferences:</p>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; margin: 20px 0;">We found <strong><?php echo count($results); ?> perfect matches</strong> based on your criteria:</p>
                <?php endif; ?>
                <div class="product-grid">
                    <?php foreach($results as $index => $row): ?>
                        <?php
                        // Calculate match percentage
                        $match_percentage = getMatchPercentage($row['match_score']);
                        
                        // Adjust labeling for alternative matches
                        if (isset($row['is_alternative']) && $row['is_alternative']) {
                            // More lenient thresholds for alternatives
                            $match_class = $match_percentage >= 70 ? 'match-good' : 
                                          ($match_percentage >= 55 ? 'match-fair' : 'match-fair');
                            $match_label = $match_percentage >= 70 ? 'Close Match' : 
                                          ($match_percentage >= 55 ? 'Similar Option' : 'Alternative');
                        } else {
                            // Original thresholds for exact matches
                            $match_class = $match_percentage >= 85 ? 'match-excellent' : 
                                          ($match_percentage >= 70 ? 'match-good' : 'match-fair');
                            $match_label = $match_percentage >= 85 ? 'Excellent Match' : 
                                          ($match_percentage >= 70 ? 'Good Match' : 'Fair Match');
                        }
                        
                        // Enhanced rationale logic
                        $rationale_points = [];
                        $compromises = []; // Track what doesn't match perfectly
                        
                        // 1. Performance match
                        if ($performance == 'heavy' && $row['ram_gb'] >= 16) {
                            $rationale_points[] = "<strong>{$row['ram_gb']}GB RAM</strong> handles demanding applications effortlessly";
                        } elseif ($performance == 'heavy' && $row['ram_gb'] >= 12) {
                            $rationale_points[] = "<strong>{$row['ram_gb']}GB RAM</strong> - close to your heavy performance needs";
                            $compromises[] = "Slightly less RAM than ideal";
                        } elseif ($performance == 'everyday' && $row['ram_gb'] >= 8) {
                            $rationale_points[] = "<strong>{$row['ram_gb']}GB RAM</strong> ensures smooth multitasking";
                        } elseif ($performance == 'light') {
                            $rationale_points[] = "Optimized for efficient everyday computing";
                        }
                        
                        // 2. Screen size match
                        if ($screen_size == 'large' && $row['display_size'] >= 16) {
                            $rationale_points[] = "Spacious <strong>{$row['display_size']}\" display</strong> for immersive viewing";
                        } elseif ($screen_size == 'large' && $row['display_size'] >= 15) {
                            $rationale_points[] = "<strong>{$row['display_size']}\" display</strong> - nearly matches your large screen preference";
                            $compromises[] = "Slightly smaller screen";
                        } elseif ($screen_size == 'small' && $row['display_size'] < 14) {
                            $rationale_points[] = "Compact <strong>{$row['display_size']}\" screen</strong> for maximum portability";
                        } elseif ($screen_size == 'small' && $row['display_size'] <= 14.5) {
                            $rationale_points[] = "<strong>{$row['display_size']}\" display</strong> - still quite portable";
                            $compromises[] = "Slightly larger than preferred";
                        } elseif ($screen_size == 'medium') {
                            $rationale_points[] = "Balanced <strong>{$row['display_size']}\" display</strong> for versatile use";
                        }
                        
                        // 3. Use case specific
                        if ($row['primary_use_case'] == $use_case) {
                            if ($use_case == 'Gaming' && (stripos($row['gpu'], 'RTX') !== false || stripos($row['gpu'], 'RX') !== false)) {
                                $rationale_points[] = "Powerful <strong>{$row['gpu']}</strong> delivers excellent gaming performance";
                            } elseif ($use_case == 'Creative') {
                                $rationale_points[] = "High-performance specs ideal for creative workflows";
                            } elseif ($use_case == 'Business') {
                                $rationale_points[] = "Professional design with reliable business features";
                            } elseif ($use_case == 'Student') {
                                $rationale_points[] = "Perfect balance of performance and value for students";
                            } else {
                                $rationale_points[] = "Optimized for <strong>{$row['primary_use_case']}</strong> tasks";
                            }
                        } else {
                            // Different use case - explain why it might still work
                            $rationale_points[] = "Designed for <strong>{$row['primary_use_case']}</strong> - versatile enough for your needs";
                            $compromises[] = "Different primary use case";
                        }
                        
                        // 4. Storage highlight
                        if ($row['storage_gb'] >= 1024) {
                            $rationale_points[] = "Generous <strong>{$row['storage_gb']}GB {$row['storage_type']}</strong> for all your files";
                        }
                        
                        // 5. Value proposition
                        $price_ratio = $row['price'] / $budget;
                        if ($price_ratio >= 0.9 && $price_ratio <= 1.0) {
                            $rationale_points[] = "Maximizes your budget with premium features";
                        } elseif ($price_ratio > 1.0 && $price_ratio <= 1.2) {
                            $rationale_points[] = "Slightly above budget but offers excellent value";
                            $compromises[] = "Exceeds budget by $" . number_format($row['price'] - $budget, 0);
                        } elseif ($price_ratio < 0.7) {
                            $rationale_points[] = "Great value, leaving room in your budget";
                        } else {
                            $rationale_points[] = "Excellent price-to-performance ratio";
                        }
                        
                        // Top pick badge
                        $is_top_pick = $index === 0;
                        
                        // Limit to 3 points
                        $rationale_points = array_slice($rationale_points, 0, 3);
                        ?>
                        <div class="product-card">
                            <?php if ($is_top_pick): ?>
                                <div style="background: #10b981; color: white; padding: 8px; text-align: center; font-weight: 600; font-size: 0.85rem;">
                                    ⭐ <?php echo $is_relaxed_search ? 'BEST ALTERNATIVE' : 'TOP RECOMMENDATION'; ?>
                                </div>
                            <?php endif; ?>
                            <a href="product_details.php?product_id=<?php echo $row['product_id']; ?>">
                                <img src="<?php echo !empty($row['image_url']) ? htmlspecialchars($row['image_url']) : 'https://via.placeholder.com/280'; ?>" 
                                     alt="<?php echo htmlspecialchars($row['product_name']); ?>">
                                <div class="product-card-info">
                                    <span class="match-badge <?php echo $match_class; ?>">
                                        <?php echo $match_percentage; ?>% <?php echo $match_label; ?>
                                    </span>
                                    <p class="brand"><?php echo htmlspecialchars($row['brand']); ?></p>
                                    <h3><?php echo htmlspecialchars($row['product_name']); ?></h3>
                                    <p class="product-price">$<?php echo number_format($row['price'], 2); ?></p>
                                </div>
                            </a>
                            <div class="recommendation-rationale">
                                <h4>Why This Laptop?</h4>
                                <ul>
                                    <?php foreach ($rationale_points as $point): ?>
                                        <li><?php echo $point; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if (!empty($compromises) && count($compromises) > 0): ?>
                                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                                        <p style="font-size: 0.85rem; color: #6c757d; margin: 0;">
                                            <strong>Note:</strong> <?php echo implode(', ', array_slice($compromises, 0, 2)); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results-container">
                    <h3>No Perfect Matches Found</h3>
                    <p>We couldn't find laptops that match all your specific criteria.</p>
                    <div class="alternative-recommendations">
                        <h4>💡 Suggestions:</h4>
                        <ul style="text-align: left; max-width: 500px; margin: 15px auto;">
                            <li>Try increasing your budget</li>
                            <li>Consider a different screen size</li>
                            <li>Adjust your performance requirements</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="advisor.php" class="btn">Start New Search</a>
                <a href="products.php" class="btn" style="margin-left: 10px;">Browse All Laptops</a>
            </div>
        </div>
        
    <?php else: // --- QUIZ VIEW --- ?>
        <?php if ($step > 0): ?>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo (($step) / $total_steps) * 100; ?>%;"></div>
                <div class="progress-text">Step <?php echo $step; ?> of <?php echo $total_steps; ?></div>
            </div>
        <?php endif; ?>
        
        <?php switch ($step):
            case 0: ?>
                <div class="quiz-step text-center">
                    <h3 class="quiz-step-title">Find Your Perfect Laptop Match</h3>
                    <p style="max-width: 600px; margin: 0 auto 1rem auto; color: #666;">
                        Answer 5 quick questions and we'll recommend laptops tailored specifically to your needs, 
                        backed by smart matching technology.
                    </p>
                    <div style="display: flex; justify-content: center; gap: 30px; margin: 30px 0; flex-wrap: wrap;">
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; color: #3b82f6;">🎯</div>
                            <div style="font-weight: 600; margin-top: 8px;">Personalized</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; color: #3b82f6;">⚡</div>
                            <div style="font-weight: 600; margin-top: 8px;">Fast</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; color: #3b82f6;">✓</div>
                            <div style="font-weight: 600; margin-top: 8px;">Accurate</div>
                        </div>
                    </div>
                    <a href="?step=1" class="btn btn-primary btn-lg">Get Started</a>
                </div>
            <?php break; ?>
            
            <?php case 1: ?>
                <div class="quiz-step">
                    <h3 class="quiz-step-title">What's your primary use case?</h3>
                    <p class="quiz-step-subtitle">This helps us match you with laptops optimized for your needs</p>
                    <form class="quiz-form" action="advisor.php" method="GET">
                        <input type="hidden" name="step" value="2">
                        <div class="selection-grid">
                            <label class="selection-card">
                                <input type="radio" name="use_case" value="General Use" required>
                                <div class="card-content">
                                    <div style="font-size: 2rem; margin-bottom: 10px;">🌐</div>
                                    <strong>General Use</strong>
                                    <p>Browsing, Email, Streaming</p>
                                </div>
                            </label>
                            <label class="selection-card">
                                <input type="radio" name="use_case" value="Student" required>
                                <div class="card-content">
                                    <div style="font-size: 2rem; margin-bottom: 10px;">📚</div>
                                    <strong>Student</strong>
                                    <p>Notes, Research, Projects</p>
                                </div>
                            </label>
                            <label class="selection-card">
                                <input type="radio" name="use_case" value="Business" required>
                                <div class="card-content">
                                    <div style="font-size: 2rem; margin-bottom: 10px;">💼</div>
                                    <strong>Business</strong>
                                    <p>Productivity, Meetings, Travel</p>
                                </div>
                            </label>
                            <label class="selection-card">
                                <input type="radio" name="use_case" value="Gaming" required>
                                <div class="card-content">
                                    <div style="font-size: 2rem; margin-bottom: 10px;">🎮</div>
                                    <strong>Gaming</strong>
                                    <p>AAA Titles, High FPS</p>
                                </div>
                            </label>
                            <label class="selection-card">
                                <input type="radio" name="use_case" value="Creative" required>
                                <div class="card-content">
                                    <div style="font-size: 2rem; margin-bottom: 10px;">🎨</div>
                                    <strong>Creative Work</strong>
                                    <p>Video Editing, Design, 3D</p>
                                </div>
                            </label>
                        </div>
                    </form>
                </div>
            <?php break; ?>
            
            <?php case 2: ?>
                <div class="quiz-step">
                    <h3 class="quiz-step-title">What performance level do you need?</h3>
                    <p class="quiz-step-subtitle">This determines the processing power and memory</p>
                    <form class="quiz-form" action="advisor.php" method="GET">
                        <input type="hidden" name="step" value="3">
                        <input type="hidden" name="use_case" value="<?php echo htmlspecialchars($use_case); ?>">
                        <div class="selection-grid">
                            <label class="selection-card">
                                <input type="radio" name="performance" value="light" required>
                                <div class="card-content">
                                    <strong>Light Tasks</strong>
                                    <p>Web browsing, documents, email</p>
                                    <small style="color: #888;">≤8GB RAM</small>
                                </div>
                            </label>
                            <label class="selection-card">
                                <input type="radio" name="performance" value="everyday" required>
                                <div class="card-content">
                                    <strong>Everyday Use</strong>
                                    <p>Streaming, multitasking, light editing</p>
                                    <small style="color: #888;">8-16GB RAM</small>
                                </div>
                            </label>
                            <label class="selection-card">
                                <input type="radio" name="performance" value="heavy" required>
                                <div class="card-content">
                                    <strong>Heavy Workloads</strong>
                                    <p>Gaming, video editing, development</p>
                                    <small style="color: #888;">≥16GB RAM</small>
                                </div>
                            </label>
                        </div>
                    </form>
                </div>
            <?php break; ?>
            
            <?php case 3: ?>
                <div class="quiz-step">
                    <h3 class="quiz-step-title">What screen size do you prefer?</h3>
                    <p class="quiz-step-subtitle">Balance between portability and screen real estate</p>
                    <form class="quiz-form" action="advisor.php" method="GET">
                        <input type="hidden" name="step" value="4">
                        <input type="hidden" name="use_case" value="<?php echo htmlspecialchars($use_case); ?>">
                        <input type="hidden" name="performance" value="<?php echo htmlspecialchars($performance); ?>">
                        <div class="selection-grid">
                            <label class="selection-card">
                                <input type="radio" name="screen_size" value="small" required>
                                <div class="card-content">
                                    <strong>Compact</strong>
                                    <p>Under 14" - Ultra portable</p>
                                </div>
                            </label>
                            <label class="selection-card">
                                <input type="radio" name="screen_size" value="medium" required>
                                <div class="card-content">
                                    <strong>Standard</strong>
                                    <p>14" - 15.6" - Best balance</p>
                                </div>
                            </label>
                            <label class="selection-card">
                                <input type="radio" name="screen_size" value="large" required>
                                <div class="card-content">
                                    <strong>Large</strong>
                                    <p>16"+ - Immersive viewing</p>
                                </div>
                            </label>
                        </div>
                    </form>
                </div>
            <?php break; ?>
            
            <?php case 4: ?>
                <div class="quiz-step">
                    <h3 class="quiz-step-title">How important is portability?</h3>
                    <p class="quiz-step-subtitle">Will you carry it around often?</p>
                    <form class="quiz-form" action="advisor.php" method="GET">
                        <input type="hidden" name="step" value="5">
                        <input type="hidden" name="use_case" value="<?php echo htmlspecialchars($use_case); ?>">
                        <input type="hidden" name="performance" value="<?php echo htmlspecialchars($performance); ?>">
                        <input type="hidden" name="screen_size" value="<?php echo htmlspecialchars($screen_size); ?>">
                        <div class="selection-grid">
                            <label class="selection-card">
                                <input type="radio" name="portability" value="stationary" required>
                                <div class="card-content">
                                    <strong>Mostly Stationary</strong>
                                    <p>Desktop replacement</p>
                                </div>
                            </label>
                            <label class="selection-card">
                                <input type="radio" name="portability" value="occasional" required>
                                <div class="card-content">
                                    <strong>Occasional Travel</strong>
                                    <p>Sometimes on the go</p>
                                </div>
                            </label>
                            <label class="selection-card">
                                <input type="radio" name="portability" value="frequent" required>
                                <div class="card-content">
                                    <strong>Highly Mobile</strong>
                                    <p>Daily commute/travel</p>
                                </div>
                            </label>
                        </div>
                    </form>
                </div>
            <?php break; ?>
            
            <?php case 5: ?>
                <div class="quiz-step">
                    <h3 class="quiz-step-title">What's your budget?</h3>
                    <p class="quiz-step-subtitle">Set your maximum spending limit</p>
                    <form class="quiz-form" action="advisor.php" method="GET">
                        <input type="hidden" name="step" value="6">
                        <input type="hidden" name="use_case" value="<?php echo htmlspecialchars($use_case); ?>">
                        <input type="hidden" name="performance" value="<?php echo htmlspecialchars($performance); ?>">
                        <input type="hidden" name="screen_size" value="<?php echo htmlspecialchars($screen_size); ?>">
                        <input type="hidden" name="portability" value="<?php echo htmlspecialchars($portability); ?>">
                        <div class="form-group">
                            <label for="budget" class="budget-label">
                                <span id="budget-value">$<?php echo htmlspecialchars($budget); ?></span>
                            </label>
                            <input type="range" class="form-range" id="budget" name="budget" 
                                   min="500" max="4000" step="100" 
                                   value="<?php echo htmlspecialchars($budget); ?>">
                            <div style="display: flex; justify-content: space-between; margin-top: 10px; color: #888; font-size: 0.85rem;">
                                <span>$500</span>
                                <span>$4,000</span>
                            </div>
                        </div>
                        <div class="quiz-nav">
                            <button type="submit" class="btn btn-accent btn-lg">Show My Matches 🎯</button>
                        </div>
                    </form>
                </div>
            <?php break; ?>
        <?php endswitch; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const radioForms = document.querySelectorAll('.quiz-form');
    radioForms.forEach(form => {
        const radios = form.querySelectorAll('input[type="radio"]');
        radios.forEach(radio => {
            radio.addEventListener('change', () => {
                form.submit();
            });
        });
    });

    const budgetSlider = document.getElementById('budget');
    const budgetValue = document.getElementById('budget-value');
    if (budgetSlider) {
        budgetSlider.addEventListener('input', (event) => {
            budgetValue.textContent = '$' + parseInt(event.target.value).toLocaleString();
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>