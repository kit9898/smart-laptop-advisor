<?php
/**
 * ASUS-Style AI Chatbot Backend
 * Enhanced with inventory awareness, "You may also like", and persistent chat
 */
require_once 'includes/db_connect.php';
require_once 'includes/config.php';
require_once 'includes/ollama_client.php';

header('Content-Type: application/json');

// Handle JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? 'send_message';

switch ($action) {
    case 'send_message':
        handle_send_message($input);
        break;
    case 'get_history':
        handle_get_history($input);
        break;
    case 'start_session':
        handle_start_session();
        break;
    case 'get_inventory_summary':
        handle_get_inventory_summary();
        break;
    default:
        echo json_encode(['reply' => 'Invalid action.']);
}

function handle_start_session() {
    global $conn;
    $session_id = 'chat_' . uniqid() . '_' . bin2hex(random_bytes(8));
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_id = $_SESSION['user_id'] ?? null;

    $stmt = $conn->prepare("INSERT INTO conversations (session_id, user_id, user_ip, source, started_at) VALUES (?, ?, ?, 'web', NOW())");
    $stmt->bind_param("sis", $session_id, $user_id, $user_ip);
    
    if ($stmt->execute()) {
        // Get inventory summary for initial context
        $inventory = get_inventory_summary();
        echo json_encode([
            'success' => true, 
            'session_id' => $session_id,
            'inventory_summary' => $inventory
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create session']);
    }
}

/**
 * Get inventory summary for chatbot context
 */
function handle_get_inventory_summary() {
    echo json_encode(get_inventory_summary());
}

function get_inventory_summary() {
    global $conn;
    
    $summary = [
        'total_products' => 0,
        'categories' => [],
        'brands' => [],
        'price_range' => ['min' => 0, 'max' => 0],
        'use_cases' => []
    ];
    
    // Get total active products
    $result = $conn->query("SELECT COUNT(*) as total FROM products WHERE is_active = 1 AND stock_quantity > 0");
    $summary['total_products'] = $result->fetch_assoc()['total'];
    
    // Get categories with counts
    $result = $conn->query("SELECT product_category, COUNT(*) as count FROM products WHERE is_active = 1 AND stock_quantity > 0 GROUP BY product_category ORDER BY count DESC");
    while ($row = $result->fetch_assoc()) {
        $summary['categories'][$row['product_category']] = $row['count'];
    }
    
    // Get brands with counts
    $result = $conn->query("SELECT brand, COUNT(*) as count FROM products WHERE is_active = 1 AND stock_quantity > 0 AND brand IS NOT NULL GROUP BY brand ORDER BY count DESC LIMIT 10");
    while ($row = $result->fetch_assoc()) {
        $summary['brands'][$row['brand']] = $row['count'];
    }
    
    // Get price range
    $result = $conn->query("SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE is_active = 1 AND stock_quantity > 0");
    $priceData = $result->fetch_assoc();
    $summary['price_range']['min'] = floatval($priceData['min_price']);
    $summary['price_range']['max'] = floatval($priceData['max_price']);
    
    // Get use cases
    $result = $conn->query("SELECT primary_use_case, COUNT(*) as count FROM products WHERE is_active = 1 AND stock_quantity > 0 AND primary_use_case IS NOT NULL GROUP BY primary_use_case ORDER BY count DESC");
    while ($row = $result->fetch_assoc()) {
        $summary['use_cases'][$row['primary_use_case']] = $row['count'];
    }
    
    return $summary;
}

function handle_send_message($input) {
    global $conn;
    
    $session_id = $input['session_id'] ?? null;
    $user_message = trim($input['message'] ?? '');
    $user_id = $_SESSION['user_id'] ?? null;
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $current_page = $input['current_page'] ?? null; // Track which page user is on

    if (empty($session_id)) {
        echo json_encode(['success' => false, 'error' => 'Missing session ID']);
        return;
    }

    if (empty($user_message)) {
        echo json_encode(['success' => false, 'error' => 'Empty message']);
        return;
    }

    // Lead Capture: Check for Email
    $email_pattern = "/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i";
    if (preg_match($email_pattern, $user_message, $matches)) {
        $capturedEmail = $matches[0];
        if (!empty($session_id)) {
            $stmt = $conn->prepare("UPDATE conversations SET customer_email = ? WHERE session_id = ?");
            $stmt->bind_param("ss", $capturedEmail, $session_id);
            $stmt->execute();
        }
    }

    // Check/Create Conversation
    $stmt = $conn->prepare("SELECT conversation_id FROM conversations WHERE session_id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO conversations (session_id, user_id, user_ip, source, started_at) VALUES (?, ?, ?, 'web', NOW())");
        $stmt->bind_param("sis", $session_id, $user_id, $user_ip);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Failed to create conversation']);
            return;
        }
        $conversation_id = $conn->insert_id;
    } else {
        $row = $result->fetch_assoc();
        $conversation_id = $row['conversation_id'];
        
        $stmt = $conn->prepare("UPDATE conversations SET updated_at = NOW(), duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW()), message_count = message_count + 1 WHERE conversation_id = ?");
        $stmt->bind_param("i", $conversation_id);
        $stmt->execute();
    }

    // Store User Message
    $stmt = $conn->prepare("INSERT INTO conversation_messages (conversation_id, message_type, message_content, timestamp) VALUES (?, 'user', ?, NOW())");
    $stmt->bind_param("is", $conversation_id, $user_message);
    $stmt->execute();

    // Generate ASUS-style Bot Response
    $response_data = generate_asus_style_response($user_message, $input, $current_page);

    // Store Bot Response
    $stmt = $conn->prepare("INSERT INTO conversation_messages (conversation_id, message_type, message_content, timestamp) VALUES (?, 'bot', ?, NOW())");
    $bot_text = $response_data['response'];
    $stmt->bind_param("is", $conversation_id, $bot_text);
    $stmt->execute();

    // Send Enhanced Response
    echo json_encode([
        'success' => true,
        'response' => $response_data['response'],
        'products' => $response_data['products'] ?? [],
        'also_like' => $response_data['also_like'] ?? [], // "You may also like" section
        'actions' => $response_data['actions'] ?? [],
        'suggestions' => $response_data['suggestions'] ?? [],
        'product_links' => $response_data['product_links'] ?? [], // Clickable links in text
        'conversation_id' => $conversation_id
    ]);
}

