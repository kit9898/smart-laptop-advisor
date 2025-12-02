<?php
include 'includes/header.php';
require_once 'includes/auth_check.php'; 

// Check if product_id is provided and is a valid number
if (isset($_GET['product_id']) && is_numeric($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);

    $sql = "SELECT * FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $product = $result->fetch_assoc();
    } else {
        $product = null;
    }
    $stmt->close();
    
    // Get similar products (same use case, different product)
    $similar_products = [];
    if ($product) {
        $similar_sql = "SELECT * FROM products 
                       WHERE primary_use_case = ? AND product_id != ? 
                       ORDER BY ABS(price - ?) ASC 
                       LIMIT 4";
        $similar_stmt = $conn->prepare($similar_sql);
        $similar_stmt->bind_param("sid", $product['primary_use_case'], $product_id, $product['price']);
        $similar_stmt->execute();
        $similar_result = $similar_stmt->get_result();
        while ($row = $similar_result->fetch_assoc()) {
            $similar_products[] = $row;
        }
        $similar_stmt->close();
    }
} else {
    $product = null;
}

// Helper function to get performance rating
function getPerformanceRating($ram, $gpu) {
    $score = 0;
    // RAM scoring
    if ($ram >= 32) $score += 50;
    elseif ($ram >= 16) $score += 40;
    elseif ($ram >= 8) $score += 25;
    else $score += 10;
    
    // GPU scoring
    if (stripos($gpu, 'RTX 4090') !== false) $score += 50;
    elseif (stripos($gpu, 'RTX 4080') !== false || stripos($gpu, 'RTX 3090') !== false) $score += 45;
    elseif (stripos($gpu, 'RTX 4070') !== false || stripos($gpu, 'RTX 3080') !== false) $score += 40;
    elseif (stripos($gpu, 'RTX 4060') !== false || stripos($gpu, 'RTX 3070') !== false) $score += 35;
    elseif (stripos($gpu, 'RTX 3060') !== false || stripos($gpu, 'RTX 4050') !== false) $score += 30;
    elseif (stripos($gpu, 'RTX 3050') !== false || stripos($gpu, 'GTX') !== false) $score += 20;
}
.breadcrumb a {
    color: #3b82f6;
    text-decoration: none;
}
.breadcrumb a:hover {
    text-decoration: underline;
}

.product-detail-wrapper {
    max-width: 1200px;
    margin: 0 auto;
}

.product-detail-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 50px;
}

.product-image-gallery {
    position: sticky;
    top: 20px;
    height: fit-content;
}

.main-image {
    width: 100%;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    margin-bottom: 15px;
}

.use-case-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
    margin-bottom: 10px;
}

.product-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 10px 0 5px 0;
    color: #1a1a1a;
}

.brand-tag-large {
    font-size: 1.1rem;
    color: #666;
    font-weight: 500;
}

.price-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin: 20px 0;
}

.price-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.product-price-display {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 5px 0;
}

.key-features {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin: 25px 0;
}

.feature-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    border-left: 4px solid #3b82f6;
}

.feature-box strong {
    display: block;
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 5px;
}

.feature-box span {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1a1a1a;
}

.performance-meter {
    margin: 25px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
}

.meter-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-weight: 600;
}

.meter-bar {
    height: 12px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.meter-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981 0%, #3b82f6 100%);
    border-radius: 10px;
    transition: width 1s ease;
}

.spec-tabs {
    display: flex;
    gap: 10px;
    border-bottom: 2px solid #e9ecef;
    margin: 30px 0 20px 0;
}

.spec-tab {
    padding: 12px 20px;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
}

.spec-tab.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}

.spec-tab:hover {
    color: #3b82f6;
}

.tab-content {
    display: none;
    padding: 20px 0;
}

