<?php
/**
 * AJAX Handler: Get Conversation Details
 * Fetches full conversation details and message history with enhanced formatting
 */

header('Content-Type: application/json');

require_once '../includes/db_connect.php';

try {
    // Validate input
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid conversation ID');
    }
    
    $conversationId = (int)$_GET['id'];
    
    // Fetch conversation details
    $conv_query = "SELECT 
        c.conversation_id,
        c.session_id,
        c.source,
        c.started_at,
        c.ended_at,
        c.duration_seconds,
        c.message_count,
        c.user_message_count,
        c.bot_message_count,
        c.sentiment,
        c.sentiment_score,
        c.outcome,
        c.satisfaction_rating,
        c.user_ip,
        COALESCE(u.full_name, 'Anonymous User') as user_name,
        COALESCE(u.email, c.user_ip) as user_identifier
    FROM conversations c
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE c.conversation_id = ?";
    
    $stmt = $conn->prepare($conv_query);
    $stmt->bind_param('i', $conversationId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Conversation not found');
    }
    
    $conversation = $result->fetch_assoc();
    $stmt->close();
    
    // Format duration
    $minutes = floor($conversation['duration_seconds'] / 60);
    $seconds = $conversation['duration_seconds'] % 60;
    $conversation['duration_formatted'] = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";
    
    // Format started_at
    $conversation['started_formatted'] = date('M j, Y g:i A', strtotime($conversation['started_at']));
    
    // Fetch all messages for this conversation
    $messages_query = "SELECT 
        message_id,
        message_type,
        message_content,
        intent_detected,
        intent_confidence,
        timestamp,
        response_time_ms
    FROM conversation_messages
    WHERE conversation_id = ?
    ORDER BY timestamp ASC";
    
    $stmt = $conn->prepare($messages_query);
    $stmt->bind_param('i', $conversationId);
    $stmt->execute();
    $messages_result = $stmt->get_result();
    
    $messages = [];
    while ($row = $messages_result->fetch_assoc()) {
        // Format timestamp for better readability
        $time_obj = new DateTime($row['timestamp']);
        $formatted_time = $time_obj->format('g:i A'); // e.g., "3:45 PM"
        $formatted_date = $time_obj->format('M j, Y'); // e.g., "Nov 24, 2025"
        
        // Format response time
        $response_time_display = null;
        if ($row['response_time_ms'] !== null) {
            $ms = $row['response_time_ms'];
            if ($ms >= 1000) {
                $response_time_display = round($ms / 1000, 1) . 's';
            } else {
                $response_time_display = $ms . 'ms';
            }
        }
        
        $messages[] = [
            'message_id' => $row['message_id'],
            'message_type' => $row['message_type'],
            'message_content' => htmlspecialchars($row['message_content'], ENT_QUOTES, 'UTF-8'),
            'intent_detected' => $row['intent_detected'],
            'intent_confidence' => $row['intent_confidence'] ? round($row['intent_confidence'] * 100, 1) : null,
            'timestamp' => $formatted_date . ' at ' . $formatted_time,
            'time_only' => $formatted_time,
            'response_time_ms' => $row['response_time_ms'],
            'response_time_display' => $response_time_display
        ];
    }
    $stmt->close();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'conversation' => $conversation,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
