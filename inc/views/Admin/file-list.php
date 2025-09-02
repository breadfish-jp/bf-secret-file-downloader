<?php
/**
 * File list page view file
 *
 * @package BfSecretFileDownloader
 *
 * Available variables:
 * @var array    $files                    File list
 * @var int      $total_files             Total number of files
 * @var string   $upload_limit            Upload limit
 * @var bool     $current_user_can_upload Upload permission
 * @var bool     $current_user_can_delete Delete permission
 * @var bool     $current_user_can_create_dir Directory creation permission
 * @var bool     $current_user_can_manage_auth Authentication management permission
 * @var string   $current_path            Current path
 * @var int      $page                    Current page
 * @var int      $total_pages             Total number of pages
 * @var int      $files_per_page          Number of files per page
 * @var string   $nonce                   Nonce
 * @var bool     $target_directory_set    Target directory is set
 * @var bool     $current_directory_has_password  Current directory has password
 *
 * Available functions:
 * @var callable $__                      Translation function
 * @var callable $esc_html                HTML escape function
 * @var callable $esc_html_e              HTML escape output function
 * @var callable $get_admin_page_title    Page title retrieval function
 */

// Security check: prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Fallback settings if variables are not set
if ( ! isset( $current_user_can_delete ) ) {
    $current_user_can_delete = current_user_can( 'manage_options' );
}
if ( ! isset( $current_user_can_create_dir ) ) {
    $current_user_can_create_dir = current_user_can( 'manage_options' );
}
if ( ! isset( $current_user_can_manage_auth ) ) {
    $current_user_can_manage_auth = current_user_can( 'manage_options' );
}
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <div class="bf-secret-file-downloader-file-list">
        <div class="bf-secret-file-downloader-header">
                            <p><?php esc_html_e('Manage files in a hidden directory.', 'bf-secret-file-downloader' ); ?></p>
        </div>

        <?php if ( ! $target_directory_set || ! $secure_directory_exists ) : ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Directory does not exist: ', 'bf-secret-file-downloader' ); ?></strong>
                </p>

                <?php if ( ! empty( $secure_directory_path ) ) : ?>
                <p>
                    <strong><?php esc_html_e('Pass:', 'bf-secret-file-downloader' ); ?></strong>
                    <code style="background-color: #fff; padding: 3px 5px; margin-left: 5px; word-break: break-all;">
                        <?php echo esc_html( $secure_directory_path ); ?>
                    </code>
                </p>
                <?php endif; ?>

                <p>
                    <button type="button" id="bf-recreate-secure-directory" class="button button-primary">
                        <?php esc_html_e('Create directory', 'bf-secret-file-downloader' ); ?>
                    </button>

                    <?php if ( ! empty( $secure_directory_path ) ) : ?>
                    <button type="button" onclick="location.reload();" class="button button-secondary" style="margin-left: 10px;">
                        <?php esc_html_e('Reload', 'bf-secret-file-downloader' ); ?>
                    </button>
                    <?php endif; ?>

                    <span id="bf-recreate-status" style="margin-left: 10px;"></span>
                </p>
            </div>
        <?php endif; ?>

        <?php if ( $target_directory_set && $secure_directory_exists ) : ?>
            <div class="bf-secret-file-downloader-content">
                <!-- Current path display -->
                <div class="bf-secret-file-downloader-path">
                    <div class="bf-path-info">
                        <strong><?php esc_html_e('Current directory:', 'bf-secret-file-downloader' ); ?></strong>
                        <code id="current-path-display"><?php echo esc_html( $current_path_display ); ?></code>
                        <input type="hidden" id="current-path" value="<?php echo esc_attr( $current_path ); ?>">
                        <?php if ( isset( $current_directory_has_auth ) && $current_directory_has_auth ) : ?>
                            <span class="bf-auth-indicator">
                                <span class="dashicons dashicons-lock"></span>
                                <span class="bf-auth-status-text"><?php esc_html_e('Target directory settings', 'bf-secret-file-downloader' ); ?></span>
                            </span>
                            <div class="bf-auth-details">
                                <div class="auth-details-title"><?php esc_html_e('Directory-specific authentication settings:', 'bf-secret-file-downloader' ); ?></div>
                                <div id="auth-details-content">
                                    <!-- Display settings content dynamically with JavaScript -->
                                </div>
                                <button type="button" id="remove-auth-btn" class="button button-small">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php esc_html_e('Delete directory-specific settings', 'bf-secret-file-downloader' ); ?>
                                </button>
                            </div>
                        <?php else : ?>
                            <span class="bf-auth-indicator" style="color: #666;">
                                <span class="dashicons dashicons-admin-users"></span>
                                <span class="bf-auth-status-text"><?php esc_html_e('Common authentication settings applied', 'bf-secret-file-downloader' ); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="bf-path-actions">
                        <?php if ( ! empty( $current_path ) ) : ?>
                            <button type="button" id="go-up-btn" class="button button-small">
                                <span class="dashicons dashicons-arrow-up-alt2"></span>
                                <?php esc_html_e('Go to parent directory', 'bf-secret-file-downloader' ); ?>
                            </button>
                        <?php endif; ?>
                        <!-- Directory-specific authentication button (displayed for non-root directories) -->
                        <?php if ( ! empty( $current_path ) ) : ?>
                            <button type="button" id="directory-auth-btn" class="button button-small">
                                <span class="dashicons dashicons-admin-users"></span>
                                <?php esc_html_e('Authentication settings', 'bf-secret-file-downloader' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
    <!-- Hidden template for directory auth details -->
    <template id="bf-auth-details-template">
        <div class="bf-auth-details">
            <div class="auth-details-title"><?php esc_html_e('Directory-specific authentication settings:', 'bf-secret-file-downloader' ); ?></div>
            <div id="auth-details-content"></div>
            <button type="button" class="button button-small" id="remove-auth-btn">
                <span class="dashicons dashicons-trash"></span><?php esc_html_e('Delete directory-specific settings', 'bf-secret-file-downloader' ); ?>
            </button>
        </div>
    </template>


                <!-- File operation area -->
                <?php if ( $current_user_can_upload && $current_path_writable ) : ?>
                    <!-- Directory creation and file upload -->
                    <div class="bf-secret-file-downloader-actions">
                        <div class="bf-actions-header">
                            <h3><?php esc_html_e('File operations', 'bf-secret-file-downloader' ); ?></h3>
                            <div class="bf-action-buttons">
                                <button type="button" id="create-directory-btn" class="button">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                    <?php esc_html_e('Create directory', 'bf-secret-file-downloader' ); ?>
                                </button>
                                <button type="button" id="select-files-btn" class="button">
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php esc_html_e('Select files', 'bf-secret-file-downloader' ); ?>
                                </button>

                            </div>
                        </div>

                        <!-- Directory creation form -->
                        <div id="create-directory-form" class="bf-create-directory-form" style="display: none;">
                            <div class="form-group">
                                <label for="directory-name-input"><?php esc_html_e('Directory name:', 'bf-secret-file-downloader' ); ?></label>
                                <input type="text" id="directory-name-input" class="regular-text" placeholder="<?php esc_attr_e('Enter directory name', 'bf-secret-file-downloader' ); ?>">
                                <div class="form-actions">
                                    <button type="button" id="create-directory-submit" class="button button-primary"><?php esc_html_e('Create', 'bf-secret-file-downloader' ); ?></button>
                                    <button type="button" id="create-directory-cancel" class="button"><?php esc_html_e('Cancel', 'bf-secret-file-downloader' ); ?></button>
                                </div>
                            </div>
                            <p class="description">
                                <?php esc_html_e('Alphanumeric characters, underscores (_), hyphens (-), and dots (.) can be used.', 'bf-secret-file-downloader' ); ?>
                            </p>
                        </div>

                        <!-- File upload area -->
                        <div id="drop-zone" class="bf-secret-file-downloader-drop-zone">
                            <div class="drop-zone-content">
                                <span class="dashicons dashicons-upload"></span>
                                <p><strong><?php esc_html_e('Drag and drop files here', 'bf-secret-file-downloader' ); ?></strong></p>
                                <p><?php
                                    /* translators: %s: maximum file size in MB */
                                    echo esc_html( sprintf( __('(Maximum: %sMB)', 'bf-secret-file-downloader' ), $max_file_size_mb ) );
                                ?></p>
                                <input type="file" id="file-input" multiple style="display: none;">
                            </div>
                            <div class="drop-zone-overlay" style="display: none;">
                                <p><?php esc_html_e('Please drop files here', 'bf-secret-file-downloader' ); ?></p>
                            </div>
                        </div>

                        <!-- Upload progress display -->
                        <div id="upload-progress" style="display: none; margin: 20px 0;">
                            <div class="upload-progress-bar" style="background: #f1f1f1; border-radius: 3px; overflow: hidden;">
                                <div class="upload-progress-fill" style="background: #0073aa; height: 20px; width: 0%; transition: width 0.3s;"></div>
                            </div>
                            <p id="upload-status"></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- File statistics -->
                <div class="bf-secret-file-downloader-stats">
                    <p>
                        <?php
                        if ( $total_files > 0 ) {
                            /* translators: %d: number of items found */
                            echo esc_html( sprintf( __('%d items found.', 'bf-secret-file-downloader' ), (int) $total_files ) );
                        } else {
                            esc_html_e('No items found.', 'bf-secret-file-downloader' );
                        }
                        ?>
                    </p>
                </div>

                <!-- Bulk operations and pagination (top) -->
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'bf-secret-file-downloader' ); ?></label>
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1"><?php esc_html_e('Bulk actions', 'bf-secret-file-downloader' ); ?></option>
                            <?php if ( $current_user_can_delete ) : ?>
                                <option value="delete"><?php esc_html_e('Delete', 'bf-secret-file-downloader' ); ?></option>
                            <?php endif; ?>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e('Apply', 'bf-secret-file-downloader' ); ?>">
                    </div>
                    <?php if ( $total_pages > 1 ) : ?>
                        <div class="tablenav-pages">
                            <?php echo wp_kses_post( $pagination_html ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- HTML Templates for JavaScript -->
                <script type="text/template" id="top-tablenav-template">
                    <div class="tablenav top">
                        <div class="alignleft actions bulkactions">
                            <label for="bulk-action-selector-top" class="screen-reader-text"><?php echo esc_js( __('Select bulk action', 'bf-secret-file-downloader' ) ); ?></label>
                            <select name="action" id="bulk-action-selector-top">
                                <option value="-1"><?php echo esc_js( __('Bulk actions', 'bf-secret-file-downloader' ) ); ?></option>
                                {{DELETE_OPTION}}
                            </select>
                            <input type="submit" id="doaction" class="button action" value="<?php echo esc_js( __('Apply', 'bf-secret-file-downloader' ) ); ?>">
                        </div>
                        {{PAGINATION_SECTION}}
                    </div>
                </script>

                <script type="text/template" id="bottom-tablenav-template">
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="pagination-links">
                                {{PAGINATION_LINKS}}
                            </span>
                        </div>
                    </div>
                </script>

                <script type="text/template" id="pagination-template">
                    <span class="pagination-links">
                        {{PREVIOUS_LINK}}
                        {{PAGE_NUMBERS}}
                        {{NEXT_LINK}}
                    </span>
                </script>

                <script type="text/template" id="path-display-template">
                    <div class="bf-path-info">
                        <strong>{{CURRENT_DIRECTORY_LABEL}}</strong>
                        <code id="current-path-display">{{CURRENT_PATH_DISPLAY}}</code>
                        <input type="hidden" id="current-path" value="{{CURRENT_PATH_VALUE}}">
                    </div>
                    <div class="bf-path-actions">
                        {{GO_UP_BUTTON}}
                        {{AUTH_SETTINGS_BUTTON}}
                    </div>
                </script>

                <!-- File list table -->
                <div class="bf-secret-file-downloader-file-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column" style="width: 40px;">
                                    <label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e('Select all', 'bf-secret-file-downloader' ); ?></label>
                                    <input id="cb-select-all-1" type="checkbox">
                                </td>
                                <th class="manage-column column-name sortable <?php echo $sort_by === 'name' ? 'sorted ' . esc_attr( $sort_order ) : ''; ?>" style="width: 45%;">
                                    <a href="#" class="sort-link" data-sort="name">
                                        <span><?php esc_html_e('Filename', 'bf-secret-file-downloader' ); ?></span>
                                        <span class="sorting-indicator"></span>
                                    </a>
                                </th>
                                <th class="manage-column column-type" style="width: 15%;">
                                    <?php esc_html_e('Type', 'bf-secret-file-downloader' ); ?>
                                </th>
                                <th class="manage-column column-size sortable <?php echo $sort_by === 'size' ? 'sorted ' . esc_attr( $sort_order ) : ''; ?>" style="width: 15%;">
                                    <a href="#" class="sort-link" data-sort="size">
                                        <span><?php esc_html_e('Size', 'bf-secret-file-downloader' ); ?></span>
                                        <span class="sorting-indicator"></span>
                                    </a>
                                </th>
                                <th class="manage-column column-modified sortable <?php echo $sort_by === 'modified' ? 'sorted ' . esc_attr( $sort_order ) : ''; ?>" style="width: 20%;">
                                    <a href="#" class="sort-link" data-sort="modified">
                                        <span><?php esc_html_e('Modified', 'bf-secret-file-downloader' ); ?></span>
                                        <span class="sorting-indicator"></span>
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="file-list-tbody">
                            <!-- File list is generated dynamically with JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination (bottom) -->
                <?php if ( $total_pages > 1 ) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php echo wp_kses_post( $pagination_html ); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Loading display -->
    <div id="bf-secret-file-downloader-loading" style="display: none; text-align: center; margin: 20px;">
        <span class="spinner is-active"></span>
        <span><?php esc_html_e('Loading...', 'bf-secret-file-downloader' ); ?></span>
    </div>

</div>

<!-- Directory authentication settings modal -->
<div id="bf-directory-auth-modal" class="bf-modal" style="display: none;">
    <div class="bf-modal-content" style="width: 70%; max-width: 700px;">
        <div class="bf-modal-header">
            <h3 id="bf-auth-modal-title"><?php esc_html_e('Directory authentication settings', 'bf-secret-file-downloader' ); ?></h3>
            <span class="bf-modal-close">&times;</span>
        </div>
        <div class="bf-modal-body">
            <!-- Current status display -->
            <div id="bf-current-auth-status" class="bf-status-box">
                <div class="bf-status-content">
                    <span class="bf-auth-status-icon">
                        <span class="dashicons dashicons-lock"></span>
                    </span>
                    <div class="bf-status-text">
                        <strong id="bf-auth-status-title"><?php esc_html_e('Current status', 'bf-secret-file-downloader' ); ?></strong>
                        <p id="bf-auth-status-description"><?php esc_html_e('This directory is not password protected.', 'bf-secret-file-downloader' ); ?></p>
                    </div>
                </div>
            </div>

            <p id="bf-auth-modal-description">
                <?php esc_html_e('Please set a password that will be required when downloading files in this directory.', 'bf-secret-file-downloader' ); ?>
            </p>

            <!-- Authentication settings -->
            <div class="bf-auth-section">
                <h4><?php esc_html_e('Authentication method', 'bf-secret-file-downloader' ); ?></h4>
                <fieldset>
                    <legend class="screen-reader-text"><?php esc_html_e('Authentication method', 'bf-secret-file-downloader' ); ?></legend>
                    <label>
                        <input type="checkbox" name="bf_auth_methods[]" value="logged_in" id="bf-auth-methods-logged-in" />
                        <?php esc_html_e('Logged in users', 'bf-secret-file-downloader' ); ?>
                    </label>
                    <div id="bf-allowed-roles-section" style="margin-top: 10px; padding: 10px; background-color: #f9f9f9; border-left: 4px solid #0073aa; display: none;">
                        <label for="bf-allowed-roles">
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
                                <input type="checkbox" name="bf_allowed_roles[]" value="<?php echo esc_attr( $role ); ?>" class="bf-role-checkbox" id="bf-allowed-roles-<?php echo esc_attr( $role ); ?>"
                                       />
                                <?php echo esc_html( $label ); ?>
                            </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description" style="margin-top: 10px;"><?php esc_html_e('Please select user roles that are allowed to access files. Multiple selections are possible.', 'bf-secret-file-downloader' ); ?></p>
                    </div>
                    <br>
                    <label>
                        <input type="checkbox" name="bf_auth_methods[]" value="simple_auth" id="bf-auth-methods-simple-auth" />
                        <?php esc_html_e('Users who passed simple authentication', 'bf-secret-file-downloader' ); ?>
                    </label>
                    <div id="bf-simple-auth-password-section" style="margin-top: 10px; padding: 10px; background-color: #f9f9f9; border-left: 4px solid #0073aa; display: none;">
                        <label for="bf-simple-auth-password">
                            <strong><?php esc_html_e('Simple authentication password', 'bf-secret-file-downloader' ); ?></strong>
                        </label>
                        <br>
                        <input type="password" name="bf_simple_auth_password" id="bf-simple-auth-password"
                               class="regular-text" style="margin-top: 5px;" />
                        <p class="description" style="margin-top: 5px;"><?php esc_html_e('Please enter password.', 'bf-secret-file-downloader' ); ?></p>
                    </div>
                </fieldset>
                <p class="description"><?php esc_html_e('Please select authentication methods that are allowed to access files. Multiple selections are possible.', 'bf-secret-file-downloader' ); ?></p>
            </div>
        </div>
        <div class="bf-modal-footer">
            <div class="bf-action-buttons-left">
                <button type="button" id="bf-remove-auth" class="button button-secondary bf-danger-button" style="display: none;">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Delete authentication settings', 'bf-secret-file-downloader' ); ?>
                </button>
            </div>
            <div class="bf-action-buttons-right">
                <button type="button" id="bf-save-auth" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Save', 'bf-secret-file-downloader' ); ?>
                </button>
                <button type="button" id="bf-cancel-auth" class="button">
                    <?php esc_html_e('Cancel', 'bf-secret-file-downloader' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Directory password settings modal -->
<div id="bf-directory-password-modal" class="bf-modal" style="display: none;">
    <div class="bf-modal-content" style="width: 60%; max-width: 600px;">
        <div class="bf-modal-header">
            <h3 id="bf-password-modal-title"><?php esc_html_e('Directory password settings', 'bf-secret-file-downloader' ); ?></h3>
            <span class="bf-modal-close">&times;</span>
        </div>
        <div class="bf-modal-body">
            <!-- Current status display -->
            <div id="bf-current-status" class="bf-status-box">
                <div class="bf-status-content">
                    <span class="bf-status-icon">
                        <span class="dashicons dashicons-lock"></span>
                    </span>
                    <div class="bf-status-text">
                        <strong id="bf-status-title"><?php esc_html_e('Current status', 'bf-secret-file-downloader' ); ?></strong>
                        <p id="bf-status-description"><?php esc_html_e('This directory is not password protected.', 'bf-secret-file-downloader' ); ?></p>
                    </div>
                </div>
            </div>

            <p id="bf-password-modal-description">
                <?php esc_html_e('Please set a password that will be required when downloading files in this directory.', 'bf-secret-file-downloader' ); ?>
            </p>

            <div class="bf-password-form">
                <label for="bf-directory-password-input"><?php esc_html_e('Password:', 'bf-secret-file-downloader' ); ?></label>
                <div class="bf-password-input-group">
                    <input type="password" id="bf-directory-password-input" class="regular-text"
                           placeholder="<?php esc_attr_e('Enter password', 'bf-secret-file-downloader' ); ?>" />
                    <button type="button" id="bf-password-toggle" class="button">
                        <?php esc_html_e('Show', 'bf-secret-file-downloader' ); ?>
                    </button>
                    <button type="button" id="bf-show-current-password" class="button" style="display: none;">
                        <?php esc_html_e('Current password', 'bf-secret-file-downloader' ); ?>
                    </button>
                </div>
                <p class="description">
                    <?php esc_html_e('For security, we recommend setting a complex password of 8 characters or more.', 'bf-secret-file-downloader' ); ?>
                </p>
            </div>
        </div>
        <div class="bf-modal-footer">
            <div class="bf-action-buttons-left">
                <button type="button" id="bf-remove-password" class="button button-secondary bf-danger-button" style="display: none;">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Remove password protection', 'bf-secret-file-downloader' ); ?>
                </button>
            </div>
            <div class="bf-action-buttons-right">
                <button type="button" id="bf-save-password" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Save', 'bf-secret-file-downloader' ); ?>
                </button>
                <button type="button" id="bf-cancel-password" class="button">
                    <?php esc_html_e('Cancel', 'bf-secret-file-downloader' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- URL copy modal -->
<div id="bf-url-copy-modal" class="bf-modal" style="display: none;">
    <div class="bf-modal-content" style="width: 70%; max-width: 700px;">
        <div class="bf-modal-header">
            <h3><?php esc_html_e('File access URL', 'bf-secret-file-downloader' ); ?></h3>
            <span class="bf-modal-close">&times;</span>
        </div>
        <div class="bf-modal-body">
            <div class="bf-url-info">
                <h4 id="bf-url-file-name"><?php esc_html_e('Filename', 'bf-secret-file-downloader' ); ?></h4>
                <p class="description"><?php esc_html_e('You can access files using the following URL.', 'bf-secret-file-downloader' ); ?></p>
            </div>

            <div class="bf-url-options">
                <h4><?php esc_html_e('Select access method', 'bf-secret-file-downloader' ); ?></h4>
                <div class="bf-url-option-group">
                    <label class="bf-url-option">
                        <input type="radio" name="url_type" value="download" checked>
                        <span class="bf-option-content">
                            <span class="bf-option-icon dashicons dashicons-download"></span>
                            <div class="bf-option-text">
                                <strong><?php esc_html_e('Download', 'bf-secret-file-downloader' ); ?></strong>
                                <span><?php esc_html_e('Download file directly', 'bf-secret-file-downloader' ); ?></span>
                            </div>
                        </span>
                    </label>
                    <label class="bf-url-option">
                        <input type="radio" name="url_type" value="display">
                        <span class="bf-option-content">
                            <span class="bf-option-icon dashicons dashicons-visibility"></span>
                            <div class="bf-option-text">
                                <strong><?php esc_html_e('View inline', 'bf-secret-file-downloader' ); ?></strong>
                                <span><?php esc_html_e('View the file in the browser', 'bf-secret-file-downloader' ); ?></span>
                            </div>
                        </span>
                    </label>
                </div>
            </div>

            <div class="bf-url-display">
                <label for="bf-url-input"><?php esc_html_e('URL:', 'bf-secret-file-downloader' ); ?></label>
                <div class="bf-url-input-group">
                    <input type="text" id="bf-url-input" class="regular-text" readonly>
                    <button type="button" id="bf-copy-url-btn" class="button">
                        <span class="dashicons dashicons-clipboard"></span>
                        <?php esc_html_e('Copy', 'bf-secret-file-downloader' ); ?>
                    </button>
                </div>
            </div>

            <div class="bf-url-preview">
                <h4><?php esc_html_e('Preview', 'bf-secret-file-downloader' ); ?></h4>
                <div class="bf-preview-frame">
                    <iframe id="bf-url-preview-frame" style="width: 100%; height: 300px; border: 1px solid #ddd; border-radius: 4px;"></iframe>
                </div>
            </div>
        </div>
        <div class="bf-modal-footer">
            <div class="bf-action-buttons-right">
                <button type="button" id="bf-open-url-btn" class="button button-primary">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e('Open in new tab', 'bf-secret-file-downloader' ); ?>
                </button>
                <button type="button" id="bf-close-url-modal" class="button">
                    <?php esc_html_e('Close', 'bf-secret-file-downloader' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>


<script type="text/javascript">
jQuery(document).ready(function($) {

    // Check if Dashicons are loaded
    bfSfdCheckDashicons();

    // Initialize authentication details display on page load
    setTimeout(function() {
        bfSfdInitializeAuthDetails();
    }, 200);

    // Delete link event (from mouse over menu)
    $(document).on('click', '.delete-file-link', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Stop event propagation
        var $link = $(this);
        var filePath = $link.data('file-path');
        var fileName = $link.data('file-name');
        var fileType = $link.data('file-type');

        bfSfdDeleteFile(filePath, fileName, fileType);
    });

    // Remove directory click processing - only row action links are used

    // Directory authentication settings button click processing
    $('#directory-auth-btn').on('click', function(e) {
        e.preventDefault();
        openDirectoryAuthModal();
    });

    // Authentication settings modal related events
    $('.bf-modal-close, #bf-cancel-auth').on('click', function() {
        closeDirectoryAuthModal();
    });

    // Close authentication settings modal by clicking outside
    $('#bf-directory-auth-modal').on('click', function(e) {
        if (e.target === this) {
            closeDirectoryAuthModal();
        }
    });

    // Simple authentication checkbox control
    $(document).on('change', '#bf-auth-methods-simple-auth', function() {
        if ($(this).is(':checked')) {
            $('#bf-simple-auth-password-section').show();
        } else {
            $('#bf-simple-auth-password-section').hide();
        }
    });

    // Authentication settings save button
    $('#bf-save-auth').on('click', function() {
        saveDirectoryAuth();
    });

    // Authentication settings delete button
    $('#bf-remove-auth').on('click', function() {
        removeDirectoryAuth();
    });

    // Modal related events
    $('.bf-modal-close, #bf-cancel-password').on('click', function() {
        closeDirectoryPasswordModal();
    });

    // Close modal by clicking outside
    $('#bf-directory-password-modal').on('click', function(e) {
        if (e.target === this) {
            closeDirectoryPasswordModal();
        }
    });

    // URL copy modal related events
    $('.bf-modal-close, #bf-close-url-modal').on('click', function() {
        closeUrlCopyModal();
    });

    // Close URL copy modal by clicking outside
    $('#bf-url-copy-modal').on('click', function(e) {
        if (e.target === this) {
            closeUrlCopyModal();
        }
    });

    // Password display/hide toggle
    $('#bf-password-toggle').on('click', function() {
        var passwordField = $('#bf-directory-password-input');
        var button = $(this);

        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            button.text('<?php esc_html_e('Hide', 'bf-secret-file-downloader' ); ?>');
        } else {
            passwordField.attr('type', 'password');
            button.text('<?php esc_html_e('Show', 'bf-secret-file-downloader' ); ?>');
        }
    });

    // Password save button
    $('#bf-save-password').on('click', function() {
        saveDirectoryPassword();
    });

    // Password delete button
    $('#bf-remove-password').on('click', function() {
        removeDirectoryPassword();
    });

    // Current password display button
    $('#bf-show-current-password').on('click', function() {
        showCurrentPassword();
    });

    // Enter key to save password
    $('#bf-directory-password-input').on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            saveDirectoryPassword();
        }
    });

    // URL copy modal related events
    $(document).on('change', 'input[name="url_type"]', function() {
        updateUrlDisplay();
    });

    // URL copy button
    $('#bf-copy-url-btn').on('click', function() {
        copyUrlToClipboard();
    });

    // Open in new tab button
    $('#bf-open-url-btn').on('click', function() {
        openUrlInNewTab();
    });

    // Go up button click processing
    $('#go-up-btn').on('click', function(e) {
        e.preventDefault();
        var currentPath = $('#current-path').val();
        if (currentPath) {
            var parentPath = bfSfdGetParentPath(currentPath);
            bfSfdNavigateToDirectory(parentPath, 1);
        }
    });

    // Sort link click processing
    $(document).on('click', '.sort-link', function(e) {
        e.preventDefault();
        var sortBy = $(this).data('sort');
        var currentPath = $('#current-path').val();
        var currentSortBy = bfSfdGetCurrentSortBy();
        var currentSortOrder = bfSfdGetCurrentSortOrder();

        // If the same column is clicked, reverse the order
        var newSortOrder = 'asc';
        if (sortBy === currentSortBy && currentSortOrder === 'asc') {
            newSortOrder = 'desc';
        }

        bfSfdNavigateToDirectoryWithSort(currentPath, 1, sortBy, newSortOrder);
    });

    // Paging link click processing
    $(document).on('click', '.pagination-links a', function(e) {
        e.preventDefault();
        var url = new URL(this.href);
        var page = url.searchParams.get('paged') || 1;
        var path = url.searchParams.get('path') || $('#current-path').val();
        bfSfdNavigateToDirectory(path, page);
    });

    // Directory creation button click processing
    $('#create-directory-btn').on('click', function(e) {
        e.preventDefault();
        $('#create-directory-form').slideDown();
        $('#directory-name-input').focus();
    });

    // Directory creation form cancel
    $('#create-directory-cancel').on('click', function(e) {
        e.preventDefault();
        $('#create-directory-form').slideUp();
        $('#directory-name-input').val('');
    });

    // Execute directory creation
    $('#create-directory-submit').on('click', function(e) {
        e.preventDefault();
        bfSfdCreateDirectory();
    });

    // Enter key to create directory
    $('#directory-name-input').on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            bfSfdCreateDirectory();
        }
    });

    // Download link event
    $(document).on('click', '.download-file-link', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Stop event propagation
        var $link = $(this);
        var filePath = $link.data('file-path');
        var fileName = $link.data('file-name');

        bfSfdDownloadFile(filePath, fileName);
    });

    // URL copy link event
    $(document).on('click', '.copy-url-link', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Stop event propagation
        var $link = $(this);
        var filePath = $link.data('file-path');
        var fileName = $link.data('file-name');

        openUrlCopyModal(filePath, fileName);
    });

    // Open directory link event
    $(document).on('click', '.open-directory', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Stop event propagation
        var $link = $(this);
        var path = $link.data('path');

        if (path) {
            bfSfdNavigateToDirectory(path, 1);
        }
    });

    // All selection checkbox event
    $(document).on('change', '#cb-select-all-1', function() {
        var isChecked = $(this).prop('checked');
        $('input[name="file_paths[]"]').prop('checked', isChecked);
    });

    // Individual checkbox event
    $(document).on('change', 'input[name="file_paths[]"]', function() {
        var totalCheckboxes = $('input[name="file_paths[]"]').length;
        var checkedCheckboxes = $('input[name="file_paths[]"]:checked').length;

        // If all checkboxes are checked, check the all selection checkbox
        $('#cb-select-all-1').prop('checked', totalCheckboxes === checkedCheckboxes);
    });

    // Stop event propagation when clicking checkbox
    $(document).on('click', 'input[name="file_paths[]"]', function(e) {
        e.stopPropagation();
    });

    // Stop event propagation when clicking checkbox label
    $(document).on('click', '.check-column label', function(e) {
        e.stopPropagation();
    });

    // Bulk operation button event
    $(document).on('click', '#doaction', function(e) {
        e.preventDefault();

        var action = $('#bulk-action-selector-top').val();
        if (action === '-1') {
            alert('<?php echo esc_js( __('Please select an action.', 'bf-secret-file-downloader' ) ); ?>');
            return;
        }

        var checkedFiles = $('input[name="file_paths[]"]:checked');
        if (checkedFiles.length === 0) {
            alert('<?php echo esc_js( __('Please select items to delete.', 'bf-secret-file-downloader' ) ); ?>');
            return;
        }

        if (action === 'delete') {
            bulkDeleteFiles();
        }
    });

    // File selection button click processing
    $('#select-files-btn').on('click', function(e) {
        e.preventDefault();
        $('#file-input').click();
    });

    // File selection processing
    $('#file-input').on('change', function(e) {
        var files = e.target.files;
        if (files.length > 0) {
            bfSfdUploadFiles(files);
        }
    });

    // Drag and drop processing
    var dropZone = $('#drop-zone');

    if (dropZone.length > 0) {
        // Drag enter
        dropZone.on('dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
            $('.drop-zone-overlay').show();
        });

        // Drag over
        dropZone.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });

        // Drag leave
        dropZone.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var rect = this.getBoundingClientRect();
            var x = e.originalEvent.clientX;
            var y = e.originalEvent.clientY;

            // Only process if it is outside the drop zone
            if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                $(this).removeClass('dragover');
                $('.drop-zone-overlay').hide();
            }
        });

        // Drop
        dropZone.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            $('.drop-zone-overlay').hide();

            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                bfSfdUploadFiles(files);
            }
        });

        // Disable default drag and drop on the entire page
        $(document).on('dragenter dragover drop', function(e) {
            e.preventDefault();
        });
    }



    function bulkDeleteFiles() {
        var checkedFiles = $('input[name="file_paths[]"]:checked');
        var filePaths = [];
        var fileNames = [];
        var hasDirectories = false;

        checkedFiles.each(function() {
            filePaths.push($(this).val());
            fileNames.push($(this).data('file-name'));
            if ($(this).data('file-type') === 'directory') {
                hasDirectories = true;
            }
        });

        // Confirm message
        var confirmMessage;
        if (hasDirectories) {
            confirmMessage = '<?php
                /* translators: %d: number of selected items */
                echo esc_js( __('Delete %d selected items (including directories) and all their contents? This action cannot be undone.', 'bf-secret-file-downloader' ) ); ?>';
        } else {
            confirmMessage = '<?php
                /* translators: %d: number of selected items */
                echo esc_js( __('Delete %d selected items? This action cannot be undone.', 'bf-secret-file-downloader' ) ); ?>';
        }

        if (!confirm(confirmMessage.replace('%d', filePaths.length))) {
            return;
        }

        // Disable the bulk delete button
        $('#doaction').prop('disabled', true).val('<?php esc_html_e('Deleting...', 'bf-secret-file-downloader' ); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_bulk_delete',
                file_paths: filePaths,
                current_path: $('#current-path').val(),
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    bfSfdShowSuccessMessage(response.data.message);

                    // Detailed display of the deletion result (if there are failures)
                    if (response.data.failed_count > 0) {
                        console.log('削除に失敗したファイル:', response.data.failed_files);
                    }

                    // Processing when the current path is deleted
                    var targetPath = $('#current-path').val();
                    if (response.data.current_path_deleted && response.data.redirect_path !== undefined) {
                        targetPath = response.data.redirect_path;
                        console.log('現在のディレクトリが削除されたため、親ディレクトリに移動: ' + targetPath);
                    }

                    // Update file list
                    bfSfdNavigateToDirectory(targetPath, 1);
                } else {
                    var errorMsg = response.data || '<?php esc_html_e('Bulk delete failed.', 'bf-secret-file-downloader' ); ?>';
                    console.log('一括削除処理がサーバー側で失敗:', errorMsg);
                    alert(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.log('一括削除処理で通信エラーが発生:', {xhr: xhr, status: status, error: error});
                alert('<?php esc_html_e('Communication error occurred during bulk deletion. Please try again.', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                // Enable button
                $('#doaction').prop('disabled', false).val('<?php esc_attr_e('Apply', 'bf-secret-file-downloader' ); ?>');

                // Clear checkboxes
                $('input[name="file_paths[]"]').prop('checked', false);
                $('#cb-select-all-1').prop('checked', false);
            }
        });
    }

    // Open the directory password modal
    function openDirectoryPasswordModal() {
        var currentPath = $('#current-path').val();
        var currentPathDisplay = $('#current-path-display').text();
        var hasPassword = checkCurrentDirectoryHasPassword();

        // Update the modal title
        if (hasPassword) {
            $('#bf-password-modal-title').text('<?php esc_html_e('Directory password management', 'bf-secret-file-downloader' ); ?>');
        } else {
            $('#bf-password-modal-title').text('<?php esc_html_e('Directory password settings', 'bf-secret-file-downloader' ); ?>');
        }

        // Update the current status display
        var statusIcon = $('.bf-status-icon .dashicons');
        var statusDescription = $('#bf-status-description');

        if (hasPassword) {
            statusIcon.removeClass('dashicons-unlock').addClass('dashicons-lock');
            statusIcon.css('color', '#d63638');
            statusDescription.html('<?php esc_html_e('This directory (', 'bf-secret-file-downloader' ); ?><code>' + currentPathDisplay + '</code><?php esc_html_e(') is currently password protected.', 'bf-secret-file-downloader' ); ?>');
            $('#bf-password-modal-description').text('<?php esc_html_e('Enter a new password to change it, or remove protection using the \'Remove password protection\' button below.', 'bf-secret-file-downloader' ); ?>');
            $('#bf-remove-password').show();
            $('#bf-show-current-password').show();
        } else {
            statusIcon.removeClass('dashicons-lock').addClass('dashicons-unlock');
            statusIcon.css('color', '#46b450');
            statusDescription.html('<?php esc_html_e('This directory (', 'bf-secret-file-downloader' ); ?><code>' + currentPathDisplay + '</code><?php esc_html_e(') is not password protected.', 'bf-secret-file-downloader' ); ?>');
            $('#bf-password-modal-description').text('<?php esc_html_e('Please set a password that will be required when downloading files in this directory.', 'bf-secret-file-downloader' ); ?>');
            $('#bf-remove-password').hide();
            $('#bf-show-current-password').hide();
        }

        // Clear the password field
        $('#bf-directory-password-input').val('').attr('type', 'password');
        $('#bf-password-toggle').text('<?php esc_html_e('Show', 'bf-secret-file-downloader' ); ?>');

        // Show the modal
        $('#bf-directory-password-modal').fadeIn(300);
        $('#bf-directory-password-input').focus();
    }

    // Close the directory password modal
    function closeDirectoryPasswordModal() {
        $('#bf-directory-password-modal').fadeOut(300);
    }

    // Check if the current directory has a password
    function checkCurrentDirectoryHasPassword() {
        return $('.bf-password-indicator').length > 0;
    }

    // Save the directory password
    function saveDirectoryPassword() {
        var currentPath = $('#current-path').val();
        var password = $('#bf-directory-password-input').val().trim();

        if (!password) {
            alert('<?php esc_html_e('Please enter password.', 'bf-secret-file-downloader' ); ?>');
            $('#bf-directory-password-input').focus();
            return;
        }

        if (password.length < 4) {
            alert('<?php esc_html_e('Password must be at least 4 characters.', 'bf-secret-file-downloader' ); ?>');
            $('#bf-directory-password-input').focus();
            return;
        }

        // Disable button
        $('#bf-save-password').prop('disabled', true).text('<?php esc_html_e('Saving...', 'bf-secret-file-downloader' ); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_set_directory_password',
                path: currentPath,
                password: password,
                action_type: 'set',
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    bfSfdShowSuccessMessage(response.data.message);
                    closeDirectoryPasswordModal();
                    updatePasswordIndicator(response.data.has_password);
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to set password.', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Communication error occurred.', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                // Enable button
                $('#bf-save-password').prop('disabled', false).text('<?php esc_html_e('Save', 'bf-secret-file-downloader' ); ?>');
            }
        });
    }

    // Remove the directory password
    function removeDirectoryPassword() {
        if (!confirm('<?php esc_html_e('Remove password protection for this directory?', 'bf-secret-file-downloader' ); ?>')) {
            return;
        }

        var currentPath = $('#current-path').val();

        // Disable button
        $('#bf-remove-password').prop('disabled', true).text('<?php esc_html_e('Deleting...', 'bf-secret-file-downloader' ); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_set_directory_password',
                path: currentPath,
                action_type: 'remove',
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    bfSfdShowSuccessMessage(response.data.message);
                    closeDirectoryPasswordModal();
                    updatePasswordIndicator(response.data.has_password);
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to remove password.', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Communication error occurred.', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                // Enable button
                $('#bf-remove-password').prop('disabled', false).text('<?php esc_html_e('Remove password', 'bf-secret-file-downloader' ); ?>');
            }
        });
    }

    // Update the password indicator
    function updatePasswordIndicator(hasPassword) {

        var passwordIndicator = $('.bf-password-indicator');
        var passwordButton = $('#directory-password-btn');

        if (passwordButton.length > 0) {
            if (hasPassword) {
                if (passwordIndicator.length === 0) {
                    $('#current-path').after('<span class="bf-password-indicator">' +
                        '<span class="dashicons dashicons-lock"></span>' +
                        '<span class="bf-password-status-text"><?php esc_html_e('Password protected', 'bf-secret-file-downloader' ); ?></span>' +
                        '</span>');
                }
                passwordButton.html('<span class="dashicons dashicons-admin-network"></span><?php esc_html_e('Password management', 'bf-secret-file-downloader' ); ?>');
            } else {
                passwordIndicator.remove();
                passwordButton.html('<span class="dashicons dashicons-admin-network"></span><?php esc_html_e('Password settings', 'bf-secret-file-downloader' ); ?>');
            }
        }
    }

    // Display the current password
    function showCurrentPassword() {
        var currentPath = $('#current-path').val();

        // Disable button
        $('#bf-show-current-password').prop('disabled', true).text('<?php esc_html_e('Retrieving...', 'bf-secret-file-downloader' ); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_get_directory_password',
                path: currentPath,
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Current password: ', 'bf-secret-file-downloader' ); ?>' + response.data.password);
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to retrieve password.', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Communication error occurred.', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                // Enable button
                $('#bf-show-current-password').prop('disabled', false).text('<?php esc_html_e('Current password', 'bf-secret-file-downloader' ); ?>');
            }
        });
    }

    // Open the URL copy modal
    function openUrlCopyModal(filePath, fileName) {
        // Update the modal elements
        $('#bf-url-file-name').text(fileName);

        // Save the file path in the modal
        $('#bf-url-copy-modal').data('file-path', filePath);

        // Select download by default
        $('input[name="url_type"][value="download"]').prop('checked', true);

        // Update URL
        updateUrlDisplay();

        // Show the modal
        $('#bf-url-copy-modal').fadeIn(300);
    }

    // Close the URL copy modal
    function closeUrlCopyModal() {
        $('#bf-url-copy-modal').fadeOut(300);
    }

    // Update URL display
    function updateUrlDisplay() {
        var filePath = $('#bf-url-copy-modal').data('file-path');
        var urlType = $('input[name="url_type"]:checked').val();
        var baseUrl = '<?php echo esc_url( home_url() ); ?>/?path=' + encodeURIComponent(filePath);

        var url = baseUrl + '&dflag=' + urlType;
        $('#bf-url-input').val(url);

        // Update the preview frame (only for image files)
        updatePreviewFrame(url);
    }

    // Update the preview frame
    function updatePreviewFrame(url) {
        var fileName = $('#bf-url-file-name').text();
        var urlType = $('input[name="url_type"]:checked').val();
        var previewFrame = $('#bf-url-preview-frame');

        // Display preview only for image files
        var imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
        var fileExtension = fileName.split('.').pop().toLowerCase();

        if (urlType === 'display' && imageExtensions.includes(fileExtension)) {
            previewFrame.attr('src', url);
            $('.bf-url-preview').show();
        } else {
            previewFrame.attr('src', '');
            $('.bf-url-preview').hide();
        }
    }

    // Copy URL to clipboard
    function copyUrlToClipboard() {
        var url = $('#bf-url-input').val();

        // Use the modern browser Clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(function() {
                bfSfdShowSuccessMessage('<?php esc_html_e('Download URL copied to clipboard:', 'bf-secret-file-downloader' ); ?> ' + url);
            }).catch(function(err) {
                console.error('<?php esc_html_e('Failed to copy to clipboard:', 'bf-secret-file-downloader' ); ?>', err);
                copyUrlFallback(url);
            });
        } else {
            // Use a fallback (for older browsers)
            copyUrlFallback(url);
        }
    }

    // Open URL in a new tab
    function openUrlInNewTab() {
        var url = $('#bf-url-input').val();
        window.open(url, '_blank');
    }

    // URL copy fallback (for older browsers)
    function copyUrlFallback(url) {
        var textArea = document.createElement('textarea');
        textArea.value = url;
        textArea.style.position = 'fixed';
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.width = '2em';
        textArea.style.height = '2em';
        textArea.style.padding = '0';
        textArea.style.border = 'none';
        textArea.style.outline = 'none';
        textArea.style.boxShadow = 'none';
        textArea.style.background = 'transparent';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            var successful = document.execCommand('copy');
            if (successful) {
                bfSfdShowSuccessMessage('<?php esc_html_e('Download URL copied to clipboard:', 'bf-secret-file-downloader' ); ?> ' + url);
            } else {
                showUrlPrompt(url);
            }
        } catch (err) {
            console.error('<?php esc_html_e('Failed to copy to clipboard:', 'bf-secret-file-downloader' ); ?>', err);
            showUrlPrompt(url);
        }

        document.body.removeChild(textArea);
    }

    // Display URL for manual copy
    function showUrlPrompt(url) {
        prompt('<?php esc_html_e('Please copy the following download URL:', 'bf-secret-file-downloader' ); ?>', url);
    }

    // Open the directory authentication modal
    function openDirectoryAuthModal() {
        var currentPath = $('#current-path').val();
        var currentPathDisplay = $('#current-path-display').text();
        var hasAuth = bfSfdCheckCurrentDirectoryHasAuth();

        // Update the modal title
        if (hasAuth) {
            $('#bf-auth-modal-title').text('<?php esc_html_e('Target directory settings', 'bf-secret-file-downloader' ); ?>');
        } else {
            $('#bf-auth-modal-title').text('<?php esc_html_e('Directory authentication settings', 'bf-secret-file-downloader' ); ?>');
        }

        // Update the current status display
        var statusIcon = $('.bf-auth-status-icon .dashicons');
        var statusDescription = $('#bf-auth-status-description');

        if (hasAuth) {
            statusIcon.removeClass('dashicons-unlock').addClass('dashicons-lock');
            statusIcon.css('color', '#0073aa');
            statusDescription.html('<?php esc_html_e('This directory (', 'bf-secret-file-downloader' ); ?><code>' + currentPathDisplay + '</code><?php esc_html_e('Target directory is not configured.', 'bf-secret-file-downloader' ); ?>');
            $('#bf-auth-modal-description').text('<?php esc_html_e('Change directory-specific settings or return to common settings using the \'Delete directory-specific settings\' button below.', 'bf-secret-file-downloader' ); ?>');
            $('#bf-remove-auth').show();
            $('#bf-show-current-auth').show();
        } else {
            statusIcon.removeClass('dashicons-lock').addClass('dashicons-admin-users');
            statusIcon.css('color', '#666');
            statusDescription.html('<?php esc_html_e('This directory (', 'bf-secret-file-downloader' ); ?><code>' + currentPathDisplay + '</code><?php esc_html_e('Target directory is not configured.', 'bf-secret-file-downloader' ); ?>');
            $('#bf-auth-modal-description').text('<?php esc_html_e('Common settings are applied. To add directory-specific authentication settings, configure them below.', 'bf-secret-file-downloader' ); ?>');
            $('#bf-remove-auth').hide();
            $('#bf-show-current-auth').hide();
        }

        // Get authentication settings
        if (hasAuth) {
            loadDirectoryAuthSettings(currentPath);
        } else {
            // If no directory-specific settings, uncheck everything
            $('#bf-auth-methods-logged-in').prop('checked', false);
            $('#bf-auth-methods-simple-auth').prop('checked', false);
            $('input[name="bf_allowed_roles[]"]').prop('checked', false);
            $('#bf-simple-auth-password').val('');
            $('#bf-simple-auth-password-section').hide();
            $('#bf-allowed-roles-section').hide();
        }

        // Show the modal
        $('#bf-directory-auth-modal').fadeIn(300);
    }

    // Close the directory authentication modal
    function closeDirectoryAuthModal() {
        $('#bf-directory-auth-modal').fadeOut(300);
    }

    // Save the directory authentication settings
    function saveDirectoryAuth() {
        var currentPath = $('#current-path').val();
        var authMethods = [];
        var allowedRoles = [];
        var simpleAuthPassword = $('#bf-simple-auth-password').val().trim();

        // Get authentication methods
        $('input[name="bf_auth_methods[]"]:checked').each(function() {
            authMethods.push($(this).val());
        });

        // Get allowed roles
        $('input[name="bf_allowed_roles[]"]:checked').each(function() {
            allowedRoles.push($(this).val());
        });

        if (authMethods.length === 0) {
            alert('<?php esc_html_e('Please select an authentication method.', 'bf-secret-file-downloader' ); ?>');
            return;
        }

        // If simple authentication is selected, a password is required
        if (authMethods.includes('simple_auth') && !simpleAuthPassword) {
            alert('<?php esc_html_e('If you select simple authentication, please set a password.', 'bf-secret-file-downloader' ); ?>');
            $('#bf-simple-auth-password').focus();
            return;
        }

        // Disable button
        $('#bf-save-auth').prop('disabled', true).text('<?php esc_html_e('Saving...', 'bf-secret-file-downloader' ); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_set_directory_auth',
                path: currentPath,
                auth_methods: authMethods,
                allowed_roles: allowedRoles,
                simple_auth_password: simpleAuthPassword,
                action_type: 'set',
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    bfSfdShowSuccessMessage(response.data.message);
                    closeDirectoryAuthModal();
                    updateAuthIndicator(response.data.has_auth);

                    // Display authentication details
                    if (response.data.has_auth) {
                        loadDirectoryAuthSettings(currentPath);
                    }
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to save authentication settings.', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Communication error occurred.', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                $('#bf-save-auth').prop('disabled', false).text('<?php esc_html_e('Save', 'bf-secret-file-downloader' ); ?>');
            }
        });
    }

    // Remove the directory authentication settings
    function removeDirectoryAuth() {
        if (!confirm('<?php esc_html_e('Remove authentication settings for this directory?', 'bf-secret-file-downloader' ); ?>')) {
            return;
        }

        var currentPath = $('#current-path').val();

        // Disable button
        $('#bf-remove-auth').prop('disabled', true).text('<?php esc_html_e('Deleting...', 'bf-secret-file-downloader' ); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_set_directory_auth',
                path: currentPath,
                action_type: 'remove',
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    bfSfdShowSuccessMessage(response.data.message);
                    closeDirectoryAuthModal();
                    updateAuthIndicator(response.data.has_auth);
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to delete authentication settings.', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Communication error occurred.', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                $('#bf-remove-auth').prop('disabled', false).text('<?php esc_html_e('Delete authentication settings', 'bf-secret-file-downloader' ); ?>');
            }
        });
    }

    // Control simple authentication checkbox
    $('#bf-auth-methods-simple-auth').on('change', function() {
        if ($(this).is(':checked')) {
            $('#bf-simple-auth-password-section').show();
        } else {
            $('#bf-simple-auth-password-section').hide();
        }
    });

    // Control login user checkbox
    $('#bf-auth-methods-logged-in').on('change', function() {
        if ($(this).is(':checked')) {
            $('#bf-allowed-roles-section').show();
        } else {
            $('#bf-allowed-roles-section').hide();
        }
    });

    // Control role selection
    $('#bf-select-all-roles').on('click', function() {
        $('.bf-role-checkbox').prop('checked', true);
    });

    $('#bf-deselect-all-roles').on('click', function() {
        $('.bf-role-checkbox').prop('checked', false);
    });

    // Remove authentication settings button event listener
    $(document).on('click', '#remove-auth-btn', function() {
        removeDirectoryAuth();
    });

    // Secure directory re-creation button processing
    $('#bf-recreate-secure-directory').on('click', function() {
        var $button = $(this);
        var $status = $('#bf-recreate-status');

        // Disable button
        $button.prop('disabled', true).text('<?php esc_html_e('Creating...', 'bf-secret-file-downloader' ); ?>');
        $status.html('<span style="color: #0073aa;"><?php esc_html_e('Processing...', 'bf-secret-file-downloader' ); ?></span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_recreate_secure_directory',
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: #46b450;">' + response.data.message + '</span>');

                    // Reload the page after 3 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    $status.html('<span style="color: #dc3232;">' + response.data + '</span>');
                    $button.prop('disabled', false).text('<?php esc_html_e('Create directory', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function(xhr, status, error) {
                $status.html('<span style="color: #dc3232;"><?php esc_html_e('An error occurred', 'bf-secret-file-downloader' ); ?>: ' + error + '</span>');
                $button.prop('disabled', false).text('<?php esc_html_e('Create directory', 'bf-secret-file-downloader' ); ?>');
            }
        });
    });

    // Display initial data (using data passed from wp_localize_script)
    if (typeof bfFileListData !== 'undefined' && bfFileListData.initialData) {
        bfSfdUpdateFileList(bfFileListData.initialData);
    }

});
</script>
