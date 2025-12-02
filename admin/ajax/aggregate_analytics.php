<?php
/**
 * Chatbot Analytics Aggregation Script
 * Aggregates real data from conversations, messages, and intents
 * into the chatbot_analytics summary table
 * 
 * Run this script daily via cron job or manually to update analytics
 */

// Include database connection
require_once '../includes/db_connect.php';

// Set execution time limit for large datasets
set_time_limit(300); // 5 minutes

// Get date to aggregate (default: yesterday, or specify via GET parameter)
$targetDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d', strtotime('-1 day'));

echo "=== Chatbot Analytics Aggregation ===\n";
echo "Aggregating data for: {$targetDate}\n\n";

try {
    // ========================================
    // 1. CONVERSATION METRICS
    // ========================================
    echo "1. Calculating conversation metrics...\n";
    
    $conv_query = "SELECT 
        COUNT(DISTINCT conversation_id) as total_conversations,
        COUNT(DISTINCT CASE WHEN sentiment = 'positive' THEN conversation_id END) as positive_convs,
        COUNT(DISTINCT CASE WHEN sentiment = 'neutral' THEN conversation_id END) as neutral_convs,
        COUNT(DISTINCT CASE WHEN sentiment = 'negative' THEN conversation_id END) as negative_convs,
        AVG(satisfaction_rating) as avg_satisfaction
    FROM conversations
    WHERE DATE(created_at) = ?";
    
    $stmt = $conn->prepare($conv_query);
    $stmt->bind_param("s", $targetDate);
    $stmt->execute();
    $conv_metrics = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $total_convs = $conv_metrics['total_conversations'] ?? 0;
    
    // Calculate sentiment percentages
    $positive_pct = $total_convs > 0 ? ($conv_metrics['positive_convs'] / $total_convs) * 100 : 0;
    $neutral_pct = $total_convs > 0 ? ($conv_metrics['neutral_convs'] / $total_convs) * 100 : 0;
    $negative_pct = $total_convs > 0 ? ($conv_metrics['negative_convs'] / $total_convs) * 100 : 0;
    
    echo "   - Total conversations: {$total_convs}\n";
    echo "   - Positive: {$conv_metrics['positive_convs']} ({$positive_pct}%)\n";
    echo "   - Neutral: {$conv_metrics['neutral_convs']} ({$neutral_pct}%)\n";
    echo "   - Negative: {$conv_metrics['negative_convs']} ({$negative_pct}%)\n\n";
    
    // ========================================
    // 2. MESSAGE METRICS
    // ========================================
    echo "2. Calculating message metrics...\n";
    
    $msg_query = "SELECT 
        COUNT(*) as total_messages,
        AVG(response_time_ms) as avg_response_time,
        COUNT(DISTINCT cm.conversation_id) as convs_with_messages
    FROM conversation_messages cm
    JOIN conversations c ON cm.conversation_id = c.conversation_id
    WHERE DATE(c.created_at) = ? AND cm.message_type = 'bot'";
    
    $stmt = $conn->prepare($msg_query);
    $stmt->bind_param("s", $targetDate);
    $stmt->execute();
    $msg_metrics = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $total_messages = $msg_metrics['total_messages'] ?? 0;
    $avg_response_time = round($msg_metrics['avg_response_time'] ?? 0);
    $avg_messages_per_session = $total_convs > 0 ? round($total_messages / $total_convs, 1) : 0;
    
    echo "   - Total messages: {$total_messages}\n";
    echo "   - Avg response time: {$avg_response_time}ms\n";
    echo "   - Avg messages/session: {$avg_messages_per_session}\n\n";
    
    // ========================================
    // 3. INTENT METRICS
    // ========================================
    echo "3. Calculating intent metrics...\n";
    
    // Get intent accuracy from intents table
    $intent_query = "SELECT 
        SUM(usage_count) as total_usage,
        SUM(success_count) as total_success
    FROM intents
    WHERE last_used_at >= ? AND last_used_at < DATE_ADD(?, INTERVAL 1 DAY)";
    
    $stmt = $conn->prepare($intent_query);
    $stmt->bind_param("ss", $targetDate, $targetDate);
    $stmt->execute();
    $intent_metrics = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $intent_accuracy = 0;
    if ($intent_metrics['total_usage'] > 0) {
        $intent_accuracy = ($intent_metrics['total_success'] / $intent_metrics['total_usage']) * 100;
    }
    
    echo "   - Intent accuracy: " . round($intent_accuracy, 1) . "%\n\n";
    
    // ========================================
    // 4. UNRECOGNIZED QUERIES
    // ========================================
    echo "4. Counting unrecognized queries...\n";
    
    $unrec_query = "SELECT COUNT(*) as unrecognized_count
    FROM conversation_messages cm
    JOIN conversations c ON cm.conversation_id = c.conversation_id
    WHERE DATE(c.created_at) = ? 
    AND cm.message_type = 'user'
    AND cm.intent_detected IS NULL";
    
    $stmt = $conn->prepare($unrec_query);
    $stmt->bind_param("s", $targetDate);
    $stmt->execute();
    $unrec_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $unrecognized_count = $unrec_result['unrecognized_count'] ?? 0;
    
    echo "   - Unrecognized queries: {$unrecognized_count}\n\n";
    
    // ========================================
    // 5. CALCULATE RESOLUTION & SATISFACTION
    // ========================================
    echo "5. Calculating resolution rate...\n";
    
    // Estimate resolution rate based on conversation length and sentiment
    // Conversations with >3 messages and positive/neutral sentiment = resolved
    $resolution_query = "SELECT 
        COUNT(DISTINCT CASE 
            WHEN msg_count > 3 AND (sentiment IN ('positive', 'neutral')) 
            THEN c.conversation_id 
        END) as resolved_count
    FROM conversations c
    LEFT JOIN (
        SELECT conversation_id, COUNT(*) as msg_count
        FROM conversation_messages
        GROUP BY conversation_id
    ) msgs ON c.conversation_id = msgs.conversation_id
    WHERE DATE(c.created_at) = ?";
    
    $stmt = $conn->prepare($resolution_query);
    $stmt->bind_param("s", $targetDate);
    $stmt->execute();
    $resolution_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $resolved_count = $resolution_result['resolved_count'] ?? 0;
    $resolution_rate = $total_convs > 0 ? ($resolved_count / $total_convs) * 100 : 0;
    
    echo "   - Resolution rate: " . round($resolution_rate, 1) . "%\n\n";
    
    // ========================================
    // 6. INSERT OR UPDATE ANALYTICS RECORD
    // ========================================
    echo "6. Saving to chatbot_analytics table...\n";
    
    // Check if record exists for this date
    $check_query = "SELECT analytics_id FROM chatbot_analytics WHERE date = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $targetDate);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $satisfaction_score = $conv_metrics['avg_satisfaction'] ?? 0;
    $fallback_count = 0; // Can be calculated if you track fallbacks
    
    if ($exists) {
        // Update existing record
        $update_query = "UPDATE chatbot_analytics SET
            total_conversations = ?,
            total_messages = ?,
            avg_messages_per_session = ?,
            avg_response_time_ms = ?,
            positive_sentiment_pct = ?,
            neutral_sentiment_pct = ?,
            negative_sentiment_pct = ?,
            intent_accuracy = ?,
            resolution_rate = ?,
            satisfaction_score = ?,
            unrecognized_intent_count = ?,
            fallback_count = ?,
            updated_at = NOW()
        WHERE date = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("iiiddddddiis", 
            $total_convs,
            $total_messages,
            $avg_messages_per_session,
            $avg_response_time,
            $positive_pct,
            $neutral_pct,
            $negative_pct,
            $intent_accuracy,
            $resolution_rate,
            $satisfaction_score,
            $unrecognized_count,
            $fallback_count,
            $targetDate
        );
        $stmt->execute();
        $stmt->close();
        
        echo "   ✓ Updated existing record for {$targetDate}\n";
    } else {
        // Insert new record
        $insert_query = "INSERT INTO chatbot_analytics (
            date, total_conversations, total_messages, avg_messages_per_session,
            avg_response_time_ms, positive_sentiment_pct, neutral_sentiment_pct,
            negative_sentiment_pct, intent_accuracy, resolution_rate,
            satisfaction_score, unrecognized_intent_count, fallback_count,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("siiddddddddii",
            $targetDate,
            $total_convs,
            $total_messages,
            $avg_messages_per_session,
            $avg_response_time,
            $positive_pct,
            $neutral_pct,
            $negative_pct,
            $intent_accuracy,
            $resolution_rate,
            $satisfaction_score,
            $unrecognized_count,
            $fallback_count
        );
        $stmt->execute();
        $stmt->close();
        
        echo "   ✓ Created new record for {$targetDate}\n";
    }
    
    echo "\n=== Aggregation Complete! ===\n";
    echo "Analytics for {$targetDate} have been updated.\n";
    echo "You can now view the data in admin_chatbot_analytics.php\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    http_response_code(500);
}

$conn->close();
?>
