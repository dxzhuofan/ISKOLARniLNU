// Creates the throw-away test database (never touches your real lnu_scholarship_db).
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import mysql from 'mysql2/promise';

process.env.DB_NAME = process.env.TEST_DB_NAME || 'lnu_scholarship_test';
const { config } = await import('../src/config.js');
if (!config.db.database.endsWith('_test')) throw new Error('Test database name must end with _test');

const dir = path.dirname(fileURLToPath(import.meta.url));
const read = (p) => fs.readFileSync(path.join(dir, p), 'utf8');
const { database, ...rest } = config.db;
const conn = await mysql.createConnection({ ...rest, multipleStatements: true });
await conn.query(`DROP DATABASE IF EXISTS \`${database}\`; CREATE DATABASE \`${database}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; USE \`${database}\`;`);
await conn.query(read('../test/fixtures/schema.sql'));
await conn.query(read('../sql/01_applicants_optional_fields.sql')); // the change the team agreed to
await conn.end();
console.log(`Test database ${database} ready.`);
