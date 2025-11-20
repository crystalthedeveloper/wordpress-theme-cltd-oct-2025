(() => {
  const config = window.CLTDTheme || {};
  const homeUrl = typeof config.homeUrl === 'string' && config.homeUrl ? config.homeUrl : '/';
  const orderCancelledPath = normalizePath('/order-cancelled/');
  const restNonce = typeof config.restNonce === 'string' ? config.restNonce : '';
  const restBase = typeof config.restUrl === 'string' ? config.restUrl : '';
  const pageRestBase = typeof config.pagePopupRestUrl === 'string' ? config.pagePopupRestUrl : '';
  const ajaxUrl = typeof config.ajaxUrl === 'string' && config.ajaxUrl ? config.ajaxUrl : '';
  const clownhuntRestBase = typeof config.clownhuntRestBase === 'string' && config.clownhuntRestBase ? config.clownhuntRestBase : '';
  const clownhuntGameUrl = typeof config.clownhuntGameUrl === 'string' && config.clownhuntGameUrl ? config.clownhuntGameUrl : 'https://clown-hunt.vercel.app/';
  const clownhuntApi = typeof config.clownhuntApi === 'object' && config.clownhuntApi ? config.clownhuntApi : {};
  const heroBackground = config.heroBackground || {};
  const popupPages = Array.isArray(config.popupPages) ? config.popupPages : [];
  const popupPageLookup = new Map();
  const popupSlugLookup = new Map();
  const processedLightboxImages = new WeakSet();
  const preparedCartButtons = new WeakSet();
  const shouldSkipCltdForm = (form) => {
    if (!form) {
      return false;
    }
    if (form.dataset && form.dataset.cltdPlayForm === '1') {
      return true;
    }
    if (typeof form.closest === 'function' && form.closest('[data-cltd-play-form="1"]')) {
      return true;
    }
    return false;
  };
  const loadProfileEndpoint =
    typeof clownhuntApi.loadProfile === 'string' && clownhuntApi.loadProfile
      ? clownhuntApi.loadProfile
      : '';
  const saveProfileEndpoint =
    typeof clownhuntApi.saveProfile === 'string' && clownhuntApi.saveProfile
      ? clownhuntApi.saveProfile
      : '';
  const loadGuestEndpoint =
    typeof clownhuntApi.loadGuest === 'string' && clownhuntApi.loadGuest
      ? clownhuntApi.loadGuest
      : '';
  const saveGuestEndpoint =
    typeof clownhuntApi.saveGuest === 'string' && clownhuntApi.saveGuest
      ? clownhuntApi.saveGuest
      : '';
  const leaderboardEndpoint =
    typeof clownhuntApi.leaderboard === 'string' && clownhuntApi.leaderboard
      ? clownhuntApi.leaderboard
      : '';
  let lightboxElements = null;
  const strings = Object.assign(
    {
      loading: 'Loading…',
      error: 'We could not load that content right now. Please try again.',
      close: 'Close popup'
    },
    config.strings || {}
  );
  const leaderboardEmptyMessage = strings.leaderboardEmpty || 'Leaderboard data will appear here soon.';
  const leaderboardLoadingMessage = strings.leaderboardLoading || strings.loading || 'Loading…';

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

  function normalizeHostname(host) {
    if (!host) {
      return '';
    }
    return host.toLowerCase().replace(/^www\./u, '');
  }

  function toNumber(value, fallback = 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function getCurrentUserId() {
    if (typeof window.wpUserId !== 'undefined') {
      const localized = toNumber(window.wpUserId, 0);
      if (localized > 0) {
        return localized;
      }
    }

    if (typeof config.currentUserId !== 'undefined') {
      const fromConfig = toNumber(config.currentUserId, 0);
      if (fromConfig > 0) {
        return fromConfig;
      }
    }

    return 0;
  }

  function normalizePath(url) {
    if (!url) {
      return '';
    }

    const link = document.createElement('a');
    link.href = url;

    const currentHost = normalizeHostname(window.location.hostname || window.location.host);
    const fallbackHost = window.location.hostname || window.location.host || '';
    const targetHost = normalizeHostname(link.hostname || fallbackHost);

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

  function getPopupSlugFromQuery() {
    try {
      const params = new URLSearchParams(window.location.search || '');
      const raw = params.get('popup') || params.get('cltd_popup');
      if (!raw) {
        return '';
      }
      const trimmed = raw.trim().toLowerCase();
      return trimmed.replace(/[^a-z0-9-_]/g, '');
    } catch (error) {
      return '';
    }
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

    if (!link.dataset.gtmPopup) {
      const explicitSlug = typeof page.slug === 'string' && page.slug ? page.slug : '';
      const derivedSlug = explicitSlug || extractSlug(normalizePath(page.permalink || page.url || ''));
      if (derivedSlug) {
        link.dataset.gtmPopup = derivedSlug;
      } else if (page.title) {
        link.dataset.gtmPopup = extractSlug(page.title.toLowerCase().replace(/\s+/g, '-'));
      }
    }
  }

  function getCurrentPopupMatch() {
    const current = normalizePath(window.location.href || window.location.pathname || '');
    if (!current) {
      return null;
    }
    return findPopupPage(current);
  }

  async function hydrateAccountStats() {
    const killsEl = document.getElementById('user-kills');
    const rankEl = document.getElementById('user-rank');

    if (!killsEl || !rankEl) {
      return;
    }

    const applyStats = (killsValue, rankValue) => {
      killsEl.textContent = String(toNumber(killsValue, 0));
      rankEl.textContent = String(toNumber(rankValue, 0));
    };

    applyStats(killsEl.textContent, rankEl.textContent);

    const userId = getCurrentUserId();
    if (!userId) {
      applyStats(0, 0);
      return;
    }

    if (!loadProfileEndpoint) {
      console.warn('Clown Hunt load profile endpoint missing.');
      applyStats(0, 0);
      return;
    }

    try {
      const profileRes = await fetch(loadProfileEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify({ user_id: String(userId) })
      });

      if (!profileRes.ok) {
        throw new Error(`Profile request failed with status ${profileRes.status}`);
      }

      const profile = await profileRes.json();
      if (!profile || profile.status !== 'success') {
        throw new Error(profile && profile.message ? profile.message : 'Invalid profile response');
      }
      const killsValue = typeof profile.kills !== 'undefined' ? profile.kills : 0;
      const rankValue = typeof profile.rank !== 'undefined' ? profile.rank : 0;
      applyStats(killsValue, rankValue);
    } catch (error) {
      console.warn('Unable to load Clown Hunt profile.', error);
    }
  }

  function renderLeaderboardFallback(tbody, message) {
    if (!tbody) {
      return;
    }

    const row = document.createElement('tr');
    row.className = 'cltd-auth__leaderboard-row is-empty';
    const cell = document.createElement('td');
    cell.colSpan = 3;
    cell.textContent = message;
    row.appendChild(cell);
    tbody.innerHTML = '';
    tbody.appendChild(row);
  }

  async function hydrateLeaderboard() {
    const tbody = document.getElementById('leaderboard-body');
    if (!tbody) {
      return;
    }

    if (!leaderboardEndpoint) {
      renderLeaderboardFallback(tbody, leaderboardEmptyMessage);
      console.warn('Clown Hunt leaderboard endpoint missing.');
      return;
    }

    renderLeaderboardFallback(tbody, leaderboardLoadingMessage);

    try {
      const response = await fetch(leaderboardEndpoint, {
        headers: {
          Accept: 'application/json'
        }
      });
      if (!response.ok) {
        throw new Error(`Leaderboard request failed with status ${response.status}`);
      }

      const board = await response.json();
      if (!board || board.status !== 'success') {
        throw new Error(board && board.message ? board.message : 'Invalid leaderboard response');
      }
      const players = Array.isArray(board.leaderboard) ? board.leaderboard : [];

      if (!players.length) {
        renderLeaderboardFallback(tbody, leaderboardEmptyMessage);
        return;
      }

      tbody.innerHTML = '';
      players.forEach((player, index) => {
        const row = document.createElement('tr');
        row.className = 'cltd-auth__leaderboard-row';

        const rankCell = document.createElement('td');
        const killsCell = document.createElement('td');
        const nameCell = document.createElement('td');

        const resolvedRank = toNumber(player && player.rank, index + 1);
        const resolvedKills = toNumber(player && player.kills, 0);
        const resolvedName =
          (player && typeof player.first_name === 'string' && player.first_name.trim()) ||
          (player && typeof player.name === 'string' && player.name.trim()) ||
          'Unknown';

        rankCell.textContent = String(resolvedRank);
        nameCell.textContent = resolvedName;
        killsCell.textContent = String(resolvedKills);

        row.appendChild(rankCell);
        row.appendChild(nameCell);
        row.appendChild(killsCell);
        tbody.appendChild(row);
      });
    } catch (error) {
      console.warn('Unable to load leaderboard.', error);
      renderLeaderboardFallback(tbody, leaderboardEmptyMessage);
    }
  }

  function cleanupViewCartLinks(scope) {
    if (!scope) {
      return;
    }
    const links = scope.querySelectorAll ? scope.querySelectorAll('.added_to_cart') : [];
    links.forEach((link) => link.remove());
  }

  function hydrateCartButtons(root = document) {
    if (!root) {
      return;
    }

    const buttons = root.querySelectorAll('.cltd-add-to-cart');
    buttons.forEach((button) => {
      if (!button || preparedCartButtons.has(button)) {
        return;
      }
      const label = button.textContent ? button.textContent.trim() : '';
      if (label && !button.dataset.cltdCartLabel) {
        button.dataset.cltdCartLabel = label;
      }
      cleanupViewCartLinks(button.parentElement || null);
      preparedCartButtons.add(button);
    });
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

  function renderLoginErrors(form, messages) {
    if (!form) {
      return;
    }

    const container = form.closest('.cltd-auth');
    if (!container) {
      return;
    }

    const normalizedMessages = Array.isArray(messages) ? messages.filter((message) => typeof message === 'string' && message.trim()) : [];

    if (!normalizedMessages.length) {
      const existingNotice = container.querySelector('[data-cltd-login-errors]');
      if (existingNotice) {
        existingNotice.remove();
      }
      return;
    }

    let notice = container.querySelector('[data-cltd-login-errors]');
    if (!notice) {
      notice = document.createElement('div');
      notice.className = 'cltd-auth__notice cltd-auth__notice--error';
      notice.setAttribute('data-cltd-login-errors', '1');

      const description = container.querySelector('.cltd-auth__description');
      if (description && typeof description.insertAdjacentElement === 'function') {
        description.insertAdjacentElement('afterend', notice);
      } else {
        container.insertBefore(notice, container.firstChild);
      }
    }

    let list = notice.querySelector('ul');
    if (!list) {
      list = document.createElement('ul');
      notice.innerHTML = '';
      notice.appendChild(list);
    } else {
      list.innerHTML = '';
    }

    normalizedMessages.forEach((message) => {
      const item = document.createElement('li');
      item.textContent = message;
      list.appendChild(item);
    });
  }

  function renderGuestErrors(wrapper, messages) {
    if (!wrapper) {
      return;
    }

    wrapper.innerHTML = '';
    const normalizedMessages = Array.isArray(messages) ? messages.filter((message) => typeof message === 'string' && message.trim()) : [];

    if (!normalizedMessages.length) {
      return;
    }

    const notice = document.createElement('div');
    notice.className = 'cltd-auth__notice cltd-auth__notice--error';
    const list = document.createElement('ul');
    normalizedMessages.forEach((message) => {
      const li = document.createElement('li');
      li.textContent = message;
      list.appendChild(li);
    });
    notice.appendChild(list);
    wrapper.appendChild(notice);
  }

  async function submitLoginForm(form) {
    if (!ajaxUrl || !form || shouldSkipCltdForm(form)) {
      return;
    }

    if (form.dataset.cltdLoginSubmitting === '1') {
      return;
    }

    form.dataset.cltdLoginSubmitting = '1';

    const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
    const originalLabel = submitButton && submitButton.tagName === 'BUTTON' ? submitButton.textContent : '';
    if (submitButton) {
      submitButton.disabled = true;
      if (submitButton.tagName === 'BUTTON' && strings.loginProcessing) {
        submitButton.dataset.cltdOriginalText = originalLabel;
        submitButton.textContent = strings.loginProcessing;
      }
    }

    renderLoginErrors(form, []);

    const formData = new FormData(form);
    formData.append('action', 'cltd_auth_login');
    const nonceField = formData.get('cltd_auth_login_nonce');
    if (nonceField && !formData.has('nonce')) {
      formData.append('nonce', nonceField);
    }

    try {
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      });

      const payload = await response.json().catch(() => null);
      if (!payload) {
        throw new Error('Invalid response');
      }

      if (payload.success && payload.data) {
        const redirect = typeof payload.data.redirect === 'string' && payload.data.redirect ? payload.data.redirect : window.location.href;
        window.location.href = redirect;
        return;
      }

      const messages = payload.data && Array.isArray(payload.data.messages) && payload.data.messages.length
        ? payload.data.messages
        : [strings.loginError || 'Login failed. Please try again.'];
      renderLoginErrors(form, messages);
    } catch (error) {
      renderLoginErrors(form, [strings.loginError || 'Login failed. Please try again.']);
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        if (submitButton.dataset.cltdOriginalText) {
          submitButton.textContent = submitButton.dataset.cltdOriginalText;
          delete submitButton.dataset.cltdOriginalText;
        }
      }

      delete form.dataset.cltdLoginSubmitting;
    }
  }

  function hydrateLoginForms(root = document) {
    if (!root || !ajaxUrl) {
      return;
    }

    const forms = root.querySelectorAll('form[data-cltd-auth-login]');
    forms.forEach((form) => {
      if (!form || form.dataset.cltdLoginHydrated === '1' || shouldSkipCltdForm(form)) {
        return;
      }

      form.dataset.cltdLoginHydrated = '1';
      form.addEventListener('submit', (event) => {
        if (shouldSkipCltdForm(form)) {
          return;
        }
        event.preventDefault();
        submitLoginForm(form);
      });
    });
  }

  async function submitGuestForm(form) {
    if (!clownhuntRestBase || !form || form.dataset.cltdGuestSubmitting === '1' || shouldSkipCltdForm(form)) {
      return;
    }

    const firstNameInput = form.querySelector('input[name="cltd_guest_first_name"]');
    const emailInput = form.querySelector('input[name="cltd_guest_email"]');
    const errorWrapper = form.parentElement ? form.parentElement.querySelector('[data-cltd-guest-errors]') : null;

    const firstName = firstNameInput ? firstNameInput.value.trim() : '';
    const email = emailInput ? emailInput.value.trim() : '';

    renderGuestErrors(errorWrapper, []);

    if (!firstName || !email) {
      renderGuestErrors(errorWrapper, [strings.loginError || 'Please complete all guest fields.']);
      return;
    }

    form.dataset.cltdGuestSubmitting = '1';

    const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
    }

    try {
      const response = await fetch(`${clownhuntRestBase}create_guest`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': restNonce
        },
        body: JSON.stringify({
          email,
          first_name: firstName
        })
      });

      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload || typeof payload !== 'object') {
        const message = payload && payload.message ? payload.message : (strings.loginError || 'Unable to create guest token.');
        renderGuestErrors(errorWrapper, [message]);
        return;
      }

      const gameUrl =
        (payload && payload.game_url) ||
        (payload && payload.data && payload.data.game_url) ||
        '';
      const restBase =
        (payload && payload.rest_base) ||
        (payload && payload.data && payload.data.rest_base) ||
        '';

      if (restBase && typeof window !== 'undefined' && window.localStorage) {
        try {
          window.localStorage.setItem('clownhunt_rest_base', restBase);
        } catch (storageError) {
          // Ignore storage errors (e.g. private mode).
        }
      }

      if (!gameUrl) {
        renderGuestErrors(errorWrapper, [strings.loginError || 'Unable to determine Clown Hunt guest URL.']);
        return;
      }

      window.location.href = gameUrl;
    } catch (error) {
      renderGuestErrors(errorWrapper, [strings.loginError || 'Unable to create guest token.']);
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
      }
      delete form.dataset.cltdGuestSubmitting;
    }
  }

  function hydrateGuestForms(root = document) {
    if (!root) {
      return;
    }

    const forms = root.querySelectorAll('form[data-cltd-guest-login]');
    forms.forEach((form) => {
      if (form.dataset.cltdGuestHydrated === '1' || shouldSkipCltdForm(form)) {
        return;
      }
      form.dataset.cltdGuestHydrated = '1';
      form.addEventListener('submit', (event) => {
        if (shouldSkipCltdForm(form)) {
          return;
        }
        event.preventDefault();
        submitGuestForm(form);
      });
    });
  }

  function hydrateGuestSections(root = document) {
    if (!root) {
      return;
    }

    const areas = root.querySelectorAll('[data-cltd-guest-area]');
    areas.forEach((area) => {
      if (!area || area.dataset.cltdGuestHydrated === '1') {
        return;
      }

      const toggle = area.querySelector('[data-cltd-guest-toggle]');
      const panel = area.querySelector('[data-cltd-guest-panel]');
      if (!toggle || !panel) {
        return;
      }

      const setState = (open) => {
        if (open) {
          panel.hidden = false;
          area.dataset.cltdGuestOpen = 'true';
        } else {
          panel.hidden = true;
          area.dataset.cltdGuestOpen = 'false';
        }
      };

      setState(false);

      toggle.addEventListener('click', () => {
        setState(panel.hidden);
      });

      area.dataset.cltdGuestHydrated = '1';
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

  function initInvertModeToggle() {
    const body = document.body;
    if (!body) {
      return;
    }

    let lastTouchToggle = 0;

    function isInvertToggle(element) {
      if (!element) {
        return false;
      }

      if (element.matches('[data-cltd-invert-toggle]')) {
        return true;
      }

      const label = (element.textContent || '').trim().toLowerCase();
      if (label === 'dark' || label === 'light') {
        return true;
      }

      if (element.matches('a[href]')) {
        const href = element.getAttribute('href') || '';
        if (!href) {
          return false;
        }

        const trimmed = href.trim();
        if (!trimmed) {
          return false;
        }

        if (trimmed.replace(/^#+/u, '').toLowerCase() === 'dark') {
          return true;
        }

        const normalized = normalizePath(trimmed);
        if (normalized === '/dark') {
          return true;
        }
      }

      return false;
    }

    function getInvertToggleElements() {
      const candidates = document.querySelectorAll('a[href], button[data-cltd-invert-toggle], [data-cltd-invert-toggle]');
      return Array.from(candidates).filter(isInvertToggle);
    }

    function toggleInvertModeState() {
      body.classList.toggle('invert-mode');
      updateLabel();
    }

    function updateLabel() {
      const isActive = body.classList.contains('invert-mode');
      const toggles = getInvertToggleElements();
      toggles.forEach((toggle) => {
        if (!toggle) {
          return;
        }
        if (!(toggle.tagName && toggle.tagName.toLowerCase() === 'a')) {
          toggle.setAttribute('role', 'button');
          toggle.setAttribute('tabindex', toggle.getAttribute('tabindex') || '0');
        } else if (toggle.getAttribute('role') === 'button') {
          toggle.removeAttribute('role');
        }

        toggle.textContent = isActive ? 'Light' : 'Dark';
        toggle.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
    }

    function activateToggle(event) {
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }
      toggleInvertModeState();
    }

    function handleDelegatedClick(event) {
      const target = event.target.closest('[data-cltd-invert-toggle], a[href], button');
      if (!target || !isInvertToggle(target)) {
        return;
      }

      if (event.type === 'touchend') {
        lastTouchToggle = Date.now();
      } else if (event.type === 'click') {
        if (lastTouchToggle && Date.now() - lastTouchToggle < 350) {
          event.preventDefault();
          return;
        }
      } else if (event.type === 'keydown' && event.key !== ' ' && event.key !== 'Enter') {
        return;
      }

      activateToggle(event);
    }

    document.addEventListener('click', handleDelegatedClick);
    document.addEventListener('touchend', handleDelegatedClick, { passive: false });
    document.addEventListener('keydown', (event) => {
      const target = event.target.closest('[data-cltd-invert-toggle], a[href], button');
      if (!target || !isInvertToggle(target)) {
        return;
      }
      handleDelegatedClick(event);
    });

    if (typeof window.MutationObserver === 'function') {
      let scheduled = false;
      const scheduleUpdate = () => {
        if (scheduled) {
          return;
        }
        scheduled = true;
        const runner = window.requestAnimationFrame || window.webkitRequestAnimationFrame || ((cb) => setTimeout(cb, 16));
        runner(() => {
          scheduled = false;
          updateLabel();
        });
      };

      const observer = new MutationObserver((mutations) => {
        for (let i = 0; i < mutations.length; i += 1) {
          if (mutations[i].addedNodes && mutations[i].addedNodes.length) {
            scheduleUpdate();
            break;
          }
        }
      });
      observer.observe(document.body, { childList: true, subtree: true });
    }

    updateLabel();
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
            if (slide.dataset.hideControls === '1') {
              video.classList.add('is-hidden-controls');
              video.removeAttribute('controls');
            }
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
    hydrateCartButtons();
    hydrateLightboxImages();
    hydrateGuestSections();
    hydrateGuestForms();
    hydrateLoginForms();
    initInvertModeToggle();
    hydrateAccountStats();
    hydrateLeaderboard();
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
    redirectAfterOrderCancelledClose();
  }

  function redirectAfterOrderCancelledClose() {
    if (!orderCancelledPath) {
      return;
    }
    const currentPath = normalizePath(window.location.pathname || window.location.href || '');
    if (currentPath === orderCancelledPath) {
      window.location.href = homeUrl || '/';
    }
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
        credentials: 'same-origin',
        headers: restNonce ? { 'X-WP-Nonce': restNonce } : {}
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
        hydrateCartButtons(contentEl);
        cleanupViewCartLinks(contentEl);
        hydrateLightboxImages(contentEl);
        hydrateGuestSections(contentEl);
        hydrateGuestForms(contentEl);
        hydrateLoginForms(contentEl);
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
        credentials: 'same-origin',
        headers: restNonce ? { 'X-WP-Nonce': restNonce } : {}
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
        hydrateCartButtons(contentEl);
        cleanupViewCartLinks(contentEl);
        hydrateLightboxImages(contentEl);
        hydrateGuestSections(contentEl);
        hydrateGuestForms(contentEl);
        hydrateLoginForms(contentEl);
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
        hydrateCartButtons(contentEl);
        cleanupViewCartLinks(contentEl);
        hydrateLightboxImages(contentEl);
        hydrateGuestSections(contentEl);
        hydrateGuestForms(contentEl);
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

  function createAutoTriggerForPage(page) {
    if (!page || !page.id) {
      return null;
    }

    const phantom = document.createElement('a');
    phantom.href = page.permalink || window.location.href;
    phantom.dataset.popup = 'true';
    phantom.dataset.popupPageId = String(page.id);

    if (page.permalink) {
      phantom.dataset.popupUrl = page.permalink;
    }
    if (page.title) {
      phantom.dataset.popupTitle = page.title;
    }

    phantom.setAttribute('tabindex', '-1');
    phantom.setAttribute('aria-hidden', 'true');
    phantom.style.position = 'absolute';
    phantom.style.width = '1px';
    phantom.style.height = '1px';
    phantom.style.overflow = 'hidden';
    phantom.style.clip = 'rect(0 0 0 0)';
    phantom.style.clipPath = 'inset(50%)';
    if (!phantom.dataset.gtmPopup) {
      const explicitSlug = typeof page.slug === 'string' && page.slug ? page.slug : '';
      const derivedSlug = explicitSlug || extractSlug(normalizePath(page.permalink || page.url || window.location.pathname || ''));
      if (derivedSlug) {
        phantom.dataset.gtmPopup = derivedSlug;
      }
    }

    document.body.appendChild(phantom);
    return phantom;
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
    if (event.target && typeof event.target.closest === 'function' && event.target.closest('[data-cltd-play-form="1"]')) {
      return;
    }
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

  let hasAutoOpenedPopup = false;
  const autoOpenPage = getCurrentPopupMatch();
  if (autoOpenPage) {
    const runAutoOpen = () => {
      const phantomTrigger = createAutoTriggerForPage(autoOpenPage);
      if (!phantomTrigger) {
        return;
      }
      requestAnimationFrame(() => {
        activatePopupTrigger(phantomTrigger);
        phantomTrigger.remove();
        hasAutoOpenedPopup = true;
      });
    };

    if (document.readyState !== 'loading') {
      runAutoOpen();
    } else {
      document.addEventListener('DOMContentLoaded', runAutoOpen, { once: true });
    }
  }

  const autoPopupSlug = getPopupSlugFromQuery();
  if (autoPopupSlug && !hasAutoOpenedPopup) {
    const runSlugOpen = () => {
      openModal();
      fetchPopup(autoPopupSlug);
      hasAutoOpenedPopup = true;
    };

    if (document.readyState !== 'loading') {
      runSlugOpen();
    } else {
      document.addEventListener('DOMContentLoaded', runSlugOpen, { once: true });
    }
  }

  if (window.jQuery) {
    const $ = window.jQuery;
    $(document.body).on('added_to_cart', (event, fragments, cartHash, $button) => {
      if (!$button || !$button.length || !$button.hasClass('cltd-add-to-cart')) {
        return;
      }
      const element = $button.get(0);
      if (element && element.dataset.cltdCartLabel) {
        $button.text(element.dataset.cltdCartLabel);
      }
      $button.removeClass('added');
      const parent = $button.parent().get(0) || null;
      cleanupViewCartLinks(parent || document);
    });
  }
})();
