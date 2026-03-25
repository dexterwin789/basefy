const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');
const { Client } = require('pg');

function env(name, fallback = '') {
  const value = process.env[name];
  return (value === undefined || value === null || String(value).trim() === '') ? fallback : String(value);
}

const mysqlCfg = {
  host: env('MYSQL_HOST', env('LOCAL_DB_HOST', '127.0.0.1')),
  port: Number(env('MYSQL_PORT', env('LOCAL_DB_PORT', '3306'))),
  user: env('MYSQL_USER', env('LOCAL_DB_USER', 'root')),
  password: env('MYSQL_PASSWORD', env('LOCAL_DB_PASS', '')),
  database: env('MYSQL_DATABASE', env('LOCAL_DB_NAME', 'mercado_admin')),
};

const pgCfg = {
  host: env('PGHOST', env('POSTGRES_HOST', '')),
  port: Number(env('PGPORT', env('POSTGRES_PORT', '5432'))),
  user: env('PGUSER', env('POSTGRES_USER', 'postgres')),
  password: env('PGPASSWORD', env('POSTGRES_PASSWORD', '')),
  database: env('PGDATABASE', env('POSTGRES_DB', 'railway')),
  ssl: { rejectUnauthorized: false },
};

if (!pgCfg.host || !pgCfg.user || !pgCfg.database) {
  console.error('Defina PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD (ou POSTGRES_*)');
  process.exit(1);
}

const tables = [
  'users', 'categories', 'products', 'orders', 'order_items', 'platform_settings',
  'payment_transactions', 'webhook_events', 'seller_requests', 'seller_profiles',
  'wallet_withdrawals', 'wallet_transactions', 'wallets', 'sales', 'sale_action_logs',
  'admins', 'usuarios', 'vendedores'
];

const boolColumns = new Set([
  'users.ativo',
  'users.is_vendedor',
  'categories.ativo',
  'products.ativo',
  'usuarios.ativo',
  'vendedores.ativo'
]);

(async () => {
  let my;
  const pg = new Client(pgCfg);

  try {
    my = await mysql.createConnection(mysqlCfg);
    await pg.connect();

    const schemaPath = path.join(__dirname, '..', 'sql', 'schema.postgres.sql');
    const schemaSql = fs.readFileSync(schemaPath, 'utf8');
    await pg.query(schemaSql);

    await pg.query('BEGIN');
    await pg.query('SET session_replication_role = replica');

    for (const table of [...tables].reverse()) {
      await pg.query(`TRUNCATE TABLE "${table}" RESTART IDENTITY CASCADE`);
    }

    let totalRows = 0;
    const idTables = new Set();

    for (const table of tables) {
      const [rows] = await my.query(`SELECT * FROM \`${table}\``);

      if (!rows || rows.length === 0) {
        console.log(`[${table}] 0 linha(s)`);
        continue;
      }

      const cols = Object.keys(rows[0]);
      const colNames = cols.map((c) => `"${c}"`).join(',');
      const placeholders = cols.map((_, idx) => `$${idx + 1}`).join(',');
      const hasIdCol = cols.includes('id');
      const overriding = hasIdCol ? ' OVERRIDING SYSTEM VALUE' : '';
      const insertSql = `INSERT INTO "${table}" (${colNames})${overriding} VALUES (${placeholders})`;

      if (hasIdCol) {
        idTables.add(table);
      }

      for (const row of rows) {
        const values = cols.map((col) => {
          const key = `${table}.${col}`;
          const value = row[col];
          if (value === null || value === undefined) return null;
          if (boolColumns.has(key)) {
            return Number(value) === 1;
          }
          return value;
        });
        await pg.query(insertSql, values);
      }

      totalRows += rows.length;
      console.log(`[${table}] ${rows.length} linha(s)`);
    }

    for (const table of idTables) {
      const seqResult = await pg.query(`SELECT pg_get_serial_sequence('"${table}"', 'id') AS seq`);
      const seqName = seqResult.rows[0]?.seq;

      if (!seqName) continue;

      await pg.query(`SELECT setval('${seqName}', COALESCE((SELECT MAX(id) FROM "${table}"), 0) + 1, false)`);
    }

    await pg.query('SET session_replication_role = origin');
    await pg.query('COMMIT');

    console.log(`\nMigração concluída com sucesso. Total: ${totalRows} linha(s).`);
    await pg.end();
    await my.end();
    process.exit(0);
  } catch (err) {
    try {
      await pg.query('ROLLBACK');
      await pg.query('SET session_replication_role = origin');
    } catch (_) {}

    if (pg) {
      try { await pg.end(); } catch (_) {}
    }
    if (my) {
      try { await my.end(); } catch (_) {}
    }

    console.error('Erro na migração:', err.message);
    process.exit(1);
  }
})();
