import { createApp } from './app.js';
import { config } from './config.js';
import { pool } from './db.js';
import { applicantsRequireExtraFields } from './schemaCheck.js';

try {
  await pool.query('SELECT 1');
} catch (e) {
  console.error(`Cannot connect to MySQL at ${config.db.host}:${config.db.port} (${e.message}).\nCheck that MySQL is running and your .env DB_* values are correct.`);
  process.exit(1);
}
if (await applicantsRequireExtraFields(pool)) {
  console.warn('\n[warning] applicants.birth_date and/or sex are still NOT NULL, so student registration will fail.\n          Run sql/01_applicants_optional_fields.sql (ask your database owner) to fix this.\n');
}

const { app } = createApp();
app.listen(config.port, () => console.log(`Auth API running on http://localhost:${config.port}  (frontend origin: ${config.clientOrigin})`));
