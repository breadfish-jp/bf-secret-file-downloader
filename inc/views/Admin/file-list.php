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

});
</script>
