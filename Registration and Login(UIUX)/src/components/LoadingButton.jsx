import { SpinnerIcon } from './icons';

export default function LoadingButton({ loading, loadingText, children, ...props }) {
  return (
    <button type="submit" className="btn-primary" disabled={loading} aria-busy={loading} {...props}>
      {loading && <SpinnerIcon />}
      <span className="relative">{loading ? loadingText : children}</span>
    </button>
  );
}
