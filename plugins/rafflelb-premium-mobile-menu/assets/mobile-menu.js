(function () {
  'use strict';

  var drawer = document.getElementById('rlmm-drawer');
  var backdrop = document.getElementById('rlmm-backdrop');

  if (!drawer || !backdrop) {
    return;
  }

  function mountIntoWoodmartDrawer() {
    var nativeDrawer = document.querySelector('.mobile-nav:not(.rlmm-drawer)');
    var menuContent = drawer.querySelector('.rlmm-scroll');

    if (!nativeDrawer || !menuContent) {
      return false;
    }

    nativeDrawer.replaceChildren(menuContent);
    drawer.remove();

    nativeDrawer.classList.add('rlmm-drawer', 'rlmm-native-mounted');
    nativeDrawer.id = 'rlmm-drawer';
    nativeDrawer.setAttribute('data-rlmm-version', '1.1.2');
    nativeDrawer.setAttribute('aria-label', 'RaffleLB mobile navigation');
    nativeDrawer.setAttribute('aria-hidden', 'true');
    nativeDrawer.setAttribute('inert', '');
    drawer = nativeDrawer;

    return true;
  }

  mountIntoWoodmartDrawer();
  document.documentElement.setAttribute('data-rlmm-version', '1.1.2');

  var mobile = window.matchMedia('(max-width: 1024px)');
  var closeButton = drawer.querySelector('.rlmm-close');
  var lastFocused = null;

  var triggerSelector = [
    '.wd-header-mobile-nav',
    '.wd-header-mobile-nav > a',
    '.wd-header-mobile-nav a',
    '.mobile-nav-icon',
    '.mobile-nav-icon > a',
    'a.mobile-nav-icon'
  ].join(',');

  function triggers() {
    return Array.prototype.slice.call(document.querySelectorAll(triggerSelector));
  }

  function setTriggerState(open) {
    triggers().forEach(function (trigger) {
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      trigger.setAttribute('aria-controls', 'rlmm-drawer');
      if (!trigger.getAttribute('aria-label')) {
        trigger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
      }
    });
  }

  function closeWoodmartDrawer() {
    document.documentElement.classList.remove('mobile-nav-opened');
    document.body.classList.remove('mobile-nav-opened', 'wd-opened');

    document.querySelectorAll('.mobile-nav.wd-opened, .wd-side-hidden.wd-opened').forEach(function (menu) {
      menu.classList.remove('wd-opened');
    });
  }

  function focusableItems() {
    return Array.prototype.slice.call(
      drawer.querySelectorAll('a[href]:not([hidden]), button:not([disabled])')
    ).filter(function (element) {
      return element.offsetParent !== null;
    });
  }

  function openMenu(trigger) {
    if (!mobile.matches) {
      return;
    }

    if (document.body.classList.contains('rlmm-is-open')) {
      return;
    }

    closeWoodmartDrawer();
    lastFocused = trigger || document.activeElement;
    document.body.classList.add('rlmm-is-open');
    drawer.removeAttribute('inert');
    drawer.setAttribute('aria-hidden', 'false');
    backdrop.setAttribute('aria-hidden', 'false');
    setTriggerState(true);

    window.requestAnimationFrame(function () {
      if (closeButton) {
        closeButton.focus({ preventScroll: true });
      }
    });
  }

  function closeMenu(returnFocus) {
    document.body.classList.remove('rlmm-is-open');
    drawer.setAttribute('aria-hidden', 'true');
    drawer.setAttribute('inert', '');
    backdrop.setAttribute('aria-hidden', 'true');
    setTriggerState(false);

    if (returnFocus && lastFocused && typeof lastFocused.focus === 'function') {
      lastFocused.focus({ preventScroll: true });
    }
  }

  function interceptWoodmartTrigger(event) {
    if (!mobile.matches || !(event.target instanceof Element)) {
      return;
    }

    var trigger = event.target.closest(triggerSelector);

    if (!trigger || trigger.closest('#rlmm-drawer')) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    openMenu(trigger);
  }

  document.addEventListener('click', interceptWoodmartTrigger, true);
  document.addEventListener('touchend', interceptWoodmartTrigger, {
    capture: true,
    passive: false
  });

  if (closeButton) {
    closeButton.addEventListener('click', function (event) {
      event.preventDefault();
      closeMenu(true);
    });
  }

  backdrop.addEventListener('click', function () {
    closeMenu(true);
  });

  drawer.addEventListener('click', function (event) {
    if (!(event.target instanceof Element)) {
      return;
    }

    var disabledSocial = event.target.closest('[data-rlmm-social][aria-disabled="true"]');

    if (disabledSocial) {
      event.preventDefault();
      return;
    }

    if (event.target.closest('a[href]')) {
      closeMenu(false);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (!document.body.classList.contains('rlmm-is-open')) {
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      closeMenu(true);
      return;
    }

    if (event.key !== 'Tab') {
      return;
    }

    var items = focusableItems();
    if (!items.length) {
      return;
    }

    var first = items[0];
    var last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  function handleBreakpoint(event) {
    if (!event.matches) {
      closeMenu(false);
    }
  }

  function woodmartDrawerIsOpen() {
    return document.documentElement.classList.contains('mobile-nav-opened')
      || document.body.classList.contains('mobile-nav-opened')
      || document.body.classList.contains('wd-opened')
      || Boolean(document.querySelector('.mobile-nav.wd-opened, .mobile-nav.opened'));
  }

  function replaceNativeDrawer() {
    if (
      mobile.matches
      && !document.body.classList.contains('rlmm-is-open')
      && woodmartDrawerIsOpen()
    ) {
      openMenu(document.activeElement);
    }
  }

  var nativeDrawerObserver = new MutationObserver(replaceNativeDrawer);

  nativeDrawerObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class']
  });

  nativeDrawerObserver.observe(document.body, {
    attributes: true,
    attributeFilter: ['class']
  });

  document.querySelectorAll('.mobile-nav').forEach(function (nativeDrawer) {
    nativeDrawerObserver.observe(nativeDrawer, {
      attributes: true,
      attributeFilter: ['class']
    });
  });

  if (typeof mobile.addEventListener === 'function') {
    mobile.addEventListener('change', handleBreakpoint);
  } else if (typeof mobile.addListener === 'function') {
    mobile.addListener(handleBreakpoint);
  }

  function discoverSocialLinks() {
    drawer.querySelectorAll('[data-rlmm-social]').forEach(function (item) {
      if (item.getAttribute('href') !== '#') {
        item.hidden = false;
        return;
      }

      var domain = item.getAttribute('data-rlmm-domain');
      if (!domain) {
        return;
      }

      var candidates = Array.prototype.slice.call(
        document.querySelectorAll('a[href*="' + domain.replace(/"/g, '') + '"]')
      );

      var existing = candidates.find(function (candidate) {
        return !candidate.closest('#rlmm-drawer');
      });

      if (existing && existing.href) {
        item.href = existing.href;
        item.removeAttribute('aria-disabled');
      }
    });
  }

  setTriggerState(false);
  discoverSocialLinks();
}());
