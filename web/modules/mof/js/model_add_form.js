(function (Drupal, debounce) {

  'use strict';

  function licenseChange(e) {
    let na = ['_na', '_not_included'];
    let cId = e.srcElement.dataset.componentId;
    let element = document.getElementById('component-' + cId);
    if (e.srcElement.value.length > 0) { 
      element.classList.add('open');
    }
    else {
      element.classList.remove('open');
    }
  }

  Drupal.behaviors.keyupDebounced = {
    attach: function (context, settings) {
      const input = context.querySelector('#edit-repository-0-value');

      if (!input) {
        return;
      }

      let timeout = null;
      const delay = parseInt(input.dataset.delay, 10) || 1000;
      const triggerEvent = input.dataset.event || 'keyup_debounced';

      function handleKeyup() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
          input.dispatchEvent(new CustomEvent(triggerEvent));
        }, delay);
      }

      // Remove any previous listener of this type to avoid duplicates
      input.removeEventListener('keyup', handleKeyup);
      input.addEventListener('keyup', handleKeyup);

      if (!input._keyupHandler) {
        input._keyupHandler = handleKeyup;
      }
    },
    detach: function (context, settings, trigger) {
      const input = context.querySelector('#edit-repository-0-value');
      if (input && input._keyupHandler) {
        input.removeEventListener('keyup', input._keyupHandler);
        delete input._keyupHandler;
      }
    }
  };

  Drupal.behaviors.licenseChange = {
    attach: function (context, settings) {
      let select = document.getElementsByClassName('license-input');
      [...select].forEach(e => {
        e.addEventListener('keyup', licenseChange);
      });
    }
  };

})(Drupal, Drupal.debounce);
