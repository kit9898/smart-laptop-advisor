<?php
/**
 * Chat Start API Endpoint
 * Generates a unique session ID and initializes a new conversation
 */

header('Content-Type: application/json');

require_once '../LaptopAdvisor/includes/db_connect.php';
require_once '../LaptopAdvisor/includes/config.php';

try {
    // Generate unique session ID
    $sessionId = 'chat_' . uniqid() . '_' . bin2hex(random_bytes(8));
    
    // Get user IP address
    $userIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Get user ID from session if logged in
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Insert new conversation into database
    $stmt = $conn->prepare("INSERT INTO conversations (session_id, user_id, user_ip, source, started_at) VALUES (?, ?, ?, 'web', NOW())");
    
    if ($userId) {
        $stmt->bind_param("sis", $sessionId, $userId, $userIp);
    } else {
        $null = null;
        $stmt->bind_param("sis", $sessionId, $null, $userIp);
    }
    
    if ($stmt->execute()) {
        $conversationId = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'conversation_id' => $conversationId,
            'message' => 'Chat session created successfully'
        ]);
    } else {
        throw new Exception('Failed to create conversation: ' . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
