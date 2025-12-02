<?php
/**
 * Intent Actions API
 * Handles CRUD operations for intent management
 */

header('Content-Type: application/json');

require_once '../includes/db_connect.php';

try {
    $action = $_REQUEST['action'] ?? '';
    
    switch ($action) {
        case 'get':
            // Get single intent with training phrases and responses
            $intentId = $_GET['intent_id'] ?? 0;
            
            // Get intent details
            $stmt = $conn->prepare("SELECT * FROM intents WHERE intent_id = ?");
            $stmt->bind_param("i", $intentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Intent not found');
            }
            
            $intent = $result->fetch_assoc();
            $stmt->close();
            
            // Get training phrases
            $stmt = $conn->prepare("SELECT phrase_text FROM training_phrases WHERE intent_id = ? AND is_active = 1");
            $stmt->bind_param("i", $intentId);
            $stmt->execute();
            $phrasesResult = $stmt->get_result();
            
            $trainingPhrases = [];
            while ($row = $phrasesResult->fetch_assoc()) {
                $trainingPhrases[] = $row['phrase_text'];
            }
            $stmt->close();
            
            // Get default response
            $stmt = $conn->prepare("SELECT response_text FROM intent_responses WHERE intent_id = ? AND is_default = 1");
            $stmt->bind_param("i", $intentId);
            $stmt->execute();
            $responseResult = $stmt->get_result();
            
            $defaultResponse = '';
            if ($responseResult->num_rows > 0) {
                $defaultResponse = $responseResult->fetch_assoc()['response_text'];
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'intent' => $intent,
                'training_phrases' => $trainingPhrases,
                'default_response' => $defaultResponse
            ]);
            break;
            
        case 'create':
            // Create new intent
            $intentName = trim($_POST['intent_name'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = $_POST['category'] ?? 'support';
            $priority = (int)($_POST['priority'] ?? 5);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $trainingPhrases = trim($_POST['training_phrases'] ?? '');
            $defaultResponse = trim($_POST['default_response'] ?? '');
            
            if (empty($intentName) || empty($displayName) || empty($defaultResponse)) {
                throw new Exception('Required fields missing');
            }
            
            // Insert intent
            $stmt = $conn->prepare("INSERT INTO intents (intent_name, display_name, description, category, priority, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("ssssii", $intentName, $displayName, $description, $category, $priority, $isActive);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create intent: ' . $stmt->error);
            }
            
            $intentId = $conn->insert_id;
            $stmt->close();
            
            // Insert training phrases
            if (!empty($trainingPhrases)) {
                $phrases = array_filter(array_map('trim', explode("\n", $trainingPhrases)));
                $stmt = $conn->prepare("INSERT INTO training_phrases (intent_id, phrase_text, is_active) VALUES (?, ?, 1)");
                
                foreach ($phrases as $phrase) {
                    if (!empty($phrase)) {
                        $stmt->bind_param("is", $intentId, $phrase);
                        $stmt->execute();
                    }
                }
                $stmt->close();
            }
            
            // Insert default response
            if (!empty($defaultResponse)) {
                $stmt = $conn->prepare("INSERT INTO intent_responses (intent_id, response_text, is_default, is_active) VALUES (?, ?, 1, 1)");
                $stmt->bind_param("is", $intentId, $defaultResponse);
                $stmt->execute();
                $stmt->close();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Intent created successfully',
                'intent_id' => $intentId
            ]);
            break;
            
        case 'update':
            // Update existing intent
            $intentId = (int)($_POST['intent_id'] ?? 0);
            $intentName = trim($_POST['intent_name'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = $_POST['category'] ?? 'support';
            $priority = (int)($_POST['priority'] ?? 5);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $trainingPhrases = trim($_POST['training_phrases'] ?? '');
            $defaultResponse = trim($_POST['default_response'] ?? '');
            
            if ($intentId === 0 || empty($intentName) || empty($displayName)) {
                throw new Exception('Required fields missing');
            }
            
            // Update intent
            $stmt = $conn->prepare("UPDATE intents SET intent_name = ?, display_name = ?, description = ?, category = ?, priority = ?, is_active = ?, updated_at = NOW() WHERE intent_id = ?");
            $stmt->bind_param("ssssiii", $intentName, $displayName, $description, $category, $priority, $isActive, $intentId);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update intent: ' . $stmt->error);
            }
            $stmt->close();
            
            // Update training phrases - delete old and insert new
            $stmt = $conn->prepare("DELETE FROM training_phrases WHERE intent_id = ?");
            $stmt->bind_param("i", $intentId);
            $stmt->execute();
            $stmt->close();
            
            if (!empty($trainingPhrases)) {
                $phrases = array_filter(array_map('trim', explode("\n", $trainingPhrases)));
                $stmt = $conn->prepare("INSERT INTO training_phrases (intent_id, phrase_text, is_active) VALUES (?, ?, 1)");
                
                foreach ($phrases as $phrase) {
                    if (!empty($phrase)) {
                        $stmt->bind_param("is", $intentId, $phrase);
                        $stmt->execute();
                    }
                }
                $stmt->close();
            }
            
            // Update default response
            if (!empty($defaultResponse)) {
                // Check if default response exists
                $stmt = $conn->prepare("SELECT response_id FROM intent_responses WHERE intent_id = ? AND is_default = 1");
                $stmt->bind_param("i", $intentId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing
                    $responseId = $result->fetch_assoc()['response_id'];
                    $stmt->close();
                    
                    $stmt = $conn->prepare("UPDATE intent_responses SET response_text = ? WHERE response_id = ?");
                    $stmt->bind_param("si", $defaultResponse, $responseId);
                    $stmt->execute();
                } else {
                    // Insert new
                    $stmt->close();
                    $stmt = $conn->prepare("INSERT INTO intent_responses (intent_id, response_text, is_default, is_active) VALUES (?, ?, 1, 1)");
                    $stmt->bind_param("is", $intentId, $defaultResponse);
                    $stmt->execute();
                }
                $stmt->close();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Intent updated successfully'
            ]);
            break;
            
        case 'delete':
            // Delete intent and related data
            $intentId = (int)($_POST['intent_id'] ?? 0);
            
            if ($intentId === 0) {
                throw new Exception('Invalid intent ID');
            }
            
            // Delete training phrases
            $stmt = $conn->prepare("DELETE FROM training_phrases WHERE intent_id = ?");
            $stmt->bind_param("i", $intentId);
            $stmt->execute();
            $stmt->close();
            
            // Delete responses
            $stmt = $conn->prepare("DELETE FROM intent_responses WHERE intent_id = ?");
            $stmt->bind_param("i", $intentId);
            $stmt->execute();
            $stmt->close();
            
            // Delete intent
            $stmt = $conn->prepare("DELETE FROM intents WHERE intent_id = ?");
            $stmt->bind_param("i", $intentId);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to delete intent: ' . $stmt->error);
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Intent deleted successfully'
            ]);
            break;
            
        case 'duplicate':
            // Duplicate an intent
            $intentId = (int)($_POST['intent_id'] ?? 0);
            
            if ($intentId === 0) {
                throw new Exception('Invalid intent ID');
            }
            
            // Get original intent
            $stmt = $conn->prepare("SELECT * FROM intents WHERE intent_id = ?");
            $stmt->bind_param("i", $intentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Intent not found');
            }
            
            $original = $result->fetch_assoc();
            $stmt->close();
            
            // Create new intent with "_copy" suffix
            $newIntentName = $original['intent_name'] . '_copy';
            $newDisplayName = $original['display_name'] . ' (Copy)';
            
            $stmt = $conn->prepare("INSERT INTO intents (intent_name, display_name, description, category, priority, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())");
            $stmt->bind_param("ssssi", $newIntentName, $newDisplayName, $original['description'], $original['category'], $original['priority']);
            $stmt->execute();
            
            $newIntentId = $conn->insert_id;
            $stmt->close();
            
            // Copy training phrases
            $stmt = $conn->prepare("INSERT INTO training_phrases (intent_id, phrase_text, is_active) SELECT ?, phrase_text, is_active FROM training_phrases WHERE intent_id = ?");
            $stmt->bind_param("ii", $newIntentId, $intentId);
            $stmt->execute();
            $stmt->close();
            
            // Copy responses
            $stmt = $conn->prepare("INSERT INTO intent_responses (intent_id, response_text, is_default, is_active) SELECT ?, response_text, is_default, is_active FROM intent_responses WHERE intent_id = ?");
            $stmt->bind_param("ii", $newIntentId, $intentId);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Intent duplicated successfully',
                'new_intent_id' => $newIntentId
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
