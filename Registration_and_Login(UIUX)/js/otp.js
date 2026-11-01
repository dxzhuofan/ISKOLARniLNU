(() => {
  const button = document.getElementById('resendButton');
  const code = document.getElementById('code');
  if (code) {
    code.addEventListener('input', () => {
      code.value = code.value.replace(/\D/g, '').slice(0, 6);
    });
    code.addEventListener('paste', (event) => {
      event.preventDefault();
      code.value = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
    });
  }
  if (!button) return;
  let remaining = Number(button.dataset.wait) || 0;
  const update = () => {
    button.disabled = remaining > 0;
    button.textContent = remaining > 0 ? `Resend code in ${remaining}s` : 'Resend code';
  };
  update();
  if (remaining > 0) {
    const timer = window.setInterval(() => {
      remaining -= 1;
      update();
      if (remaining <= 0) window.clearInterval(timer);
    }, 1000);
  }
})();
