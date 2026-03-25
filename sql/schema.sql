SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS mercado_admin
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mercado_admin;

DROP VIEW IF EXISTS saques;

DROP TABLE IF EXISTS sale_action_logs;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS wallet_transactions;
DROP TABLE IF EXISTS wallet_withdrawals;
DROP TABLE IF EXISTS wallets;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS seller_profiles;
DROP TABLE IF EXISTS seller_requests;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS vendedores;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'usuario',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    is_vendedor TINYINT(1) NOT NULL DEFAULT 0,
    status_vendedor VARCHAR(20) NOT NULL DEFAULT 'nao_solicitado',
    wallet_saldo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_ativo (ativo),
    KEY idx_users_status_vendedor (status_vendedor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'produto',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_categories_tipo (tipo),
    KEY idx_categories_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendedor_id INT UNSIGNED NOT NULL,
    categoria_id INT UNSIGNED NOT NULL,
    nome VARCHAR(160) NOT NULL,
    descricao TEXT NULL,
    preco DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    imagem VARCHAR(255) NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'produto',
    quantidade INT UNSIGNED NOT NULL DEFAULT 0,
    prazo_entrega_dias INT UNSIGNED NULL,
    data_entrega DATE NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_products_vendedor (vendedor_id),
    KEY idx_products_categoria (categoria_id),
    KEY idx_products_ativo (ativo),
    KEY idx_products_tipo (tipo),
    CONSTRAINT fk_products_vendedor FOREIGN KEY (vendedor_id) REFERENCES users(id),
    CONSTRAINT fk_products_categoria FOREIGN KEY (categoria_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gross_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    wallet_used DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_orders_user (user_id),
    KEY idx_orders_status (status),
    KEY idx_orders_data (criado_em),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    vendedor_id INT UNSIGNED NOT NULL,
    quantidade INT UNSIGNED NOT NULL DEFAULT 1,
    preco_unit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    moderation_status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    moderation_motivo VARCHAR(255) NULL,
    moderation_at DATETIME NULL,
    moderation_by INT UNSIGNED NULL,
    delivered_by_buyer_at DATETIME NULL,
    auto_release_at DATETIME NULL,
    released_at DATETIME NULL,
    release_trigger VARCHAR(30) NULL,
    escrow_fee_percent DECIMAL(5,2) NULL,
    escrow_fee_amount DECIMAL(12,2) NULL,
    escrow_net_amount DECIMAL(12,2) NULL,
    delivery_content TEXT NULL,
    delivered_at DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id),
    KEY idx_order_items_vendedor (vendedor_id),
    KEY idx_order_items_moderation (moderation_status),
    KEY idx_order_items_auto_release (auto_release_at),
    KEY idx_order_items_released (released_at),
    KEY idx_order_items_data (criado_em),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_order_items_vendedor FOREIGN KEY (vendedor_id) REFERENCES users(id),
    CONSTRAINT fk_order_items_moderation_by FOREIGN KEY (moderation_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_transactions (
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

CREATE TABLE webhook_events (
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

CREATE TABLE seller_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    motivo_recusa TEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_seller_requests_user (user_id),
    KEY idx_seller_requests_status (status),
    CONSTRAINT fk_seller_requests_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seller_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    nome_loja VARCHAR(160) NULL,
    documento VARCHAR(50) NULL,
    telefone VARCHAR(40) NULL,
    bio TEXT NULL,
    chave_pix VARCHAR(191) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_seller_profiles_user (user_id),
    CONSTRAINT fk_seller_profiles_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_withdrawals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    chave_pix VARCHAR(191) NULL,
    tipo_chave VARCHAR(30) NULL,
    observacao TEXT NULL,
    transaction_id VARCHAR(60) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_wallet_withdrawals_user (user_id),
    KEY idx_wallet_withdrawals_status (status),
    KEY idx_wallet_withdrawals_data (criado_em),
    CONSTRAINT fk_wallet_withdrawals_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    origem VARCHAR(50) NOT NULL,
    referencia_tipo VARCHAR(50) NULL,
    referencia_id INT UNSIGNED NULL,
    valor DECIMAL(12,2) NOT NULL,
    descricao VARCHAR(255) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wallet_transactions_user (user_id),
    KEY idx_wallet_transactions_tipo (tipo),
    KEY idx_wallet_transactions_origem (origem),
    KEY idx_wallet_transactions_data (criado_em),
    CONSTRAINT fk_wallet_transactions_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    saldo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wallets_user (user_id),
    CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT UNSIGNED NULL,
    comprador_id INT UNSIGNED NULL,
    vendedor_id INT UNSIGNED NULL,
    valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status_moderacao VARCHAR(20) NOT NULL DEFAULT 'pendente',
    motivo VARCHAR(255) NULL,
    moderado_por INT UNSIGNED NULL,
    moderado_em DATETIME NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_order_item (order_item_id),
    KEY idx_sales_vendedor (vendedor_id),
    KEY idx_sales_status (status_moderacao),
    CONSTRAINT fk_sales_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_comprador FOREIGN KEY (comprador_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_vendedor FOREIGN KEY (vendedor_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_moderado_por FOREIGN KEY (moderado_por) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_action_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id INT UNSIGNED NOT NULL,
    acao VARCHAR(30) NOT NULL,
    motivo VARCHAR(255) NULL,
    admin_id INT UNSIGNED NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sale_logs_sale (sale_id),
    KEY idx_sale_logs_admin (admin_id),
    CONSTRAINT fk_sale_logs_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_logs_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    email VARCHAR(190) NOT NULL,
    name VARCHAR(120) NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admins_email (email),
    UNIQUE KEY uq_admins_user (user_id),
    CONSTRAINT fk_admins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    foto_perfil VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendedores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    status_vendedor VARCHAR(20) NOT NULL DEFAULT 'pendente',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vendedores_email (email),
    UNIQUE KEY uq_vendedores_user (user_id),
    CONSTRAINT fk_vendedores_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE VIEW saques AS
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
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL DEFAULT 'product',
    entity_id INT NOT NULL DEFAULT 0,
    file_data LONGTEXT NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'image/jpeg',
    original_name VARCHAR(255) DEFAULT NULL,
    is_cover TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_media_entity (entity_type, entity_id),
    INDEX idx_media_cover (entity_type, entity_id, is_cover)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;