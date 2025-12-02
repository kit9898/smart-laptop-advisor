// --- ENHANCED RECOMMENDATION LOGIC ---
// Replace lines 81-107 in your products.php with this code:

if ($view == 'recommendations') {
    $user_id = $_SESSION['user_id'];
    $user_stmt = $conn->prepare("SELECT primary_use_case FROM users WHERE user_id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_pref = $user_stmt->get_result()->fetch_assoc()['primary_use_case'] ?? 'General Use';
    $user_stmt->close();
    
    // Advanced recommendation engine with multi-factor scoring
    $sql = "SELECT p.*, 
            COALESCE(r.rating, 0) as user_rating,
            
            -- User Rating Score (40% weight)
            (CASE 
                WHEN r.rating = 1 THEN 40
                WHEN r.rating IS NULL THEN 20
                ELSE 0
            END) as rating_score,
            
            -- Spec Quality Score based on use case (30% weight)
            (CASE 
                -- Gaming: Prioritize RAM, GPU, and high-end CPU
                WHEN p.primary_use_case = 'Gaming' THEN
                    (CASE 
                        WHEN p.ram_gb >= 32 THEN 15
                        WHEN p.ram_gb >= 16 THEN 12
                        WHEN p.ram_gb >= 8 THEN 8
                        ELSE 5
                    END) +
                    (CASE 
                        WHEN p.gpu LIKE '%RTX%' OR p.gpu LIKE '%RX%' THEN 15
                        WHEN p.gpu LIKE '%GTX%' OR p.gpu LIKE '%Radeon%' THEN 10
                        ELSE 5
                    END)
                    
                -- Business/Professional: Prioritize CPU, RAM, and storage
                WHEN p.primary_use_case = 'Business' THEN
                    (CASE 
                        WHEN p.cpu LIKE '%i7%' OR p.cpu LIKE '%Ryzen 7%' THEN 15
                        WHEN p.cpu LIKE '%i5%' OR p.cpu LIKE '%Ryzen 5%' THEN 12
                        ELSE 8
                    END) +
                    (CASE 
                        WHEN p.ram_gb >= 16 THEN 15
                        WHEN p.ram_gb >= 8 THEN 10
                        ELSE 5
                    END)
                    
                -- Student: Balance of specs and value
                WHEN p.primary_use_case = 'Student' THEN
                    (CASE 
                        WHEN p.ram_gb >= 16 AND p.storage_gb >= 512 THEN 20
                        WHEN p.ram_gb >= 8 AND p.storage_gb >= 256 THEN 15
                        ELSE 10
                    END) +
                    (CASE 
                        WHEN p.price < 1000 THEN 10
                        WHEN p.price < 1500 THEN 5
                        ELSE 0
                    END)
                    
                -- General Use: Balanced specs
                ELSE
                    (CASE 
                        WHEN p.ram_gb >= 8 AND p.storage_gb >= 256 THEN 20
                        WHEN p.ram_gb >= 4 THEN 15
                        ELSE 10
                    END) +
                    (CASE 
                        WHEN p.price < 800 THEN 10
                        ELSE 5
                    END)
            END) as spec_score,
            
            -- Value Score: Price-to-Performance ratio (20% weight)
            (CASE 
                WHEN p.price < 800 THEN 20
                WHEN p.price < 1200 THEN 15
                WHEN p.price < 1800 THEN 10
                ELSE 5
            END) as value_score,
            
            -- Popularity Score: Based on how many users liked it (10% weight)
            (SELECT COUNT(*) * 2 FROM recommendation_ratings rr 
             WHERE rr.product_id = p.product_id AND rr.rating = 1) as popularity_score,
            
            -- Total Recommendation Score (sum of all factors)
            (
                (CASE 
                    WHEN r.rating = 1 THEN 40
                    WHEN r.rating IS NULL THEN 20
                    ELSE 0
                END) +
                (CASE 
                    WHEN p.primary_use_case = 'Gaming' THEN
                        (CASE 
                            WHEN p.ram_gb >= 32 THEN 15
                            WHEN p.ram_gb >= 16 THEN 12
                            WHEN p.ram_gb >= 8 THEN 8
                            ELSE 5
                        END) +
                        (CASE 
                            WHEN p.gpu LIKE '%RTX%' OR p.gpu LIKE '%RX%' THEN 15
                            WHEN p.gpu LIKE '%GTX%' OR p.gpu LIKE '%Radeon%' THEN 10
                            ELSE 5
                        END)
                    WHEN p.primary_use_case = 'Business' THEN
                        (CASE 
                            WHEN p.cpu LIKE '%i7%' OR p.cpu LIKE '%Ryzen 7%' THEN 15
                            WHEN p.cpu LIKE '%i5%' OR p.cpu LIKE '%Ryzen 5%' THEN 12
                            ELSE 8
                        END) +
                        (CASE 
                            WHEN p.ram_gb >= 16 THEN 15
                            WHEN p.ram_gb >= 8 THEN 10
                            ELSE 5
                        END)
                    WHEN p.primary_use_case = 'Student' THEN
                        (CASE 
                            WHEN p.ram_gb >= 16 AND p.storage_gb >= 512 THEN 20
                            WHEN p.ram_gb >= 8 AND p.storage_gb >= 256 THEN 15
                            ELSE 10
                        END) +
                        (CASE 
                            WHEN p.price < 1000 THEN 10
                            WHEN p.price < 1500 THEN 5
                            ELSE 0
                        END)
                    ELSE
                        (CASE 
                            WHEN p.ram_gb >= 8 AND p.storage_gb >= 256 THEN 20
                            WHEN p.ram_gb >= 4 THEN 15
                            ELSE 10
                        END) +
                        (CASE 
                            WHEN p.price < 800 THEN 10
                            ELSE 5
                        END)
                END) +
                (CASE 
                    WHEN p.price < 800 THEN 20
                    WHEN p.price < 1200 THEN 15
                    WHEN p.price < 1800 THEN 10
                    ELSE 5
                END) +
                (SELECT COUNT(*) * 2 FROM recommendation_ratings rr 
                 WHERE rr.product_id = p.product_id AND rr.rating = 1)
            ) as total_recommendation_score
            
            FROM products p 
            LEFT JOIN recommendation_ratings r ON p.product_id = r.product_id AND r.user_id = ?
            WHERE p.primary_use_case = ? AND (r.rating IS NULL OR r.rating != -1)
            ORDER BY total_recommendation_score DESC, p.ram_gb DESC, p.price ASC
            LIMIT 12";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $user_pref);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_results = $result->num_rows;
}
