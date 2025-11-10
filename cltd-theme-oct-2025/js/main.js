(() => {
  const config = window.CLTDTheme || {};
  const restBase = typeof config.restUrl === 'string' ? config.restUrl : '';
  const pageRestBase = typeof config.pagePopupRestUrl === 'string' ? config.pagePopupRestUrl : '';
  const heroBackground = config.heroBackground || {};
  const popupPages = Array.isArray(config.popupPages) ? config.popupPages : [];
  const popupPageLookup = new Map();
  const popupSlugLookup = new Map();
  const processedLightboxImages = new WeakSet();
  let lightboxElements = null;
  const strings = Object.assign(
    {
      loading: 'Loading…',
      error: 'We could not load that content right now. Please try again.',
      close: 'Close popup'
    },
    config.strings || {}
  );

  if (popupPages.length) {
    popupPages.forEach((page) => {
      const normalized = normalizePath(page.permalink || page.url || '');
      if (!normalized) {
        return;
      }

      popupPageLookup.set(normalized, page);

      const slug = extractSlug(normalized);
      if (slug && !popupSlugLookup.has(slug)) {
        popupSlugLookup.set(slug, page);
      }
    });
  }

  function normalizePath(url) {
    if (!url) {
      return '';
    }

    const link = document.createElement('a');
    link.href = url;

    const currentHost = window.location.host ? window.location.host.toLowerCase() : '';
    const targetHost = link.host ? link.host.toLowerCase() : currentHost;

    if (targetHost && currentHost && targetHost !== currentHost) {
      return '';
    }

    let path = link.pathname || '/';
    if (path.charAt(0) !== '/') {
      path = `/${path}`;
    }
    path = path.replace(/\/+$/u, '');
    if (!path) {
      path = '/';
    }

    return path.toLowerCase();
  }

  function extractSlug(path) {
    if (!path) {
      return '';
    }

    const parts = path.split('/').filter(Boolean);
    return parts.length ? parts[parts.length - 1] : '';
  }

  function findPopupPage(path) {
    if (!path) {
      return null;
    }

    const direct = popupPageLookup.get(path);
    if (direct) {
      return direct;
    }

    const slug = extractSlug(path);
    if (slug) {
      return popupSlugLookup.get(slug) || null;
    }

    return null;
  }

  function applyPopupAttributesToLink(link, page) {
    if (!link || !page) {
      return;
    }

    if (link.dataset.popup === 'true' || link.dataset.popupSlug) {
      return;
    }

    link.dataset.popup = 'true';
    if (page.id) {
      link.dataset.popupPageId = String(page.id);
    }
    if (page.permalink) {
      link.dataset.popupUrl = page.permalink;
    }
    if (!link.dataset.popupTitle && page.title) {
      link.dataset.popupTitle = page.title;
    }
  }

  function hydratePopupLinks(root = document) {
    if ((!popupPageLookup.size && !popupSlugLookup.size) || !root) {
      return;
    }

    const links = root.querySelectorAll('a[href]');
    links.forEach((link) => {
      const href = link.getAttribute('href');
      if (!href || href.charAt(0) === '#') {
        return;
      }

      const match = findPopupPage(normalizePath(href));
      if (match) {
        applyPopupAttributesToLink(link, match);
      }
    });
  }

  function ensureLightboxElements() {
    if (lightboxElements) {
      return lightboxElements;
    }

    const overlay = document.createElement('div');
    overlay.className = 'cltd-lightbox';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', strings.close);

    const inner = document.createElement('div');
    inner.className = 'cltd-lightbox__inner';

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'cltd-lightbox__close';
    closeButton.setAttribute('aria-label', strings.close);
    closeButton.innerHTML = '&times;';

    const image = document.createElement('img');
    image.className = 'cltd-lightbox__image';
    image.alt = '';

    const caption = document.createElement('p');
    caption.className = 'cltd-lightbox__caption';
    caption.hidden = true;

    inner.appendChild(closeButton);
    inner.appendChild(image);
    inner.appendChild(caption);
    overlay.appendChild(inner);
    document.body.appendChild(overlay);

    const closeHandler = (event) => {
      event.preventDefault();
      closeLightbox();
    };

    closeButton.addEventListener('click', closeHandler);
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) {
        closeLightbox();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && overlay.classList.contains('is-active')) {
        event.preventDefault();
        closeLightbox();
      }
    });

    lightboxElements = {
      overlay,
      image,
      caption,
      closeButton
    };

    return lightboxElements;
  }

  function closeLightbox() {
    if (!lightboxElements) {
      return;
    }

    lightboxElements.overlay.classList.remove('is-active');
    document.body.classList.remove('cltd-lightbox-open');

    setTimeout(() => {
      if (!lightboxElements) {
        return;
      }
      lightboxElements.image.removeAttribute('src');
      lightboxElements.image.alt = '';
      lightboxElements.caption.textContent = '';
      lightboxElements.caption.hidden = true;
    }, 200);
  }

  function openLightbox(src, alt, titleText) {
    if (!src) {
      return;
    }

    const { overlay, image, caption } = ensureLightboxElements();
    image.src = src;
    image.alt = alt || '';

    const captionText = titleText || alt || '';
    if (captionText) {
      caption.textContent = captionText;
      caption.hidden = false;
    } else {
      caption.textContent = '';
      caption.hidden = true;
    }

    overlay.classList.add('is-active');
    document.body.classList.add('cltd-lightbox-open');
  }

  function getLargestSrcFromSrcset(srcset) {
    if (!srcset) {
      return '';
    }

    return srcset
      .split(',')
      .map((candidate) => {
        const parts = candidate.trim().split(/\s+/u);
        if (!parts.length) {
          return { url: '', width: 0 };
        }
        const url = parts[0];
        const descriptor = parts[1] || '';
        const width = descriptor.endsWith('w') ? parseInt(descriptor, 10) : 0;
        return { url, width: Number.isNaN(width) ? 0 : width };
      })
      .sort((a, b) => b.width - a.width)
      .map((entry) => entry.url)
      .find(Boolean) || '';
  }

  function resolveImageSource(img, anchor) {
    if (!img) {
      return '';
    }

    const dataAttributes = [
      'data-full-src',
      'data-full-url',
      'data-orig-file',
      'data-large-file',
      'data-src',
      'data-original',
      'data-lazy-src'
    ];

    for (let i = 0; i < dataAttributes.length; i += 1) {
      const attribute = img.getAttribute(dataAttributes[i]);
      if (attribute) {
        return attribute;
      }
    }

    const srcset = img.getAttribute('data-srcset') || img.getAttribute('srcset');
    if (srcset) {
      const largest = getLargestSrcFromSrcset(srcset);
      if (largest) {
        return largest;
      }
    }

    if (anchor && anchor.getAttribute) {
      const href = anchor.getAttribute('href');
      if (href && /\.(jpe?g|png|webp|gif|avif|svg)$/iu.test(href)) {
        return href;
      }
    }

    return img.currentSrc || img.src || '';
  }

  function shouldAttachLightbox(img) {
    if (!img || processedLightboxImages.has(img)) {
      return false;
    }

    if (img.closest('[data-no-lightbox]')) {
      return false;
    }

    if (img.hasAttribute('data-skip-lightbox') || img.classList.contains('skip-lightbox')) {
      return false;
    }

    if (img.width && img.width < 48) {
      return false;
    }

    return true;
  }

  function hydrateLightboxImages(root = document) {
    if (!root) {
      return;
    }

    const selectors = '.cltd-modal__content img, .entry-content img, .grid-group img, .project-grid img, .wp-block-image img';
    const scope = root.querySelectorAll ? root.querySelectorAll(selectors) : [];

    scope.forEach((img) => {
      if (!shouldAttachLightbox(img)) {
        return;
      }

      const link = img.closest('a');
      const lightboxHandler = (event) => {
        const targetImg = img;
        const targetLink = link || null;
        const src = resolveImageSource(targetImg, targetLink);

        if (!src) {
          return;
        }

        event.preventDefault();
        openLightbox(src, targetImg.getAttribute('alt') || '', targetImg.getAttribute('title') || '');
      };

      if (link && link.getAttribute) {
        const href = link.getAttribute('href') || '';
        if (href && /\.(jpe?g|png|webp|gif|avif|svg)$/iu.test(href)) {
          link.addEventListener('click', lightboxHandler);
        } else {
          processedLightboxImages.add(img);
          return;
        }
      } else {
        img.addEventListener('click', lightboxHandler);
      }

      img.classList.add('cltd-lightbox-trigger');
      processedLightboxImages.add(img);
    });
  }


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

  function initLottieIcons() {
    const icons = document.querySelectorAll('[data-lottie-icon]');
    if (!icons.length || typeof window.lottie === 'undefined') {
      return;
    }

    icons.forEach((icon) => {
      if (!icon || icon.dataset.lottieMounted === '1') {
        return;
      }

      const src = icon.getAttribute('data-lottie-src');
      if (!src) {
        return;
      }

      window.lottie.loadAnimation({
        container: icon,
        renderer: 'svg',
        loop: icon.getAttribute('data-lottie-loop') !== 'false',
        autoplay: icon.getAttribute('data-lottie-autoplay') !== 'false',
        path: src,
        rendererSettings: {
          preserveAspectRatio: 'xMidYMid meet',
          progressiveLoad: true
        }
      });

      icon.dataset.lottieMounted = '1';
    });
  }

  function initDomFeatures() {
    initHeroBackground();
    initLottieIcons();
    hydratePopupLinks();
    hydrateLightboxImages();
  }

  if (document.readyState !== 'loading') {
    initDomFeatures();
  } else {
    document.addEventListener('DOMContentLoaded', initDomFeatures);
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
      const defaultTitle = trigger.getAttribute('data-popup-title') || (trigger.textContent ? trigger.textContent.trim() : '');
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
        hydratePopupLinks(contentEl);
        hydrateLightboxImages(contentEl);
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

  async function fetchPagePopup(pageId, fallbackUrl) {
    const numericId = Number(pageId);

    if (!numericId) {
      setStatus(strings.error, true);
      return;
    }

    if (scrollIndicator) {
      scrollIndicator.classList.remove('is-visible');
    }

    const base = ensureRestUrl(pageRestBase);
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
      const response = await fetch(`${base}${numericId}`, {
        signal: controller.signal,
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
      }

      const data = await response.json();
      const title = data && typeof data.title === 'string' ? data.title : '';
      const content = data && typeof data.content === 'string' ? data.content : '';

      if (title) {
        titleEl.textContent = title;
      }

      if (content) {
        contentEl.innerHTML = content;
        hydratePopupLinks(contentEl);
        hydrateLightboxImages(contentEl);
      } else if (fallbackUrl) {
        const paragraph = document.createElement('p');
        paragraph.className = 'cltd-modal__status is-error';
        const link = document.createElement('a');
        link.href = fallbackUrl;
        link.textContent = strings.error;
        paragraph.appendChild(link);
        contentEl.innerHTML = '';
        contentEl.appendChild(paragraph);
        hydratePopupLinks(contentEl);
        hydrateLightboxImages(contentEl);
      } else {
        setStatus(strings.error, true);
      }

      requestAnimationFrame(updateScrollIndicator);
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }

      setStatus(strings.error, true);
      console.error('CLTD page popup request failed:', error);
    } finally {
      if (activeRequest === controller) {
        activeRequest = null;
      }

      requestAnimationFrame(updateScrollIndicator);
    }
  }

  function activatePopupTrigger(trigger) {
    if (!trigger) {
      return;
    }

    if (trigger.matches('[disabled], [aria-disabled="true"]')) {
      return;
    }

    if (isOpen && trigger === activeTrigger) {
      return;
    }

    openModal(trigger);

    if (trigger.getAttribute('data-popup') === 'true') {
      const pageId = trigger.getAttribute('data-popup-page-id');
      const fallbackUrl = trigger.getAttribute('data-popup-url') || trigger.getAttribute('href');
      fetchPagePopup(pageId, fallbackUrl);
      return;
    }

    if (trigger.hasAttribute('data-popup-slug')) {
      const slug = trigger.getAttribute('data-popup-slug');
      fetchPopup(slug);
    }
  }

  function handleTriggerClick(event) {
    const trigger = event.target.closest('[data-popup-slug], [data-popup="true"]');
    if (!trigger) {
      return;
    }

    event.preventDefault();
    activatePopupTrigger(trigger);
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
