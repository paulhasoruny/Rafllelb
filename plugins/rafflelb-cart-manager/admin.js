(() => {
  'use strict';
  const search = document.getElementById('rlcm-search');
  if (search) search.addEventListener('input', () => {
    let visible = 0;
    document.querySelectorAll('.rlcm-product').forEach(product => {
      product.hidden = !product.dataset.search.includes(search.value.trim().toLowerCase());
      if (!product.hidden) visible++;
    });
    document.getElementById('rlcm-no-match').hidden = visible !== 0;
  });
  const loaded = Date.now();
  setInterval(() => {
    const elapsed = Math.floor((Date.now() - loaded) / 1000);
    document.querySelectorAll('[data-remaining]').forEach(timer => {
      const remaining = Math.max(0, Number(timer.dataset.remaining) - elapsed);
      timer.textContent = remaining ? `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}` : 'Expired — refresh';
    });
  }, 1000);
  document.querySelectorAll('.rlcm-remove').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm('Remove this product from this cart? Other active reservations keep their original timer.')) event.preventDefault();
    else { form.querySelector('button').disabled = true; form.querySelector('button').textContent = 'Removing…'; }
  }));
})();
