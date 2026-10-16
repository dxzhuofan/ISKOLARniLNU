import { AlertIcon, CheckIcon, InfoIcon } from './icons';

const styles = {
  error: { box: 'border-red-200 bg-red-50 text-danger', Icon: AlertIcon, role: 'alert' },
  success: { box: 'border-green-200 bg-green-50 text-ok', Icon: CheckIcon, role: 'status' },
  info: { box: 'border-blue-200 bg-blue-50 text-navy-900', Icon: InfoIcon, role: 'note' },
};

export default function AlertMessage({ variant = 'info', children }) {
  const { box, Icon, role } = styles[variant];
  return (
    <div role={role} className={`field-error flex items-start gap-3 rounded-xl border px-4 py-3 text-[0.95rem] ${box}`}>
      <Icon className="mt-0.5 shrink-0" />
      <div>{children}</div>
    </div>
  );
}
