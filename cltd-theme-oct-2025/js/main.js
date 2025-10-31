(() => {
  const config = window.CLTDTheme || {};
  const restBase = typeof config.restUrl === 'string' ? config.restUrl : '';
  const heroBackground = config.heroBackground || {};
  const strings = Object.assign(
    {
      loading: 'Loading…',
      error: 'We could not load that content right now. Please try again.',
      close: 'Close popup'
    },
    config.strings || {}
  );

  function initHeroBackground() {
    const sliderEl = document.querySelector('[data-hero-slider]');
    const slidesConfig = Array.isArray(heroBackground.slides) ? heroBackground.slides : [];

    if (sliderEl) {
      const slides = Array.from(sliderEl.querySelectorAll('[data-hero-slide]'));
      if (!slides.length) {
        return;
      }

      const intervalAttr = parseInt(sliderEl.getAttribute('data-interval') || heroBackground.interval || 7000, 10);
      const interval = Number.isNaN(intervalAttr) ? 7000 : Math.max(2000, intervalAttr);

      const videoMap = new Map();
      const lottieConfig = new Map();
      const lottieInstances = new Map();

      slides.forEach((slide, index) => {
        const type = slide.dataset.slideType || 'image';
        const slideConfig = slidesConfig[index] || {};

        if (type === 'video') {
          const video = slide.querySelector('video');
          if (video) {
            video.loop = !!slideConfig.loop;
            video.muted = !!slideConfig.mute;
            videoMap.set(slide, { element: video, config: slideConfig });
          }
        } else if (type === 'lottie') {
          lottieConfig.set(slide, slideConfig);
        }
      });

      function playVideo(slide) {
        const entry = videoMap.get(slide);
        if (!entry) {
          return;
        }
        const { element, config: slideConfig } = entry;
        if (!slideConfig.autoplay) {
          element.pause();
          return;
        }
        const attempt = element.play();
        if (attempt && typeof attempt.catch === 'function') {
          attempt.catch(() => {});
        }
      }

      function pauseVideo(slide) {
        const entry = videoMap.get(slide);
        if (!entry) {
          return;
        }
        const { element } = entry;
        element.pause();
        try {
          element.currentTime = 0;
        } catch (e) {
          // Ignore seek errors.
        }
      }

      function startLottie(slide) {
        if (!lottieConfig.has(slide)) {
          return;
        }
        const slideConfig = lottieConfig.get(slide);
        const container = slide.querySelector('[data-lottie-container]');
        if (!container) {
          return;
        }

        const initialise = () => {
          let instance = lottieInstances.get(slide);
          if (!instance && typeof window.lottie !== 'undefined') {
            instance = window.lottie.loadAnimation({
              container,
              renderer: 'svg',
              loop: !!slideConfig.loop,
              autoplay: !!slideConfig.autoplay,
              path: container.getAttribute('data-lottie-src'),
              rendererSettings: {
                preserveAspectRatio: 'xMidYMid slice',
                progressiveLoad: true
              }
            });
            lottieInstances.set(slide, instance);
          }

          if (instance) {
            if (typeof instance.setSpeed === 'function') {
              instance.setSpeed(slideConfig.speed || 1);
            }
            instance.loop = !!slideConfig.loop;
            if (slideConfig.autoplay) {
              if (typeof instance.goToAndPlay === 'function') {
                instance.goToAndPlay(0, true);
              } else if (typeof instance.play === 'function') {
                instance.play();
              }
            } else if (typeof instance.goToAndStop === 'function') {
              instance.goToAndStop(0, true);
            } else if (typeof instance.stop === 'function') {
              instance.stop();
            }
          }
        };

        if (typeof window.lottie === 'undefined') {
          const watcher = setInterval(() => {
            if (typeof window.lottie !== 'undefined') {
              clearInterval(watcher);
              initialise();
            }
          }, 200);
          setTimeout(() => clearInterval(watcher), 10000);
        } else {
          initialise();
        }
      }

      function stopLottie(slide) {
        const instance = lottieInstances.get(slide);
        if (instance && typeof instance.stop === 'function') {
          instance.stop();
        }
      }

      let currentIndex = 0;
      slides.forEach((slide, idx) => {
        slide.classList.toggle('is-active', idx === 0);
      });

      if (videoMap.has(slides[0])) {
        playVideo(slides[0]);
      }
      if (lottieConfig.has(slides[0])) {
        startLottie(slides[0]);
      }

      if (slides.length > 1) {
        setInterval(() => {
          const currentSlide = slides[currentIndex];
          const nextIndex = (currentIndex + 1) % slides.length;
          const nextSlide = slides[nextIndex];

          currentSlide.classList.remove('is-active');
          pauseVideo(currentSlide);
          stopLottie(currentSlide);

          nextSlide.classList.add('is-active');
          playVideo(nextSlide);
          startLottie(nextSlide);

          currentIndex = nextIndex;
        }, interval);
      }

      return;
    }
  }

  if (document.readyState !== 'loading') {
    initHeroBackground();
  } else {
    document.addEventListener('DOMContentLoaded', initHeroBackground);
  }

  const modal = document.querySelector('[data-popup-modal]');
  if (!modal) {
    return;
  }

  const dialog = modal.querySelector('.cltd-modal__dialog');
  const contentEl = modal.querySelector('#cltd-modal-content');
  const titleEl = modal.querySelector('#cltd-modal-title');
  const scrollIndicator = modal.querySelector('[data-popup-scroll-indicator]');

  if (!dialog || !contentEl || !titleEl) {
    return;
  }

  let activeTrigger = null;
  let closeTimer = null;
  let isOpen = false;
  let activeRequest;

  const ANIMATION_DURATION = 280;

  const focusableSelectors = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  function clearCloseTimer() {
    if (closeTimer) {
      clearTimeout(closeTimer);
      closeTimer = null;
    }
  }

  function setStatus(message, isError) {
    const paragraph = document.createElement('p');
    paragraph.className = 'cltd-modal__status' + (isError ? ' is-error' : '');
    paragraph.textContent = message;
    contentEl.innerHTML = '';
    contentEl.appendChild(paragraph);
    if (scrollIndicator) {
      scrollIndicator.classList.remove('is-visible');
    }
  }

  function ensureRestUrl(base) {
    if (!base) {
      return '';
    }
    return base.endsWith('/') ? base : `${base}/`;
  }

  function updateScrollIndicator() {
    if (!scrollIndicator || !isOpen) {
      return;
    }

    const tolerance = 12;
    const available = dialog.clientHeight;
    const total = dialog.scrollHeight;
    const current = dialog.scrollTop;
    const hasOverflow = total - available > tolerance;

    if (!hasOverflow) {
      scrollIndicator.classList.remove('is-visible');
      return;
    }

    const reachedBottom = current + available >= total - tolerance;
    if (reachedBottom) {
      scrollIndicator.classList.remove('is-visible');
    } else {
      scrollIndicator.classList.add('is-visible');
    }
  }

  function openModal(trigger) {
    clearCloseTimer();

    if (modal.hidden) {
      modal.hidden = false;
    }

    requestAnimationFrame(() => {
      modal.classList.add('is-open');
    });

    document.body.classList.add('cltd-modal-open');
    isOpen = true;
    activeTrigger = trigger || null;

    if (trigger) {
      const defaultTitle = trigger.getAttribute('data-popup-title') || '';
      titleEl.textContent = defaultTitle;
    } else {
      titleEl.textContent = '';
    }

    setStatus(strings.loading, false);

    // Ensure dialog is focusable before shifting focus.
    if (!dialog.hasAttribute('tabindex')) {
      dialog.setAttribute('tabindex', '-1');
    }

    requestAnimationFrame(() => {
      dialog.focus({ preventScroll: true });
    });

    requestAnimationFrame(updateScrollIndicator);
  }

  function closeModal() {
    if (!isOpen) {
      return;
    }

    clearCloseTimer();
    modal.classList.remove('is-open');
    document.body.classList.remove('cltd-modal-open');
    isOpen = false;

    if (activeRequest) {
      activeRequest.abort();
      activeRequest = null;
    }

    closeTimer = setTimeout(() => {
      modal.hidden = true;
      titleEl.textContent = '';
      contentEl.innerHTML = '';
      if (scrollIndicator) {
        scrollIndicator.classList.remove('is-visible');
      }
    }, ANIMATION_DURATION);

    if (activeTrigger && typeof activeTrigger.focus === 'function') {
      activeTrigger.focus();
    }
    activeTrigger = null;
  }

  function trapFocus(event) {
    if (!isOpen || event.key !== 'Tab') {
      return;
    }

    const focusable = dialog.querySelectorAll(focusableSelectors);
    if (!focusable.length) {
      event.preventDefault();
      dialog.focus();
      return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const isShift = event.shiftKey;
    const active = document.activeElement;

    if (!isShift && active === last) {
      event.preventDefault();
      first.focus();
    } else if (isShift && active === first) {
      event.preventDefault();
      last.focus();
    }
  }

  function handleKeydown(event) {
    if (!isOpen) {
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
      return;
    }

    trapFocus(event);
  }

  async function fetchPopup(slug) {
    if (!slug) {
      setStatus(strings.error, true);
      return;
    }

    if (scrollIndicator) {
      scrollIndicator.classList.remove('is-visible');
    }

    const base = ensureRestUrl(restBase);
    if (!base) {
      setStatus(strings.error, true);
      return;
    }

    if (activeRequest) {
      activeRequest.abort();
    }

    const controller = new AbortController();
    activeRequest = controller;

    try {
      const response = await fetch(`${base}${encodeURIComponent(slug)}`, {
        signal: controller.signal,
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
      }

      const data = await response.json();
      const { title = '', content = '' } = data || {};

      titleEl.textContent = title || titleEl.textContent;

      if (content) {
        contentEl.innerHTML = content;
      } else {
        setStatus(strings.error, true);
      }

      requestAnimationFrame(updateScrollIndicator);
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }

      setStatus(strings.error, true);
      console.error('CLTD popup request failed:', error);
    } finally {
      if (activeRequest === controller) {
        activeRequest = null;
      }

      requestAnimationFrame(updateScrollIndicator);
    }
  }

  function handleTriggerClick(event) {
    const trigger = event.target.closest('[data-popup-slug]');
    if (!trigger) {
      return;
    }

    event.preventDefault();

    const slug = trigger.getAttribute('data-popup-slug');
    const title = trigger.getAttribute('data-popup-title');

    openModal(trigger);

    if (title) {
      titleEl.textContent = title;
    }

    fetchPopup(slug);
  }

  document.addEventListener('click', handleTriggerClick);
  document.addEventListener('keydown', handleKeydown, true);

  dialog.addEventListener('scroll', updateScrollIndicator);
  window.addEventListener('resize', updateScrollIndicator);

  modal.addEventListener('click', (event) => {
    const closeTarget = event.target.closest('[data-popup-close]');
    if (closeTarget) {
      event.preventDefault();
      closeModal();
    }
  });
})();
