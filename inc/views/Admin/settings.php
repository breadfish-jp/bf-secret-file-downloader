<?php
/**
 * 設定ページのビューファイル
 *
 * @package BfSecretFileDownloader
 *
 * 利用可能な変数:
 * @var int    $max_file_size      max file size
 * @var bool   $log_downloads      log downloads flag
 * @var string $security_level     security level
 * @var string $target_directory   target directory
 * @var array  $auth_methods       authentication methods
 * @var array  $allowed_roles      allowed user roles
 * @var string $simple_auth_password simple authentication password
 * @var string $menu_title         menu title
 * @var bool   $allow_editor_admin allow editor admin flag
 * @var int    $auth_timeout       authentication timeout (minutes)
 *
 * @var string $nonce              AJAX nonce
 *
 * 利用可能な関数:
 * @var callable $__                     translation function
 * @var callable $esc_html             HTML escape function
 * @var callable $esc_html_e           HTML escape output function
 * @var callable $get_admin_page_title  get page title function
 * @var callable $settings_fields       settings field function
 * @var callable $do_settings_sections  settings section function
 * @var callable $submit_button         submit button function
 */

// Security check: prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php settings_errors(); ?>

    <div class="bf-secret-file-downloader-settings">
        <div class="bf-secret-file-downloader-header">
            <p><?php esc_html_e('Manage BF Secret File Downloader settings. Authentication is required for file access, and authentication is possible with logged-in users or simple authentication passwords.', 'bf-secret-file-downloader' ); ?></p>
            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e('About authentication settings:', 'bf-secret-file-downloader' ); ?></strong>
                    <?php esc_html_e('The authentication settings set on this page will be applied as common settings. Each directory can also have individual authentication settings, and if there are directory-specific settings, they will override the common settings.', 'bf-secret-file-downloader' ); ?>
                </p>
            </div>
        </div>

        <div class="bf-secret-file-downloader-content">
            <h2><?php esc_html_e('Basic settings', 'bf-secret-file-downloader' ); ?></h2>



            <!-- Settings form -->
            <div class="bf-secret-file-downloader-settings-form">
                <form method="post" action="options.php">
                    <?php settings_fields( 'bf_sfd_settings' ); ?>
                    <?php do_settings_sections( 'bf_sfd_settings' ); ?>

                    <!-- Target directory setting -->
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Target directory', 'bf-secret-file-downloader' ); ?></th>
                            <td>
                                <div class="bf-directory-item">
                                    <code><?php echo esc_html( $target_directory ?: 'ディレクトリが設定されていません' ); ?></code>
                                </div>
                                <p class="description"><?php esc_html_e('A secure directory created automatically when the plugin is activated. All access from outside is completely blocked by .htaccess.', 'bf-secret-file-downloader' ); ?></p>
                            </td>
                        </tr>
                    </table>



                    <!-- Authentication settings -->
                    <h3><?php esc_html_e('Authentication settings', 'bf-secret-file-downloader' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Authentication method', 'bf-secret-file-downloader' ); ?></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php esc_html_e('Authentication method', 'bf-secret-file-downloader' ); ?></legend>
                                    <label>
                                        <input type="checkbox" name="bf_sfd_auth_methods[]" value="logged_in"
                                               <?php echo in_array( 'logged_in', $auth_methods ?? array() ) ? 'checked' : ''; ?> />
                                        <?php esc_html_e('Logged in users', 'bf-secret-file-downloader' ); ?>
                                    </label>
                                    <div id="allowed_roles_section" style="margin-top: 10px; padding: 10px; background-color: #f9f9f9; border-left: 4px solid #0073aa; <?php echo in_array( 'logged_in', $auth_methods ?? array() ) ? '' : 'display: none;'; ?>">
                                        <label for="bf_sfd_allowed_roles">
                                            <strong><?php esc_html_e('Allowed user roles', 'bf-secret-file-downloader' ); ?></strong>
                                        </label>
                                        <div class="bf-role-selection-controls" style="margin: 10px 0;">
                                            <button type="button" id="bf-select-all-roles" class="button button-small"><?php esc_html_e('Select all', 'bf-secret-file-downloader' ); ?></button>
                                            <button type="button" id="bf-deselect-all-roles" class="button button-small"><?php esc_html_e('Clear all', 'bf-secret-file-downloader' ); ?></button>
                                        </div>
                                        <fieldset>
                                            <legend class="screen-reader-text"><?php esc_html_e('Allowed user roles', 'bf-secret-file-downloader' ); ?></legend>
                                            <?php
                                            $roles = array(
                                                'administrator' => __('Administrator', 'bf-secret-file-downloader' ),
                                                'editor' => __('Editor', 'bf-secret-file-downloader' ),
                                                'author' => __('Author', 'bf-secret-file-downloader' ),
                                                'contributor' => __('Contributor', 'bf-secret-file-downloader' ),
                                                'subscriber' => __('Subscriber', 'bf-secret-file-downloader' )
                                            );
                                            foreach ( $roles as $role => $label ) :
                                            ?>
                                            <label>
                                                <input type="checkbox" name="bf_sfd_allowed_roles[]" value="<?php echo esc_attr( $role ); ?>" class="bf-role-checkbox"
                                                       <?php echo in_array( $role, $allowed_roles ?? array() ) ? 'checked' : ''; ?> />
                                                <?php echo esc_html( $label ); ?>
                                            </label>
                                            <br>
                                            <?php endforeach; ?>
                                        </fieldset>
                                        <p class="description" style="margin-top: 10px;"><?php esc_html_e('Please select user roles that are allowed to access files. Multiple selections are possible.', 'bf-secret-file-downloader' ); ?></p>
                                    </div>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="bf_sfd_auth_methods[]" value="simple_auth" id="simple_auth_checkbox"
                                               <?php echo in_array( 'simple_auth', $auth_methods ?? array() ) ? 'checked' : ''; ?> />
                                        <?php esc_html_e('Users who passed simple authentication', 'bf-secret-file-downloader' ); ?>
                                    </label>
                                    <div id="simple_auth_password_section" style="margin-top: 10px; padding: 10px; background-color: #f9f9f9; border-left: 4px solid #0073aa; <?php echo in_array( 'simple_auth', $auth_methods ?? array() ) ? '' : 'display: none;'; ?>">
                                        <label for="bf_sfd_simple_auth_password">
                                            <strong><?php esc_html_e('Simple authentication password', 'bf-secret-file-downloader' ); ?></strong>
                                        </label>
                                        <br>
                                        <input type="password" name="bf_sfd_simple_auth_password" id="bf_sfd_simple_auth_password"
                                               value="<?php echo esc_attr( $simple_auth_password ?? '' ); ?>"
                                               class="regular-text" style="margin-top: 5px;" />
                                        <p class="description" style="margin-top: 5px;"><?php esc_html_e('Please enter password.', 'bf-secret-file-downloader' ); ?></p>
                                    </div>
                                </fieldset>
                                <p class="description"><?php esc_html_e('Please select authentication methods that are allowed to access files. Multiple selections are possible.', 'bf-secret-file-downloader' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Authentication timeout', 'bf-secret-file-downloader' ); ?></th>
                            <td>
                                <input type="number" name="bf_sfd_auth_timeout" id="bf_sfd_auth_timeout"
                                       value="<?php echo isset( $auth_timeout ) ? esc_attr( $auth_timeout ) : '30'; ?>"
                                       min="1" max="1440" class="small-text" />
                                <span><?php esc_html_e('minutes', 'bf-secret-file-downloader' ); ?></span>
                                <p class="description"><?php esc_html_e('Set the time after which re-authentication is required. After this time, re-authentication is required. (1 minute to 24 hours)', 'bf-secret-file-downloader' ); ?></p>
                            </td>
                        </tr>
                    </table>

                    <!-- Other settings -->
                    <h3><?php esc_html_e('Other settings', 'bf-secret-file-downloader' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Menu title', 'bf-secret-file-downloader' ); ?></th>
                            <td>
                                <input type="text" name="bf_sfd_menu_title" id="bf_sfd_menu_title"
                                       value="<?php echo isset( $menu_title ) ? esc_attr( $menu_title ) : esc_attr__('BF Secret File Downloader', 'bf-secret-file-downloader' ); ?>"
                                       class="regular-text" maxlength="50" />
                                <p class="description"><?php esc_html_e('The title displayed in the admin menu. If left blank, the default name is used.', 'bf-secret-file-downloader' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Editor management permissions', 'bf-secret-file-downloader' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="bf_sfd_allow_editor_admin" value="1"
                                           <?php echo isset( $allow_editor_admin ) && $allow_editor_admin ? 'checked' : ''; ?> />
                                    <?php esc_html_e('Give editor management permissions', 'bf-secret-file-downloader' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e('If checked, editors can access the file management function and download files. File upload and file/directory deletion are not allowed. If unchecked, editors will not see the menu and will not be able to view files.', 'bf-secret-file-downloader' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Upload limit', 'bf-secret-file-downloader' ); ?></th>
                            <td>
                                <input type="number" name="bf_sfd_max_file_size"
                                       value="<?php echo isset( $max_file_size ) ? esc_html( $max_file_size ) : '10'; ?>"
                                       min="1" max="100" />
                                <span><?php esc_html_e('MB', 'bf-secret-file-downloader' ); ?></span>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(); ?>
                </form>

                <!-- Reset settings section -->
                <div class="bf-reset-settings-section" style="margin-top: 30px; padding: 20px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                    <h3 style="margin-top: 0; color: #856404;"><?php esc_html_e('Reset settings', 'bf-secret-file-downloader' ); ?></h3>
                    <p style="margin-bottom: 15px; color: #856404;">
                        <?php esc_html_e('Clicking this button will reset all settings to their initial state. This action cannot be undone.', 'bf-secret-file-downloader' ); ?>
                    </p>

                    <!-- File deletion option -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: inline-flex; align-items: center; color: #856404;">
                            <input type="checkbox" id="bf-delete-files-on-reset" style="margin-right: 8px;">
                            <?php esc_html_e('Delete files in the target directory', 'bf-secret-file-downloader' ); ?>
                        </label>
                        <p class="description" style="margin-top: 5px; margin-left: 24px; color: #6c757d; font-size: 13px;">
                            <?php esc_html_e('If checked, all files in the secure directory will be deleted. By default, only settings are reset and files are kept.', 'bf-secret-file-downloader' ); ?>
                        </p>
                    </div>

                    <button type="button" id="bf-reset-settings" class="button button-secondary" style="background-color: #dc3545; border-color: #dc3545; color: white;">
                        <?php esc_html_e('Reset settings', 'bf-secret-file-downloader' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


