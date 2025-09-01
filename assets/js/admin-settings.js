/* BF Secret File Downloader - Admin Settings JS */
/* global jQuery, bfSfdSettingsData */
(function($){
  $(function(){
    // Remove error highlight on focus
    $('input[type="text"], input[type="password"]').on('focus', function(){
      $(this).removeClass('form-field-error');
    });

    // Toggle simple auth password section
    $('#simple_auth_checkbox').on('change', function(){
      if ($(this).is(':checked')) { $('#simple_auth_password_section').show(); }
      else { $('#simple_auth_password_section').hide(); }
    });

    // Toggle allowed roles section for logged_in
    $('input[name="bf_sfd_auth_methods[]"][value="logged_in"]').on('change', function(){
      if ($(this).is(':checked')) { $('#allowed_roles_section').show(); }
      else { $('#allowed_roles_section').hide(); }
    });

    // Role selection helpers
    $('#bf-select-all-roles').on('click', function(){ $('.bf-role-checkbox').prop('checked', true); });
    $('#bf-deselect-all-roles').on('click', function(){ $('.bf-role-checkbox').prop('checked', false); });

    // Reset settings
    $('#bf-reset-settings').on('click', function(){
      var deleteFiles = $('#bf-delete-files-on-reset').is(':checked');
      var i18n = (bfSfdSettingsData && bfSfdSettingsData.i18n) || {};
      if (!window.confirm(i18n.confirmReset || 'Reset all settings?')) return;

      var $btn = $(this);
      var originalText = $btn.text();
      $btn.prop('disabled', true).text(i18n.resetting || 'Resetting...');

      $.ajax({
        url: (bfSfdSettingsData && bfSfdSettingsData.ajaxUrl) || (window.ajaxurl || ''),
        type: 'POST',
        data: {
          action: 'bf_sfd_reset_settings',
          nonce: (bfSfdSettingsData && bfSfdSettingsData.nonce) || '',
          delete_files: deleteFiles
        }
      }).done(function(resp){
        if (resp && resp.success) {
          alert(resp.data && resp.data.message ? resp.data.message : 'Done');
          window.location.reload();
        } else {
          alert(i18n.downloadFailed || 'Failed');
          $btn.prop('disabled', false).text(originalText);
        }
      }).fail(function(_xhr, _status, error){
        alert((i18n.errorOccurred || 'Error') + (error ? ': ' + error : ''));
        $btn.prop('disabled', false).text(originalText);
      });
    });
  });
})(jQuery);
