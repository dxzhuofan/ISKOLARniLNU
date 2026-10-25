(() => {
  'use strict';
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Toasts are feedback only; authentication outcomes still come from PHP/database responses.
  const toastHost = document.createElement('div');
  toastHost.className = 'toast-host';
  toastHost.setAttribute('aria-live', 'polite');
  toastHost.setAttribute('aria-atomic', 'false');
  document.body.appendChild(toastHost);

  function showToast(message, type = 'success') {
    if (!message) return;
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    const mark = document.createElement('span');
    mark.className = 'toast-mark';
    mark.textContent = type === 'error' ? '!' : '✓';
    const text = document.createElement('span');
    text.className = 'toast-message';
    text.textContent = message;
    const close = document.createElement('button');
    close.className = 'toast-close';
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.textContent = '×';
    close.addEventListener('click', () => toast.remove());
    toast.append(mark, text, close);
    toastHost.appendChild(toast);
    window.setTimeout(() => {
      toast.classList.add('toast-leave');
      window.setTimeout(() => toast.remove(), reduceMotion ? 0 : 220);
    }, 5200);
  }

  document.querySelectorAll('.alert').forEach((alert) => {
    const message = alert.innerText.trim();
    if (message) showToast(message, alert.classList.contains('error') ? 'error' : 'success');
  });

  document.querySelectorAll('[data-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.toggle);
      if (!input) return;
      const reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
      button.classList.toggle('revealed', reveal);
    });
  });

  const roleInput = document.getElementById('roleInput');
  const roleTabs = document.querySelector('.role-tabs');
  function setRole(tab) {
    if (!roleInput || !tab) return;
    const role = tab.dataset.role;
    roleInput.value = role;
    if (roleTabs) roleTabs.dataset.active = role;
    document.querySelectorAll('.role-tab').forEach((other) => {
      const active = other === tab;
      other.classList.toggle('active', active);
      other.setAttribute('aria-selected', String(active));
      other.tabIndex = active ? 0 : -1;
    });
    const heading = document.getElementById('form-title');
    if (heading) heading.textContent = role === 'admin' ? 'Administrator Sign In' : 'Student Sign In';
    const email = document.getElementById('email');
    if (email) email.setAttribute('autocomplete', 'username');
  }
  document.querySelectorAll('.role-tab').forEach((tab) => {
    tab.addEventListener('click', () => setRole(tab));
    tab.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const tabs = [...document.querySelectorAll('.role-tab')];
      const index = tabs.indexOf(tab);
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
      tabs[next].focus();
      setRole(tabs[next]);
    });
  });
  const initialTab = document.querySelector('.role-tab.active');
  if (initialTab) setRole(initialTab);

  const password = document.getElementById('password');
  const rules = document.getElementById('passwordRules');
  const confirmPassword = document.getElementById('confirm_password');
  const updatePasswordFeedback = () => {
    if (password && rules) {
      const value = password.value;
      const checks = { length: value.length >= 8, letter: /[A-Za-z]/.test(value), number: /[0-9]/.test(value) };
      Object.entries(checks).forEach(([name, passed]) => {
        const item = rules.querySelector(`[data-rule="${name}"]`);
        if (item) {
          item.classList.toggle('passed', passed);
          item.textContent = `${passed ? '✓' : '○'} ${{length:'At least 8 characters',letter:'At least one letter',number:'At least one number'}[name]}`;
        }
      });
      const strength = Object.values(checks).filter(Boolean).length;
      rules.dataset.strength = value.length === 0 ? 'empty' : strength === 3 ? 'strong' : strength === 2 ? 'medium' : 'weak';
    }
    if (password && confirmPassword && confirmPassword.value) {
      const matches = password.value === confirmPassword.value;
      confirmPassword.classList.toggle('input-valid', matches);
      confirmPassword.classList.toggle('input-invalid', !matches);
      confirmPassword.setCustomValidity(matches ? '' : 'Passwords do not match.');
    }
  };
  if (password) password.addEventListener('input', updatePasswordFeedback);
  if (confirmPassword) confirmPassword.addEventListener('input', updatePasswordFeedback);
  updatePasswordFeedback();

  const registerForm = document.getElementById('registerForm');
  const resetForm = document.getElementById('resetForm');
  const loginForm = document.getElementById('loginForm');
  document.querySelectorAll('form[novalidate]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      let firstInvalid = null;
      form.querySelectorAll('input[required]').forEach((field) => {
        if (field === confirmPassword) field.setCustomValidity(password && field.value !== password.value ? 'Passwords do not match.' : '');
        if (field.type === 'checkbox' && !field.checked) field.setCustomValidity('Please confirm this checkbox.');
        else if (field.type !== 'checkbox' && !field.checkValidity()) field.setCustomValidity(field.type === 'email' ? 'Enter a valid email address.' : 'Please complete this field.');
        else if (field.type !== 'checkbox' && field !== confirmPassword) field.setCustomValidity('');
        if (!field.checkValidity() && !firstInvalid) firstInvalid = field;
      });
      if (firstInvalid) {
        event.preventDefault();
        firstInvalid.reportValidity();
        firstInvalid.classList.add('input-invalid');
        showToast(firstInvalid.validationMessage || 'Please check the highlighted fields.', 'error');
        return;
      }
      if ((registerForm || resetForm) && password && (password.value.length < 8 || !/[A-Za-z]/.test(password.value) || !/[0-9]/.test(password.value))) {
        event.preventDefault();
        showToast('Use at least 8 characters, including a letter and a number.', 'error');
        password.focus();
        return;
      }
      if ((registerForm || resetForm) && password && confirmPassword && password.value !== confirmPassword.value) {
        event.preventDefault();
        showToast('The passwords do not match.', 'error');
        confirmPassword.focus();
        return;
      }
      if (registerForm) {
        const agree = document.getElementById('agree');
        if (agree && !agree.checked) {
          event.preventDefault();
          showToast('Please confirm the account terms before continuing.', 'error');
          agree.focus();
          return;
        }
      }
      const submit = form.querySelector('button[type="submit"]');
      if (submit) {
        submit.disabled = true;
        submit.classList.add('is-loading');
        if (!submit.dataset.originalText) submit.dataset.originalText = submit.innerText.trim();
        submit.innerHTML = '<span class="button-spinner" aria-hidden="true"></span><span>Processing…</span>';
      }
    });
  });

  document.querySelectorAll('input').forEach((field) => {
    field.addEventListener('input', () => {
      if (field.checkValidity()) field.classList.remove('input-invalid');
      if (field.type === 'checkbox' && field.checked) field.setCustomValidity('');
    });
  });

  // Gentle staggered entrance on content blocks; CSS disables it for reduced-motion users.
  document.querySelectorAll('.intro-panel > *, .form-inner > *, .dashboard-cards article').forEach((element, index) => {
    element.style.setProperty('--stagger-index', Math.min(index, 8));
    element.classList.add('enter-stagger');
  });
})();
