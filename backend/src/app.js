import express from 'express';
import helmet from 'helmet';
import cors from 'cors';
import session from 'express-session';
import MySQLStoreFactory from 'express-mysql-session';
import { config } from './config.js';
import { pool } from './db.js';
import authRouter from './routes/auth.js';
import { adminRouter, studentRouter } from './routes/protected.js';
import { requireRole } from './middleware/auth.js';

export function createApp() {
  const app = express();
  if (config.isProd) app.set('trust proxy', 1); // behind a reverse proxy / HTTPS terminator
  app.disable('x-powered-by');
  app.use(helmet());
  app.use(cors({ origin: config.clientOrigin, credentials: true }));
  app.use(express.json({ limit: '10kb' }));

  // CSRF defence: state-changing requests must come from our own frontend origin.
  app.use((req, res, next) => {
    if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) return next();
    const origin = req.get('origin');
    if (origin && origin !== config.clientOrigin) return res.status(403).json({ message: 'Request origin not allowed.' });
    next();
  });

  const MySQLStore = MySQLStoreFactory(session);
  const store = new MySQLStore({ createDatabaseTable: true, clearExpired: true, checkExpirationInterval: 15 * 60 * 1000 }, pool);
  app.use(session({
    name: 'iskolar.sid',
    secret: config.sessionSecret,
    store,
    resave: false,
    saveUninitialized: false,
    rolling: true, // every request pushes the idle timeout forward
    cookie: { httpOnly: true, sameSite: 'lax', secure: config.isProd, maxAge: config.idleMinutes * 60 * 1000 },
  }));

  app.get('/health', (req, res) => res.json({ ok: true }));
  app.use('/auth', authRouter);
  app.use('/api/student', requireRole('student'), studentRouter);
  app.use('/api/admin', requireRole('administrator'), adminRouter);

  app.use((req, res) => res.status(404).json({ message: 'Not found.' }));
  // eslint-disable-next-line no-unused-vars
  app.use((err, req, res, next) => {
    if (err.type === 'entity.parse.failed') return res.status(400).json({ message: 'Invalid JSON.' });
    console.error(err);
    res.status(500).json({ message: 'Something went wrong. Please try again.' });
  });

  return { app, store };
}
