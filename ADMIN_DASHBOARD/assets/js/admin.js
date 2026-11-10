document.querySelectorAll('form[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault();}));
document.querySelectorAll('form').forEach(f=>f.addEventListener('submit',e=>{if(e.defaultPrevented)return;const b=f.querySelector('button[type=submit],button:not([type])');if(b&&!f.matches('.bar')){setTimeout(()=>{b.disabled=true;b.textContent='Please wait…';},0);}}));
const selectAll=document.getElementById('select-all-applications');
if(selectAll)selectAll.addEventListener('change',()=>document.querySelectorAll('.application-select').forEach(box=>box.checked=selectAll.checked));
const scholarshipDialog=document.getElementById('scholarship-dialog');
const addScholarshipButton=document.getElementById('add-scholarship-button');
const closeScholarshipDialog=document.getElementById('close-scholarship-dialog');
if(scholarshipDialog){
  const form=scholarshipDialog.querySelector('#scholarship-form');
  let formIsDirty=false;
  const openScholarshipDialog=()=>{
    formIsDirty=false;
    scholarshipDialog.showModal();
    document.body.classList.add('scholarship-dialog-open');
  };
  const closeScholarshipDialogSafely=()=>{
    if(formIsDirty&&!confirm('Discard your unsaved scholarship changes?'))return;
    scholarshipDialog.close();
  };
  document.addEventListener('keydown',event=>{
    if(event.key==='Escape'&&scholarshipDialog.open){
      event.preventDefault();
      closeScholarshipDialogSafely();
    }
  },true);
  addScholarshipButton?.addEventListener('click',openScholarshipDialog);
  closeScholarshipDialog?.addEventListener('click',closeScholarshipDialogSafely);
  document.getElementById('cancel-scholarship-dialog')?.addEventListener('click',closeScholarshipDialogSafely);
  scholarshipDialog.addEventListener('cancel',event=>{
    if(formIsDirty){
      event.preventDefault();
      closeScholarshipDialogSafely();
    }
  });
  scholarshipDialog.addEventListener('click',event=>{
    if(event.target===scholarshipDialog)closeScholarshipDialogSafely();
  });
  document.querySelectorAll('.scholarship-college-toggle').forEach(label=>{
    label.addEventListener('click',event=>event.stopPropagation());
  });
  scholarshipDialog.addEventListener('close',()=>document.body.classList.remove('scholarship-dialog-open'));
  form?.addEventListener('input',()=>{formIsDirty=true;});
  form?.addEventListener('change',()=>{formIsDirty=true;});

  const updateEligibility=()=>{
    const summaries=[];
    document.querySelectorAll('.scholarship-college').forEach(group=>{
      const code=group.dataset.college;
      const toggle=group.querySelector('[data-college-toggle]');
      const programs=[...group.querySelectorAll('[data-college-program]')];
      const selected=programs.filter(input=>input.checked).length;
      toggle.indeterminate=selected>0&&selected<programs.length;
      toggle.checked=programs.length>0&&selected===programs.length;
      group.querySelector('[data-college-count]').textContent=`${selected} of ${programs.length} selected`;
      if(selected===programs.length&&selected>0)summaries.push(`${code} (all)`);
      else if(selected>0)summaries.push(`${code} (${selected} ${selected===1?'program':'programs'})`);
    });
    document.getElementById('eligibility-summary').textContent=summaries.length
      ?`Eligible: ${summaries.join(', ')}`
      :'Eligible: all colleges and programs';
  };
  document.querySelectorAll('[data-college-toggle]').forEach(toggle=>{
    toggle.addEventListener('change',()=>{
      const group=toggle.closest('.scholarship-college');
      group.querySelectorAll('[data-college-program]').forEach(program=>{program.checked=toggle.checked;});
      updateEligibility();
    });
  });
  document.querySelectorAll('[data-college-program]').forEach(program=>program.addEventListener('change',updateEligibility));
  document.querySelectorAll('[data-required-document]').forEach(required=>{
    const optional=document.querySelector(`[data-optional-document][value="${CSS.escape(required.value)}"]`);
    const sync=()=>{
      if(required.checked)optional.checked=false;
      optional.disabled=required.checked;
      optional.closest('label').classList.toggle('is-disabled',required.checked);
    };
    required.addEventListener('change',sync);
    optional.addEventListener('change',()=>{if(optional.checked)required.checked=false;sync();});
    sync();
  });
  document.querySelectorAll('[data-digit-only]').forEach(input=>{
    input.addEventListener('beforeinput',event=>{
      if(event.data&&!/^\d+$/.test(event.data))event.preventDefault();
    });
    input.addEventListener('paste',event=>{
      const pasted=event.clipboardData?.getData('text')||'';
      if(!/^\d*$/.test(pasted))event.preventDefault();
    });
  });
  updateEligibility();
  if(scholarshipDialog.dataset.open==='true')openScholarshipDialog();
}
