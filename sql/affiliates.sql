-- ============================================================
--  AFFILIATE SYSTEM — PostgreSQL schema
-- ============================================================

-- Affiliate profiles
CREATE TABLE IF NOT EXISTS affiliates (
    id              SERIAL PRIMARY KEY,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    referral_code   VARCHAR(32) NOT NULL UNIQUE,
    status          VARCHAR(20) NOT NULL DEFAULT 'pendente',    -- pendente | ativo | suspenso | rejeitado
    custom_rate     NUMERIC(5,2) DEFAULT NULL,                  -- NULL = use global rate
    total_clicks    INT NOT NULL DEFAULT 0,
    total_conversions INT NOT NULL DEFAULT 0,
    total_earned    NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_paid      NUMERIC(12,2) NOT NULL DEFAULT 0,
    pix_key_type    VARCHAR(20) DEFAULT NULL,
    pix_key         VARCHAR(255) DEFAULT NULL,
    bio             TEXT DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_affiliates_user UNIQUE (user_id)
);

-- Affiliate click tracking
CREATE TABLE IF NOT EXISTS affiliate_clicks (
    id              SERIAL PRIMARY KEY,
    affiliate_id    INT NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    product_id      INT DEFAULT NULL,
    ip_address      VARCHAR(45) DEFAULT NULL,
    user_agent      TEXT DEFAULT NULL,
    referrer_url    TEXT DEFAULT NULL,
    landing_url     TEXT DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Affiliate conversions (linked to orders)
CREATE TABLE IF NOT EXISTS affiliate_conversions (
    id              SERIAL PRIMARY KEY,
    affiliate_id    INT NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    order_id        INT NOT NULL,
    buyer_id        INT NOT NULL,
    order_total     NUMERIC(12,2) NOT NULL DEFAULT 0,
    commission_rate NUMERIC(5,2) NOT NULL DEFAULT 0,
    commission_amount NUMERIC(12,2) NOT NULL DEFAULT 0,
    status          VARCHAR(20) NOT NULL DEFAULT 'pendente',    -- pendente | aprovada | paga | cancelada
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at         TIMESTAMP DEFAULT NULL,
    CONSTRAINT uq_affiliate_order UNIQUE (affiliate_id, order_id)
);

-- Affiliate payout requests
CREATE TABLE IF NOT EXISTS affiliate_payouts (
    id              SERIAL PRIMARY KEY,
    affiliate_id    INT NOT NULL REFERENCES affiliates(id) ON DELETE CASCADE,
    amount          NUMERIC(12,2) NOT NULL,
    pix_key_type    VARCHAR(20) NOT NULL,
    pix_key         VARCHAR(255) NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'pendente',    -- pendente | aprovado | pago | rejeitado
    admin_notes     TEXT DEFAULT NULL,
    requested_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at    TIMESTAMP DEFAULT NULL,
    processed_by    INT DEFAULT NULL
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_aff_clicks_aff    ON affiliate_clicks(affiliate_id);
CREATE INDEX IF NOT EXISTS idx_aff_clicks_dt     ON affiliate_clicks(created_at);
CREATE INDEX IF NOT EXISTS idx_aff_conv_aff      ON affiliate_conversions(affiliate_id);
CREATE INDEX IF NOT EXISTS idx_aff_conv_order    ON affiliate_conversions(order_id);
CREATE INDEX IF NOT EXISTS idx_aff_conv_status   ON affiliate_conversions(status);
CREATE INDEX IF NOT EXISTS idx_aff_payouts_aff   ON affiliate_payouts(affiliate_id);
CREATE INDEX IF NOT EXISTS idx_aff_payouts_status ON affiliate_payouts(status);