.tab-content.active {
    display: block;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.spec-table {
    width: 100%;
    border-collapse: collapse;
}

.spec-table tr {
    border-bottom: 1px solid #e9ecef;
}

.spec-table td {
    padding: 15px 10px;
}

.spec-table td:first-child {
    font-weight: 600;
    color: #666;
    width: 35%;
}

.spec-table td:last-child {
    color: #1a1a1a;
}

.cart-section {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-top: 20px;
}

.quantity-selector {
    display: flex;
    align-items: center;
    gap: 15px;
    margin: 20px 0;
}

.quantity-btn {
    width: 40px;
    height: 40px;
    border: 2px solid #e9ecef;
    background: white;
    border-radius: 8px;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s;
}

.quantity-btn:hover {
    border-color: #3b82f6;
    color: #3b82f6;
}

.quantity-display {
    font-size: 1.3rem;
    font-weight: 600;
    min-width: 40px;
    text-align: center;
}

.add-to-cart-btn {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.add-to-cart-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.quick-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.quick-action-btn {
    flex: 1;
    padding: 10px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
    text-decoration: none;
    color: #495057;
    font-weight: 500;
}

.quick-action-btn:hover {
    background: #e9ecef;
    border-color: #dee2e6;
}

.similar-products-section {
    margin-top: 60px;
    padding-top: 40px;
    border-top: 2px solid #e9ecef;
}

.similar-products-section h3 {
    font-size: 1.8rem;
    margin-bottom: 25px;
    color: #1a1a1a;
}

.similar-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.trust-badges {
    display: flex;
    gap: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    margin: 20px 0;
}

.trust-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #666;
}

@media (max-width: 768px) {
    .product-detail-container {
        grid-template-columns: 1fr;
        gap: 20px;
    }
        <div class="breadcrumb">
            <a href="products.php">Products</a> / 
            <a href="products.php?view=browse&use_case=<?php echo urlencode($product['primary_use_case']); ?>">
                <?php echo htmlspecialchars($product['primary_use_case']); ?>
            </a> / 
            <span><?php echo htmlspecialchars($product['product_name']); ?></span>
        </div>

        <div class="product-detail-container">
            <!-- Left Column: Image Gallery -->
            <div class="product-image-gallery">
                <img class="main-image" 
                     src="<?php echo !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : 'https://via.placeholder.com/600'; ?>" 
                     alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                
                <div class="trust-badges">
                    <div class="trust-badge">
                        <span>✓</span> Free Shipping
                    </div>
                    <div class="trust-badge">
                        <span>↺</span> 30-Day Returns
                    </div>
                    <div class="trust-badge">
                        <span>★</span> Warranty Included
                    </div>
                </div>
            </div>

            <!-- Right Column: Product Info -->
            <div class="product-info-section">
                <?php 
                $badge = getUseCaseBadge($product['primary_use_case']);
                ?>
                <div class="use-case-badge" style="background-color: <?php echo $badge['color']; ?>">
                    <span><?php echo $badge['icon']; ?></span>
                    <span><?php echo htmlspecialchars($product['primary_use_case']); ?></span>
                </div>
                
                <p class="brand-tag-large"><?php echo htmlspecialchars($product['brand']); ?></p>
                <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                
                <div class="price-section">
                    <div class="price-label">Price</div>
                    <div class="product-price-display">$<?php echo number_format($product['price'], 2); ?></div>
                </div>

                <p class="description" style="margin: 20px 0; color: #495057; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </p>

                <!-- Key Features at a Glance -->
                <h3 style="margin: 25px 0 15px 0; font-size: 1.2rem;">Key Specifications</h3>
                <div class="key-features">
                    <div class="feature-box">
                        <strong>Processor</strong>
                        <span><?php echo htmlspecialchars($product['cpu']); ?></span>
                    </div>
                    <div class="feature-box">
                        <strong>Graphics</strong>
                        <span><?php echo htmlspecialchars($product['gpu']); ?></span>
                    </div>
                    <div class="feature-box">
                        <strong>Memory</strong>
                        <span><?php echo htmlspecialchars($product['ram_gb']); ?> GB RAM</span>
                    </div>
                    <div class="feature-box">
                        <strong>Storage</strong>
                        <span><?php echo htmlspecialchars($product['storage_gb']); ?> GB <?php echo htmlspecialchars($product['storage_type']); ?></span>
                    </div>
                </div>

                <!-- Performance Rating -->
                <?php $performance_score = getPerformanceRating($product['ram_gb'], $product['gpu']); ?>
                <div class="performance-meter">
                    <div class="meter-label">
                        <span>Performance Rating</span>
                        <span><?php echo $performance_score; ?>/100</span>
                    </div>
                    <div class="meter-bar">
                        <div class="meter-fill" style="width: <?php echo $performance_score; ?>%"></div>
                    </div>
                    <p style="margin-top: 8px; font-size: 0.85rem; color: #666;">
                        <?php 
                        if ($performance_score >= 80) echo "⚡ Exceptional performance for demanding tasks";
                        elseif ($performance_score >= 60) echo "✓ Great performance for most applications";
                        elseif ($performance_score >= 40) echo "→ Good for everyday computing";
                        else echo "→ Suitable for basic tasks";
                        ?>
                    </p>
                </div>

                <!-- Add to Cart Section -->
<div class="cart-section">
    <?php 
        // Stock Logic
        $stock = $product['stock_quantity'];
        $is_out_of_stock = ($stock <= 0);
        
        if ($is_out_of_stock) {
            $stock_class = 'stock-out';
            $stock_text = '❌ Out of Stock';
        } elseif ($stock < 5) {
            $stock_class = 'stock-low';
            $stock_text = '⚠️ Low Stock: Only ' . $stock . ' left!';
        } else {
            $stock_class = 'stock-in';
            $stock_text = '✅ In Stock (' . $stock . ' units)';
        }
    ?>

    <div class="stock-indicator <?php echo $stock_class; ?>">
        <?php echo $stock_text; ?>
    </div>

    <form action="cart_process.php" method="post" id="cartForm">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
        <input type="hidden" name="quantity" id="quantityInput" value="1">
        
        <label style="font-weight: 600; display: block; margin-bottom: 10px;">Quantity</label>
        
        <div class="quantity-selector" style="<?php echo $is_out_of_stock ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
            <button type="button" class="quantity-btn" onclick="changeQuantity(-1)">−</button>
            <span class="quantity-display" id="quantityDisplay">1</span>
            <button type="button" class="quantity-btn" onclick="changeQuantity(1)">+</button>
        </div>
        
        <?php if ($is_out_of_stock): ?>
            <button type="button" class="add-to-cart-btn" disabled style="background: #9ca3af; cursor: not-allowed;">
                🚫 Sold Out
            </button>
        <?php else: ?>
            <button type="submit" class="add-to-cart-btn">
                🛒 Add to Cart
            </button>
        <?php endif; ?>
    </form>
    
    <div class="quick-actions">
        <a href="compare.php?ids=<?php echo $product['product_id']; ?>" class="quick-action-btn">
            ⚖️ Compare
        </a>
        <a href="products.php?view=browse&use_case=<?php echo urlencode($product['primary_use_case']); ?>" class="quick-action-btn">
            🔍 Similar Items
        </a>
    </div>
</div>

        <!-- Detailed Specifications Tabs -->
        <div class="content-box" style="margin-top: 40px;">
            <div class="spec-tabs">
                <button class="spec-tab active" onclick="switchTab('overview')">Overview</button>
                <button class="spec-tab" onclick="switchTab('specs')">Full Specifications</button>
                <button class="spec-tab" onclick="switchTab('performance')">Performance Analysis</button>
            </div>

            <div id="overview" class="tab-content active">
                <h3 style="margin-bottom: 15px;">Product Overview</h3>
                <p style="line-height: 1.8; color: #495057;">
                    The <strong><?php echo htmlspecialchars($product['product_name']); ?></strong> from 
                    <strong><?php echo htmlspecialchars($product['brand']); ?></strong> is designed for 
                    <strong><?php echo htmlspecialchars($product['primary_use_case']); ?></strong>. 
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </p>
                <div style="margin-top: 25px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                    <h4 style="margin-bottom: 10px;">Best For:</h4>
                    <ul style="line-height: 2; color: #495057;">
                        <?php
                        $use_case = $product['primary_use_case'];
                        if ($use_case == 'Gaming') {
                            echo "<li>AAA gaming titles with high FPS</li>";
                            echo "<li>VR and immersive experiences</li>";
                            echo "<li>Live streaming and content creation</li>";
                        } elseif ($use_case == 'Creative') {
                            echo "<li>Video editing and rendering</li>";
                            echo "<li>3D modeling and animation</li>";
                            echo "<li>Graphic design and photo editing</li>";
                        } elseif ($use_case == 'Business') {
                            echo "<li>Productivity applications</li>";
                            echo "<li>Video conferencing</li>";
                            echo "<li>Professional presentations</li>";
                        } elseif ($use_case == 'Student') {
                            echo "<li>Note-taking and research</li>";
                            echo "<li>Online learning</li>";
                            echo "<li>Document creation</li>";
                        } else {
                            echo "<li>Web browsing and email</li>";
                            echo "<li>Media streaming</li>";
                            echo "<li>Light multitasking</li>";
                        }
                        ?>
                    </ul>
                </div>
            </div>

            <div id="specs" class="tab-content">
                <h3 style="margin-bottom: 15px;">Complete Technical Specifications</h3>
                <table class="spec-table">
                    <tr>
                        <td>Brand</td>
                        <td><?php echo htmlspecialchars($product['brand']); ?></td>
                    </tr>
                    <tr>
                        <td>Model</td>
                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                    </tr>
                    <tr>
                        <td>Processor (CPU)</td>
                        <td><?php echo htmlspecialchars($product['cpu']); ?></td>
                    </tr>
                    <tr>
                        <td>Graphics (GPU)</td>
                        <td><?php echo htmlspecialchars($product['gpu']); ?></td>
                    </tr>
                    <tr>
                        <td>Memory (RAM)</td>
                        <td><?php echo htmlspecialchars($product['ram_gb']); ?> GB</td>
                    </tr>
                    <tr>
                        <td>Storage Capacity</td>
                        <td><?php echo htmlspecialchars($product['storage_gb']); ?> GB</td>
                    </tr>
                    <tr>
                        <td>Storage Type</td>
                        <td><?php echo htmlspecialchars($product['storage_type']); ?></td>
                    </tr>
                    <tr>
                        <td>Display Size</td>
                        <td><?php echo htmlspecialchars($product['display_size']); ?> inches</td>
                    </tr>
                    <tr>
                        <td>Primary Use Case</td>
                        <td><?php echo htmlspecialchars($product['primary_use_case']); ?></td>
                    </tr>
                    <tr>
                        <td>Price</td>
                        <td style="font-size: 1.2rem; font-weight: 600; color: #667eea;">
                            $<?php echo number_format($product['price'], 2); ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="performance" class="tab-content">
                <h3 style="margin-bottom: 15px;">Performance Analysis</h3>
                <div style="display: grid; gap: 20px;">
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h4 style="margin-bottom: 10px; color: #3b82f6;">💻 Processing Power</h4>
                        <p style="color: #495057; line-height: 1.6;">
                            Equipped with <strong><?php echo htmlspecialchars($product['cpu']); ?></strong>, 
                            this laptop provides 
                            <?php 
                            if (stripos($product['cpu'], 'i9') !== false || stripos($product['cpu'], 'Ryzen 9') !== false) {
                                echo "exceptional multi-core performance for the most demanding applications.";
                            } elseif (stripos($product['cpu'], 'i7') !== false || stripos($product['cpu'], 'Ryzen 7') !== false) {
                                echo "excellent performance for multitasking and professional applications.";
                            } elseif (stripos($product['cpu'], 'i5') !== false || stripos($product['cpu'], 'Ryzen 5') !== false) {
                                echo "balanced performance for everyday computing and moderate workloads.";
                            } else {
                                echo "reliable performance for standard computing tasks.";
                            }
                            ?>
                        </p>
                    </div>
                    
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h4 style="margin-bottom: 10px; color: #10b981;">🎮 Graphics Performance</h4>
                        <p style="color: #495057; line-height: 1.6;">
                            The <strong><?php echo htmlspecialchars($product['gpu']); ?></strong> 
                            <?php 
                            if (stripos($product['gpu'], 'RTX 40') !== false || stripos($product['gpu'], 'RTX 3090') !== false) {
                                echo "delivers cutting-edge graphics performance, perfect for 4K gaming and professional 3D work.";
                            } elseif (stripos($product['gpu'], 'RTX 30') !== false) {
                                echo "provides excellent graphics performance for gaming and creative applications.";
                            } elseif (stripos($product['gpu'], 'GTX') !== false) {
                                echo "handles gaming and light creative work with good performance.";
                            } else {
                                echo "is suitable for everyday tasks, video playback, and light photo editing.";
                            }
                            ?>
                        </p>
                    </div>
                    
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h4 style="margin-bottom: 10px; color: #f59e0b;">⚡ Memory & Storage</h4>
                        <p style="color: #495057; line-height: 1.6;">
                            With <strong><?php echo htmlspecialchars($product['ram_gb']); ?>GB RAM</strong>, 
                            you can run 
                            <?php 
                            if ($product['ram_gb'] >= 32) echo "numerous demanding applications simultaneously";
                            elseif ($product['ram_gb'] >= 16) echo "multiple applications smoothly with room for heavy multitasking";
                            elseif ($product['ram_gb'] >= 8) echo "several applications comfortably for everyday use";
                            else echo "essential applications for basic computing needs";
                            ?>. 
                            The <strong><?php echo htmlspecialchars($product['storage_gb']); ?>GB <?php echo htmlspecialchars($product['storage_type']); ?></strong> 
                            provides <?php echo $product['storage_gb'] >= 1000 ? 'ample' : 'sufficient'; ?> space 
                            with fast read/write speeds.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Similar Products Section -->
        <?php if (!empty($similar_products)): ?>
            <div class="similar-products-section">
                <h3>You May Also Like</h3>
                <div class="similar-grid">
                    <?php foreach ($similar_products as $similar): ?>
                        <div class="product-card">
                            <a href="product_details.php?product_id=<?php echo $similar['product_id']; ?>">
                                <img src="<?php echo !empty($similar['image_url']) ? htmlspecialchars($similar['image_url']) : 'https://via.placeholder.com/280'; ?>" 
                                     alt="<?php echo htmlspecialchars($similar['product_name']); ?>">
                                <div class="product-card-info">
                                    <p class="brand"><?php echo htmlspecialchars($similar['brand']); ?></p>
                                    <h3><?php echo htmlspecialchars($similar['product_name']); ?></h3>
                                    <p class="product-price">$<?php echo number_format($similar['price'], 2); ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-danger" style="text-align: center; padding: 60px 20px;">
            <h2>Product Not Found</h2>
            <p style="margin: 20px 0;">Sorry, the product you are looking for does not exist or the ID is invalid.</p>
            <a href="products.php" class="btn btn-primary">Browse All Products</a>
        </div>
    <?php endif; ?>
</div>

<script>
// Quantity selector
let quantity = 1;
// Set max quantity based on database stock (Use PHP to inject the value)
const maxStock = <?php echo (int)$product['stock_quantity']; ?>;
// Cap the manual limit at 10, or the stock if it's lower than 10
const maxLimit = Math.min(10, maxStock); 

function changeQuantity(change) {
    if (maxStock <= 0) return; // Do nothing if out of stock
    
    // Update logic to respect stock limit
    quantity = Math.max(1, Math.min(maxLimit, quantity + change));
    
    document.getElementById('quantityDisplay').textContent = quantity;
    document.getElementById('quantityInput').value = quantity;
}

// Tab switching
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.spec-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

// Animate performance meter on load
window.addEventListener('load', function() {
    const meterFill = document.querySelector('.meter-fill');
    if (meterFill) {
        const targetWidth = meterFill.style.width;
        meterFill.style.width = '0';
        setTimeout(() => {
            meterFill.style.width = targetWidth;
        }, 100);
    }
});

// Form submission with loading state
document.getElementById('cartForm').addEventListener('submit', function(e) {
    const btn = this.querySelector('.add-to-cart-btn');
    btn.textContent = 'Adding...';
    btn.disabled = true;
});
</script>

<?php include 'includes/footer.php'; ?>