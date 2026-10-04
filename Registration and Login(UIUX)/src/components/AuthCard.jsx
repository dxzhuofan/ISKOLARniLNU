/** White form card with an animated gold top bar. `shake` replays on a failed submit. */
export default function AuthCard({ title, subtitle, top, children, footer, shake, onShakeEnd }) {
  return (
    <section onAnimationEnd={(e) => e.target === e.currentTarget && onShakeEnd?.()} className={`relative overflow-hidden rounded-3xl bg-white p-6 shadow-2xl shadow-black/30 sm:p-10 ${shake ? 'shake' : ''}`}>
      <div className="card-bar absolute inset-x-0 top-0 h-1.5" aria-hidden="true" />
      {top && <div className="mb-7">{top}</div>}
      <h1 className="font-serif text-3xl font-bold text-navy-950">{title}</h1>
      {subtitle && <p className="mt-1 text-lg text-slate-500">{subtitle}</p>}
      <div className="mt-7">{children}</div>
      {footer && <div className="mt-7 border-t border-slate-100 pt-6 text-center text-slate-500">{footer}</div>}
    </section>
  );
}
