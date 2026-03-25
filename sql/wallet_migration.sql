SET NAMES utf8mb4;
USE mercado_admin;

ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS delivered_by_buyer_at DATETIME NULL AFTER moderation_by,
  ADD COLUMN IF NOT EXISTS auto_release_at DATETIME NULL AFTER delivered_by_buyer_at,
  ADD COLUMN IF NOT EXISTS released_at DATETIME NULL AFTER auto_release_at,
  ADD COLUMN IF NOT EXISTS release_trigger VARCHAR(30) NULL AFTER released_at,
  ADD COLUMN IF NOT EXISTS escrow_fee_percent DECIMAL(5,2) NULL AFTER release_trigger,
  ADD COLUMN IF NOT EXISTS escrow_fee_amount DECIMAL(12,2) NULL AFTER escrow_fee_percent,
  ADD COLUMN IF NOT EXISTS escrow_net_amount DECIMAL(12,2) NULL AFTER escrow_fee_amount,
  ADD KEY idx_order_items_auto_release (auto_release_at),
  ADD KEY idx_order_items_released (released_at);

CREATE TABLE IF NOT EXISTS platform_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(30) NOT NULL DEFAULT 'blackcat',
    order_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    external_ref VARCHAR(191) NULL,
    provider_transaction_id VARCHAR(191) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    payment_method VARCHAR(20) NULL,
    amount_centavos INT UNSIGNED NOT NULL,
    net_centavos INT UNSIGNED NULL,
    fees_centavos INT UNSIGNED NULL,
    invoice_url VARCHAR(255) NULL,
    raw_response LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_provider_txn (provider_transaction_id),
    UNIQUE KEY uq_payment_provider_external_ref (provider, external_ref),
    KEY idx_payment_order (order_id),
    KEY idx_payment_user (user_id),
    KEY idx_payment_status (status),
    CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(30) NOT NULL,
    event_name VARCHAR(80) NOT NULL,
    idempotency_key VARCHAR(64) NOT NULL,
    payload LONGTEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'received',
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    UNIQUE KEY uq_webhook_idempotency (idempotency_key),
    KEY idx_webhook_provider_event (provider, event_name),
    KEY idx_webhook_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_settings (setting_key, setting_value)
VALUES
  ('wallet.auto_release_days', '7'),
  ('wallet.platform_fee_percent', '5.00'),
  ('wallet.auto_release_enabled', '1'),
  ('wallet.platform_admin_user_id', '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP;
