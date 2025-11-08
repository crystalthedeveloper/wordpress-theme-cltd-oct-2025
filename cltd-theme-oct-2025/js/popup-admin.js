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

    function isAllowedIcon(attachment) {
      if (!attachment) {
        return false;
      }
      const mime = (attachment.mime || attachment.subtype || '').toLowerCase();
      const url = (attachment.url || '').toLowerCase();

      if (mime.startsWith('image/')) {
        return true;
      }

      if (mime === 'application/json' || /\.json($|\?)/i.test(url)) {
        return true;
      }

      if (/\.svgz?($|\?)/i.test(url)) {
        return true;
      }

      return false;
    }

    function openMediaFrame() {
      const frame = wp.media({
        title: CLTDPopupAdmin.chooseIcon,
        button: {
          text: CLTDPopupAdmin.useIcon,
        },
        multiple: false,
      });

      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        if (!attachment || !attachment.url) {
          return;
        }

        if (!isAllowedIcon(attachment)) {
          window.alert(CLTDPopupAdmin.errorIcon);
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
