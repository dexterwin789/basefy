const { Client } = require('pg');

const pgCfg = {
  host: process.env.PGHOST,
  port: Number(process.env.PGPORT || 5432),
  user: process.env.PGUSER,
  password: process.env.PGPASSWORD,
  database: process.env.PGDATABASE,
  ssl: { rejectUnauthorized: false },
};

const tables = [
  'users', 'categories', 'products', 'orders', 'order_items', 'platform_settings',
  'payment_transactions', 'webhook_events', 'seller_requests', 'seller_profiles',
  'wallet_withdrawals', 'wallet_transactions', 'wallets', 'sales', 'sale_action_logs',
  'admins', 'usuarios', 'vendedores'
];

(async () => {
  const client = new Client(pgCfg);
  await client.connect();

  for (const table of tables) {
    const result = await client.query(`SELECT COUNT(*)::int AS c FROM "${table}"`);
    console.log(`${table}: ${result.rows[0].c}`);
  }

  const seqCheckTables = ['users', 'orders', 'wallet_transactions', 'payment_transactions'];
  console.log('\nSequence check:');
  for (const table of seqCheckTables) {
    const seqResult = await client.query(`SELECT pg_get_serial_sequence('"${table}"', 'id') AS seq`);
    const seqName = seqResult.rows[0]?.seq;
    if (!seqName) {
      console.log(`${table}: sem sequence`);
      continue;
    }

    const maxResult = await client.query(`SELECT COALESCE(MAX(id), 0) AS max_id FROM "${table}"`);
    const currentResult = await client.query(`SELECT last_value, is_called FROM ${seqName}`);
    const maxId = Number(maxResult.rows[0].max_id);
    const lastValue = Number(currentResult.rows[0].last_value);
    const isCalled = currentResult.rows[0].is_called;
    const nextExpected = maxId + 1;
    const nextFromSeq = isCalled ? lastValue + 1 : lastValue;

    console.log(`${table}: max_id=${maxId}, next_seq=${nextFromSeq}, esperado=${nextExpected}`);
  }

  await client.end();
})().catch((error) => {
  console.error('Erro:', error.message);
  process.exit(1);
});
