(function () {
  'use strict';

  function navs() {
    return document.querySelectorAll('[data-project-switcher], [data-version-selector]');
  }

  function label(nav) {
    var caption = nav.querySelector('.nav-label');
    return caption ? caption.textContent.trim() : nav.getAttribute('aria-label') || 'Menu';
  }

  function close(nav) {
    var button = nav.querySelector('.nav-toggle');
    var list = nav.querySelector('ul');
    if (!button || !list) {
      return;
    }
    button.setAttribute('aria-expanded', 'false');
    list.hidden = true;
    nav.classList.remove('is-open');
  }

  function open(nav) {
    var button = nav.querySelector('.nav-toggle');
    var list = nav.querySelector('ul');
    if (!button || !list) {
      return;
    }
    button.setAttribute('aria-expanded', 'true');
    list.hidden = false;
    nav.classList.add('is-open');
  }

  function enhance(nav) {
    var list = nav.querySelector('ul');
    if (!list || nav.querySelector('.nav-toggle')) {
      return;
    }

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'nav-toggle';
    button.setAttribute('aria-expanded', 'false');
    button.textContent = label(nav);

    nav.insertBefore(button, list);
    nav.classList.add('has-toggle');

    button.addEventListener('click', function () {
      var expanded = 'true' === button.getAttribute('aria-expanded');

      closeAll(nav);

      if (expanded) {
        close(nav);
      } else {
        open(nav);
      }
    });

    close(nav);
  }

  function closeAll(except) {
    var all = navs();
    for (var index = 0; index < all.length; index++) {
      if (all[index] !== except) {
        close(all[index]);
      }
    }
  }

  function init() {
    var all = navs();
    for (var index = 0; index < all.length; index++) {
      enhance(all[index]);
    }

    document.addEventListener('keydown', function (event) {
      if ('Escape' !== event.key && 'Esc' !== event.key) {
        return;
      }
      closeAll(null);
    });

    document.addEventListener('click', function (event) {
      var target = event.target;
      var owner = target && target.closest ? target.closest('[data-project-switcher], [data-version-selector]') : null;
      if (!owner) {
        closeAll(null);
      }
    });
  }

  if ('loading' === document.readyState) {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
