-- Chat System Migration
-- Conversations between buyers and vendors

-- Add chat_enabled column to seller_profiles
ALTER TABLE seller_profiles ADD COLUMN IF NOT EXISTS chat_enabled BOOLEAN NOT NULL DEFAULT TRUE;

-- Conversations table
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
CREATE UNIQUE INDEX IF NOT EXISTS idx_chat_conv_unique ON chat_conversations(buyer_id, vendor_id, COALESCE(product_id, 0));

-- Messages table
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
