import { Router } from 'express';

// Starter endpoints for the Student and Admin dashboard modules.
// requireRole() is applied in app.js, so everything in these routers is already role-protected.
export const studentRouter = Router();
studentRouter.get('/me', (req, res) => res.json({ user: req.user }));

export const adminRouter = Router();
adminRouter.get('/me', (req, res) => res.json({ user: req.user }));
