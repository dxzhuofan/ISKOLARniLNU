import { useId } from 'react';
import { AlertIcon } from './icons';

/** Labelled text input with inline error. `labelRight` sits beside the label, `adornment` inside the input. */
export default function InputField({ label, error, hint, labelRight, adornment, className = '', id: idProp, ...props }) {
  const auto = useId();
  const id = idProp || auto;
  const describedBy = [error && `${id}-error`, hint && `${id}-hint`].filter(Boolean).join(' ') || undefined;

  return (
    <div className={`group ${className}`}>
      <div className="mb-1.5 flex items-center justify-between gap-2">
        <label htmlFor={id} className="text-[0.8rem] font-semibold uppercase tracking-wide text-navy-900 transition-colors group-focus-within:text-link">
          {label}
        </label>
        {labelRight}
      </div>
      <div className="relative">
        <input
          id={id}
          aria-invalid={error ? 'true' : 'false'}
          aria-describedby={describedBy}
          className={`w-full rounded-lg border bg-white px-4 py-3.5 text-base text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-4 ${adornment ? 'pr-12' : ''} ${
            error ? 'border-danger focus:ring-danger/15' : 'border-slate-300 hover:border-slate-400 focus:border-navy-700 focus:ring-navy-700/15'
          }`}
          {...props}
        />
        {adornment && <div className="absolute inset-y-0 right-1 flex items-center">{adornment}</div>}
      </div>
      {hint && !error && <p id={`${id}-hint`} className="mt-1.5 text-sm text-slate-500">{hint}</p>}
      {error && (
        <p id={`${id}-error`} role="alert" className="field-error mt-1.5 flex items-start gap-1.5 text-sm font-medium text-danger">
          <AlertIcon width={16} height={16} className="mt-0.5 shrink-0" />
          {error}
        </p>
      )}
    </div>
  );
}
