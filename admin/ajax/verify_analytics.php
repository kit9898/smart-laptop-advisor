<?php
/**
 * Analytics Verification Tool
 * Shows database state before/after aggregation to verify it's working
 */

require_once '../includes/db_connect.php';

$targetDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d', strtotime('-1 day'));

?>
<!DOCTYPE html>
<html>
<head>
    <title>Analytics Verification</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        tr:hover { background: #f8f9fa; }
        .section { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .btn { background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        .btn:hover { background: #218838; }
        .btn-primary { background: #007bff; }
        .btn-primary:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .status { padding: 5px 10px; border-radius: 3px; font-weight: bold; }
        .status-yes { background: #d4edda; color: #155724; }
        .status-no { background: #f8d7da; color: #721c24; }
        .metric { font-size: 24px; font-weight: bold; color: #007bff; }
        .label { color: #666; font-size: 14px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .card { background: #f8f9fa; padding: 20px; border-radius: 5px; text-align: center; }
        .code { background: #272822; color: #f8f8f2; padding: 15px; border-radius: 5px; font-family: monospace; margin: 10px 0; overflow-x: auto; }
        .alert { padding: 15px; border-radius: 5px; margin: 20px 0; }
        .alert-info { background: #d1ecf1; border-left: 4px solid #0c5460; color: #0c5460; }
        .alert-success { background: #d4edda; border-left: 4px solid #155724; color: #155724; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #856404; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Analytics Verification Tool</h1>
        <p>This tool shows you exactly what's in your database to verify that aggregation is working.</p>
        
        <div class="section">
            <h3>Date to Check: <?php echo $targetDate; ?></h3>
            <form method="GET" style="display: inline;">
                <input type="date" name="date" value="<?php echo $targetDate; ?>" required>
                <button type="submit" class="btn btn-primary">Check This Date</button>
            </form>
            <button onclick="window.location.href='run_analytics_aggregation.php'" class="btn">Go to Aggregation Runner</button>
        </div>

        <?php
        // ========================================
        // 1. CHECK SOURCE DATA (Conversations)
        // ========================================
        echo "<h2>1️⃣ Source Data: Conversations Table</h2>";
        
        $conv_check = "SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN sentiment = 'positive' THEN 1 END) as positive,
            COUNT(CASE WHEN sentiment = 'neutral' THEN 1 END) as neutral,
            COUNT(CASE WHEN sentiment = 'negative' THEN 1 END) as negative,
            AVG(satisfaction_rating) as avg_rating
        FROM conversations 
        WHERE DATE(created_at) = ?";
        
        $stmt = $conn->prepare($conv_check);
        $stmt->bind_param("s", $targetDate);
        $stmt->execute();
        $conv_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($conv_data['total'] == 0) {
            echo "<div class='alert alert-warning'>";
            echo "⚠️ <strong>No conversations found for {$targetDate}</strong><br>";
            echo "This means there's no data to aggregate. You need to:<br>";
            echo "1. Use the chatbot to create some conversations<br>";
            echo "2. Or choose a different date that has conversations<br>";
            echo "3. Check your database: <code>SELECT * FROM conversations ORDER BY created_at DESC</code>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-success'>";
            echo "✅ <strong>Found {$conv_data['total']} conversations for {$targetDate}</strong>";
            echo "</div>";
            
            echo "<div class='grid'>";
            echo "<div class='card'><div class='label'>Total Conversations</div><div class='metric'>{$conv_data['total']}</div></div>";
            echo "<div class='card'><div class='label'>Positive</div><div class='metric' style='color: #28a745;'>{$conv_data['positive']}</div></div>";
            echo "<div class='card'><div class='label'>Neutral</div><div class='metric' style='color: #6c757d;'>{$conv_data['neutral']}</div></div>";
            echo "<div class='card'><div class='label'>Negative</div><div class='metric' style='color: #dc3545;'>{$conv_data['negative']}</div></div>";
            echo "<div class='card'><div class='label'>Avg Rating</div><div class='metric'>" . number_format($conv_data['avg_rating'], 1) . "/5</div></div>";
            echo "</div>";
        }
        
        // ========================================
        // 2. CHECK AGGREGATED DATA
        // ========================================
        echo "<h2>2️⃣ Aggregated Data: chatbot_analytics Table</h2>";
        
        $analytics_check = "SELECT * FROM chatbot_analytics WHERE date = ?";
        $stmt = $conn->prepare($analytics_check);
        $stmt->bind_param("s", $targetDate);
        $stmt->execute();
        $analytics_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$analytics_data) {
            echo "<div class='alert alert-warning'>";
            echo "⚠️ <strong>No analytics record found for {$targetDate}</strong><br>";
            echo "This means aggregation hasn't been run yet for this date.<br>";
            echo "<button onclick=\"runAggregation()\" class='btn btn-primary' style='margin-top: 10px;'>Run Aggregation Now</button>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-success'>";
            echo "✅ <strong>Analytics record exists for {$targetDate}</strong><br>";
            echo "Last updated: " . $analytics_data['updated_at'];
            echo "</div>";
            
            echo "<table>";
            echo "<tr><th>Metric</th><th>Value</th></tr>";
            echo "<tr><td>Total Conversations</td><td class='metric' style='font-size: 16px;'>{$analytics_data['total_conversations']}</td></tr>";
            echo "<tr><td>Total Messages</td><td class='metric' style='font-size: 16px;'>{$analytics_data['total_messages']}</td></tr>";
            echo "<tr><td>Avg Messages/Session</td><td class='metric' style='font-size: 16px;'>{$analytics_data['avg_messages_per_session']}</td></tr>";
            echo "<tr><td>Avg Response Time</td><td class='metric' style='font-size: 16px;'>{$analytics_data['avg_response_time_ms']}ms</td></tr>";
            echo "<tr><td>Positive Sentiment %</td><td class='metric' style='font-size: 16px; color: #28a745;'>{$analytics_data['positive_sentiment_pct']}%</td></tr>";
            echo "<tr><td>Neutral Sentiment %</td><td class='metric' style='font-size: 16px; color: #6c757d;'>{$analytics_data['neutral_sentiment_pct']}%</td></tr>";
            echo "<tr><td>Negative Sentiment %</td><td class='metric' style='font-size: 16px; color: #dc3545;'>{$analytics_data['negative_sentiment_pct']}%</td></tr>";
            echo "<tr><td>Intent Accuracy</td><td class='metric' style='font-size: 16px;'>{$analytics_data['intent_accuracy']}%</td></tr>";
            echo "<tr><td>Resolution Rate</td><td class='metric' style='font-size: 16px;'>{$analytics_data['resolution_rate']}%</td></tr>";
            echo "<tr><td>Satisfaction Score</td><td class='metric' style='font-size: 16px;'>{$analytics_data['satisfaction_score']}/5.0</td></tr>";
            echo "<tr><td>Unrecognized Queries</td><td class='metric' style='font-size: 16px;'>{$analytics_data['unrecognized_intent_count']}</td></tr>";
            echo "</table>";
            
            echo "<button onclick=\"runAggregation()\" class='btn'>Re-run Aggregation (Update Data)</button>";
        }
        
        // ========================================
        // 3. COMPARISON
        // ========================================
        if ($conv_data['total'] > 0 && $analytics_data) {
            echo "<h2>3️⃣ Verification: Does Aggregated Data Match Source Data?</h2>";
            
            $matches = true;
            echo "<table>";
            echo "<tr><th>Metric</th><th>Source Data</th><th>Aggregated Data</th><th>Match?</th></tr>";
            
            // Check conversations count
            $match1 = $conv_data['total'] == $analytics_data['total_conversations'];
            $matches = $matches && $match1;
            echo "<tr>";
            echo "<td>Total Conversations</td>";
            echo "<td>{$conv_data['total']}</td>";
            echo "<td>{$analytics_data['total_conversations']}</td>";
            echo "<td><span class='status " . ($match1 ? 'status-yes' : 'status-no') . "'>" . ($match1 ? '✓ YES' : '✗ NO') . "</span></td>";
            echo "</tr>";
            
            // Check sentiment
            $source_positive_pct = $conv_data['total'] > 0 ? round(($conv_data['positive'] / $conv_data['total']) * 100, 1) : 0;
            $match2 = abs($source_positive_pct - $analytics_data['positive_sentiment_pct']) < 1;
            $matches = $matches && $match2;
            echo "<tr>";
            echo "<td>Positive Sentiment %</td>";
            echo "<td>{$source_positive_pct}%</td>";
            echo "<td>{$analytics_data['positive_sentiment_pct']}%</td>";
            echo "<td><span class='status " . ($match2 ? 'status-yes' : 'status-no') . "'>" . ($match2 ? '✓ YES' : '✗ NO') . "</span></td>";
            echo "</tr>";
            
            echo "</table>";
            
            if ($matches) {
                echo "<div class='alert alert-success'>";
                echo "✅ <strong>SUCCESS!</strong> Aggregated data matches source data. The system is working correctly!";
                echo "</div>";
            } else {
                echo "<div class='alert alert-warning'>";
                echo "⚠️ Data mismatch detected. Try re-running the aggregation.";
                echo "</div>";
            }
        }
        
        // ========================================
        // 4. SQL QUERIES YOU CAN RUN
        // ========================================
        echo "<h2>4️⃣ SQL Queries to Verify in phpMyAdmin</h2>";
        echo "<div class='section'>";
        echo "<h4>Check if conversations exist:</h4>";
        echo "<div class='code'>SELECT * FROM conversations WHERE DATE(created_at) = '{$targetDate}' LIMIT 10;</div>";
        
        echo "<h4>Check aggregated analytics:</h4>";
        echo "<div class='code'>SELECT * FROM chatbot_analytics WHERE date = '{$targetDate}';</div>";
        
        echo "<h4>Check all analytics records:</h4>";
        echo "<div class='code'>SELECT date, total_conversations, satisfaction_score FROM chatbot_analytics ORDER BY date DESC;</div>";
        echo "</div>";
        
        ?>
        
    </div>
    
    <script>
    function runAggregation() {
        const date = '<?php echo $targetDate; ?>';
        const url = 'aggregate_analytics.php?date=' + date;
        
        if (confirm('Run aggregation for ' + date + '?')) {
            window.open(url, '_blank');
            setTimeout(() => {
                alert('Aggregation completed! Refreshing this page to show updated data...');
                location.reload();
            }, 3000);
        }
    }
    </script>
    
</body>
</html>
<?php $conn->close(); ?>
