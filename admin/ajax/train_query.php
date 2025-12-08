<?php
/**
 * Train Query API
 * Adds unrecognized queries as training phrases to intents
 * Part of Smart Laptop Advisor - Admin Module
 */

header('Content-Type: application/json');

require_once '../../LaptopAdvisor/includes/db_connect.php';

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        
        // ============================================
        // GET ALL ACTIVE INTENTS (for dropdown)
        // ============================================
        case 'get_intents':
            $query = "SELECT intent_id, intent_name, display_name, description, 
                             usage_count, is_active
                      FROM intents 
                      WHERE is_active = 1 
                      ORDER BY display_name ASC";
            
            $result = $conn->query($query);
            $intents = [];
            
            while ($row = $result->fetch_assoc()) {
                $intents[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'intents' => $intents
            ]);
            break;
            
        // ============================================
        // ADD TRAINING PHRASE TO EXISTING INTENT
        // ============================================
        case 'add_phrase':
            $intentId = (int)($_POST['intent_id'] ?? 0);
            $phrase = trim($_POST['phrase'] ?? '');
            
            if ($intentId === 0) {
                throw new Exception('Invalid intent ID');
            }
            
            if (empty($phrase)) {
                throw new Exception('Training phrase cannot be empty');
            }
            
            // Check if phrase already exists for this intent
            $checkStmt = $conn->prepare("SELECT phrase_id FROM training_phrases WHERE intent_id = ? AND phrase_text = ?");
            $checkStmt->bind_param("is", $intentId, $phrase);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                throw new Exception('This phrase already exists for this intent');
            }
            $checkStmt->close();
            
            // Insert new training phrase
            $stmt = $conn->prepare("INSERT INTO training_phrases (intent_id, phrase_text, is_active, created_at) VALUES (?, ?, 1, NOW())");
            $stmt->bind_param("is", $intentId, $phrase);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to add training phrase: ' . $stmt->error);
            }
            
            $phraseId = $conn->insert_id;
            $stmt->close();
            
            // Get intent name for response
            $intentStmt = $conn->prepare("SELECT display_name FROM intents WHERE intent_id = ?");
            $intentStmt->bind_param("i", $intentId);
            $intentStmt->execute();
            $intentResult = $intentStmt->get_result();
            $intentName = $intentResult->fetch_assoc()['display_name'] ?? 'Unknown';
            $intentStmt->close();
            
            // Log the training action
            $logQuery = "INSERT INTO admin_activity_log (action_type, action_description, admin_id, created_at) 
                         VALUES ('INTENT_TRAINING', ?, NULL, NOW())";
            $logDesc = "Added training phrase '$phrase' to intent '$intentName'";
            $logStmt = $conn->prepare($logQuery);
            if ($logStmt) {
                $logStmt->bind_param("s", $logDesc);
                $logStmt->execute();
                $logStmt->close();
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Training phrase added to '$intentName'",
                'phrase_id' => $phraseId,
                'intent_name' => $intentName
            ]);
            break;
            
        // ============================================
        // CREATE NEW INTENT WITH TRAINING PHRASE
        // ============================================
        case 'create_intent':
            $intentName = trim($_POST['intent_name'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $phrase = trim($_POST['phrase'] ?? '');
            $defaultResponse = trim($_POST['default_response'] ?? '');
            
            if (empty($intentName)) {
                throw new Exception('Intent name is required');
            }
            
            if (empty($displayName)) {
                $displayName = ucwords(str_replace('_', ' ', $intentName));
            }
            
            if (empty($phrase)) {
                throw new Exception('At least one training phrase is required');
            }
            
            // Check if intent name already exists
            $checkStmt = $conn->prepare("SELECT intent_id FROM intents WHERE intent_name = ?");
            $checkStmt->bind_param("s", $intentName);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows > 0) {
                throw new Exception('An intent with this name already exists');
            }
            $checkStmt->close();
            
            // Create new intent
            $stmt = $conn->prepare("INSERT INTO intents (intent_name, display_name, description, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
            $stmt->bind_param("sss", $intentName, $displayName, $description);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create intent: ' . $stmt->error);
            }
            
            $newIntentId = $conn->insert_id;
            $stmt->close();
            
            // Add training phrase
            $phraseStmt = $conn->prepare("INSERT INTO training_phrases (intent_id, phrase_text, is_active, created_at) VALUES (?, ?, 1, NOW())");
            $phraseStmt->bind_param("is", $newIntentId, $phrase);
            $phraseStmt->execute();
            $phraseStmt->close();
            
            // Add default response if provided
            if (!empty($defaultResponse)) {
                $respStmt = $conn->prepare("INSERT INTO intent_responses (intent_id, response_text, is_default, created_at) VALUES (?, ?, 1, NOW())");
                $respStmt->bind_param("is", $newIntentId, $defaultResponse);
                $respStmt->execute();
                $respStmt->close();
            }
            
            echo json_encode([
                'success' => true,
                'message' => "New intent '$displayName' created successfully",
                'intent_id' => $newIntentId
            ]);
            break;
            
        // ============================================
        // GET PHRASE COUNT FOR INTENT (for stats)
        // ============================================
        case 'get_phrase_count':
            $intentId = (int)($_GET['intent_id'] ?? 0);
            
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM training_phrases WHERE intent_id = ? AND is_active = 1");
            $stmt->bind_param("i", $intentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['count'];
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
