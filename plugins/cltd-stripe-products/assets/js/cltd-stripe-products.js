(() => {
  const config = window.CLTDStripe || {};
  const restUrl = typeof config.restUrl === 'string' ? config.restUrl : '';
  const nonce = typeof config.nonce === 'string' ? config.nonce : '';
  const strings = Object.assign(
    {
      processing: 'Processing…',
      error: 'Something went wrong. Please try again.'
    },
    config.strings || {}
  );

  if (!restUrl) {
    return;
  }

  async function handleBuyNowClick(button) {
    if (!button || button.disabled) {
      return;
    }

    const priceId = button.getAttribute('data-price-id');
    if (!priceId) {
      return;
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = strings.processing;

    try {
      const response = await fetch(restUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          price_id: priceId,
          quantity: 1
        })
      });

      if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
      }

      const data = await response.json();
      if (data && data.url) {
        window.location.href = data.url;
        return;
      }

      throw new Error('Missing checkout URL');
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error('CLTD Stripe checkout failed:', error);
      alert(strings.error); // Intentional minimal UX; styling handled elsewhere.
    } finally {
      button.disabled = false;
      button.textContent = originalText;
    }
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.cltd-buy-now');
    if (!button) {
      return;
    }

    event.preventDefault();
    handleBuyNowClick(button);
  });
})();
