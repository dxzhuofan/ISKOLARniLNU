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
    if (heading) heading.textContent = role === 'admin' ? 'Staff Sign In' : 'Student Sign In';
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
      const checks = {
        length: value.length >= 8,
        uppercase: /[A-Z]/.test(value),
        number: /[0-9]/.test(value),
        symbol: /[^A-Za-z0-9]/.test(value)
      };
      const labels = {
        length: 'At least 8 characters',
        uppercase: 'Uppercase letter',
        number: 'Number',
        symbol: 'Symbol'
      };
      Object.entries(checks).forEach(([name, passed]) => {
        const item = rules.querySelector(`[data-rule="${name}"]`);
        if (item) {
          item.classList.toggle('passed', passed);
          item.textContent = `${passed ? '✓' : '○'} ${labels[name]}`;
        }
      });
      const strength = Object.values(checks).filter(Boolean).length;
      rules.dataset.strength = value.length === 0 ? 'empty' : strength >= 4 ? 'strong' : strength >= 3 ? 'medium' : 'weak';
      const meter = document.querySelector('.password-strength');
      if (meter) {
        meter.dataset.strength = value.length === 0 ? 'empty' : strength >= 4 ? 'strong' : strength >= 3 ? 'medium' : 'weak';
        [...meter.children].forEach((bar, index) => bar.classList.toggle('filled', index < strength));
      }
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
  const loginEmail = loginForm && loginForm.querySelector('input[name="email"]');
  const updateLoginEmailHint = () => {
    if (!loginEmail) return;
    const value = loginEmail.value.trim();
    const validDomain = /@lnu\.edu\.ph$/i.test(value);
    loginEmail.setCustomValidity(value && !validDomain ? 'Use an email address ending in @lnu.edu.ph.' : '');
  };
  if (loginEmail) {
    loginEmail.addEventListener('input', updateLoginEmailHint);
    updateLoginEmailHint();
  }
  document.querySelectorAll('form[novalidate]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (form === registerForm) return;
      let firstInvalid = null;
      form.querySelectorAll('input[required]').forEach((field) => {
        if (field === confirmPassword) field.setCustomValidity(password && field.value !== password.value ? 'Passwords do not match.' : '');
        if (field.type === 'checkbox' && !field.checked) field.setCustomValidity('Please confirm this checkbox.');
        else if (field.type !== 'checkbox' && !field.checkValidity()) {
          field.setCustomValidity(field === loginEmail
            ? 'Enter a valid email address ending in @lnu.edu.ph.'
            : field.type === 'email' ? 'Enter a valid email address.' : 'Please complete this field.');
        }
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
      if (resetForm && password && (password.value.length < 8 || !/[A-Z]/.test(password.value) || !/[0-9]/.test(password.value) || !/[^A-Za-z0-9]/.test(password.value))) {
        event.preventDefault();
        showToast('Use at least 8 characters, an uppercase letter, a number, and a symbol.', 'error');
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
