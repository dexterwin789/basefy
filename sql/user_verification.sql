-- User Verification System
-- Stores verification status for each verification step

CREATE TABLE IF NOT EXISTS user_verifications (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER NOT NULL,
    tipo        VARCHAR(30) NOT NULL,       -- 'dados', 'telefone', 'email', 'documentos'
    status      VARCHAR(20) NOT NULL DEFAULT 'pendente', -- 'pendente', 'verificado', 'rejeitado'
    dados       TEXT,                       -- JSON with step-specific data
    observacao  TEXT,                       -- admin notes / rejection reason
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, tipo)
);

-- Document uploads for identity verification
CREATE TABLE IF NOT EXISTS user_verification_docs (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER NOT NULL,
    tipo_doc    VARCHAR(30) NOT NULL,       -- 'identidade', 'selfie', 'comprovante_residencia'
    status      VARCHAR(20) NOT NULL DEFAULT 'pendente',
    arquivo     TEXT,                       -- file path / media:ID
    observacao  TEXT,
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
