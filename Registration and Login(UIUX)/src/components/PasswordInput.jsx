import { useState } from 'react';
import InputField from './InputField';
import { EyeIcon, EyeOffIcon } from './icons';

export default function PasswordInput({ label = 'Password', autoComplete = 'current-password', ...props }) {
  const [visible, setVisible] = useState(false);
  return (
    <InputField
      label={label}
      type={visible ? 'text' : 'password'}
      autoComplete={autoComplete}
      adornment={
        <button
          type="button"
          onClick={() => setVisible((v) => !v)}
          aria-label={visible ? `Hide ${label.toLowerCase()}` : `Show ${label.toLowerCase()}`}
          aria-pressed={visible}
          className="grid size-11 place-items-center rounded-md text-slate-500 transition hover:text-navy-800"
        >
          <span key={String(visible)} className="pop">{visible ? <EyeOffIcon /> : <EyeIcon />}</span>
        </button>
      }
      {...props}
    />
  );
}
