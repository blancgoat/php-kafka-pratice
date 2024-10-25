SET NAMES utf8mb4;

INSERT INTO orders (order_id, user_id, total_amount, payment_method, `status`) VALUES
('ORD_001', 'USER_001', 50000.00, 'CARD', 'PENDING'),
('ORD_002', 'USER_002', 75000.00, 'BANK', 'PENDING'),
('ORD_003', 'USER_003', 30000.00, 'CARD', 'PENDING'),
('ORD_004', 'USER_001', 45000.00, 'BANK', 'COMPLETED'),
('ORD_005', 'USER_002', 60000.00, 'CARD', 'PROCESSING'),
('ORD_006', 'USER_003', 25000.00, 'CARD', 'FAILED');

INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES
('ORD_001', 'PROD_001', '티셔츠', 2, 25000.00),
('ORD_002', 'PROD_002', '청바지', 1, 75000.00),
('ORD_003', 'PROD_003', '모자', 3, 10000.00),
('ORD_004', 'PROD_001', '티셔츠', 1, 25000.00),
('ORD_004', 'PROD_003', '모자', 2, 10000.00),
('ORD_005', 'PROD_002', '청바지', 1, 60000.00),
('ORD_006', 'PROD_003', '모자', 5, 5000.00);
