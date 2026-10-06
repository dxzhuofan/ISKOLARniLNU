import 'dotenv/config';

const env = process.env;
const num = (v, d) => (v !== undefined && v !== '' && Number.isFinite(Number(v)) ? Number(v) : d);

export const config = {
  isProd: env.NODE_ENV === 'production',
  port: num(env.PORT, 4000),
  clientOrigin: env.CLIENT_ORIGIN || 'http://localhost:5173',
  db: {
    host: env.DB_HOST || '127.0.0.1',
    port: num(env.DB_PORT, 3306),
    user: env.DB_USER || 'root',
    password: env.DB_PASSWORD || '',
    database: env.DB_NAME || 'lnu_scholarship_db',
  },
  sessionSecret: env.SESSION_SECRET,
  idleMinutes: num(env.SESSION_IDLE_MINUTES, 30),
  rememberDays: num(env.SESSION_REMEMBER_DAYS, 7),
  maxFailedLogins: num(env.MAX_FAILED_LOGINS, 5),
  lockMinutes: num(env.LOCK_MINUTES, 15),
  bcryptRounds: num(env.BCRYPT_ROUNDS, 12),
  rateLimitEnabled: env.RATE_LIMIT_ENABLED !== 'false',
};

if (!config.sessionSecret || config.sessionSecret.startsWith('change-me')) {
  if (config.isProd) throw new Error('SESSION_SECRET must be set to a long random value in production.');
  config.sessionSecret = 'dev-only-secret-not-for-production';
}
