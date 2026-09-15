(() => {
  const card = document.querySelector('.rlb-whish-card');
  if (!card) return;
  const statusText = card.querySelector('.rlb-whish-status-text');
  const statusBox = card.querySelector('.rlb-whish-status');
  const cancelForm = card.querySelector('.rlb-whish-cancel-form');
  const cancelButton = card.querySelector('.rlb-whish-cancel-button');
  const endpoint = card.dataset.statusEndpoint;
  const orderId = card.dataset.orderId;
  const key = card.dataset.orderKey;
  let done = false;

  const setState = (name, text) => {
    statusBox.classList.remove('is-waiting','is-detected','is-paid','is-error');
    statusBox.classList.add(`is-${name}`);
    statusText.textContent = text;
  };

  const setCancelEnabled = (enabled) => {
    if (!cancelButton) return;
    cancelButton.disabled = !enabled;
    if (!enabled) cancelButton.setAttribute('aria-disabled', 'true');
    else cancelButton.removeAttribute('aria-disabled');
  };

  if (cancelForm && cancelButton) {
    cancelForm.addEventListener('submit', (event) => {
      const minutes = cancelButton.dataset.graceMinutes || '5';
      const ok = window.confirm(`Cancel this Whish payment? Your items will return to the cart and raffle reservations will have only ${minutes} minutes remaining.`);
      if (!ok) {
        event.preventDefault();
        return;
      }
      cancelButton.disabled = true;
      cancelButton.textContent = 'Cancelling…';
    });
  }

  async function poll() {
    if (done) return;
    try {
      const url = new URL(endpoint, window.location.origin);
      url.searchParams.set('order_id', orderId);
      url.searchParams.set('key', key);
      const res = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
      if (!res.ok) throw new Error('status');
      const data = await res.json();
      if (data.paid) {
        setState('paid', 'Payment received ✓ Order confirmed.');
        setCancelEnabled(false);
        done = true;
        setTimeout(() => { if (data.redirect) window.location.href = data.redirect; }, 2200);
        return;
      }
      if (data.detected) {
        setState('detected', 'Payment detected. Awaiting verification…');
        setCancelEnabled(false);
      } else if (data.expired || data.status === 'cancelled') {
        setState('error', 'Payment window expired. Please place the order again.');
        setCancelEnabled(false);
        done = true;
        return;
      } else {
        setState('waiting', 'Waiting for payment…');
        setCancelEnabled(true);
      }
    } catch (e) {
      setState('waiting', 'Waiting for payment…');
    }
    setTimeout(poll, 3000);
  }
  poll();
})();
