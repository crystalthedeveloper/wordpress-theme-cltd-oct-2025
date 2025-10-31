(() => {
  function onReady(callback) {
    if (document.readyState !== 'loading') {
      callback();
    } else {
      document.addEventListener('DOMContentLoaded', callback);
    }
  }

  function getGroups(element, attr) {
    const value = element.getAttribute(attr);
    if (!value) {
      return [];
    }
    return value.split(',').map((item) => item.trim()).filter(Boolean);
  }

  function toggleDisabledWithin(element, disabled) {
    element.querySelectorAll('input, select, textarea, button').forEach((child) => {
      if (disabled) {
        child.setAttribute('disabled', 'disabled');
      } else {
        child.removeAttribute('disabled');
      }
    });
  }

  onReady(() => {
    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
      return;
    }

    const slidesContainer = document.getElementById('cltd-hero-slides');
    if (!slidesContainer) {
      return;
    }

    const template = document.getElementById('cltd-hero-slide-template');
    const addButton = document.querySelector('[data-add-slide]');
    let nextIndex = parseInt(slidesContainer.getAttribute('data-next-index') || '0', 10);

    function initMediaButton(button) {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const targetInput = document.getElementById(button.dataset.targetInput);
        const targetId = document.getElementById(button.dataset.targetId);

        const frame = wp.media({
          title: button.dataset.title || (CLTDHeroAdmin && CLTDHeroAdmin.chooseMedia) || 'Select',
          button: {
            text: button.dataset.button || (CLTDHeroAdmin && CLTDHeroAdmin.chooseMedia) || 'Select',
          },
          library: button.dataset.library ? { type: button.dataset.library } : undefined,
          multiple: false,
        });

        frame.on('select', () => {
          const attachment = frame.state().get('selection').first().toJSON();
          if (targetInput) {
            targetInput.value = attachment.url || '';
            targetInput.dispatchEvent(new Event('input'));
          }
          if (targetId) {
            targetId.value = attachment.id || '';
          }
        });

        frame.open();
      });
    }

    function refreshSlideLabels() {
      const slides = slidesContainer.querySelectorAll('[data-slide]');
      slides.forEach((slide, index) => {
        const label = slide.querySelector('[data-slide-label]');
        if (!label) {
          return;
        }
        const template = CLTDHeroAdmin && CLTDHeroAdmin.strings && CLTDHeroAdmin.strings.slideLabel
          ? CLTDHeroAdmin.strings.slideLabel
          : 'Slide %s';
        label.textContent = template.replace('%s', index + 1);
      });
    }

    function toggleVisibility(slide) {
      const typeSelect = slide.querySelector('.cltd-hero-type');
      const currentType = typeSelect ? typeSelect.value : 'image';

      slide.querySelectorAll('[data-background-groups]').forEach((row) => {
        const groups = getGroups(row, 'data-background-groups');
        const shouldShow = !groups.length || groups.indexOf(currentType) !== -1;
        row.hidden = !shouldShow;
        toggleDisabledWithin(row, !shouldShow);
      });

      slide.querySelectorAll('[data-background-only]').forEach((element) => {
        const groups = getGroups(element, 'data-background-only');
        const shouldShow = !groups.length || groups.indexOf(currentType) !== -1;
        element.style.display = shouldShow ? '' : 'none';
        toggleDisabledWithin(element, !shouldShow);
      });
    }

    function initSlide(slide) {
      const typeSelect = slide.querySelector('.cltd-hero-type');
      if (typeSelect) {
        typeSelect.addEventListener('change', () => toggleVisibility(slide));
      }
      toggleVisibility(slide);

      slide.querySelectorAll('[data-media-button]').forEach(initMediaButton);

      const removeButton = slide.querySelector('[data-remove-slide]');
      if (removeButton) {
        removeButton.addEventListener('click', (event) => {
          event.preventDefault();
          const slides = slidesContainer.querySelectorAll('[data-slide]');
          if (slides.length <= 1) {
            if (CLTDHeroAdmin && CLTDHeroAdmin.strings && CLTDHeroAdmin.strings.minimumSlides) {
              window.alert(CLTDHeroAdmin.strings.minimumSlides);
            }
            return;
          }
          slide.remove();
          refreshSlideLabels();
        });
      }
    }

    slidesContainer.querySelectorAll('[data-slide]').forEach(initSlide);
    refreshSlideLabels();

    if (addButton && template) {
      addButton.addEventListener('click', (event) => {
        event.preventDefault();
        const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
        slidesContainer.insertAdjacentHTML('beforeend', html);
        const newSlide = slidesContainer.querySelector('[data-slide-index="' + nextIndex + '"]');
        if (newSlide) {
          initSlide(newSlide);
        }
        refreshSlideLabels();
        nextIndex += 1;
      });
    }
  });
})();
