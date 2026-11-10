(() => {
  const catalog = window.LNU_ACADEMIC_CATALOG || {};
  const college = document.getElementById('college_code');
  const program = document.getElementById('program_code');
  const specialization = document.getElementById('specialization');
  const specializationField = document.getElementById('specializationField');
  if (!college || !program || !specialization) return;

  function populateSpecializations(selected = '') {
    const details = catalog[college.value]?.programs?.[program.value];
    specialization.replaceChildren(new Option('Choose specialization', ''));
    specialization.required = Boolean(details?.requires_specialization);
    specialization.disabled = !details?.requires_specialization;
    specializationField.hidden = !details?.requires_specialization;
    (details?.specializations || []).forEach((name) => specialization.add(new Option(name, name)));
    if (selected && details?.specializations.includes(selected)) specialization.value = selected;
  }
  function populatePrograms(selected = '') {
    const collegeData = catalog[college.value];
    program.replaceChildren(new Option('Choose program', ''));
    if (!collegeData) {
      program.disabled = true;
      populateSpecializations();
      return;
    }
    Object.entries(collegeData.programs).forEach(([code, details]) => program.add(new Option(details.name, code)));
    program.disabled = false;
    if (selected && collegeData.programs[selected]) program.value = selected;
    populateSpecializations(specialization.dataset.selected || '');
  }
  college.addEventListener('change', () => {
    program.dataset.selected = '';
    specialization.dataset.selected = '';
    populatePrograms();
  });
  program.addEventListener('change', () => {
    specialization.dataset.selected = '';
    populateSpecializations();
  });
  populatePrograms(program.dataset.selected || '');
})();
