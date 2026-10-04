const base = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true };
export const EyeIcon = (p) => (<svg {...base} {...p}><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>);
export const EyeOffIcon = (p) => (<svg {...base} {...p}><path d="M9.9 5.2A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4M6.6 6.6A17 17 0 0 0 2 12s3.6 7 10 7a10 10 0 0 0 5.4-1.6" /><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2M2 2l20 20" /></svg>);
export const CheckIcon = (p) => (<svg {...base} {...p}><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>);
export const AlertIcon = (p) => (<svg {...base} {...p}><circle cx="12" cy="12" r="10" /><path d="M12 7v6M12 16.5v.5" /></svg>);
export const InfoIcon = (p) => (<svg {...base} {...p}><circle cx="12" cy="12" r="10" /><path d="M12 11v6M12 7.5v.5" /></svg>);
export const SpinnerIcon = (p) => (<svg {...base} className="animate-spin" {...p}><path d="M12 3a9 9 0 1 0 9 9" /></svg>);
