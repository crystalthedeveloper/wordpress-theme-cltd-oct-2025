(() => {
  function onReady(callback) {
    if (document.readyState !== 'loading') {
      callback();
    } else {
      document.addEventListener('DOMContentLoaded', callback);
    }
  }

  function updateLabels(container) {
    const items = container.querySelectorAll('.cltd-popup-item');
    items.forEach((item, index) => {
      const labelEl = item.querySelector('[data-item-label]');
      if (labelEl) {
        labelEl.textContent = index + 1;
      }
    });
    container.setAttribute('data-next-index', String(items.length));
  }

  function replaceIndex(html, index) {
    return html.replace(/__INDEX__/g, String(index));
  }

  function initItem(item) {
    if (!item) {
      return;
    }

    const typeSelect = item.querySelector('.cltd-popup-item__type');
    const popupRow = item.querySelector('[data-field="popup"]');
    const linkRow = item.querySelector('[data-field="link"]');
    const popupSelect = item.querySelector('.cltd-popup-item__popup-select');
    const slugInput = item.querySelector('.cltd-popup-item__slug');

    function toggleRows() {
      if (!typeSelect) {
        return;
      }
      const type = typeSelect.value;
      if (popupRow) {
        popupRow.style.display = type === 'popup' ? '' : 'none';
      }
      if (linkRow) {
        linkRow.style.display = type === 'link' ? '' : 'none';
      }
    }

    function syncSlug() {
      if (!popupSelect || !slugInput) {
        return;
      }
      const option = popupSelect.options[popupSelect.selectedIndex];
      if (option && option.dataset.slug && option.value !== '0') {
        slugInput.value = option.dataset.slug;
      }
    }

    if (typeSelect) {
      typeSelect.addEventListener('change', () => {
        toggleRows();
        if (typeSelect.value === 'popup') {
          syncSlug();
        }
      });
    }

    if (popupSelect) {
      popupSelect.addEventListener('change', () => {
        syncSlug();
      });
    }

    toggleRows();
    syncSlug();
  }

  function initIconButtons(scope) {
    const buttons = scope.querySelectorAll('[data-cltd-select-icon]');
    buttons.forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const input = button.previousElementSibling;
        if (!input) {
          return;
        }
        const frame = wp.media({
          title: button.dataset.label || 'Select SVG',
          library: { type: 'image/svg+xml' },
          button: { text: button.dataset.button || 'Use SVG' },
          multiple: false
        });
        frame.on('select', () => {
          const attachment = frame.state().get('selection').first();
          if (!attachment) {
            return;
          }
          const model = attachment.toJSON();
          input.value = model.url || '';
          input.dispatchEvent(new Event('input'));
        });
        frame.open();
      });
    });
  }

  function initRemoveButtons(scope, container) {
    scope.querySelectorAll('[data-cltd-remove-popup-item]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const item = button.closest('.cltd-popup-item');
        if (item) {
          item.remove();
          updateLabels(container);
        }
      });
    });
  }

  function initAccordion(list) {
    if (typeof window.jQuery === 'undefined' || !list) {
      return;
    }
    const $ = window.jQuery;
    const $list = $(list);
    if ($list.hasClass('ui-accordion')) {
      try {
        $list.accordion('destroy');
      } catch (e) {}
    }
    $list.accordion({
      header: '.cltd-popup-item__header',
      collapsible: true,
      heightStyle: 'content',
      active: false,
      animate: 160
    });
  }

  function initPreview(container) {
    if (!container) {
      return;
    }
    const spanSelect = container.querySelector('#cltd-grid-span');
    const preview = container.querySelector('[data-cltd-grid-preview]');
    if (!spanSelect || !preview) {
      return;
    }

    const applyPreview = () => {
      const span = parseInt(spanSelect.value || '1', 10) || 1;
      preview.setAttribute('data-span', String(Math.max(1, Math.min(3, span))));
    };

    spanSelect.addEventListener('change', applyPreview);
    applyPreview();
  }

  onReady(() => {
    const wrapper = document.getElementById('cltd-popup-items');
    if (!wrapper) {
      return;
    }

    const list = wrapper.querySelector('.cltd-popup-items__list');
    const template = document.getElementById('cltd-popup-item-template');
    const addButton = wrapper.querySelector('[data-cltd-add-popup-item]');

    if (!list || !template || !addButton) {
      return;
    }

    initIconButtons(wrapper);
    initRemoveButtons(wrapper, list);
    list.querySelectorAll('.cltd-popup-item').forEach(initItem);
    updateLabels(list);
    initAccordion(list);
    initPreview(wrapper);

    addButton.addEventListener('click', (event) => {
      event.preventDefault();
      const next = parseInt(list.getAttribute('data-next-index') || wrapper.getAttribute('data-next-index') || '0', 10) || 0;
      const html = replaceIndex(template.innerHTML, next);
      const fragment = document.createRange().createContextualFragment(html);
      list.appendChild(fragment);
      const items = list.querySelectorAll('.cltd-popup-item');
      const newItem = items.length ? items[items.length - 1] : null;
      updateLabels(list);
      initIconButtons(newItem || list);
      initRemoveButtons(newItem || list, list);
      initItem(newItem);
      initAccordion(list);
    });
  });
})();
