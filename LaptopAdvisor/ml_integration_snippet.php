<?php
/**
 * ML Recommendation Engine Integration Snippet
 * 
 * INSTRUCTIONS:
 * 1. Start the Python API: cd recommendation_engine && start_api.bat
 * 2. Copy the code from this file
 * 3. Paste it into products.php after line 88 (after $user_pref is set)
 */

// --- MACHINE LEARNING INTEGRATION ---
require_once 'includes/recommendation_api.php';
$ml_api = new RecommendationAPI();
$ml_available = false;
$ml_product_ids = [];

// Try to connect to Python ML API
if ($ml_api->healthCheck()) {
    $ml_available = true;
    
    // Get ML-based recommendations
    $ml_recommendations = $ml_api->getRecommendations($user_id, $user_pref, 15);
    
    if ($ml_recommendations && count($ml_recommendations) > 0) {
        foreach ($ml_recommendations as $rec) {
            $ml_product_ids[] = (int)$rec['product_id'];
        }
    }
}

// Display ML status indicator
if ($ml_available): ?>
    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;">
        <strong>🤖 AI Recommendations Active</strong> - <?php echo count($ml_product_ids); ?> ML picks
    </div>
<?php endif;
?>