function handle_get_history($input) {
    global $conn;
    $session_id = $input['session_id'] ?? '';
    
    if (empty($session_id)) {
        echo json_encode(['success' => false, 'history' => []]);
        return;
    }

    // Get conversation ID
    $stmt = $conn->prepare("SELECT conversation_id FROM conversations WHERE session_id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 0) {
        echo json_encode(['success' => true, 'history' => []]);
        return;
    }
    
    $conversation_id = $res->fetch_assoc()['conversation_id'];

    $history = [];
    $stmt = $conn->prepare("SELECT message_type as sender, message_content as message, timestamp FROM conversation_messages WHERE conversation_id = ? ORDER BY timestamp ASC");
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $history[] = [
            'sender' => $row['sender'] === 'user' ? 'user' : 'bot',
            'message' => $row['message'],
            'timestamp' => $row['timestamp']
        ];
    }
    
    echo json_encode(['success' => true, 'history' => $history]);
}

/**
 * Generate ASUS-style chatbot response with full inventory awareness
 */
function generate_asus_style_response($input_text, $raw_input = [], $current_page = null) {
    global $conn;
    
    // Currency settings
    $currency = $raw_input['currency'] ?? 'MYR';
    $exchange_rate = floatval($raw_input['exchange_rate'] ?? 4.47);
    $currency_symbol = ($currency == 'MYR') ? 'RM' : (($currency == 'CNY') ? '¥' : '$');
    
    // Initialize result
    $result = [
        'response' => '',
        'products' => [],
        'also_like' => [],
        'actions' => [],
        'suggestions' => [],
        'product_links' => []
    ];
    
    // Initialize Ollama Client
    $ollama = new OllamaClient(OLLAMA_API_URL, OLLAMA_MODEL, OLLAMA_TIMEOUT);
    
    // ========== STEP 1: Load Current Inventory ==========
    $inventory = load_full_inventory($conn, $exchange_rate, $currency_symbol);
    
    // ========== STEP 2: Check for Intent Matches in DB ==========
    $stmt = $conn->prepare("
        SELECT ir.response_text 
        FROM training_phrases tp
        JOIN intents i ON tp.intent_id = i.intent_id
        JOIN intent_responses ir ON i.intent_id = ir.intent_id
        WHERE tp.phrase_text = ? AND i.is_active = 1 AND ir.is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param("s", $input_text);
    $stmt->execute();
    $intentResult = $stmt->get_result();
    if ($intentResult->num_rows > 0) {
        $result['response'] = $intentResult->fetch_assoc()['response_text'];
        $result['suggestions'] = get_default_suggestions();
        return $result;
    }
    
    // ========== STEP 3: Use AI to Analyze Intent ==========
    $analysisPrompt = "Analyze this user message about laptop/accessories shopping: \"$input_text\"\n" .
                     "Return ONLY a valid JSON object with these fields:\n" .
                     "{\n" .
                     "  \"type\": \"product_search\" or \"product_specs\" or \"product_comparison\" or \"accessory\" or \"budget_query\" or \"general\",\n" .
                     "  \"category\": null or \"laptop\" or \"mouse\" or \"keyboard\" or \"headset\" or \"bag\" or \"monitor\",\n" .
                     "  \"brand\": null or brand name mentioned,\n" .
                     "  \"budget_max\": null or number (extract budget if mentioned),\n" .
                     "  \"use_case\": null or \"Gaming\" or \"Professional\" or \"Student\" or \"Creative\" or \"Home User\",\n" .
                     "  \"product_names\": [] or array of specific product names mentioned,\n" .
                     "  \"display_size\": null or number (if screen size mentioned like 15 inch, 17 inch)\n" .
                     "}";
    
    $analysis = $ollama->chat([
        ['role' => 'system', 'content' => 'You are a JSON analyzer. Output ONLY valid JSON, no explanation.'],
        ['role' => 'user', 'content' => $analysisPrompt]
    ]);
    
    $intentData = json_decode($analysis['message'] ?? '{}', true);
    if (!is_array($intentData)) $intentData = [];
    
    $intentType = $intentData['type'] ?? 'general';
    
    // ========== STEP 4: Search Products Based on Intent ==========
    $foundProducts = search_products_by_intent($conn, $intentData, $exchange_rate);
    
    // ========== STEP 5: Build Product Cards ==========
    foreach ($foundProducts as $p) {
        $localPrice = $p['price'] * $exchange_rate;
        $imageUrl = get_product_image_url($p['image_url']);
        
        $result['products'][] = [
            'id' => $p['product_id'],
            'name' => $p['product_name'],
            'brand' => $p['brand'],
            'price' => $currency_symbol . number_format($localPrice, 2),
            'price_raw' => $localPrice,
            'image' => $imageUrl,
            'category' => $p['product_category'] ?? 'laptop',
            'specs' => [
                'cpu' => $p['cpu'] ?? '',
                'gpu' => $p['gpu'] ?? '',
                'ram' => $p['ram_gb'] ? $p['ram_gb'] . 'GB' : '',
                'storage' => $p['storage_gb'] ? $p['storage_gb'] . 'GB ' . ($p['storage_type'] ?? 'SSD') : '',
                'display' => $p['display_size'] ? $p['display_size'] . '"' : ''
            ],
            'description' => $p['description'] ?? '',
            'use_case' => $p['primary_use_case'] ?? '',
            'stock' => $p['stock_quantity'],
            'link' => 'product_details.php?product_id=' . $p['product_id'],
            'buy_link' => 'cart.php?action=add&product_id=' . $p['product_id']
        ];
        
        // Build product links for text replacement
        $result['product_links'][$p['product_name']] = 'product_details.php?product_id=' . $p['product_id'];
    }
    
    // ========== STEP 6: "You May Also Like" Recommendations ==========
    if (!empty($foundProducts)) {
        $mainCategory = $foundProducts[0]['product_category'] ?? 'laptop';
        $result['also_like'] = get_related_products($conn, $foundProducts, $mainCategory, $exchange_rate, $currency_symbol);
    }
    
    // ========== STEP 7: Build Action Buttons ==========
    if (count($result['products']) >= 2) {
        $compareIds = array_slice(array_column($result['products'], 'id'), 0, 4);
        $result['actions'][] = [
            'type' => 'compare',
            'label' => 'Compare These Products',
            'icon' => 'compare',
            'url' => 'compare.php?ids=' . implode(',', $compareIds)
        ];
    }
    
    if (count($result['products']) > 0) {
        $result['actions'][] = [
            'type' => 'browse',
            'label' => 'Browse All Products',
            'icon' => 'grid',
            'url' => 'products.php'
        ];
    }
    
    // ========== STEP 8: Smart Suggestions ==========
    $result['suggestions'] = get_contextual_suggestions($intentType, $foundProducts);
    
    // ========== STEP 9: Generate AI Response (ASUS-style) ==========
    $productContext = build_product_context($foundProducts, $currency_symbol, $exchange_rate);
    $inventoryContext = build_inventory_context($inventory);
    
    $systemPrompt = "You are a helpful Smart Laptop Advisor assistant, similar to ASUS official website's AI assistant.
    
STYLE GUIDELINES:
1. Be professional, friendly, and helpful like ASUS customer support
2. Use numbered lists when recommending multiple products
3. Always mention product names EXACTLY as they appear in inventory (so they become clickable links)
4. Include key specs: CPU, GPU, RAM, Storage, Display size
5. Always mention the price in $currency_symbol
6. End recommendations with a helpful suggestion or question
7. For accessories, mention compatibility and key features
8. Keep responses concise but informative

RESPONSE FORMAT:
- Start with a brief helpful intro
- List products with specs in numbered format
- Include a \"You may also like\" mention if relevant
- End with \"Let me know your budget range, and I'll recommend the perfect products just for you!\" or similar helpful closing";

    $userPrompt = "$inventoryContext\n\n$productContext\n\nUser asks: \"$input_text\"\n\nProvide a helpful ASUS-style response. Mention specific product names from inventory.";

    $response = $ollama->chat([
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt]
    ]);
    
    $result['response'] = $response['message'] ?? "I apologize, I'm having trouble connecting right now. Please try again.";
    
    // Add helpful closing if products were found
    if (!empty($result['products']) && strpos($result['response'], 'budget') === false) {
        $result['response'] .= "\n\nLet me know your budget range, and I'll recommend the perfect products just for you!";
    }
    
    return $result;
}

