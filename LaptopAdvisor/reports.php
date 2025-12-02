<?php
require_once 'includes/auth_check.php';
include 'includes/header.php';

$view = $_GET['view'] ?? 'dashboard'; // Default to the dashboard view
$user_id = $_SESSION['user_id'];
?>

<div class="report-page-container">
    <!-- ===== Sidebar Navigation ===== -->
    <aside class="report-sidebar">
        <h3>Reports Menu</h3>
        <ul>
            <li><a href="?view=dashboard" class="<?php if ($view == 'dashboard') echo 'active'; ?>">📊 Dashboard</a></li>
            <li><a href="?view=purchase" class="<?php if ($view == 'purchase') echo 'active'; ?>">💳 Purchase Analysis</a></li>
            <li><a href="?view=recommendations" class="<?php if ($view == 'recommendations') echo 'active'; ?>">💡 Recommendation Insights</a></li>
        </ul>
    </aside>

    <!-- ===== Main Content Area ===== -->
    <main class="report-content">
        <?php if ($view == 'dashboard'): ?>
            <!-- ======================= -->
            <!-- == DASHBOARD VIEW    == -->
            <!-- ======================= -->
            <h1>Reports Dashboard</h1>
            <p class="subtitle">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Here's a summary of your activity.</p>
            
            <?php
            // Fetch summary stats
            $order_stmt = $conn->prepare("SELECT COUNT(order_id) as order_count, SUM(total_amount) as total_spent FROM orders WHERE user_id = ?");
            $order_stmt->bind_param("i", $user_id);
            $order_stmt->execute();
            $summary = $order_stmt->get_result()->fetch_assoc();
            $order_stmt->close();
            
            $rating_stmt = $conn->prepare("SELECT COUNT(rating_id) as rating_count FROM recommendation_ratings WHERE user_id = ?");
            $rating_stmt->bind_param("i", $user_id);
            $rating_stmt->execute();
            $rating_count = $rating_stmt->get_result()->fetch_assoc()['rating_count'];
            $rating_stmt->close();
            ?>

            <div class="stat-card-grid">
                <div class="stat-card"><h4>Total Spent</h4><p>$<?php echo number_format($summary['total_spent'] ?? 0, 2); ?></p></div>
                <div class="stat-card"><h4>Orders Placed</h4><p><?php echo $summary['order_count'] ?? 0; ?></p></div>
                <div class="stat-card"><h4>Products Rated</h4><p><?php echo $rating_count ?? 0; ?></p></div>
            </div>

            <div class="content-box" style="margin-top: 2rem;">
                <h3>Recent Activity</h3>
                <p>Your last 3 orders. View your <a href="profile.php">full order history here</a>.</p>
                <?php
                // Fetch recent orders
                $recent_orders = [];
                $ro_stmt = $conn->prepare("SELECT order_id, total_amount, order_date FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 3");
                $ro_stmt->bind_param("i", $user_id);
                $ro_stmt->execute();
                $ro_result = $ro_stmt->get_result();
                while ($row = $ro_result->fetch_assoc()) $recent_orders[] = $row;
                $ro_stmt->close();
                ?>
                <?php if (!empty($recent_orders)): ?>
                    <ul class="activity-list">
                        <?php foreach($recent_orders as $order): ?>
                            <li>
                                <strong>Order #<?php echo $order['order_id']; ?></strong> placed on <?php echo date("F j, Y", strtotime($order['order_date'])); ?>
                                <span>$<?php echo number_format($order['total_amount'], 2); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No recent orders to display.</p>
                <?php endif; ?>
            </div>

        <?php elseif ($view == 'purchase'): ?>
            <!-- ======================= -->
            <!-- == PURCHASE REPORT   == -->
            <!-- ======================= -->
            <h1>Purchase Analysis</h1>
            <p class="subtitle">A detailed breakdown of your spending habits.</p>
            <?php
            $sql = "SELECT p.brand, oi.price_at_purchase, oi.quantity FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN products p ON oi.product_id = p.product_id WHERE o.user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $total_spent = 0; $spending_by_brand = [];
            while ($row = $result->fetch_assoc()) {
                $line_total = $row['price_at_purchase'] * $row['quantity'];
                $total_spent += $line_total;
                $spending_by_brand[$row['brand']] = ($spending_by_brand[$row['brand']] ?? 0) + $line_total;
            }
            $stmt->close();
            arsort($spending_by_brand);
            ?>
            <div class="chart-container content-box">
                <h3>Spending by Brand</h3>
                <?php if (!empty($spending_by_brand)): ?>
                    <canvas id="purchaseChart"></canvas>
                <?php else: ?>
                    <p>No purchase data available to generate a chart.</p>
                <?php endif; ?>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                const ctx = document.getElementById('purchaseChart');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode(array_keys($spending_by_brand)); ?>,
                            datasets: [{
                                label: 'Spent ($)',
                                data: <?php echo json_encode(array_values($spending_by_brand)); ?>,
                                backgroundColor: ['#0d6efd', '#6c757d', '#198754', '#dc3545', '#ffc107'],
                                hoverOffset: 4
                            }]
                        }
                    });
                }
            </script>

        <?php elseif ($view == 'recommendations'): ?>
            <!-- ======================= -->
            <!-- == RECOMMENDATION REPORT == -->
            <!-- ======================= -->
            <h1>Recommendation Insights</h1>
            <p class="subtitle">An overview of your preferences and feedback.</p>
            <?php
            $user_stmt = $conn->prepare("SELECT primary_use_case FROM users WHERE user_id = ?");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_pref = $user_stmt->get_result()->fetch_assoc()['primary_use_case'];
            $user_stmt->close();

            $liked_products = []; $disliked_products = [];
            $rating_sql = "SELECT p.product_id, p.product_name, p.brand, r.rating FROM recommendation_ratings r JOIN products p ON r.product_id = p.product_id WHERE r.user_id = ?";
            $rating_stmt = $conn->prepare($rating_sql);
            $rating_stmt->bind_param("i", $user_id);
            $rating_stmt->execute();
            $rating_result = $rating_stmt->get_result();
            while($row = $rating_result->fetch_assoc()) {
                if ($row['rating'] == 1) $liked_products[] = $row;
                if ($row['rating'] == -1) $disliked_products[] = $row;
            }
            ?>
             <div class="content-box">
                <h3>Your Advisor Persona</h3>
                <p>Your primary interest is set to <strong><?php echo htmlspecialchars($user_pref); ?></strong>. Our "For You" recommendations are tailored to this preference. You can change this in your <a href="edit_profile.php">profile</a>.</p>
            </div>

            <div class="insight-columns">
                <div class="insight-column content-box">
                    <h3>Products You Liked 👍</h3>
                    <?php if(!empty($liked_products)): ?>
                        <ul class="product-insight-list"><?php foreach($liked_products as $p) echo "<li><a href='product_details.php?product_id={$p['product_id']}'><strong>{$p['product_name']}</strong> ({$p['brand']})</a></li>"; ?></ul>
                    <?php else: ?><p>You haven't liked any recommendations yet.</p><?php endif; ?>
                </div>
                 <div class="insight-column content-box">
                    <h3>Products You Disliked 👎</h3>
                    <?php if(!empty($disliked_products)): ?>
                         <ul class="product-insight-list"><?php foreach($disliked_products as $p) echo "<li><a href='product_details.php?product_id={$p['product_id']}'><strong>{$p['product_name']}</strong> ({$p['brand']})</a></li>"; ?></ul>
                    <?php else: ?><p>You haven't disliked any recommendations yet.</p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>