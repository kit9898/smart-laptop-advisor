<?php
// admin_ai_weightage.php - AI Weightage Configuration
// Module C: AI Recommendation Engine

// Include database connection
require_once 'includes/db_connect.php';

// ===================== LOGIC SECTION =====================

// Get persona ID from URL or default to first available
$selected_persona_id = isset($_GET['persona']) ? intval($_GET['persona']) : null;

// Fetch all personas for dropdown
$personas_query = "SELECT persona_id, name, short_description, icon_class, color_theme FROM personas WHERE is_active = 1 ORDER BY name ASC";
$personas_result = mysqli_query($conn, $personas_query);

// If no persona selected, use the first one
if ($selected_persona_id === null && $personas_result && mysqli_num_rows($personas_result) > 0) {
    mysqli_data_seek($personas_result, 0);
    $first_persona = mysqli_fetch_assoc($personas_result);
    $selected_persona_id = $first_persona['persona_id'];
    mysqli_data_seek($personas_result, 0); // Reset pointer
}

// Fetch selected persona details and current weights
$persona_data = null;
$current_weights = null;

if ($selected_persona_id) {
    // UPDATED: Use recommendation_ratings for stats instead of recommendation_logs
    $persona_query = "SELECT p.*, 
        (SELECT COUNT(r.rating_id) 
         FROM recommendation_ratings r 
         JOIN users u ON r.user_id = u.user_id 
         WHERE u.primary_use_case = p.name) as total_recommendations,
        (SELECT (SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) / COUNT(r.rating_id)) * 100 
         FROM recommendation_ratings r 
         JOIN users u ON r.user_id = u.user_id 
         WHERE u.primary_use_case = p.name) as accuracy_rate
        FROM personas p 
        WHERE p.persona_id = ?";
    $stmt = mysqli_prepare($conn, $persona_query);
    mysqli_stmt_bind_param($stmt, 'i', $selected_persona_id);
    mysqli_stmt_execute($stmt);
    $persona_result = mysqli_stmt_get_result($stmt);
    $persona_data = mysqli_fetch_assoc($persona_result);
    mysqli_stmt_close($stmt);
    
    // Fetch current weights for this persona
    $weights_query = "SELECT * FROM ai_weightage WHERE persona_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $weights_query);
    mysqli_stmt_bind_param($stmt, 'i', $selected_persona_id);
    mysqli_stmt_execute($stmt);
    $weights_result = mysqli_stmt_get_result($stmt);
    $current_weights = mysqli_fetch_assoc($weights_result);
    mysqli_stmt_close($stmt);
}

// Handle AJAX requests for saving/loading configurations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $persona_id = intval($_POST['persona_id']);
        $cpu = floatval($_POST['cpu_weight']);
        $gpu = floatval($_POST['gpu_weight']);
        $battery = floatval($_POST['battery_weight']);
        $display = floatval($_POST['display_weight']);
        $portability = floatval($_POST['portability_weight']);
        $value = floatval($_POST['value_weight']);
        $total = $cpu + $gpu + $battery + $display + $portability + $value;
        
        // Calculate fairness score (closer to equal distribution = higher score)
        $ideal = 100 / 6; // 16.67
        $variance = 0;
        foreach ([$cpu, $gpu, $battery, $display, $portability, $value] as $weight) {
            $variance += pow($weight - $ideal, 2);
        }
        $fairness = max(0, 100 - ($variance / 10)); // Simplified fairness calculation
        
        // Deactivate old weights
        $deactivate_query = "UPDATE ai_weightage SET is_active = 0 WHERE persona_id = ?";
        $stmt = mysqli_prepare($conn, $deactivate_query);
        mysqli_stmt_bind_param($stmt, 'i', $persona_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Insert new weight configuration
        $insert_query = "INSERT INTO ai_weightage (persona_id, cpu_weight, gpu_weight, battery_weight, display_weight, portability_weight, value_weight, total_weight, fairness_score) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, 'idddddddd', $persona_id, $cpu, $gpu, $battery, $display, $portability, $value, $total, $fairness);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Configuration saved successfully', 'fairness_score' => round($fairness, 1)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving configuration']);
        }
        mysqli_stmt_close($stmt);
        exit;
    }
    
    if ($action === 'reset') {
        $persona_id = intval($_POST['persona_id']);
        // Reset to default weights (equal distribution)
        $default_weight = 16.67;
        $total = 100;
        $fairness = 100;
        
        // Deactivate old weights
        $deactivate_query = "UPDATE ai_weightage SET is_active = 0 WHERE persona_id = ?";
        $stmt = mysqli_prepare($conn, $deactivate_query);
        mysqli_stmt_bind_param($stmt, 'i', $persona_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Insert default configuration
        $insert_query = "INSERT INTO ai_weightage (persona_id, cpu_weight, gpu_weight, battery_weight, display_weight, portability_weight, value_weight, total_weight, fairness_score) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, 'idddddddd', $persona_id, $default_weight, $default_weight, $default_weight, $default_weight, $default_weight, $default_weight, $total, $fairness);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Reset to default configuration']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error resetting configuration']);
        }
        mysqli_stmt_close($stmt);
        exit;
    }
}

