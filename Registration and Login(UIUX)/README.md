# LNU Scholarship Portal: Registration & Login (UI/UX)

React + Vite + Tailwind CSS v4 + React Router. No other runtime libraries.

## Run
```bash
npm install
npm run dev      # http://localhost:5173
npm run build    # production build in /dist
```

## Routes
`/login` · `/register` · `/forgot-password` · `/reset-password?token=...` · `/student` · `/admin`

## Demo mode (development only)
With `npm run dev` and no `VITE_API_URL`, the login page shows the demo banner and a simulated backend answers.
- Student tab: any valid email + password. Administrator tab: use an email containing `admin`. Picking the wrong tab shows the role-mismatch error.
- Password `wrongpass` shows the invalid-credentials state. `taken@lnu.edu.ph` shows the duplicate-account state.
- Production builds never include demo mode: with no API configured they show "service not connected".

## Connecting the backend (see `/backend` in the repo)
Copy `.env.example` to `.env` (it points to `http://localhost:4000`), start the API, then restart `npm run dev`. Without a `.env`, the app stays in demo mode.

### Role-based behaviour in the UI
- Student | Administrator tabs; the chosen role is sent to the server, which must confirm it (wrong tab shows an error).
- `/student` and `/admin` are protected. Opening the other role's page shows a **403 Access denied** page, and the API enforces the same rule.
- After the idle timeout (server-defined; default 30 min) a 60-second warning appears, then the user is signed out and told why on the login page.
- Registration requires accepting the Data Privacy Notice and Terms of Use (`/privacy`, `/terms` are placeholders until LNU provides the official text).

All backend calls live in `src/services/authService.js` (`loginUser`, `registerStudent`, `requestPasswordReset`, `resetPassword`, `logoutUser`, `getSession`). Copy `.env.example` to `.env`, set `VITE_API_URL`, and match the endpoint contract in the header comment of that file.
- The login card has Student | Administrator tabs. The chosen role is sent with the login request, and the backend must confirm the account really has that role (reject with 403 otherwise). The returned role then decides the redirect.
- Public registration only creates student accounts. Create administrators through a secure database/admin process.
- Hash passwords on the backend, validate all input again server-side, and use httpOnly + Secure session cookies.
- Frontend validation never replaces backend validation. `ProtectedRoute` is only a UI guard.
- The forgot-password message is intentionally the same whether or not the account exists.

## Structure
```
src/components  AuthLayout, AuthCard, InputField, PasswordInput, PasswordStrengthIndicator,
                LoadingButton, AlertMessage, SuccessMark, CountUp, ProtectedRoute
src/pages       Login, Register, ForgotPassword, ResetPassword, Dashboards (placeholders)
src/services    authService.js   (only file that calls the API)
src/context     AuthContext.jsx  (session state)
src/hooks       useForm.js
src/utils       validators.js
```

## Animation
Animated background with mouse parallax, staged hero entrance, count-up stats, route wipe transition, shake on failed submit, animated check mark, strength meter, and button sheen. All of it is disabled under `prefers-reduced-motion`.
