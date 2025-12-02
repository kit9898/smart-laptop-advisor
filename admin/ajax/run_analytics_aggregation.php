<?php
/**
 * Analytics Aggregation - Quick Runner
 * Run this file to aggregate analytics for multiple days at once
 */

require_once '../includes/db_connect.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Analytics Aggregation Runner</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #252526; padding: 20px; border-radius: 8px; }
        h1 { color: #4ec9b0; }
        .btn { background: #0e639c; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #1177bb; }
        .output { background: #1e1e1e; padding: 15px; border-radius: 4px; margin-top: 20px; white-space: pre-wrap; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #4fc1ff; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📊 Analytics Aggregation Runner</h1>
        <p>Generate analytics data from your real conversation database.</p>
        
        <div style='margin: 20px 0;'>
            <button class='btn' onclick=\"runAggregation('today')\">Aggregate Today</button>
            <button class='btn' onclick=\"runAggregation('yesterday')\">Aggregate Yesterday</button>
            <button class='btn' onclick=\"runAggregation('last7days')\">Aggregate Last 7 Days</button>
            <button class='btn' onclick=\"runAggregation('last30days')\">Aggregate Last 30 Days</button>
        </div>
        
        <div id='output' class='output'>
            <span class='info'>Click a button above to start aggregation...</span>
        </div>
    </div>
    
    <script>
    function runAggregation(period) {
        const output = document.getElementById('output');
        output.innerHTML = '<span class=\"info\">Running aggregation for ' + period + '...</span>\\n\\n';
        
        let urls = [];
        const today = new Date();
        
        switch(period) {
            case 'today':
                urls.push('aggregate_analytics.php?date=' + formatDate(today));
                break;
            case 'yesterday':
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                urls.push('aggregate_analytics.php?date=' + formatDate(yesterday));
                break;
            case 'last7days':
                for(let i = 0; i < 7; i++) {
                    const d = new Date(today);
                    d.setDate(d.getDate() - i);
                    urls.push('aggregate_analytics.php?date=' + formatDate(d));
                }
                break;
            case 'last30days':
                for(let i = 0; i < 30; i++) {
                    const d = new Date(today);
                    d.setDate(d.getDate() - i);
                    urls.push('aggregate_analytics.php?date=' + formatDate(d));
                }
                break;
        }
        
        processUrls(urls, 0, output);
    }
    
    function processUrls(urls, index, output) {
        if (index >= urls.length) {
            output.innerHTML += '\\n<span class=\"success\">✓ All aggregations complete!</span>\\n';
            output.innerHTML += '<span class=\"info\">You can now view the analytics at admin_chatbot_analytics.php</span>';
            return;
        }
        
        fetch(urls[index])
            .then(response => response.text())
            .then(text => {
                output.innerHTML += text + '\\n---\\n\\n';
                output.scrollTop = output.scrollHeight;
                processUrls(urls, index + 1, output);
            })
            .catch(error => {
                output.innerHTML += '<span class=\"error\">✗ Error: ' + error + '</span>\\n\\n';
                processUrls(urls, index + 1, output);
            });
    }
    
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    </script>
</body>
</html>";
?>
