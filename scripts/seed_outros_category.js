const { Client } = require('pg');

function readStdin() {
  return new Promise((resolve) => {
    let input = '';
    process.stdin.setEncoding('utf8');
    process.stdin.on('data', (chunk) => { input += chunk; });
    process.stdin.on('end', () => resolve(input.trim()));
  });
}

function dbConfig(env) {
  if (env.DATABASE_PUBLIC_URL) {
    return {
      connectionString: env.DATABASE_PUBLIC_URL,
      ssl: { rejectUnauthorized: false },
    };
  }

  if (env.DATABASE_URL) {
    return {
      connectionString: env.DATABASE_URL,
      ssl: { rejectUnauthorized: false },
    };
  }

  return {
    host: env.PGHOST || env.DB_HOST,
    port: Number(env.PGPORT || env.DB_PORT || 5432),
    user: env.PGUSER || env.DB_USERNAME,
    password: env.PGPASSWORD || env.DB_PASSWORD,
    database: env.PGDATABASE || env.DB_DATABASE,
    ssl: { rejectUnauthorized: false },
  };
}

(async () => {
  const stdin = await readStdin();
  const railwayEnv = stdin ? JSON.parse(stdin) : {};
  const env = { ...process.env, ...railwayEnv };

  const client = new Client(dbConfig(env));
  await client.connect();

  const name = 'Outros';
  const slug = 'outros';
  const type = 'produto';
  const image = 'categories/outros-neon.svg';

  try {
    await client.query('BEGIN');
    await client.query('ALTER TABLE categories ADD COLUMN IF NOT EXISTS slug VARCHAR(191)');
    await client.query('CREATE UNIQUE INDEX IF NOT EXISTS idx_categories_slug ON categories(slug)');
    await client.query('ALTER TABLE categories ADD COLUMN IF NOT EXISTS imagem TEXT DEFAULT NULL');
    await client.query('ALTER TABLE categories ADD COLUMN IF NOT EXISTS destaque BOOLEAN NOT NULL DEFAULT FALSE');

    const existing = await client.query(
      `SELECT id
         FROM categories
        WHERE slug = $1 OR LOWER(nome) = LOWER($2)
        ORDER BY CASE WHEN slug = $1 THEN 0 ELSE 1 END, id ASC
        LIMIT 1`,
      [slug, name]
    );

    if (existing.rows.length > 0) {
      const id = Number(existing.rows[0].id);
      await client.query(
        `UPDATE categories
            SET nome = $1,
                tipo = $2,
                ativo = TRUE,
                slug = $3,
                imagem = $4
          WHERE id = $5`,
        [name, type, slug, image, id]
      );
      await client.query('COMMIT');
      console.log(`Outros category updated: #${id}`);
      return;
    }

    const inserted = await client.query(
      `INSERT INTO categories (nome, tipo, ativo, slug, imagem, destaque)
       VALUES ($1, $2, TRUE, $3, $4, FALSE)
       RETURNING id`,
      [name, type, slug, image]
    );

    await client.query('COMMIT');
    console.log(`Outros category created: #${inserted.rows[0].id}`);
  } catch (error) {
    await client.query('ROLLBACK').catch(() => {});
    throw error;
  } finally {
    await client.end();
  }
})().catch((error) => {
  console.error('Erro:', error.message);
  process.exit(1);
});