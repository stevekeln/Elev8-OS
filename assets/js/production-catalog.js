(() => {
  const ready = (fn) => document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn();
  ready(() => {
    const panel = document.querySelector('[data-material-quick-add]');
    const open = document.querySelector('[data-open-material-quick-add]');
    if (panel && open) {
      const cancel = panel.querySelector('[data-cancel-material]');
      const save = panel.querySelector('[data-save-material]');
      const message = panel.querySelector('[data-material-message]');
      const show = () => { panel.hidden = false; panel.querySelector('[data-material-name]')?.focus(); };
      const hide = () => { panel.hidden = true; if (message) message.textContent = ''; };
      open.addEventListener('click', show);
      cancel?.addEventListener('click', hide);
      save?.addEventListener('click', async () => {
        const name = panel.querySelector('[data-material-name]')?.value.trim() || '';
        if (!name) { message.textContent = 'Enter a material name.'; return; }
        save.disabled = true;
        message.textContent = 'Saving…';
        const body = new URLSearchParams({
          action: 'elev8_os_quick_add_production_material',
          nonce: window.Elev8ProductionCatalog?.materialNonce || '',
          material_name: name,
          material_code: panel.querySelector('[data-material-code]')?.value || '',
          unit: panel.querySelector('[data-material-unit]')?.value || 'unit',
          unit_cost: panel.querySelector('[data-material-cost]')?.value || '0',
          notes: panel.querySelector('[data-material-notes]')?.value || '',
          active: '1'
        });
        try {
          const response = await fetch(window.Elev8ProductionCatalog?.ajaxUrl || window.ajaxurl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body});
          const payload = await response.json();
          if (!payload.success) throw new Error(payload.data?.message || 'Material could not be saved.');
          const material = payload.data.material;
          document.querySelectorAll('.production-material-select').forEach((select) => {
            if (!select.querySelector(`option[value="${material.id}"]`)) {
              select.add(new Option(material.label, String(material.id)));
            }
          });
          const target = [...document.querySelectorAll('.production-material-select')].find((select) => !select.value || select.value === '0');
          if (target) { target.value = String(material.id); target.focus(); }
          panel.querySelectorAll('input').forEach((input) => { if (input.type !== 'number') input.value = ''; });
          panel.querySelector('[data-material-cost]').value = '0.0000';
          message.textContent = `${material.name} saved and selected.`;
          setTimeout(hide, 900);
        } catch (error) {
          message.textContent = error.message;
        } finally {
          save.disabled = false;
        }
      });
    }

    const minutes = document.querySelector('[name="estimated_time_minutes"]');
    const seconds = document.querySelector('[name="estimated_time_seconds"]');
    const preview = document.querySelector('[data-duration-preview]');
    const updateDuration = () => {
      if (!preview || !minutes || !seconds) return;
      let total = Math.max(0, parseInt(minutes.value || '0', 10) * 60 + parseInt(seconds.value || '0', 10));
      minutes.value = String(Math.floor(total / 60));
      seconds.value = String(total % 60);
      preview.textContent = `${Math.floor(total / 60)}m ${total % 60}s`;
    };
    minutes?.addEventListener('input', updateDuration);
    seconds?.addEventListener('change', updateDuration);
  });
})();
