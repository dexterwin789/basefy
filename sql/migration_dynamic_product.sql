-- Migration: Add support for dynamic product types with variants
-- Adds 'variantes' JSON column to products table

ALTER TABLE products ADD COLUMN IF NOT EXISTS variantes TEXT DEFAULT NULL;
-- variantes stores a JSON array: [{"nome":"Option A","preco":29.90,"quantidade":10}, ...]
-- Only used when tipo = 'dinamico'

COMMENT ON COLUMN products.variantes IS 'JSON array of variant objects [{nome, preco, quantidade}] for tipo=dinamico';