/**
 * Load full inventory for context
 */
function load_full_inventory($conn, $exchange_rate, $currency_symbol) {
    $inventory = [
        'laptops' => [],
        'accessories' => [],
        'summary' => ''
    ];
    
    // Load laptops
    $result = $conn->query("SELECT product_id, product_name, brand, price, primary_use_case, cpu, gpu, ram_gb, display_size 
                           FROM products 
                           WHERE is_active = 1 AND stock_quantity > 0 AND product_category = 'laptop' 
                           ORDER BY price ASC");
    while ($row = $result->fetch_assoc()) {
        $inventory['laptops'][] = $row;
    }
    
    // Load accessories
    $result = $conn->query("SELECT product_id, product_name, brand, price, product_category, description 
                           FROM products 
                           WHERE is_active = 1 AND stock_quantity > 0 AND product_category != 'laptop' 
                           ORDER BY price ASC");
    while ($row = $result->fetch_assoc()) {
        $inventory['accessories'][] = $row;
    }
    
    // Build summary
    $laptopCount = count($inventory['laptops']);
    $accCount = count($inventory['accessories']);
    $inventory['summary'] = "We have $laptopCount laptops and $accCount accessories in stock.";
    
    return $inventory;
}

/**
 * Search products based on analyzed intent
 */
function search_products_by_intent($conn, $intentData, $exchange_rate) {
    $products = [];
    
    $sql = "SELECT * FROM products WHERE is_active = 1 AND stock_quantity > 0";
    $params = [];
    $types = "";
    
    // Filter by category
    $category = $intentData['category'] ?? null;
    if ($category) {
        $sql .= " AND product_category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    // Filter by brand
    $brand = $intentData['brand'] ?? null;
    if ($brand) {
        $sql .= " AND brand LIKE ?";
        $params[] = "%" . $brand . "%";
        $types .= "s";
    }
    
    // Filter by budget (convert to USD for DB)
    $budget = $intentData['budget_max'] ?? null;
    if ($budget) {
        $budget_usd = floatval($budget) / $exchange_rate;
        $sql .= " AND price <= ?";
        $params[] = $budget_usd;
        $types .= "d";
    }
    
    // Filter by use case
    $useCase = $intentData['use_case'] ?? null;
    if ($useCase) {
        $sql .= " AND (primary_use_case LIKE ? OR related_to_category LIKE ?)";
        $params[] = "%" . $useCase . "%";
        $params[] = "%" . $useCase . "%";
        $types .= "ss";
    }
    
    // Filter by display size
    $displaySize = $intentData['display_size'] ?? null;
    if ($displaySize) {
        $sql .= " AND display_size >= ?";
        $params[] = floatval($displaySize) - 0.5; // Allow some flexibility
        $types .= "d";
    }
    
    // Search by specific product names
    $productNames = $intentData['product_names'] ?? [];
    if (!empty($productNames)) {
        $nameClauses = [];
        foreach ($productNames as $name) {
            $nameClauses[] = "product_name LIKE ?";
            $params[] = "%" . $name . "%";
            $types .= "s";
        }
        $sql .= " AND (" . implode(" OR ", $nameClauses) . ")";
    }
    
    $sql .= " ORDER BY price ASC LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    // If no results, get general recommendations
    if (empty($products)) {
        $fallbackSql = "SELECT * FROM products WHERE is_active = 1 AND stock_quantity > 0 ORDER BY RAND() LIMIT 4";
        $result = $conn->query($fallbackSql);
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    return $products;
}

/**
 * Get "You may also like" related products
 */
function get_related_products($conn, $mainProducts, $mainCategory, $exchange_rate, $currency_symbol) {
    $related = [];
    $excludeIds = array_column($mainProducts, 'product_id');
    $excludeList = implode(',', array_map('intval', $excludeIds));
    
    // If main products are laptops, suggest accessories
    if ($mainCategory === 'laptop' || $mainCategory === null) {
        $sql = "SELECT * FROM products 
                WHERE is_active = 1 AND stock_quantity > 0 
                AND product_category IN ('mouse', 'keyboard', 'headset', 'bag')
                AND product_id NOT IN ($excludeList)
                ORDER BY RAND() LIMIT 3";
    } else {
        // If accessories, suggest other accessories or laptops
        $sql = "SELECT * FROM products 
                WHERE is_active = 1 AND stock_quantity > 0 
                AND product_id NOT IN ($excludeList)
                ORDER BY RAND() LIMIT 3";
    }
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $localPrice = $row['price'] * $exchange_rate;
        $related[] = [
            'id' => $row['product_id'],
            'name' => $row['product_name'],
            'brand' => $row['brand'],
            'price' => $currency_symbol . number_format($localPrice, 2),
            'image' => get_product_image_url($row['image_url']),
            'category' => $row['product_category'],
            'link' => 'product_details.php?product_id=' . $row['product_id']
        ];
    }
    
    return $related;
}

/**
 * Build product context for AI
 */
function build_product_context($products, $currency_symbol, $exchange_rate) {
    if (empty($products)) {
        return "No specific products matched the search.";
    }
    
    $context = "=== MATCHING PRODUCTS ===\n";
    foreach ($products as $p) {
        $localPrice = $p['price'] * $exchange_rate;
        $context .= "- **{$p['product_name']}** by {$p['brand']}: {$currency_symbol}" . number_format($localPrice, 2) . "\n";
        if (!empty($p['cpu'])) $context .= "  CPU: {$p['cpu']}\n";
        if (!empty($p['gpu'])) $context .= "  GPU: {$p['gpu']}\n";
        if (!empty($p['ram_gb'])) $context .= "  RAM: {$p['ram_gb']}GB\n";
        if (!empty($p['storage_gb'])) $context .= "  Storage: {$p['storage_gb']}GB {$p['storage_type']}\n";
        if (!empty($p['display_size'])) $context .= "  Display: {$p['display_size']}\"\n";
        if (!empty($p['description'])) $context .= "  Description: {$p['description']}\n";
        $context .= "\n";
    }
    return $context;
}

/**
 * Build inventory context for AI
 */
function build_inventory_context($inventory) {
    $context = "=== CURRENT INVENTORY ===\n";
    $context .= $inventory['summary'] . "\n\n";
    
    if (!empty($inventory['laptops'])) {
        $context .= "LAPTOPS IN STOCK:\n";
        foreach (array_slice($inventory['laptops'], 0, 10) as $laptop) {
            $context .= "- {$laptop['product_name']} ({$laptop['brand']}): {$laptop['primary_use_case']}\n";
        }
    }
    
    if (!empty($inventory['accessories'])) {
        $context .= "\nACCESSORIES IN STOCK:\n";
        foreach (array_slice($inventory['accessories'], 0, 5) as $acc) {
            $context .= "- {$acc['product_name']} ({$acc['brand']}): {$acc['product_category']}\n";
        }
    }
    
    return $context;
}

/**
 * Get product image URL
 */
function get_product_image_url($image_url) {
    if (empty($image_url)) {
        return 'images/laptop1.png';
    }
    if (strpos($image_url, 'http') === 0) {
        return $image_url;
    }
    if (strpos($image_url, 'LaptopAdvisor/') === 0) {
        return str_replace('LaptopAdvisor/', '', $image_url);
    }
    if (strpos($image_url, 'images/') === 0) {
        return $image_url;
    }
    return 'images/' . basename($image_url);
}

/**
 * Get contextual suggestions based on intent
 */
function get_contextual_suggestions($intentType, $products = []) {
    switch ($intentType) {
        case 'product_search':
        case 'product_specs':
            return [
                ['text' => 'Show gaming laptops', 'query' => 'Show me gaming laptops'],
                ['text' => 'Budget under RM3000', 'query' => 'What laptops are under RM3000?'],
                ['text' => 'Best for students', 'query' => 'Recommend laptops for students'],
                ['text' => 'Compare top picks', 'query' => 'Compare the top recommended laptops']
            ];
        case 'product_comparison':
            return [
                ['text' => 'Which has better specs?', 'query' => 'Which laptop has better specifications?'],
                ['text' => 'Best value for money', 'query' => 'Which one offers the best value?'],
                ['text' => 'Recommend accessories', 'query' => 'What accessories do you recommend?']
            ];
        case 'accessory':
            return [
                ['text' => 'Wireless mouse', 'query' => 'Show me wireless mouse options'],
                ['text' => 'Gaming keyboards', 'query' => 'What gaming keyboards do you have?'],
                ['text' => 'Laptop bags', 'query' => 'Show me laptop bags']
            ];
        case 'budget_query':
            return [
                ['text' => 'Under RM2000', 'query' => 'Show laptops under RM2000'],
                ['text' => 'RM2000-RM4000', 'query' => 'Laptops between RM2000 and RM4000'],
                ['text' => 'Premium options', 'query' => 'Show me premium laptops above RM5000']
            ];
        default:
            return get_default_suggestions();
    }
}

/**
 * Default suggestions
 */
function get_default_suggestions() {
    return [
        ['text' => 'Product Recommendations', 'query' => 'Can you recommend a laptop for me?'],
        ['text' => 'Compare Products', 'query' => 'Help me compare different laptops'],
        ['text' => 'Budget Laptops', 'query' => 'What laptops do you have under RM2500?'],
        ['text' => 'Gaming Laptops', 'query' => 'Show me gaming laptops']
    ];
}
?>
