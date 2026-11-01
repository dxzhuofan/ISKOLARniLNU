(() => {
  'use strict';
  const form = document.getElementById('registerForm');
  if (!form) return;

  const catalog = window.LNU_ACADEMIC_CATALOG || {};
  const tabs = [...document.querySelectorAll('.register-step')];
  const panels = [...document.querySelectorAll('.register-step-panel')];
  const email = document.getElementById('email');
  const emailHint = document.getElementById('emailHint');
  const password = document.getElementById('password');
  const studentId = document.getElementById('student_number');
  const phone = document.getElementById('mobile_number');
  const college = document.getElementById('college_code');
  const program = document.getElementById('program_code');
  const specialization = document.getElementById('specialization');
  const specializationField = document.getElementById('specializationField');
  const certificateNote = document.getElementById('certificateNote');
  const progress = document.getElementById('registerProgressBar');
  const progressTrack = document.querySelector('.register-progress');
  let current = 1;

  function showStep(step) {
    current = step;
    tabs.forEach((tab) => {
      const active = Number(tab.dataset.step) === step;
      tab.classList.toggle('active', active);
      tab.setAttribute('aria-selected', String(active));
    });
    panels.forEach((panel) => {
      const active = panel.id === `register-step-${step}`;
      panel.classList.toggle('active', active);
      panel.hidden = !active;
    });
    if (progress) progress.style.width = `${step * 100 / 3}%`;
    if (progressTrack) {
      progressTrack.setAttribute('aria-valuenow', String(step));
      progressTrack.setAttribute('aria-label', `Registration progress, step ${step} of 3`);
    }
  }

  function validateStep(step) {
    const panel = document.getElementById(`register-step-${step}`);
    for (const field of panel.querySelectorAll('input[required], select[required]')) {
      if (!field.checkValidity()) {
        field.reportValidity();
        field.focus();
        return false;
      }
    }
    if (step === 1) {
      const password = document.getElementById('password');
      const confirm = document.getElementById('confirm_password');
      const strongEnough = password.value.length >= 8 && /[A-Z]/.test(password.value)
        && /\d/.test(password.value) && /[^A-Za-z0-9]/.test(password.value);
      if (!strongEnough) {
        password.setCustomValidity('Use at least 8 characters, an uppercase letter, a number, and a symbol.');
        password.reportValidity();
        password.focus();
        return false;
      }
      password.setCustomValidity('');
      if (confirm.value !== password.value) {
        confirm.setCustomValidity('Passwords do not match.');
        confirm.reportValidity();
        confirm.focus();
        return false;
      }
      confirm.setCustomValidity('');
      if (emailHint.classList.contains('field-error')) {
        email.reportValidity();
        email.focus();
        return false;
      }
    }
    return true;
  }

  function populatePrograms(selectedCode = '') {
    const collegeData = catalog[college.value];
    program.replaceChildren(new Option('Choose program', ''));
    specialization.replaceChildren(new Option('Choose specialization', ''));
    specialization.required = false;
    specialization.disabled = true;
    specializationField.hidden = true;
    certificateNote.hidden = true;
    if (!collegeData) {
      program.disabled = true;
      program.value = '';
      return;
    }
    Object.entries(collegeData.programs).forEach(([code, details]) => {
      program.add(new Option(`${details.name} (${details.type})`, code));
    });
    program.disabled = false;
    if (selectedCode && collegeData.programs[selectedCode]) program.value = selectedCode;
    updateSpecializations();
  }

  function updateSpecializations(selectedName = '') {
    const details = catalog[college.value]?.programs?.[program.value];
    specialization.replaceChildren(new Option('Choose specialization', ''));
    if (!details) {
      specialization.required = false;
      specialization.disabled = true;
      specializationField.hidden = true;
      certificateNote.hidden = true;
      return;
    }
    certificateNote.hidden = program.value !== 'TCP';
    if (!details.requires_specialization) {
      specialization.required = false;
      specialization.disabled = true;
      specializationField.hidden = true;
      specialization.value = '';
      return;
    }
    details.specializations.forEach((name) => specialization.add(new Option(name, name)));
    specialization.required = true;
    specialization.disabled = false;
    specializationField.hidden = false;
    if (selectedName && details.specializations.includes(selectedName)) specialization.value = selectedName;
  }

  function validateEmail() {
    const address = email.value.trim();
    const validDomain = /@lnu\.edu\.ph$/i.test(address);
    const valid = validDomain && email.validity.valid;
    email.setCustomValidity(address && !validDomain ? 'Use an email address ending in @lnu.edu.ph.' : '');
    emailHint.textContent = address && !validDomain
      ? 'Use an LNU email address ending in @lnu.edu.ph.'
      : 'Use your LNU email: juan@lnu.edu.ph';
    emailHint.classList.toggle('field-error', Boolean(address && !validDomain));
    emailHint.classList.toggle('field-valid', Boolean(address && valid));
  }

  [studentId, phone].forEach((field) => {
    field.addEventListener('input', () => {
      const limit = field === studentId ? 7 : 11;
      field.value = field.value.replace(/\D/g, '').slice(0, limit);
    });
    field.addEventListener('paste', (event) => {
      event.preventDefault();
      const text = (event.clipboardData || window.clipboardData).getData('text');
      const limit = field === studentId ? 7 : 11;
      field.value = text.replace(/\D/g, '').slice(0, limit);
      field.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });
  email.addEventListener('input', validateEmail);
  password.addEventListener('input', () => password.setCustomValidity(''));
  college.addEventListener('change', () => populatePrograms());
  program.addEventListener('change', () => updateSpecializations());

  document.getElementById('registerNext').addEventListener('click', () => {
    if (validateStep(1)) {
      showStep(2);
      document.getElementById('first_name').focus({ preventScroll: true });
    }
  });
  document.querySelectorAll('[data-next]').forEach((button) => button.addEventListener('click', () => {
    if (validateStep(current)) {
      showStep(current + 1);
      college.focus({ preventScroll: true });
    }
  }));
  document.querySelectorAll('[data-previous]').forEach((button) => button.addEventListener('click', () => showStep(current - 1)));
  tabs.forEach((tab) => tab.addEventListener('click', () => {
    const step = Number(tab.dataset.step);
    if (step <= current || (step === current + 1 && validateStep(current))) showStep(step);
  }));
  form.addEventListener('submit', (event) => {
    if (!validateStep(3)) {
      event.preventDefault();
      return;
    }
    if (![1, 2].every(validateStep)) {
      event.preventDefault();
      showStep(1);
    }
  });

  validateEmail();
  populatePrograms(program.dataset.selected || '');
  updateSpecializations(specialization.dataset.selected || '');
  const serverError = Boolean(document.querySelector('.alert.error'));
  showStep(serverError ? 3 : 1);
})();
