
jQuery.entwine('ss-template', function($) {

  /**
   * When we update values, tell the iframe what we updated.
   * Caveats:
   * 1. Obviously react fields aren't included. NO idea how we're gonna handle that gracefully.
   *
   */
  $('input,textarea').entwine({
    onchange: function(event) {
      console.log({inHost: event});
      if ($('iframe[name="cms-preview-iframe"]').length !== 0) {
        const msg = {
          className: document.querySelector('[name="ClassName"]').value,
          id: document.querySelector('[name="ID"]').value,
          field: this.attr('name'),
          value: this.val(),
        };
        console.log(msg); // debugging
        $('iframe[name="cms-preview-iframe"]')[0].contentWindow.postMessage(msg, "*");
      }
    },
  });

});
