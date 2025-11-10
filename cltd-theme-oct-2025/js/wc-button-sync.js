(() => {
  function applyButtonClass(scope = document) {
    const config = window.CLTDWcButtons || {};
    const elementClass = typeof config.elementClass === 'string' && config.elementClass ? config.elementClass : 'wp-element-button';
    const requiredClasses = [elementClass, 'wp-block-cltd-button__link', 'cltd-button'];
    const selector = [
      '.woocommerce a.button',
      '.woocommerce button.button',
      '.woocommerce input.button',
      '.woocommerce #respond input#submit',
      '.wc-block-components-button__button',
      '.wc-block-cart .wp-element-button',
      '.wc-block-checkout .wp-element-button'
    ].join(', ');
    const buttons = scope.querySelectorAll(selector);

    if (!buttons.length) {
      return;
    }

    buttons.forEach((button) => {
      requiredClasses.forEach((className) => {
        if (className && !button.classList.contains(className)) {
          button.classList.add(className);
        }
      });
    });
  }

  function ready(callback) {
    if (document.readyState !== 'loading') {
      callback();
    } else {
      document.addEventListener('DOMContentLoaded', callback);
    }
  }

  ready(() => {
    applyButtonClass();

    document.body.addEventListener('updated_wc_div', () => applyButtonClass());
    document.body.addEventListener('wc_cart_button_updated', () => applyButtonClass());
    document.body.addEventListener('wc-blocks-added', () => applyButtonClass());
  });
})();
