-- Sokna 1.36.4-dev.21 -> 1.36.4-dev.22
-- Daily human order numbers follow business_date (operational day), not midnight and not global AUTO_INCREMENT.
ALTER TABLE orders
    ADD COLUMN business_order_number INT UNSIGNED NULL AFTER created_by_user_id;
-- CAFE-STMT --
UPDATE orders o
JOIN (
    SELECT a.id,COUNT(b.id) business_order_number
    FROM orders a
    JOIN orders b
      ON b.business_date=a.business_date
     AND (b.created_at<a.created_at OR (b.created_at=a.created_at AND b.id<=a.id))
    GROUP BY a.id
) ranked ON ranked.id=o.id
SET o.business_order_number=ranked.business_order_number;
-- CAFE-STMT --
ALTER TABLE orders
    MODIFY COLUMN business_order_number INT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_orders_business_number (business_date,business_order_number);
-- CAFE-STMT --
CREATE TABLE order_business_sequences (
    business_date DATE PRIMARY KEY,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- CAFE-STMT --
INSERT INTO order_business_sequences(business_date,last_number)
SELECT business_date,MAX(business_order_number)
FROM orders
GROUP BY business_date
ON DUPLICATE KEY UPDATE last_number=GREATEST(last_number,VALUES(last_number));