// Fetch weightage history for selected persona
$history_data = [];
if ($selected_persona_id) {
    $history_query = "SELECT * FROM ai_weightage WHERE persona_id = ? ORDER BY created_at DESC LIMIT 5";
    $stmt = mysqli_prepare($conn, $history_query);
    mysqli_stmt_bind_param($stmt, 'i', $selected_persona_id);
    mysqli_stmt_execute($stmt);
    $history_result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($history_result)) {
        $history_data[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// ===================== VIEW SECTION =====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Weightage Configuration - Smart Laptop Advisor Admin</title>
    
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="source/assets/css/bootstrap.css">
    <link rel="stylesheet" href="source/assets/vendors/iconly/bold.css">
    <link rel="stylesheet" href="source/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="source/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="source/assets/css/app.css">
    <link rel="shortcut icon" href="source/assets/images/favicon.svg" type="image/x-icon">
</head>

<body>
    <div id="app">
        <?php include 'includes/admin_header.php'; ?>

        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>AI Weightage Configuration</h3>
                <p class="text-subtitle text-muted">Configure fairness framework for recommendation algorithm</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item">AI Engine</li>
                        <li class="breadcrumb-item active" aria-current="page">Weightage Config</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Persona Selection -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">Select Persona to Configure</h5>
                            <p class="text-muted mb-0">Choose a user persona to adjust its recommendation weights</p>
                        </div>
                        <div class="col-md-6">
                            <select class="form-select form-select-lg" id="personaSelect" onchange="loadPersonaWeights()">
                                <option value="">Select a Persona...</option>
                                <?php 
                                if ($personas_result && mysqli_num_rows($personas_result) > 0):
                                    while ($persona = mysqli_fetch_assoc($personas_result)):
                                        $selected = ($persona['persona_id'] == $selected_persona_id) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $persona['persona_id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($persona['name']) . ' - ' . htmlspecialchars($persona['short_description']); ?>
                                </option>
                                <?php 
                                    endwhile;
                                endif;
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($persona_data && $current_weights): ?>
<div class="page-content" id="configurationPanel">
    <!-- Current Configuration Overview -->
    <section class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 id="currentPersonaTitle">Configuration for <?php echo htmlspecialchars($persona_data['name']); ?> Persona</h4>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-warning" onclick="resetToDefaults()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Reset to Defaults
                        </button>
                        <button class="btn btn-success" onclick="saveConfiguration()">
                            <i class="bi bi-check-circle me-2"></i>Save Configuration
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar bg-<?php echo htmlspecialchars($persona_data['color_theme']); ?> me-3" id="personaAvatar">
                                    <span class="avatar-content">
                                        <i class="<?php echo htmlspecialchars($persona_data['icon_class']); ?> text-white"></i>
                                    </span>
                                </div>
                                <div>
                                    <h6 class="mb-0" id="personaName"><?php echo htmlspecialchars($persona_data['name']); ?></h6>
                                    <p class="text-muted mb-0" id="personaDescription"><?php echo htmlspecialchars($persona_data['short_description']); ?></p>
                                </div>
                            </div>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar bg-success" id="fairnessBar" style="width: <?php echo round($current_weights['fairness_score']); ?>%"></div>
                            </div>
                            <small class="text-muted">Fairness Score: <span id="fairnessScore"><?php echo round($current_weights['fairness_score'], 1); ?>%</span> - 
                                <?php 
                                $score = $current_weights['fairness_score'];
                                if ($score >= 90) echo 'Excellent balance';
                                elseif ($score >= 75) echo 'Good balance';
                                elseif ($score >= 60) echo 'Moderate balance';
                                else echo 'Consider rebalancing';
                                ?>
                            </small>
                        </div>
                        <div class="col-md-4">
                            <div class="row text-center">
                                <div class="col-6">
                                    <h5 class="text-primary mb-0" id="totalRecommendations"><?php echo number_format($persona_data['total_recommendations']); ?></h5>
                                    <small class="text-muted">Total Feedback</small>
                                </div>
                                <div class="col-6">
                                    <h5 class="text-success mb-0" id="accuracyRate"><?php echo round($persona_data['accuracy_rate'], 1); ?>%</h5>
                                    <small class="text-muted">Satisfaction Score</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Weight Configuration Sliders -->
    <section class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Attribute Weightage Configuration</h4>
                    <p class="text-muted mb-0">Adjust the importance of each laptop attribute for this persona. Total must equal 100%.</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- CPU Performance -->
                        <div class="col-md-6 mb-4">
                            <div class="card weight-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-cpu text-primary me-2 fs-4"></i>
                                            <div>
                                                <h6 class="mb-0">CPU Performance</h6>
                                                <small class="text-muted">Processing power and speed</small>
                                            </div>
                                        </div>
                                        <div class="weight-value text-primary fs-5 fw-bold" id="cpuWeight"><?php echo $current_weights['cpu_weight']; ?>%</div>
                                    </div>
                                    <input type="range" class="form-range weight-slider" min="0" max="50" value="<?php echo $current_weights['cpu_weight']; ?>" id="cpuSlider" oninput="updateWeight('cpu', this.value)">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">0%</small>
                                        <small class="text-muted">50%</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- GPU Performance -->
                        <div class="col-md-6 mb-4">
                            <div class="card weight-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-display text-success me-2 fs-4"></i>
                                            <div>
                                                <h6 class="mb-0">GPU Performance</h6>
                                                <small class="text-muted">Graphics and gaming capability</small>
                                            </div>
                                        </div>
                                        <div class="weight-value text-success fs-5 fw-bold" id="gpuWeight"><?php echo $current_weights['gpu_weight']; ?>%</div>
                                    </div>
                                    <input type="range" class="form-range weight-slider" min="0" max="50" value="<?php echo $current_weights['gpu_weight']; ?>" id="gpuSlider" oninput="updateWeight('gpu', this.value)">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">0%</small>
                                        <small class="text-muted">50%</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Battery Life -->
                        <div class="col-md-6 mb-4">
                            <div class="card weight-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-battery-charging text-warning me-2 fs-4"></i>
                                            <div>
                                                <h6 class="mb-0">Battery Life</h6>
                                                <small class="text-muted">Usage duration per charge</small>
                                            </div>
                                        </div>
                                        <div class="weight-value text-warning fs-5 fw-bold" id="batteryWeight"><?php echo $current_weights['battery_weight']; ?>%</div>
                                    </div>
                                    <input type="range" class="form-range weight-slider" min="0" max="50" value="<?php echo $current_weights['battery_weight']; ?>" id="batterySlider" oninput="updateWeight('battery', this.value)">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">0%</small>
                                        <small class="text-muted">50%</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Display Quality -->
                        <div class="col-md-6 mb-4">
                            <div class="card weight-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-tv text-info me-2 fs-4"></i>
                                            <div>
                                                <h6 class="mb-0">Display Quality</h6>
                                                <small class="text-muted">Screen resolution and color accuracy</small>
                                            </div>
                                        </div>
                                        <div class="weight-value text-info fs-5 fw-bold" id="displayWeight"><?php echo $current_weights['display_weight']; ?>%</div>
                                    </div>
                                    <input type="range" class="form-range weight-slider" min="0" max="50" value="<?php echo $current_weights['display_weight']; ?>" id="displaySlider" oninput="updateWeight('display', this.value)">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">0%</small>
                                        <small class="text-muted">50%</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Portability -->
                        <div class="col-md-6 mb-4">
                            <div class="card weight-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-backpack text-secondary me-2 fs-4"></i>
                                            <div>
                                                <h6 class="mb-0">Portability</h6>
                                                <small class="text-muted">Weight and size considerations</small>
                                            </div>
                                        </div>
                                        <div class="weight-value text-secondary fs-5 fw-bold" id="portabilityWeight"><?php echo $current_weights['portability_weight']; ?>%</div>
                                    </div>
                                    <input type="range" class="form-range weight-slider" min="0" max="50" value="<?php echo $current_weights['portability_weight']; ?>" id="portabilitySlider" oninput="updateWeight('portability', this.value)">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">0%</small>
                                        <small class="text-muted">50%</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Value for Money -->
                        <div class="col-md-6 mb-4">
                            <div class="card weight-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-currency-dollar text-danger me-2 fs-4"></i>
                                            <div>
                                                <h6 class="mb-0">Value for Money</h6>
                                                <small class="text-muted">Price-to-performance ratio</small>
                                            </div>
                                        </div>
                                        <div class="weight-value text-danger fs-5 fw-bold" id="valueWeight"><?php echo $current_weights['value_weight']; ?>%</div>
                                    </div>
                                    <input type="range" class="form-range weight-slider" min="0" max="50" value="<?php echo $current_weights['value_weight']; ?>" id="valueSlider" oninput="updateWeight('value', this.value)">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">0%</small>
                                        <small class="text-muted">50%</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Weight Indicator -->
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info d-flex align-items-center" id="totalWeightAlert">
                                <i class="bi bi-info-circle me-2"></i>
                                <div>
                                    <strong>Total Weight: <span id="totalWeight">100%</span></strong> - 
                                    <span id="weightMessage">Perfect! All weights sum to 100%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Configuration History -->
    <?php if (count($history_data) > 0): ?>
    <section class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Configuration History</h4>
                    <p class="text-muted mb-0">Recent changes to this persona's weightage configuration</p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>CPU</th>
                                    <th>GPU</th>
                                    <th>Battery</th>
                                    <th>Display</th>
                                    <th>Portability</th>
                                    <th>Value</th>
                                    <th>Fairness Score</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history_data as $history): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($history['created_at'])); ?></td>
                                    <td><?php echo $history['cpu_weight']; ?>%</td>
                                    <td><?php echo $history['gpu_weight']; ?>%</td>
                                    <td><?php echo $history['battery_weight']; ?>%</td>
                                    <td><?php echo $history['display_weight']; ?>%</td>
                                    <td><?php echo $history['portability_weight']; ?>%</td>
                                    <td><?php echo $history['value_weight']; ?>%</td>
                                    <td><?php echo round($history['fairness_score'], 1); ?>%</td>
                                    <td>
                                        <?php if ($history['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Archived</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="page-content">
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>Please select a persona to configure its AI weightage settings.
    </div>
</div>
<?php endif; ?>

<script>
    let currentWeights = {
        cpu: <?php echo $current_weights ? $current_weights['cpu_weight'] : 16.67; ?>,
        gpu: <?php echo $current_weights ? $current_weights['gpu_weight'] : 16.67; ?>,
        battery: <?php echo $current_weights ? $current_weights['battery_weight'] : 16.67; ?>,
        display: <?php echo $current_weights ? $current_weights['display_weight'] : 16.67; ?>,
        portability: <?php echo $current_weights ? $current_weights['portability_weight'] : 16.67; ?>,
        value: <?php echo $current_weights ? $current_weights['value_weight'] : 16.67; ?>
    };

    function updateWeight(attribute, value) {
        currentWeights[attribute] = parseFloat(value);
        document.getElementById(attribute + 'Weight').textContent = value + '%';
        
        // Calculate total
        const total = Object.values(currentWeights).reduce((a, b) => a + b, 0);
        document.getElementById('totalWeight').textContent = Math.round(total) + '%';
        
        // Update alert based on total
        const alert = document.getElementById('totalWeightAlert');
        const message = document.getElementById('weightMessage');
        
        if (Math.abs(total - 100) < 0.5) {
            alert.className = 'alert alert-success d-flex align-items-center';
            message.textContent = 'Perfect! All weights sum to 100%';
        } else if (total < 100) {
            alert.className = 'alert alert-warning d-flex align-items-center';
            message.textContent = 'Total is less than 100%. Increase weights.';
        } else {
            alert.className = 'alert alert-danger d-flex align-items-center';
            message.textContent = 'Total exceeds 100%. Reduce weights.';
        }
        
        // Calculate and update fairness score
        updateFairnessScore();
    }

    function updateFairnessScore() {
        const ideal = 100 / 6;
        let variance = 0;
        Object.values(currentWeights).forEach(weight => {
            variance += Math.pow(weight - ideal, 2);
        });
        const fairness = Math.max(0, 100 - (variance / 10));
        
        document.getElementById('fairnessScore').textContent = Math.round(fairness) + '%';
        document.getElementById('fairnessBar').style.width = Math.round(fairness) + '%';
        
        // Update bar color
        const bar = document.getElementById('fairnessBar');
        if (fairness >= 90) bar.className = 'progress-bar bg-success';
        else if (fairness >= 75) bar.className = 'progress-bar bg-info';
        else if (fairness >= 60) bar.className = 'progress-bar bg-warning';
        else bar.className = 'progress-bar bg-danger';
    }

    function saveConfiguration() {
        const total = Object.values(currentWeights).reduce((a, b) => a + b, 0);
        if (Math.abs(total - 100) > 0.5) {
            alert('Total weight must equal 100%. Currently: ' + Math.round(total) + '%');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('persona_id', <?php echo $selected_persona_id; ?>);
        formData.append('cpu_weight', currentWeights.cpu);
        formData.append('gpu_weight', currentWeights.gpu);
        formData.append('battery_weight', currentWeights.battery);
        formData.append('display_weight', currentWeights.display);
        formData.append('portability_weight', currentWeights.portability);
        formData.append('value_weight', currentWeights.value);
        
        fetch('admin_ai_weightage.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the configuration.');
        });
    }

    function resetToDefaults() {
        if (confirm('Are you sure you want to reset to default weights? This will set all attributes to equal values.')) {
            const formData = new FormData();
            formData.append('action', 'reset');
            formData.append('persona_id', <?php echo $selected_persona_id; ?>);
            
            fetch('admin_ai_weightage.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while resetting the configuration.');
            });
        }
    }

    function loadPersonaWeights() {
        const personaId = document.getElementById('personaSelect').value;
        if (personaId) {
            window.location.href = 'admin_ai_weightage.php?persona=' + personaId;
        }
    }
</script>

<?php
include 'includes/admin_footer.php';
?>
        </div>
    </div>
</body>
</html>
