(() => {
  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  ready(() => {
    const uploadButton = document.querySelector('[data-popup-icon-upload]');
    const clearButton = document.querySelector('[data-popup-icon-clear]');
    const input = document.getElementById('cltd-popup-icon-input');

    if (!input || !uploadButton) {
      return;
    }

    function openMediaFrame() {
      const frame = wp.media({
        title: CLTDPopupAdmin.chooseIcon,
        button: {
          text: CLTDPopupAdmin.useIcon,
        },
        library: {
          type: 'image',
        },
        multiple: false,
      });

      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        if (!attachment || !attachment.url) {
          return;
        }

        if (attachment.subtype !== 'svg+xml' && !/\.svg($|\?)/i.test(attachment.url)) {
          window.alert(CLTDPopupAdmin.errorSvg);
          return;
        }

        input.value = attachment.url;
      });

      frame.open();
    }

    uploadButton.addEventListener('click', (event) => {
      event.preventDefault();
      openMediaFrame();
    });

    if (clearButton) {
      clearButton.addEventListener('click', (event) => {
        event.preventDefault();
        input.value = '';
      });
    }
  });
})();
