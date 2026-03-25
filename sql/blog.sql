-- Blog System — posts + role-based visibility settings
-- Run after main schema

CREATE TABLE IF NOT EXISTS blog_posts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    author_id BIGINT NOT NULL REFERENCES users(id),
    titulo VARCHAR(200) NOT NULL,
    slug VARCHAR(210) NOT NULL,
    resumo VARCHAR(400),
    conteudo TEXT NOT NULL,
    imagem VARCHAR(255),
    categoria VARCHAR(100),
    status VARCHAR(20) NOT NULL DEFAULT 'rascunho',
    visualizacoes BIGINT NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX idx_blog_slug ON blog_posts(slug);
CREATE INDEX idx_blog_status ON blog_posts(status);
CREATE INDEX idx_blog_author ON blog_posts(author_id);
CREATE INDEX idx_blog_criado ON blog_posts(criado_em DESC);

-- Blog visibility settings are stored in platform_settings:
--   blog.enabled          = '1' or '0'  (global killswitch)
--   blog.visible_usuario  = '1' or '0'
--   blog.visible_vendedor = '1' or '0'
--   blog.visible_admin    = '1' or '0'
--   blog.visible_public   = '1' or '0'  (non-logged-in)
