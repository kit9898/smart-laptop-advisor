-- =============================================
-- Update Permissions Table to Match Actual Admin Pages
-- Smart Laptop Advisor - RBAC Update
-- =============================================

-- First, let's clear existing permissions and add ones that match actual pages
-- CAUTION: This will reset all role_permissions. Run with care!

-- Step 1: Clear existing role_permissions
DELETE FROM role_permissions;

-- Step 2: Clear existing permissions  
DELETE FROM permissions;

-- Step 3: Insert permissions matching actual admin pages
-- Module: Dashboard (admin_dashboard.php)
INSERT INTO permissions (permission_id, permission_name, permission_code, module, description) VALUES
(1, 'View Dashboard', 'dashboard.view', 'Dashboard', 'Access the main dashboard');

-- Module: Product (admin_products.php, admin_inventory.php, admin_reviews.php, admin_coupons.php)
INSERT INTO permissions (permission_id, permission_name, permission_code, module, description) VALUES
(2, 'View Products', 'product.view', 'Product', 'View product listings'),
(3, 'Create Products', 'product.create', 'Product', 'Create new products'),
(4, 'Edit Products', 'product.edit', 'Product', 'Edit existing products'),
(5, 'Delete Products', 'product.delete', 'Product', 'Delete products'),
(6, 'Manage Inventory', 'inventory.manage', 'Product', 'Manage inventory levels'),
(7, 'Manage Reviews', 'review.manage', 'Product', 'Manage product reviews'),
(8, 'Manage Coupons', 'coupon.manage', 'Product', 'Manage discount coupons');

-- Module: Order (admin_orders.php, admin_transactions.php)
INSERT INTO permissions (permission_id, permission_name, permission_code, module, description) VALUES
(9, 'View Orders', 'order.view', 'Order', 'View order listings'),
(10, 'Process Orders', 'order.process', 'Order', 'Process and update order status'),
(11, 'Manage Transactions', 'transaction.manage', 'Order', 'View and manage payment transactions');

-- Module: AI (admin_personas.php, admin_recommendation_logs.php, admin_ai_performance.php)
INSERT INTO permissions (permission_id, permission_name, permission_code, module, description) VALUES
(12, 'Manage Personas', 'persona.manage', 'AI', 'Manage AI personas'),
(13, 'View AI Logs', 'ai.logs', 'AI', 'View recommendation logs'),
(14, 'View AI Performance', 'ai.performance', 'AI', 'View AI performance analytics');

-- Module: Chatbot (admin_conversation_logs.php, admin_chatbot_analytics.php)
INSERT INTO permissions (permission_id, permission_name, permission_code, module, description) VALUES
(15, 'View Conversations', 'chatbot.conversations', 'Chatbot', 'View chatbot conversation logs'),
(16, 'View Chatbot Analytics', 'chatbot.analytics', 'Chatbot', 'View chatbot analytics');

-- Module: User (admin_customers.php, admin_admins.php, admin_roles.php)
INSERT INTO permissions (permission_id, permission_name, permission_code, module, description) VALUES
(17, 'View Customers', 'customer.view', 'User', 'View customer accounts'),
(18, 'Edit Customers', 'customer.edit', 'User', 'Edit customer information'),
(19, 'View Admins', 'admin.view', 'User', 'View administrator accounts'),
(20, 'Manage Admins', 'admin.manage', 'User', 'Create and manage admin accounts'),
(21, 'Manage Roles', 'role.manage', 'User', 'Manage roles and permissions');

-- Module: System (admin_logs.php, admin_settings.php, admin_reports.php, admin_profile.php)
INSERT INTO permissions (permission_id, permission_name, permission_code, module, description) VALUES
(22, 'View System Logs', 'logs.view', 'System', 'View system activity logs'),
(23, 'View Settings', 'settings.view', 'System', 'View system settings'),
(24, 'Edit Settings', 'settings.edit', 'System', 'Edit system settings'),
(25, 'View Reports', 'reports.view', 'System', 'View and generate reports'),
(26, 'Export Data', 'data.export', 'System', 'Export system data');

-- =============================================
-- Step 4: Set up role permissions
-- =============================================

-- Super Admin (role_id = 1) - Full access to everything
INSERT INTO role_permissions (role_id, permission_id, access_level)
SELECT 1, permission_id, 'full' FROM permissions;

-- Product Manager (role_id = 2) - Product-related access
INSERT INTO role_permissions (role_id, permission_id, access_level) VALUES
(2, 1, 'read'),   -- Dashboard (view only)
(2, 2, 'full'),   -- View Products
(2, 3, 'full'),   -- Create Products
(2, 4, 'full'),   -- Edit Products
(2, 5, 'full'),   -- Delete Products
(2, 6, 'full'),   -- Manage Inventory
(2, 7, 'full'),   -- Manage Reviews
(2, 8, 'full');   -- Manage Coupons

-- Order Manager (role_id = 3) - Order-related access
INSERT INTO role_permissions (role_id, permission_id, access_level) VALUES
(3, 1, 'read'),   -- Dashboard (view only)
(3, 9, 'full'),   -- View Orders
(3, 10, 'full'),  -- Process Orders
(3, 11, 'full'),  -- Manage Transactions
(3, 17, 'read');  -- View Customers (read only)

-- AI Administrator (role_id = 4) - AI-related access
INSERT INTO role_permissions (role_id, permission_id, access_level) VALUES
(4, 1, 'read'),   -- Dashboard (view only)
(4, 12, 'full'),  -- Manage Personas
(4, 13, 'full'),  -- View AI Logs
(4, 14, 'full');  -- View AI Performance

-- Chatbot Manager (role_id = 5) - Chatbot-related access
INSERT INTO role_permissions (role_id, permission_id, access_level) VALUES
(5, 1, 'read'),   -- Dashboard (view only)
(5, 15, 'full'),  -- View Conversations
(5, 16, 'full');  -- View Chatbot Analytics

-- Customer Manager (role_id = 6) - Customer-related access
INSERT INTO role_permissions (role_id, permission_id, access_level) VALUES
(6, 1, 'read'),   -- Dashboard (view only)
(6, 9, 'read'),   -- View Orders (read only)
(6, 17, 'full'),  -- View Customers
(6, 18, 'full');  -- Edit Customers

-- Reports Analyst (role_id = 7) - Reports-related access
INSERT INTO role_permissions (role_id, permission_id, access_level) VALUES
(7, 1, 'read'),   -- Dashboard (view only)
(7, 25, 'full'),  -- View Reports
(7, 26, 'full');  -- Export Data

-- Viewer (role_id = 8) - Read-only access to dashboard
INSERT INTO role_permissions (role_id, permission_id, access_level) VALUES
(8, 1, 'read');   -- Dashboard (view only)
