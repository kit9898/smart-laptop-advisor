<?php
/**
 * Ollama Client Class
 * Wrapper for communicating with the Ollama API
 */

class OllamaClient {
    private $apiUrl;
    private $model;
    private $timeout;
    
    /**
     * Constructor
     * @param string $apiUrl Base URL for Ollama API
     * @param string $model Model name to use
     * @param int $timeout Request timeout in seconds
     */
    public function __construct($apiUrl, $model, $timeout = 60) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->model = $model;
        $this->timeout = $timeout;
    }
    
    /**
     * Send a chat request to Ollama
     * @param array $messages Array of message objects with 'role' and 'content'
     * @return array Response array with 'success', 'message', and 'response_time'
     */
    public function chat($messages) {
        $startTime = microtime(true);
        
        try {
            // Convert messages to a single prompt for /api/generate (legacy support)
            $prompt = "";
            foreach ($messages as $msg) {
                $role = ucfirst($msg['role']);
                $content = $msg['content'];
                $prompt .= "$role: $content\n";
            }
            $prompt .= "Assistant: ";

            // Prepare the request payload for /api/generate
            $payload = [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'stop' => ['User:', 'System:'] // Stop generation at next turn
                ]
            ];
            
            // Initialize cURL
            $ch = curl_init($this->apiUrl . '/api/generate');
            
            // Set cURL options
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5
            ]);
            
            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Calculate response time
            $responseTime = round((microtime(true) - $startTime) * 1000); // milliseconds
            
            // Check for cURL errors
            if ($curlError) {
                $errorMsg = 'Connection error: ' . $curlError;
                if (strpos($curlError, 'timed out') !== false || strpos($curlError, 'timeout') !== false) {
                    $errorMsg .= ' - Please ensure Ollama is running.';
                }
                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'response_time' => $responseTime
                ];
            }
            
            // Check HTTP status
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'message' => 'Ollama API returned status code: ' . $httpCode . ' Body: ' . $response,
                    'response_time' => $responseTime
                ];
            }
            
            // Parse JSON response
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'message' => 'Invalid JSON response from Ollama',
                    'response_time' => $responseTime
                ];
            }
            
            // Extract the response (field is 'response' in /api/generate, 'message' in /api/chat)
            if (isset($data['response'])) {
                return [
                    'success' => true,
                    'message' => $data['response'],
                    'response_time' => $responseTime
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No valid response from Ollama',
                    'response_time' => $responseTime
                ];
            }
            
        } catch (Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'response_time' => $responseTime
            ];
        }
    }
    
    /**
     * Test connection to Ollama
     * @return bool True if connection successful
     */
    public function testConnection() {
        try {
            $ch = curl_init($this->apiUrl . '/api/tags');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 2
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Analyze sentiment of user message using LLM-as-a-Judge
     * @param string $text User message to analyze
     * @return array|false Sentiment analysis array with 'sentiment' and 'score', or false on failure
     */
    public function analyzeSentiment($text) {
        $startTime = microtime(true);
        
        try {
            // Construct analysis prompt - request JSON output only
            $prompt = "Analyze the sentiment of this user message: \"" . addslashes($text) . "\"\n\n" .
                     "Respond ONLY with a valid JSON object in this exact format:\n" .
                     "{\"sentiment\": \"positive|neutral|negative\", \"score\": 1-5}\n\n" .
                     "Rules:\n" .
                     "- sentiment: must be exactly 'positive', 'neutral', or 'negative'\n" .
                     "- score: 1 (very negative) to 5 (very positive)\n" .
                     "- Neutral messages should have score 3\n" .
                     "- Do NOT include any explanation, ONLY return the JSON object";
            
            // Prepare payload with JSON format enforcement for /api/generate
            $payload = [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json' // Force JSON output (Ollama feature)
            ];
            
            // Initialize cURL
            $ch = curl_init($this->apiUrl . '/api/generate');
            
            // Set cURL options with shorter timeout for analysis
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 15, // Shorter timeout for analysis
                CURLOPT_CONNECTTIMEOUT => 5
            ]);
            
            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Check for errors
            if ($curlError || $httpCode !== 200) {
                return false; // Silent fail for sentiment analysis
            }
            
            // Parse Ollama response
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['response'])) {
                return false;
            }
            
            // Extract and parse the sentiment JSON from the response
            $sentimentJson = $data['response'];
            $sentiment = json_decode($sentimentJson, true);
            
            // Validate sentiment structure
            if (json_last_error() !== JSON_ERROR_NONE || 
                !isset($sentiment['sentiment']) || 
                !isset($sentiment['score'])) {
                return false;
            }
            
            // Normalize sentiment value
            $sentiment['sentiment'] = strtolower(trim($sentiment['sentiment']));
            
            // Validate sentiment is one of the expected values
            if (!in_array($sentiment['sentiment'], ['positive', 'neutral', 'negative'])) {
                return false;
            }
            
            // Validate score is in range 1-5
            $sentiment['score'] = (int)$sentiment['score'];
            if ($sentiment['score'] < 1 || $sentiment['score'] > 5) {
                return false;
            }
            
            return $sentiment;
            
        } catch (Exception $e) {
            // Silent fail - sentiment analysis is optional
            return false;
        }
    }
}
?>
