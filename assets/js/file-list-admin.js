/* BF Secret File Downloader - Admin File List JS */
/* global jQuery, bfFileListData */
(function($){
  if (typeof bfFileListData === 'undefined') return;

  // Template getter for auth details
  function getAuthDetailsTemplate(){
    var tpl = document.getElementById('bf-auth-details-template');
    if (tpl && tpl.content) {
      // Return a DOM node (cloned) so callers can append directly
      return document.importNode(tpl.content, true);
    }
    // Fallback for older browsers: use innerHTML string
    var $fallback = $('#bf-auth-details-template');
    return $fallback.length ? $fallback.html() : '';
  }

  // Expose for future use if needed
  window.bfSfdGetAuthDetailsTemplate = getAuthDetailsTemplate;

  $(function(){
    // Placeholder: future logic will append getAuthDetailsTemplate() where needed
  });
})(jQuery);
