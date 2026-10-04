/** Animated check badge used for success states. */
export default function SuccessMark({ title, children }) {
  return (
    <div className="py-6 text-center" role="status">
      <div className="pop mx-auto grid size-20 place-items-center rounded-full bg-green-50 ring-8 ring-green-100">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#15803d" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path className="check-draw" d="m5 12.5 4.5 4.5L19 7.5" />
        </svg>
      </div>
      <h2 className="mt-5 font-serif text-2xl font-bold text-navy-950">{title}</h2>
      <div className="mx-auto mt-2 max-w-sm text-slate-600">{children}</div>
    </div>
  );
}
