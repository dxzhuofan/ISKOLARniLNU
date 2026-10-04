import { useEffect, useState } from 'react';

export default function CountUp({ to, prefix = '', suffix = '', duration = 1600, delay = 900 }) {
  const [n, setN] = useState(0);
  useEffect(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return setN(to);
    let raf;
    const t0 = performance.now() + delay;
    const tick = (now) => {
      const p = Math.min(Math.max((now - t0) / duration, 0), 1);
      setN(Math.round(to * (1 - Math.pow(1 - p, 3))));
      if (p < 1) raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [to, duration, delay]);
  return <>{prefix}{n}{suffix}</>;
}
