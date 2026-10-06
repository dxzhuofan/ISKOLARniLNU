import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const WARN_SECONDS = 60;
const EVENTS = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];

/** Warns before the idle timeout, then signs the user out. Keeps the server session alive while the user is active. */
export default function IdleTimeout() {
  const { user, idleSeconds, logout, refreshSession } = useAuth();
  const navigate = useNavigate();
  const [left, setLeft] = useState(null); // seconds remaining while the warning is visible
  const last = useRef(Date.now());
  const lastPing = useRef(Date.now());
  const warning = useRef(false);
  const total = idleSeconds * 1000;

  useEffect(() => {
    if (!user) return undefined;
    last.current = lastPing.current = Date.now();
    warning.current = false;
    setLeft(null);
    const warnAt = Math.max(total - WARN_SECONDS * 1000, total / 2);

    const onActivity = () => {
      if (warning.current) return; // once the warning shows, the user must choose
      const now = Date.now();
      last.current = now;
      if (now - lastPing.current > total / 6) { lastPing.current = now; refreshSession(); } // renew the server session
    };
    EVENTS.forEach((e) => window.addEventListener(e, onActivity, { passive: true }));

    const timer = setInterval(() => {
      const idle = Date.now() - last.current;
      if (idle >= total) {
        clearInterval(timer);
        logout('idle').then(() => navigate('/login', { replace: true }));
      } else if (idle >= warnAt) {
        warning.current = true;
        setLeft(Math.ceil((total - idle) / 1000));
      }
    }, 1000);

    return () => { clearInterval(timer); EVENTS.forEach((e) => window.removeEventListener(e, onActivity)); };
  }, [user, total]); // eslint-disable-line react-hooks/exhaustive-deps

  if (left === null) return null;

  const stay = async () => {
    warning.current = false;
    last.current = lastPing.current = Date.now();
    setLeft(null);
    await refreshSession();
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4">
      <div role="alertdialog" aria-modal="true" aria-labelledby="idle-title" aria-describedby="idle-desc" className="pop w-full max-w-md rounded-2xl bg-white p-7 text-center shadow-2xl">
        <h2 id="idle-title" className="font-serif text-2xl font-bold text-navy-950">Still there?</h2>
        <p id="idle-desc" className="mt-2 text-slate-600">
          For your security, you’ll be signed out in <strong className="tabular-nums text-danger">{left}</strong> second{left === 1 ? '' : 's'} due to inactivity.
        </p>
        <div className="mt-6 grid gap-3 sm:grid-cols-2">
          <button autoFocus onClick={stay} className="btn-primary">Stay signed in</button>
          <button onClick={() => logout().then(() => navigate('/login', { replace: true }))} className="inline-flex min-h-12 items-center justify-center rounded-lg border border-slate-300 px-5 font-semibold text-navy-800 transition hover:bg-slate-50">
            Sign out
          </button>
        </div>
      </div>
    </div>
  );
}
