(() => {
  function applyButtonClass(scope = document) {
    const config = window.CLTDWcButtons || {};
    const elementClass = typeof config.elementClass === 'string' && config.elementClass ? config.elementClass : 'wp-element-button';
    const selector = '.woocommerce a.button:not(.' + elementClass + '), .woocommerce button.button:not(.' + elementClass + '), .woocommerce input.button:not(.' + elementClass + '), .woocommerce #respond input#submit:not(.' + elementClass + ')';
    const buttons = scope.querySelectorAll(selector);

    if (!buttons.length) {
      return;
    }

    buttons.forEach((button) => {
      button.classList.add(elementClass);
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
