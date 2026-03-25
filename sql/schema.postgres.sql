CREATE SCHEMA IF NOT EXISTS public;

DROP VIEW IF EXISTS saques;

DROP TABLE IF EXISTS sale_action_logs CASCADE;
DROP TABLE IF EXISTS sales CASCADE;
DROP TABLE IF EXISTS wallet_transactions CASCADE;
DROP TABLE IF EXISTS wallet_withdrawals CASCADE;
DROP TABLE IF EXISTS wallets CASCADE;
DROP TABLE IF EXISTS webhook_events CASCADE;
DROP TABLE IF EXISTS payment_transactions CASCADE;
DROP TABLE IF EXISTS platform_settings CASCADE;
DROP TABLE IF EXISTS order_items CASCADE;
DROP TABLE IF EXISTS orders CASCADE;
DROP TABLE IF EXISTS products CASCADE;
DROP TABLE IF EXISTS seller_profiles CASCADE;
DROP TABLE IF EXISTS seller_requests CASCADE;
DROP TABLE IF EXISTS categories CASCADE;
DROP TABLE IF EXISTS vendedores CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;
DROP TABLE IF EXISTS admins CASCADE;
DROP TABLE IF EXISTS users CASCADE;

CREATE TABLE users (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    avatar VARCHAR(255),
    role VARCHAR(30) NOT NULL DEFAULT 'usuario',
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    is_vendedor BOOLEAN NOT NULL DEFAULT FALSE,
    status_vendedor VARCHAR(20) NOT NULL DEFAULT 'nao_solicitado',
    wallet_saldo NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_ativo ON users(ativo);
CREATE INDEX idx_users_status_vendedor ON users(status_vendedor);

CREATE TABLE categories (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'produto',
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_categories_tipo ON categories(tipo);
CREATE INDEX idx_categories_ativo ON categories(ativo);

CREATE TABLE products (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    vendedor_id BIGINT NOT NULL REFERENCES users(id),
    categoria_id BIGINT NOT NULL REFERENCES categories(id),
    nome VARCHAR(160) NOT NULL,
    slug VARCHAR(191),
    descricao TEXT,
    preco NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    imagem VARCHAR(255),
    tipo VARCHAR(20) NOT NULL DEFAULT 'produto',
    quantidade INT NOT NULL DEFAULT 0,
    prazo_entrega_dias INT,
    data_entrega DATE,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    auto_delivery_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    auto_delivery_items TEXT,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_products_vendedor ON products(vendedor_id);
CREATE INDEX idx_products_categoria ON products(categoria_id);
CREATE INDEX idx_products_ativo ON products(ativo);
CREATE INDEX idx_products_tipo ON products(tipo);
CREATE UNIQUE INDEX idx_products_slug ON products(slug);

CREATE TABLE orders (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    total NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    gross_total NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    wallet_used NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_data ON orders(criado_em);

CREATE TABLE order_items (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id BIGINT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id BIGINT NOT NULL REFERENCES products(id),
    vendedor_id BIGINT NOT NULL REFERENCES users(id),
    quantidade BIGINT NOT NULL DEFAULT 1,
    preco_unit NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    subtotal NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    moderation_status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    moderation_motivo VARCHAR(255),
    moderation_at TIMESTAMP,
    moderation_by BIGINT REFERENCES users(id),
    delivered_by_buyer_at TIMESTAMP,
    auto_release_at TIMESTAMP,
    released_at TIMESTAMP,
    release_trigger VARCHAR(30),
    escrow_fee_percent NUMERIC(5,2),
    escrow_fee_amount NUMERIC(12,2),
    escrow_net_amount NUMERIC(12,2),
    delivery_content TEXT,
    delivered_at TIMESTAMP,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_order_items_product ON order_items(product_id);
CREATE INDEX idx_order_items_vendedor ON order_items(vendedor_id);
CREATE INDEX idx_order_items_moderation ON order_items(moderation_status);
CREATE INDEX idx_order_items_auto_release ON order_items(auto_release_at);
CREATE INDEX idx_order_items_released ON order_items(released_at);
CREATE INDEX idx_order_items_data ON order_items(criado_em);

CREATE TABLE platform_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE payment_transactions (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    provider VARCHAR(30) NOT NULL DEFAULT 'blackcat',
    order_id BIGINT REFERENCES orders(id) ON DELETE SET NULL,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    external_ref VARCHAR(191),
    provider_transaction_id VARCHAR(191),
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    payment_method VARCHAR(20),
    amount_centavos BIGINT NOT NULL,
    net_centavos BIGINT,
    fees_centavos BIGINT,
    invoice_url VARCHAR(255),
    raw_response TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(provider, external_ref),
    UNIQUE(provider_transaction_id)
);
CREATE INDEX idx_payment_order ON payment_transactions(order_id);
CREATE INDEX idx_payment_user ON payment_transactions(user_id);
CREATE INDEX idx_payment_status ON payment_transactions(status);

CREATE TABLE webhook_events (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    provider VARCHAR(30) NOT NULL,
    event_name VARCHAR(80) NOT NULL,
    idempotency_key VARCHAR(64) NOT NULL UNIQUE,
    payload TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'received',
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP
);
CREATE INDEX idx_webhook_provider_event ON webhook_events(provider, event_name);
CREATE INDEX idx_webhook_status ON webhook_events(status);

CREATE TABLE seller_requests (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    motivo_recusa TEXT,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_seller_requests_user ON seller_requests(user_id);
CREATE INDEX idx_seller_requests_status ON seller_requests(status);

CREATE TABLE seller_profiles (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE REFERENCES users(id),
    nome_loja VARCHAR(160),
    documento VARCHAR(50),
    telefone VARCHAR(40),
    bio TEXT,
    chave_pix VARCHAR(191),
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE wallet_withdrawals (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    valor NUMERIC(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    chave_pix VARCHAR(191),
    tipo_chave VARCHAR(30),
    observacao TEXT,
    transaction_id VARCHAR(60),
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_wallet_withdrawals_user ON wallet_withdrawals(user_id);
CREATE INDEX idx_wallet_withdrawals_status ON wallet_withdrawals(status);
CREATE INDEX idx_wallet_withdrawals_data ON wallet_withdrawals(criado_em);

CREATE TABLE wallet_transactions (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    tipo VARCHAR(20) NOT NULL,
    origem VARCHAR(50) NOT NULL,
    referencia_tipo VARCHAR(50),
    referencia_id BIGINT,
    valor NUMERIC(12,2) NOT NULL,
    descricao VARCHAR(255),
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_wallet_transactions_user ON wallet_transactions(user_id);
CREATE INDEX idx_wallet_transactions_tipo ON wallet_transactions(tipo);
CREATE INDEX idx_wallet_transactions_origem ON wallet_transactions(origem);
CREATE INDEX idx_wallet_transactions_data ON wallet_transactions(criado_em);

CREATE TABLE wallets (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE REFERENCES users(id),
    saldo NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sales (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_item_id BIGINT UNIQUE REFERENCES order_items(id) ON DELETE SET NULL,
    comprador_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    vendedor_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    valor NUMERIC(12,2) NOT NULL DEFAULT 0.00,
    status_moderacao VARCHAR(20) NOT NULL DEFAULT 'pendente',
    motivo VARCHAR(255),
    moderado_por BIGINT REFERENCES users(id) ON DELETE SET NULL,
    moderado_em TIMESTAMP,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_sales_vendedor ON sales(vendedor_id);
CREATE INDEX idx_sales_status ON sales(status_moderacao);

CREATE TABLE sale_action_logs (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    sale_id BIGINT NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
    acao VARCHAR(30) NOT NULL,
    motivo VARCHAR(255),
    admin_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_sale_logs_sale ON sale_action_logs(sale_id);
CREATE INDEX idx_sale_logs_admin ON sale_action_logs(admin_id);

CREATE TABLE admins (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT UNIQUE REFERENCES users(id) ON DELETE SET NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE usuarios (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    foto_perfil VARCHAR(255),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE vendedores (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT UNIQUE REFERENCES users(id) ON DELETE SET NULL,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    status_vendedor VARCHAR(20) NOT NULL DEFAULT 'pendente',
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE VIEW saques AS
SELECT
    id,
    user_id AS vendedor_id,
    valor,
    status,
    chave_pix,
    observacao,
    criado_em
FROM wallet_withdrawals;

-- Media files (DB-stored images, survives deploys)
CREATE TABLE IF NOT EXISTS media_files (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL DEFAULT 'product',
    entity_id INT NOT NULL DEFAULT 0,
    file_data TEXT NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'image/jpeg',
    original_name VARCHAR(255),
    is_cover BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_media_entity ON media_files(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_media_cover ON media_files(entity_type, entity_id, is_cover);

-- Chat System
ALTER TABLE seller_profiles ADD COLUMN IF NOT EXISTS chat_enabled BOOLEAN NOT NULL DEFAULT TRUE;

CREATE TABLE IF NOT EXISTS chat_conversations (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    buyer_id BIGINT NOT NULL REFERENCES users(id),
    vendor_id BIGINT NOT NULL REFERENCES users(id),
    product_id BIGINT REFERENCES products(id),
    last_message_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    buyer_archived BOOLEAN NOT NULL DEFAULT FALSE,
    vendor_archived BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_chat_conv_buyer ON chat_conversations(buyer_id);
CREATE INDEX IF NOT EXISTS idx_chat_conv_vendor ON chat_conversations(vendor_id);
CREATE INDEX IF NOT EXISTS idx_chat_conv_last_msg ON chat_conversations(last_message_at DESC);

CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    conversation_id BIGINT NOT NULL REFERENCES chat_conversations(id) ON DELETE CASCADE,
    sender_id BIGINT NOT NULL REFERENCES users(id),
    message TEXT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_chat_msg_conv ON chat_messages(conversation_id);
CREATE INDEX IF NOT EXISTS idx_chat_msg_sender ON chat_messages(sender_id);
CREATE INDEX IF NOT EXISTS idx_chat_msg_read ON chat_messages(conversation_id, is_read);
CREATE INDEX IF NOT EXISTS idx_chat_msg_date ON chat_messages(criado_em DESC);

-- ── User Favorites / Wishlist ──
CREATE TABLE IF NOT EXISTS user_favorites (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, product_id)
);
CREATE INDEX IF NOT EXISTS idx_user_favorites_user ON user_favorites(user_id);
CREATE INDEX IF NOT EXISTS idx_user_favorites_product ON user_favorites(product_id);
