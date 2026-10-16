import { PASSWORD_RULES } from '../utils/validators';
import { CheckIcon } from './icons';

const LEVELS = [
  { label: 'Too weak', bar: 'bg-slate-300' },
  { label: 'Weak', bar: 'bg-red-500' },
  { label: 'Fair', bar: 'bg-amber-500' },
  { label: 'Good', bar: 'bg-lime-600' },
  { label: 'Strong', bar: 'bg-green-600' },
];

export default function PasswordStrengthIndicator({ password = '' }) {
  const passed = PASSWORD_RULES.filter((r) => r.test(password)).length;
  const level = password ? LEVELS[passed] : LEVELS[0];
  return (
    <div className="mt-3" aria-live="polite">
      <div className="flex items-center gap-3">
        <div className="flex flex-1 gap-1.5" aria-hidden="true">
          {PASSWORD_RULES.map((r, i) => (
            <span key={r.id} className={`h-1.5 flex-1 rounded-full transition-all duration-500 ${i < passed && password ? level.bar : 'bg-slate-200'}`} />
          ))}
        </div>
        <span className="w-16 text-right text-sm font-semibold text-slate-600">{password ? level.label : ''}</span>
      </div>
      <ul className="mt-2 grid gap-x-4 gap-y-1 text-sm sm:grid-cols-2">
        {PASSWORD_RULES.map((r) => {
          const ok = r.test(password);
          return (
            <li key={r.id} className={`flex items-center gap-2 transition-colors ${ok ? 'text-ok' : 'text-slate-500'}`}>
              <span className={`grid size-4 place-items-center rounded-full border transition-all ${ok ? 'border-ok bg-ok text-white' : 'border-slate-300'}`}>
                {ok && <CheckIcon width={12} height={12} strokeWidth={3} className="pop" />}
              </span>
              {r.label}
              <span className="sr-only">{ok ? ' (met)' : ' (not met)'}</span>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
