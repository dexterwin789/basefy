-- ============================================================
--  DELIVERY CODE — adds verification code column to orders
-- ============================================================

ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_code VARCHAR(6) DEFAULT NULL;
CREATE INDEX IF NOT EXISTS idx_orders_delivery_code ON orders(delivery_code);
