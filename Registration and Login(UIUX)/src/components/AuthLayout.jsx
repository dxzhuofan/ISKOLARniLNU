import { Link, Outlet, useLocation } from 'react-router-dom';
import { useRef } from 'react';
import CountUp from './CountUp';
import { CheckIcon } from './icons';

const STATS = [
  { to: 6, label: 'Scholarship Programs' },
  { to: 215, label: 'Available Slots' },
  { to: 40, prefix: '₱', suffix: 'K', label: 'Max Grant / Sem' },
  { to: 94, suffix: '%', label: 'OCR Accuracy' },
];
const PERKS = ['OCR Document Verification', 'Real-Time Status Tracking', 'Instant Notifications', 'Secure & Encrypted Portal'];

export default function AuthLayout() {
  const { pathname } = useLocation();
  const root = useRef(null);
  const isLogin = pathname === '/login';

  const onMove = (e) => {
    root.current?.style.setProperty('--mx', (e.clientX / window.innerWidth - 0.5).toFixed(3));
    root.current?.style.setProperty('--my', (e.clientY / window.innerHeight - 0.5).toFixed(3));
  };

  return (
    <div ref={root} onMouseMove={onMove} className="relative min-h-dvh overflow-x-hidden bg-navy-800 text-white">
      {/* Animated background */}
      <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div className="grid-bg absolute inset-0" />
        <div className="orb -left-24 top-24 size-[26rem] bg-gold-500/25" style={{ '--p': '50px' }} />
        <div className="orb -right-20 bottom-0 size-[30rem] bg-blue-500/25" style={{ '--p': '-35px', animationDelay: '-6s' }} />
        <div className="orb left-1/3 top-2/3 size-72 bg-gold-400/10" style={{ '--p': '25px', animationDelay: '-11s' }} />
      </div>

      <header className="relative z-10 border-b border-white/10">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-3">
          <Link to="/login" className="flex items-center gap-4 rounded-lg">
            <span className="grid size-14 shrink-0 place-items-center rounded-full border-2 border-gold-500 font-serif text-lg font-bold">LNU</span>
            <span>
              <span className="block font-serif text-xl font-bold leading-tight sm:text-2xl">Leyte Normal University</span>
              <span className="block text-[0.7rem] font-medium uppercase tracking-[0.18em] text-gold-400 sm:text-sm">Scholarship Management Portal</span>
            </span>
          </Link>
          <Link to={isLogin ? '/register' : '/login'} className="btn-outline shrink-0">{isLogin ? 'Create Account' : 'Sign In'}</Link>
        </div>
      </header>

      <main className="relative z-10 mx-auto grid max-w-7xl gap-12 px-4 py-8 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:items-center lg:py-14">
        <div className="hidden lg:block">
          <p className="rise inline-flex items-center gap-2.5 rounded-full border border-white/20 px-4 py-2 text-sm font-medium" style={{ '--d': '.5s' }}>
            <span className="relative flex size-2"><span className="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-75" /><span className="relative size-2 rounded-full bg-green-400" /></span>
            AY 2026–2027 Applications Now Open
          </p>
          <h2 className="mt-8 font-serif text-6xl font-bold leading-[1.1]">
            <span className="rise block" style={{ '--d': '.65s' }}>Fund Your Education.</span>
            <span className="rise gold-sheen block" style={{ '--d': '.85s' }}>Apply. Track. Succeed.</span>
          </h2>
          <p className="rise mt-8 max-w-xl text-xl leading-relaxed text-white/75" style={{ '--d': '1.05s' }}>
            The LNU Scholarship Portal lets you browse programs, apply online, upload documents with ML-powered OCR verification, and monitor your application status — all in one place.
          </p>
          <dl className="mt-12 grid max-w-xl grid-cols-4 gap-5">
            {STATS.map((s, i) => (
              <div key={s.label} className="rise" style={{ '--d': `${1.2 + i * 0.1}s` }}>
                <dt className="sr-only">{s.label}</dt>
                <dd className="font-serif text-4xl font-bold text-gold-500"><CountUp {...s} /></dd>
                <dd className="mt-1 text-sm text-white/60">{s.label}</dd>
              </div>
            ))}
          </dl>
          <ul className="mt-10 grid max-w-xl grid-cols-2 gap-x-6 gap-y-3 border-t border-white/10 pt-8 text-white/75">
            {PERKS.map((p, i) => (
              <li key={p} className="rise flex items-center gap-2.5" style={{ '--d': `${1.7 + i * 0.1}s` }}>
                <CheckIcon width={18} height={18} className="shrink-0 text-green-400" /> {p}
              </li>
            ))}
          </ul>
        </div>

        <div key={pathname} className="route-enter mx-auto w-full max-w-xl lg:max-w-none"><Outlet /></div>
      </main>

      <div key={`wipe-${pathname}`} className="route-wipe" aria-hidden="true" />
    </div>
  );
}
