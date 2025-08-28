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
                            <p><?php esc_html_e( '非公開ディレクトリにあるファイルを管理します。', 'bf-secret-file-downloader' ); ?></p>
        </div>

        <?php if ( ! $target_directory_set || ! $secure_directory_exists ) : ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e( 'セキュアディレクトリが存在しません', 'bf-secret-file-downloader' ); ?></strong>
                </p>

                <?php if ( ! empty( $secure_directory_path ) ) : ?>
                <p>
                    <strong><?php esc_html_e( 'パス:', 'bf-secret-file-downloader' ); ?></strong>
                    <code style="background-color: #fff; padding: 3px 5px; margin-left: 5px; word-break: break-all;">
                        <?php echo esc_html( $secure_directory_path ); ?>
                    </code>
                </p>
                <?php endif; ?>

                <p>
                    <button type="button" id="bf-recreate-secure-directory" class="button button-primary">
                        <?php esc_html_e( 'ディレクトリを作成', 'bf-secret-file-downloader' ); ?>
                    </button>

                    <?php if ( ! empty( $secure_directory_path ) ) : ?>
                    <button type="button" onclick="location.reload();" class="button button-secondary" style="margin-left: 10px;">
                        <?php esc_html_e( 'リロード', 'bf-secret-file-downloader' ); ?>
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
                        <strong><?php esc_html_e( '現在のディレクトリ:', 'bf-secret-file-downloader' ); ?></strong>
                        <code id="current-path-display"><?php echo esc_html( $current_path_display ); ?></code>
                        <input type="hidden" id="current-path" value="<?php echo esc_attr( $current_path ); ?>">
                        <?php if ( isset( $current_directory_has_auth ) && $current_directory_has_auth ) : ?>
                            <span class="bf-auth-indicator">
                                <span class="dashicons dashicons-lock"></span>
                                <span class="bf-auth-status-text"><?php esc_html_e( 'ディレクトリ毎認証設定あり', 'bf-secret-file-downloader' ); ?></span>
                            </span>
                            <div class="bf-auth-details">
                                <div class="auth-details-title"><?php esc_html_e( 'ディレクトリ毎認証設定詳細:', 'bf-secret-file-downloader' ); ?></div>
                                <div id="auth-details-content">
                                    <!-- Display settings content dynamically with JavaScript -->
                                </div>
                                <button type="button" id="remove-auth-btn" class="button button-small">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php esc_html_e( 'ディレクトリ毎設定削除', 'bf-secret-file-downloader' ); ?>
                                </button>
                            </div>
                        <?php else : ?>
                            <span class="bf-auth-indicator" style="color: #666;">
                                <span class="dashicons dashicons-admin-users"></span>
                                <span class="bf-auth-status-text"><?php esc_html_e( '共通認証設定適用中', 'bf-secret-file-downloader' ); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="bf-path-actions">
                        <?php if ( ! empty( $current_path ) ) : ?>
                            <button type="button" id="go-up-btn" class="button button-small">
                                <span class="dashicons dashicons-arrow-up-alt2"></span>
                                <?php esc_html_e( '上の階層へ', 'bf-secret-file-downloader' ); ?>
                            </button>
                        <?php endif; ?>
                        <!-- Directory-specific authentication button (displayed for non-root directories) -->
                        <?php if ( ! empty( $current_path ) ) : ?>
                            <button type="button" id="directory-auth-btn" class="button button-small">
                                <span class="dashicons dashicons-admin-users"></span>
                                <?php esc_html_e( '認証設定', 'bf-secret-file-downloader' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- File operation area -->
                <?php if ( $current_user_can_upload && $current_path_writable ) : ?>
                    <!-- Directory creation and file upload -->
                    <div class="bf-secret-file-downloader-actions">
                        <div class="bf-actions-header">
                            <h3><?php esc_html_e( 'ファイル操作', 'bf-secret-file-downloader' ); ?></h3>
                            <div class="bf-action-buttons">
                                <button type="button" id="create-directory-btn" class="button">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                    <?php esc_html_e( 'ディレクトリ作成', 'bf-secret-file-downloader' ); ?>
                                </button>
                                <button type="button" id="select-files-btn" class="button">
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php esc_html_e( 'ファイル選択', 'bf-secret-file-downloader' ); ?>
                                </button>

                            </div>
                        </div>

                        <!-- Directory creation form -->
                        <div id="create-directory-form" class="bf-create-directory-form" style="display: none;">
                            <div class="form-group">
                                <label for="directory-name-input"><?php esc_html_e( 'ディレクトリ名:', 'bf-secret-file-downloader' ); ?></label>
                                <input type="text" id="directory-name-input" class="regular-text" placeholder="<?php esc_attr_e( 'ディレクトリ名を入力', 'bf-secret-file-downloader' ); ?>">
                                <div class="form-actions">
                                    <button type="button" id="create-directory-submit" class="button button-primary"><?php esc_html_e( '作成', 'bf-secret-file-downloader' ); ?></button>
                                    <button type="button" id="create-directory-cancel" class="button"><?php esc_html_e( 'キャンセル', 'bf-secret-file-downloader' ); ?></button>
                                </div>
                            </div>
                            <p class="description">
                                <?php esc_html_e( '英数字、アンダーバー（_）、ハイフン（-）、ドット（.）が使用できます。', 'bf-secret-file-downloader' ); ?>
                            </p>
                        </div>

                        <!-- File upload area -->
                        <div id="drop-zone" class="bf-secret-file-downloader-drop-zone">
                            <div class="drop-zone-content">
                                <span class="dashicons dashicons-upload"></span>
                                <p><strong><?php esc_html_e( 'ファイルをここにドラッグ＆ドロップ', 'bf-secret-file-downloader' ); ?></strong></p>
                                <p><?php
                                    /* translators: %s: maximum file size in MB */
                                    echo esc_html( sprintf( __( '（最大: %sMB）', 'bf-secret-file-downloader' ), $max_file_size_mb ) );
                                ?></p>
                                <input type="file" id="file-input" multiple style="display: none;">
                            </div>
                            <div class="drop-zone-overlay" style="display: none;">
                                <p><?php esc_html_e( 'ファイルをドロップしてください', 'bf-secret-file-downloader' ); ?></p>
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
                            echo esc_html( sprintf( __( '%d個のアイテムが見つかりました。', 'bf-secret-file-downloader' ), (int) $total_files ) );
                        } else {
                            esc_html_e( 'アイテムが見つかりませんでした。', 'bf-secret-file-downloader' );
                        }
                        ?>
                    </p>
                </div>

                <!-- Bulk operations and pagination (top) -->
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( '一括操作を選択', 'bf-secret-file-downloader' ); ?></label>
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1"><?php esc_html_e( '一括操作', 'bf-secret-file-downloader' ); ?></option>
                            <?php if ( $current_user_can_delete ) : ?>
                                <option value="delete"><?php esc_html_e( '削除', 'bf-secret-file-downloader' ); ?></option>
                            <?php endif; ?>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e( '適用', 'bf-secret-file-downloader' ); ?>">
                    </div>
                    <?php if ( $total_pages > 1 ) : ?>
                        <div class="tablenav-pages">
                            <?php echo wp_kses_post( $pagination_html ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- File list table -->
                <div class="bf-secret-file-downloader-file-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column" style="width: 40px;">
                                    <label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'すべて選択', 'bf-secret-file-downloader' ); ?></label>
                                    <input id="cb-select-all-1" type="checkbox">
                                </td>
                                <th class="manage-column column-name sortable <?php echo $sort_by === 'name' ? 'sorted ' . esc_attr( $sort_order ) : ''; ?>" style="width: 45%;">
                                    <a href="#" class="sort-link" data-sort="name">
                                        <span><?php esc_html_e( 'ファイル名', 'bf-secret-file-downloader' ); ?></span>
                                        <span class="sorting-indicator"></span>
                                    </a>
                                </th>
                                <th class="manage-column column-type" style="width: 15%;">
                                    <?php esc_html_e( 'タイプ', 'bf-secret-file-downloader' ); ?>
                                </th>
                                <th class="manage-column column-size sortable <?php echo $sort_by === 'size' ? 'sorted ' . esc_attr( $sort_order ) : ''; ?>" style="width: 15%;">
                                    <a href="#" class="sort-link" data-sort="size">
                                        <span><?php esc_html_e( 'サイズ', 'bf-secret-file-downloader' ); ?></span>
                                        <span class="sorting-indicator"></span>
                                    </a>
                                </th>
                                <th class="manage-column column-modified sortable <?php echo $sort_by === 'modified' ? 'sorted ' . esc_attr( $sort_order ) : ''; ?>" style="width: 20%;">
                                    <a href="#" class="sort-link" data-sort="modified">
                                        <span><?php esc_html_e( '更新日', 'bf-secret-file-downloader' ); ?></span>
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
        <span><?php esc_html_e( '読み込み中...', 'bf-secret-file-downloader' ); ?></span>
    </div>

</div>

<!-- Directory authentication settings modal -->
<div id="bf-directory-auth-modal" class="bf-modal" style="display: none;">
    <div class="bf-modal-content" style="width: 70%; max-width: 700px;">
        <div class="bf-modal-header">
            <h3 id="bf-auth-modal-title"><?php esc_html_e( 'ディレクトリ認証設定', 'bf-secret-file-downloader' ); ?></h3>
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
                        <strong id="bf-auth-status-title"><?php esc_html_e( '現在の状態', 'bf-secret-file-downloader' ); ?></strong>
                        <p id="bf-auth-status-description"><?php esc_html_e( 'このディレクトリは認証保護されていません。', 'bf-secret-file-downloader' ); ?></p>
                    </div>
                </div>
            </div>

            <p id="bf-auth-modal-description">
                <?php esc_html_e( 'このディレクトリ内のファイルをダウンロードする際に要求する認証設定を行ってください。', 'bf-secret-file-downloader' ); ?>
            </p>

            <!-- Authentication settings -->
            <div class="bf-auth-section">
                <h4><?php esc_html_e( '認証方法', 'bf-secret-file-downloader' ); ?></h4>
                <fieldset>
                    <legend class="screen-reader-text"><?php esc_html_e( '認証方法', 'bf-secret-file-downloader' ); ?></legend>
                    <label>
                        <input type="checkbox" name="bf_auth_methods[]" value="logged_in" id="bf-auth-methods-logged-in" />
                        <?php esc_html_e( 'ログインしているユーザー', 'bf-secret-file-downloader' ); ?>
                    </label>
                    <div id="bf-allowed-roles-section" style="margin-top: 10px; padding: 10px; background-color: #f9f9f9; border-left: 4px solid #0073aa; display: none;">
                        <label for="bf-allowed-roles">
                            <strong><?php esc_html_e( '許可するユーザーロール', 'bf-secret-file-downloader' ); ?></strong>
                        </label>
                        <div class="bf-role-selection-controls" style="margin: 10px 0;">
                            <button type="button" id="bf-select-all-roles" class="button button-small"><?php esc_html_e( 'すべて選択', 'bf-secret-file-downloader' ); ?></button>
                            <button type="button" id="bf-deselect-all-roles" class="button button-small"><?php esc_html_e( 'すべて解除', 'bf-secret-file-downloader' ); ?></button>
                        </div>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e( '許可するユーザーロール', 'bf-secret-file-downloader' ); ?></legend>
                            <?php
                            $roles = array(
                                'administrator' => __( '管理者', 'bf-secret-file-downloader' ),
                                'editor' => __( '編集者', 'bf-secret-file-downloader' ),
                                'author' => __( '投稿者', 'bf-secret-file-downloader' ),
                                'contributor' => __( '寄稿者', 'bf-secret-file-downloader' ),
                                'subscriber' => __( '購読者', 'bf-secret-file-downloader' )
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
                        <p class="description" style="margin-top: 10px;"><?php esc_html_e( 'ファイルアクセスを許可するユーザーロールを選択してください。複数選択可能です。', 'bf-secret-file-downloader' ); ?></p>
                    </div>
                    <br>
                    <label>
                        <input type="checkbox" name="bf_auth_methods[]" value="simple_auth" id="bf-auth-methods-simple-auth" />
                        <?php esc_html_e( '簡易認証を通過したユーザー', 'bf-secret-file-downloader' ); ?>
                    </label>
                    <div id="bf-simple-auth-password-section" style="margin-top: 10px; padding: 10px; background-color: #f9f9f9; border-left: 4px solid #0073aa; display: none;">
                        <label for="bf-simple-auth-password">
                            <strong><?php esc_html_e( '簡易認証パスワード', 'bf-secret-file-downloader' ); ?></strong>
                        </label>
                        <br>
                        <input type="password" name="bf_simple_auth_password" id="bf-simple-auth-password"
                               class="regular-text" style="margin-top: 5px;" />
                        <p class="description" style="margin-top: 5px;"><?php esc_html_e( '簡易認証で使用するパスワードを設定してください。', 'bf-secret-file-downloader' ); ?></p>
                    </div>
                </fieldset>
                <p class="description"><?php esc_html_e( 'ファイルアクセスを許可する認証方法を選択してください。複数選択可能です。', 'bf-secret-file-downloader' ); ?></p>
            </div>
        </div>
        <div class="bf-modal-footer">
            <div class="bf-action-buttons-left">
                <button type="button" id="bf-remove-auth" class="button button-secondary bf-danger-button" style="display: none;">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e( '認証設定を削除', 'bf-secret-file-downloader' ); ?>
                </button>
            </div>
            <div class="bf-action-buttons-right">
                <button type="button" id="bf-save-auth" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e( '保存', 'bf-secret-file-downloader' ); ?>
                </button>
                <button type="button" id="bf-cancel-auth" class="button">
                    <?php esc_html_e( 'キャンセル', 'bf-secret-file-downloader' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Directory password settings modal -->
<div id="bf-directory-password-modal" class="bf-modal" style="display: none;">
    <div class="bf-modal-content" style="width: 60%; max-width: 600px;">
        <div class="bf-modal-header">
            <h3 id="bf-password-modal-title"><?php esc_html_e( 'ディレクトリパスワード設定', 'bf-secret-file-downloader' ); ?></h3>
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
                        <strong id="bf-status-title"><?php esc_html_e( '現在の状態', 'bf-secret-file-downloader' ); ?></strong>
                        <p id="bf-status-description"><?php esc_html_e( 'このディレクトリはパスワード保護されていません。', 'bf-secret-file-downloader' ); ?></p>
                    </div>
                </div>
            </div>

            <p id="bf-password-modal-description">
                <?php esc_html_e( 'このディレクトリ内のファイルをダウンロードする際に要求するパスワードを設定してください。', 'bf-secret-file-downloader' ); ?>
            </p>

            <div class="bf-password-form">
                <label for="bf-directory-password-input"><?php esc_html_e( 'パスワード:', 'bf-secret-file-downloader' ); ?></label>
                <div class="bf-password-input-group">
                    <input type="password" id="bf-directory-password-input" class="regular-text"
                           placeholder="<?php esc_attr_e( 'パスワードを入力', 'bf-secret-file-downloader' ); ?>" />
                    <button type="button" id="bf-password-toggle" class="button">
                        <?php esc_html_e( '表示', 'bf-secret-file-downloader' ); ?>
                    </button>
                    <button type="button" id="bf-show-current-password" class="button" style="display: none;">
                        <?php esc_html_e( '現在のパスワード', 'bf-secret-file-downloader' ); ?>
                    </button>
                </div>
                <p class="description">
                    <?php esc_html_e( '安全性のため、8文字以上の複雑なパスワードを設定することをお勧めします。', 'bf-secret-file-downloader' ); ?>
                </p>
            </div>
        </div>
        <div class="bf-modal-footer">
            <div class="bf-action-buttons-left">
                <button type="button" id="bf-remove-password" class="button button-secondary bf-danger-button" style="display: none;">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e( 'パスワード保護を解除', 'bf-secret-file-downloader' ); ?>
                </button>
            </div>
            <div class="bf-action-buttons-right">
                <button type="button" id="bf-save-password" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e( '保存', 'bf-secret-file-downloader' ); ?>
                </button>
                <button type="button" id="bf-cancel-password" class="button">
                    <?php esc_html_e( 'キャンセル', 'bf-secret-file-downloader' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- URL copy modal -->
<div id="bf-url-copy-modal" class="bf-modal" style="display: none;">
    <div class="bf-modal-content" style="width: 70%; max-width: 700px;">
        <div class="bf-modal-header">
            <h3><?php esc_html_e( 'ファイルアクセスURL', 'bf-secret-file-downloader' ); ?></h3>
            <span class="bf-modal-close">&times;</span>
        </div>
        <div class="bf-modal-body">
            <div class="bf-url-info">
                <h4 id="bf-url-file-name"><?php esc_html_e( 'ファイル名', 'bf-secret-file-downloader' ); ?></h4>
                <p class="description"><?php esc_html_e( '以下のURLを使用してファイルにアクセスできます。', 'bf-secret-file-downloader' ); ?></p>
            </div>

            <div class="bf-url-options">
                <h4><?php esc_html_e( 'アクセス方法を選択', 'bf-secret-file-downloader' ); ?></h4>
                <div class="bf-url-option-group">
                    <label class="bf-url-option">
                        <input type="radio" name="url_type" value="download" checked>
                        <span class="bf-option-content">
                            <span class="bf-option-icon dashicons dashicons-download"></span>
                            <div class="bf-option-text">
                                <strong><?php esc_html_e( 'ダウンロード', 'bf-secret-file-downloader' ); ?></strong>
                                <span><?php esc_html_e( 'ファイルを直接ダウンロードします', 'bf-secret-file-downloader' ); ?></span>
                            </div>
                        </span>
                    </label>
                    <label class="bf-url-option">
                        <input type="radio" name="url_type" value="display">
                        <span class="bf-option-content">
                            <span class="bf-option-icon dashicons dashicons-visibility"></span>
                            <div class="bf-option-text">
                                <strong><?php esc_html_e( 'その場で表示', 'bf-secret-file-downloader' ); ?></strong>
                                <span><?php esc_html_e( 'ブラウザでファイルを表示します', 'bf-secret-file-downloader' ); ?></span>
                            </div>
                        </span>
                    </label>
                </div>
            </div>

            <div class="bf-url-display">
                <label for="bf-url-input"><?php esc_html_e( 'URL:', 'bf-secret-file-downloader' ); ?></label>
                <div class="bf-url-input-group">
                    <input type="text" id="bf-url-input" class="regular-text" readonly>
                    <button type="button" id="bf-copy-url-btn" class="button">
                        <span class="dashicons dashicons-clipboard"></span>
                        <?php esc_html_e( 'コピー', 'bf-secret-file-downloader' ); ?>
                    </button>
                </div>
            </div>

            <div class="bf-url-preview">
                <h4><?php esc_html_e( 'プレビュー', 'bf-secret-file-downloader' ); ?></h4>
                <div class="bf-preview-frame">
                    <iframe id="bf-url-preview-frame" style="width: 100%; height: 300px; border: 1px solid #ddd; border-radius: 4px;"></iframe>
                </div>
            </div>
        </div>
        <div class="bf-modal-footer">
            <div class="bf-action-buttons-right">
                <button type="button" id="bf-open-url-btn" class="button button-primary">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e( '新しいタブで開く', 'bf-secret-file-downloader' ); ?>
                </button>
                <button type="button" id="bf-close-url-modal" class="button">
                    <?php esc_html_e( '閉じる', 'bf-secret-file-downloader' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>


<script type="text/javascript">
jQuery(document).ready(function($) {

    // Check if Dashicons are loaded
    checkDashicons();

    // Initialize authentication details display on page load
    setTimeout(function() {
        initializeAuthDetails();
    }, 200);

    // Authentication details template function
    function getAuthDetailsTemplate() {
        return '<div class="bf-auth-details">' +
               '<div class="auth-details-title"><?php esc_html_e( 'ディレクトリ毎認証設定詳細:', 'bf-secret-file-downloader' ); ?></div>' +
               '<div id="auth-details-content"></div>' +
               '<button type="button" id="remove-auth-btn" class="button button-small">' +
               '<span class="dashicons dashicons-trash"></span><?php esc_html_e( 'ディレクトリ毎設定削除', 'bf-secret-file-downloader' ); ?>' +
               '</button>' +
               '</div>';
    }

    // Initialize authentication details display on page load
    function initializeAuthDetails() {
        var currentPath = $('#current-path').val();
        var hasAuth = checkCurrentDirectoryHasAuth();

        if (hasAuth && currentPath) {
            // Check if authentication details are already displayed
            var authDetails = $('.bf-auth-details');
            if (authDetails.length === 0) {
                $('.bf-path-info').append(getAuthDetailsTemplate());
            }

            // Load and display authentication settings
            loadDirectoryAuthSettings(currentPath);
        }
    }

    // Delete link event (from mouse over menu)
    $(document).on('click', '.delete-file-link', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Stop event propagation
        var $link = $(this);
        var filePath = $link.data('file-path');
        var fileName = $link.data('file-name');
        var fileType = $link.data('file-type');

        console.log('削除リンクがクリックされました:', filePath, fileName, fileType); // デバッグ用
        deleteFile(filePath, fileName, fileType);
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
            button.text('<?php esc_html_e( '非表示', 'bf-secret-file-downloader' ); ?>');
        } else {
            passwordField.attr('type', 'password');
            button.text('<?php esc_html_e( '表示', 'bf-secret-file-downloader' ); ?>');
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
            var parentPath = getParentPath(currentPath);
            navigateToDirectory(parentPath, 1);
        }
    });

    // Sort link click processing
    $(document).on('click', '.sort-link', function(e) {
        e.preventDefault();
        var sortBy = $(this).data('sort');
        var currentPath = $('#current-path').val();
        var currentSortBy = getCurrentSortBy();
        var currentSortOrder = getCurrentSortOrder();

        // If the same column is clicked, reverse the order
        var newSortOrder = 'asc';
        if (sortBy === currentSortBy && currentSortOrder === 'asc') {
            newSortOrder = 'desc';
        }

        navigateToDirectoryWithSort(currentPath, 1, sortBy, newSortOrder);
    });

    // Paging link click processing
    $(document).on('click', '.pagination-links a', function(e) {
        e.preventDefault();
        var url = new URL(this.href);
        var page = url.searchParams.get('paged') || 1;
        var path = url.searchParams.get('path') || $('#current-path').val();
        navigateToDirectory(path, page);
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
        createDirectory();
    });

    // Enter key to create directory
    $('#directory-name-input').on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            createDirectory();
        }
    });

    // Download link event
    $(document).on('click', '.download-file-link', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Stop event propagation
        var $link = $(this);
        var filePath = $link.data('file-path');
        var fileName = $link.data('file-name');

        downloadFile(filePath, fileName);
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
            navigateToDirectory(path, 1);
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
            alert('<?php echo esc_js( __( '操作を選択してください。', 'bf-secret-file-downloader' ) ); ?>');
            return;
        }

        var checkedFiles = $('input[name="file_paths[]"]:checked');
        if (checkedFiles.length === 0) {
            alert('<?php echo esc_js( __( '削除するアイテムを選択してください。', 'bf-secret-file-downloader' ) ); ?>');
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
            uploadFiles(files);
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
                uploadFiles(files);
            }
        });

        // Disable default drag and drop on the entire page
        $(document).on('dragenter dragover drop', function(e) {
            e.preventDefault();
        });
    }

    // Check if the file is a program code file
    function isProgramCodeFile(filename) {
        // Program code file extension list
        var codeExtensions = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phps',
            'js', 'jsx', 'ts', 'tsx',
            'css', 'scss', 'sass', 'less',
            'html', 'htm', 'xhtml',
            'xml', 'xsl', 'xslt',
            'json', 'yaml', 'yml',
            'py', 'pyc', 'pyo',
            'rb', 'rbw',
            'pl', 'pm',
            'java', 'class', 'jar',
            'c', 'cpp', 'cc', 'cxx', 'h', 'hpp',
            'cs', 'vb', 'vbs',
            'sh', 'bash', 'zsh', 'fish',
            'sql', 'mysql', 'pgsql',
            'asp', 'aspx', 'jsp',
            'cgi', 'fcgi'
        ];

        // Configuration files and dangerous files
        var configFiles = [
            '.htaccess', '.htpasswd', '.env', '.ini',
            'web.config', 'composer.json', 'package.json',
            'Dockerfile', 'docker-compose.yml',
            'Makefile', 'CMakeLists.txt'
        ];

        // Check by extension
        var extension = filename.split('.').pop().toLowerCase();
        if (codeExtensions.includes(extension)) {
            return true;
        }

        // Check by filename
        if (configFiles.includes(filename)) {
            return true;
        }

        // Script file names often used without extension
        var scriptNames = [
            'index', 'config', 'settings', 'install', 'setup',
            'admin', 'login', 'auth', 'database', 'db'
        ];

        var basename = filename.split('.')[0].toLowerCase();
        if (scriptNames.includes(basename) && !filename.includes('.')) {
            return true;
        }

        return false;
    }

    function getCurrentSortBy() {
        return $('.sortable.sorted').length > 0 ?
            $('.sortable.sorted').find('.sort-link').data('sort') : 'name';
    }

    function getCurrentSortOrder() {
        if ($('.sortable.sorted.asc').length > 0) return 'asc';
        if ($('.sortable.sorted.desc').length > 0) return 'desc';
        return 'asc';
    }

    function navigateToDirectoryWithSort(path, page, sortBy, sortOrder) {
        $('#bf-secret-file-downloader-loading').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_browse_files',
                path: path,
                page: page,
                sort_by: sortBy,
                sort_order: sortOrder,
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    updateFileListWithSort(response.data, sortBy, sortOrder);
                    // Update URL (add to browser history)
                    var newUrl = new URL(window.location);
                    newUrl.searchParams.set('path', path);
                    newUrl.searchParams.set('paged', page);
                    newUrl.searchParams.set('sort_by', sortBy);
                    newUrl.searchParams.set('sort_order', sortOrder);
                    window.history.pushState({path: path, page: page, sortBy: sortBy, sortOrder: sortOrder}, '', newUrl);
                } else {
                    // If the directory cannot be accessed, try to move to the parent directory
                    var errorMessage = response.data || '<?php echo esc_js( __( 'エラーが発生しました', 'bf-secret-file-downloader' ) ); ?>';

                    if (errorMessage.indexOf('<?php echo esc_js( __( 'ディレクトリにアクセスできません', 'bf-secret-file-downloader' ) ); ?>') !== -1 ||
                        errorMessage.indexOf('アクセスできません') !== -1) {
                        // If the directory access error occurs, try to move to the parent directory
                        var parentPath = getParentPath(path);
                        if (parentPath !== path) {
                            console.log('ディレクトリアクセスエラー。親ディレクトリに移動します: ' + parentPath);
                            navigateToDirectoryWithSort(parentPath, 1, sortBy, sortOrder);
                            return;
                        }
                    }

                    alert(errorMessage);
                }
            },
            error: function() {
                alert('<?php esc_html_e( '通信エラーが発生しました', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                $('#bf-secret-file-downloader-loading').hide();
            }
        });
    }

    function navigateToDirectory(path, page) {
        var currentSortBy = getCurrentSortBy();
        var currentSortOrder = getCurrentSortOrder();
        navigateToDirectoryWithSort(path, page, currentSortBy, currentSortOrder);
    }



    function navigateToDirectoryOld(path, page) {
        $('#bf-secret-file-downloader-loading').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_browse_files',
                path: path,
                page: page,
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    updateFileList(response.data);
                    // Update URL (add to browser history)
                    var newUrl = new URL(window.location);
                    newUrl.searchParams.set('path', path);
                    newUrl.searchParams.set('paged', page);
                    window.history.pushState({path: path, page: page}, '', newUrl);
                } else {
                    alert(response.data || '<?php esc_html_e( 'エラーが発生しました', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e( '通信エラーが発生しました', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                $('#bf-secret-file-downloader-loading').hide();
            }
        });
    }

        function updateFileListWithSort(data, sortBy, sortOrder) {
        // Update sort state
        $('.sortable').removeClass('sorted asc desc');
        $('.sortable').each(function() {
            var linkSortBy = $(this).find('.sort-link').data('sort');
            if (linkSortBy === sortBy) {
                $(this).addClass('sorted ' + sortOrder);
            }
        });

        updateFileList(data);
    }

    // Template function group
    function createIconWrapper(file) {
        if (file.type === 'directory') {
            return '<span class="bf-icon-wrapper">' +
                '<span class="dashicons dashicons-folder bf-directory-icon" style="font-size: 20px !important; margin-right: 8px; vertical-align: middle; font-family: dashicons !important;"></span>' +
                '<span class="bf-fallback-icon" style="display: none; font-size: 18px; margin-right: 8px; vertical-align: middle;">📁</span>' +
                '</span>';
        } else {
            var iconClass = file.type_class || '';
            var fallbackEmoji = '📄';

            if (iconClass === 'image-file') {
                fallbackEmoji = '🖼️';
            } else if (iconClass === 'document-file') {
                fallbackEmoji = '📝';
            } else if (iconClass === 'archive-file') {
                fallbackEmoji = '📦';
            }

            return '<span class="bf-icon-wrapper">' +
                '<span class="dashicons dashicons-media-default bf-file-icon" style="font-size: 16px !important; margin-right: 8px; vertical-align: middle; font-family: dashicons !important;"></span>' +
                '<span class="bf-fallback-icon" style="display: none; font-size: 16px; margin-right: 8px; vertical-align: middle;">' + fallbackEmoji + '</span>' +
                '</span>';
        }
    }

    function createRowActions(file) {
        var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
        var rowActions = '<div class="row-actions">';

        if (file.type === 'directory') {
            if (file.readable) {
                rowActions += '<span class="open"><a href="#" class="open-directory" data-path="' + $('<div>').text(file.path).html() + '">' + (strings.open || '<?php esc_html_e( '開く', 'bf-secret-file-downloader' ); ?>') + '</a>';

                if (file.can_delete) {
                    rowActions += ' | ';
                }
                rowActions += '</span>';
            }
        } else {
            rowActions += '<span class="download"><a href="#" class="download-file-link" ' +
                'data-file-path="' + $('<div>').text(file.path).html() + '" ' +
                'data-file-name="' + $('<div>').text(file.name).html() + '">' + (strings.download || '<?php esc_html_e( 'ダウンロード', 'bf-secret-file-downloader' ); ?>') + '</a> | </span>';
            rowActions += '<span class="copy-url"><a href="#" class="copy-url-link" ' +
                'data-file-path="' + $('<div>').text(file.path).html() + '" ' +
                'data-file-name="' + $('<div>').text(file.name).html() + '">' + (strings.copyUrl || '<?php esc_html_e( 'URLをコピー', 'bf-secret-file-downloader' ); ?>') + '</a>';

            if (file.can_delete) {
                rowActions += ' | ';
            }
            rowActions += '</span>';
        }

        if (file.can_delete) {
            rowActions += '<span class="delete"><a href="#" class="delete-file-link" ' +
                'data-file-path="' + $('<div>').text(file.path).html() + '" ' +
                'data-file-name="' + $('<div>').text(file.name).html() + '" ' +
                'data-file-type="' + $('<div>').text(file.type).html() + '">' + (strings.delete || '<?php esc_html_e( '削除', 'bf-secret-file-downloader' ); ?>') + '</a></span>';
        }

        rowActions += '</div>';
        return rowActions;
    }

    function createNameCell(file) {
        var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
        var nameCell = $('<td class="column-name has-row-actions"></td>');
        var iconWrapper = createIconWrapper(file);
        var rowActions = createRowActions(file);

        if (file.type === 'directory') {
            if (file.readable) {
                nameCell.html(iconWrapper + '<strong class="bf-directory-name row-title"><a href="#" class="open-directory" data-path="' + $('<div>').text(file.path).html() + '">' + $('<div>').text(file.name).html() + '</a></strong>');
            } else {
                nameCell.html(iconWrapper + '<span class="bf-directory-name-disabled row-title">' + $('<div>').text(file.name).html() + '</span>' +
                             '<small class="bf-access-denied">(' + (strings.accessDenied || '<?php esc_html_e( 'アクセス不可', 'bf-secret-file-downloader' ); ?>') + ')</small>');
            }
        } else {
            nameCell.html(iconWrapper + '<span class="bf-file-name row-title"><a href="#" class="download-file-link" data-file-path="' + $('<div>').text(file.path).html() + '" data-file-name="' + $('<div>').text(file.name).html() + '">' + $('<div>').text(file.name).html() + '</a></span>');
        }

        nameCell.append(rowActions);
        return nameCell;
    }

    function createFileRow(file) {
        var row = $('<tr></tr>')
            .attr('data-path', file.path)
            .attr('data-type', file.type);

        if (file.type === 'directory' && file.readable) {
            row.addClass('clickable-directory').css('cursor', 'pointer');
        }

        // Checkbox column
        var checkboxCell = $('<th scope="row" class="check-column"></th>');
        var checkbox = $('<input type="checkbox" name="file_paths[]">')
            .attr('value', file.path)
            .attr('data-file-name', file.name)
            .attr('data-file-type', file.type);
        checkboxCell.append(checkbox);

        var nameCell = createNameCell(file);

        var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
        var typeCell = $('<td class="column-type"></td>').text(
            file.type === 'directory'
                ? (strings.directory || '<?php esc_html_e( 'ディレクトリ', 'bf-secret-file-downloader' ); ?>')
                : (strings.file || '<?php esc_html_e( 'ファイル', 'bf-secret-file-downloader' ); ?>')
        );

        var sizeCell = $('<td class="column-size"></td>').text(
            file.size === '-' ? '-' : formatFileSize(file.size)
        );

        var modifiedCell = $('<td class="column-modified"></td>').text(
            new Date(file.modified * 1000).toLocaleString('ja-JP')
        );

        row.append(checkboxCell, nameCell, typeCell, sizeCell, modifiedCell);
        return row;
    }

    function createPathDisplayTemplate(data) {
        var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
        var pathHtml = '<div class="bf-path-info">' +
            '<strong>' + (strings.currentDirectory || '<?php esc_html_e( '現在のディレクトリ:', 'bf-secret-file-downloader' ); ?>') + '</strong>' +
            '<code id="current-path-display">' + (data.current_path || (strings.rootDirectory || '<?php esc_html_e( "ルートディレクトリ", "bf-secret-file-downloader" ); ?>')) + '</code>' +
            '<input type="hidden" id="current-path" value="' + (data.current_path || '') + '">' +
            '</div>' +
            '<div class="bf-path-actions">';

        // Go up button
        if (data.current_path && data.current_path !== '') {
            pathHtml += '<button type="button" id="go-up-btn" class="button button-small">' +
                '<span class="dashicons dashicons-arrow-up-alt2"></span>' +
                (strings.goUp || '<?php esc_html_e( '上の階層へ', 'bf-secret-file-downloader' ); ?>') +
                '</button>';
        }

        // Directory-specific authentication settings button (displayed only for non-root directories)
        <?php if ( current_user_can( 'manage_options' ) ) : ?>
        if (data.current_path && data.current_path !== '') {
            pathHtml += '<button type="button" id="directory-auth-btn" class="button button-small">' +
                '<span class="dashicons dashicons-admin-users"></span>' +
                (strings.authSettings || '<?php esc_html_e( '認証設定', 'bf-secret-file-downloader' ); ?>') +
                '</button>';
        }
        <?php endif; ?>

        pathHtml += '</div>';
        return pathHtml;
    }

    function updateFileList(data) {
        // Update current path
        $('#current-path').val(data.current_path);
        $('#current-path-display').text(data.current_path || '<?php esc_html_e( "ルートディレクトリ", "bf-secret-file-downloader" ); ?>');

        // Rebuild the entire path display area
        $('.bf-secret-file-downloader-path').html(createPathDisplayTemplate(data));

        // Update authentication indicator (after updating the path display area)
        var hasAuth = data.current_directory_has_auth || false;
        updateAuthIndicator(hasAuth);

        // Reset event handlers
        $('#go-up-btn').on('click', function(e) {
            e.preventDefault();
            var currentPath = $('#current-path').val();
            if (currentPath) {
                var parentPath = getParentPath(currentPath);
                navigateToDirectory(parentPath, 1);
            }
        });

        $('#directory-auth-btn').on('click', function(e) {
            e.preventDefault();
            openDirectoryAuthModal();
        });

        // Update statistics
        var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
        $('.bf-secret-file-downloader-stats p').text(
            data.total_items > 0
                ? (strings.itemsFound || '<?php
                    /* translators: %d: number of items found */
                    echo esc_js( __( '%d個のアイテムが見つかりました。', 'bf-secret-file-downloader' ) );
                ?>').replace('%d', data.total_items)
                : (strings.noItemsFound || '<?php echo esc_js( __( 'アイテムが見つかりませんでした。', 'bf-secret-file-downloader' ) ); ?>')
        );

        // Update file list
        var tbody = $('#file-list-tbody');
        tbody.empty();

        if (data.items && data.items.length > 0) {
            $.each(data.items, function(index, file) {
                tbody.append(createFileRow(file));
            });

            // Stop event propagation for dynamically generated checkboxes
            $('input[name="file_paths[]"]').off('click').on('click', function(e) {
                e.stopPropagation();
            });

            // Stop event propagation for dynamically generated checkbox labels
            $('.check-column label').off('click').on('click', function(e) {
                e.stopPropagation();
            });
        } else {
            var strings = (typeof bfFileListData !== 'undefined' && bfFileListData.strings) ? bfFileListData.strings : {};
            tbody.append(
                '<tr><td colspan="5" style="text-align: center; padding: 40px;">' +
                (strings.noFilesFound || '<?php esc_html_e( 'ファイルまたはディレクトリが見つかりませんでした。', 'bf-secret-file-downloader' ); ?>') +
                '</td></tr>'
            );
        }

        // Update pagination
        updatePagination(data);
    }

    function updatePagination(data) {
        // Remove existing pagination elements
        $('.tablenav').remove();

        // Top tablenav including bulk action menu
        var topTablenav = '<div class="tablenav top">' +
            '<div class="alignleft actions bulkactions">' +
            '<label for="bulk-action-selector-top" class="screen-reader-text">' + '<?php echo esc_js( __( '一括操作を選択', 'bf-secret-file-downloader' ) ); ?>' + '</label>' +
            '<select name="action" id="bulk-action-selector-top">' +
            '<option value="-1">' + '<?php echo esc_js( __( '一括操作', 'bf-secret-file-downloader' ) ); ?>' + '</option>';

        if (data.current_user_can_delete) {
            topTablenav += '<option value="delete">' + '<?php echo esc_js( __( '削除', 'bf-secret-file-downloader' ) ); ?>' + '</option>';
        }

        topTablenav += '</select>' +
            '<input type="submit" id="doaction" class="button action" value="' + '<?php echo esc_js( __( '適用', 'bf-secret-file-downloader' ) ); ?>' + '">' +
            '</div>';

        if (data.total_pages > 1) {
            var pagination = generatePaginationHtml(data.current_page, data.total_pages, data.current_path);
            topTablenav += '<div class="tablenav-pages">' + pagination + '</div>';
        }

        topTablenav += '</div>';

        // Place top tablenav before the table
        $('.bf-secret-file-downloader-file-table').before(topTablenav);

        // If there is pagination, add bottom tablenav
        if (data.total_pages > 1) {
            var pagination = generatePaginationHtml(data.current_page, data.total_pages, data.current_path);
            $('.bf-secret-file-downloader-file-table').after('<div class="tablenav bottom"><div class="tablenav-pages">' + pagination + '</div></div>');
        }
    }

    function generatePaginationHtml(currentPage, totalPages, currentPath) {
        var html = '<span class="pagination-links">';

        // Previous page
        if (currentPage > 1) {
            html += '<a href="?page=bf-secret-file-downloader&path=' + encodeURIComponent(currentPath) + '&paged=' + (currentPage - 1) + '">&laquo; ' + '<?php echo esc_js( __( '前', 'bf-secret-file-downloader' ) ); ?>' + '</a>';
        }

        // Page number
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, currentPage + 2);

        for (var i = startPage; i <= endPage; i++) {
            if (i == currentPage) {
                html += '<span class="current">' + i + '</span>';
            } else {
                html += '<a href="?page=bf-secret-file-downloader&path=' + encodeURIComponent(currentPath) + '&paged=' + i + '">' + i + '</a>';
            }
        }

        // Next page
        if (currentPage < totalPages) {
            html += '<a href="?page=bf-secret-file-downloader&path=' + encodeURIComponent(currentPath) + '&paged=' + (currentPage + 1) + '">' + '<?php echo esc_js( __( '次', 'bf-secret-file-downloader' ) ); ?>' + ' &raquo;</a>';
        }

        html += '</span>';
        return html;
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function getFileIconClass(fileExtension) {
        // Image file
        var imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico'];
        if (imageExtensions.includes(fileExtension)) {
            return 'image-file';
        }

        // Document file
        var documentExtensions = ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'pages'];
        if (documentExtensions.includes(fileExtension)) {
            return 'document-file';
        }

        // Archive file
        var archiveExtensions = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'];
        if (archiveExtensions.includes(fileExtension)) {
            return 'archive-file';
        }

        return '';
    }

    function getParentPath(currentPath) {
        if (!currentPath || currentPath === '') {
            return '';
        }

        // Split path by separator
        var parts = currentPath.split('/').filter(function(part) {
            return part !== '';
        });

        // Remove the last part
        parts.pop();

        // Rebuild the parent path
        return parts.join('/');
    }

    function checkDashicons() {
        console.log('Dashiconsチェック開始');

        // Check if Dashicons font is loaded
        var testElement = $('<span class="dashicons dashicons-folder" style="font-family: dashicons; position: absolute; left: -9999px;"></span>');
        $('body').append(testElement);

        // Check if the font is loaded
        setTimeout(function() {
            var computedStyle = window.getComputedStyle(testElement[0]);
            var fontFamily = computedStyle.getPropertyValue('font-family');

            console.log('フォントファミリー:', fontFamily);

            if (fontFamily.indexOf('dashicons') !== -1) {
                console.log('Dashiconsが利用可能です - Dashiconsを表示します');
                // If Dashicons is loaded, display Dashicons and hide the fallback
                $('.dashicons').css('display', 'inline-block !important').show();
                $('.bf-fallback-icon').hide();

                // Additional style forced application
                $('.bf-directory-icon').css({
                    'display': 'inline-block',
                    'font-family': 'dashicons',
                    'font-size': '20px',
                    'margin-right': '8px',
                    'vertical-align': 'middle'
                });

                $('.bf-file-icon').css({
                    'display': 'inline-block',
                    'font-family': 'dashicons',
                    'font-size': '16px',
                    'margin-right': '8px',
                    'vertical-align': 'middle'
                });

            } else {
                console.log('Dashiconsが利用できません。フォールバックアイコンを使用します');
                $('.dashicons').hide();
                $('.bf-fallback-icon').show();
            }

            testElement.remove();
        }, 1000);
    }

    function uploadFiles(files) {
        var currentPath = $('#current-path').val();

        // Relative path is OK even if it is empty (root directory)
        var maxFileSize = <?php echo esc_js( $max_file_size_mb ?? 10 ); ?> * 1024 * 1024; // MB to bytes
        var uploadedCount = 0;
        var totalFiles = files.length;
        var errors = [];

        $('#upload-progress').show();
        updateUploadProgress(0, '<?php esc_html_e( 'アップロードを開始しています...', 'bf-secret-file-downloader' ); ?>');

        // Upload each file in order
        function uploadNextFile(index) {
            if (index >= totalFiles) {
                // All uploads are complete
                $('#upload-progress').hide();

                if (errors.length > 0) {
                    alert('<?php esc_html_e( '一部のファイルでエラーが発生しました:', 'bf-secret-file-downloader' ); ?>\n' + errors.join('\n'));
                } else {
                    // Show success message
                    showSuccessMessage(uploadedCount + '<?php esc_html_e( '個のファイルをアップロードしました。', 'bf-secret-file-downloader' ); ?>');
                }

                // Update file list
                navigateToDirectory(currentPath, 1);
                return;
            }

            var file = files[index];
            var fileName = file.name;

            // File size check
            if (file.size > maxFileSize) {
                errors.push(fileName + ': <?php esc_html_e( 'ファイルサイズが制限を超えています', 'bf-secret-file-downloader' ); ?>');
                uploadNextFile(index + 1);
                return;
            }

            // Program code file check
            if (isProgramCodeFile(fileName)) {
                errors.push(fileName + ': <?php esc_html_e( 'セキュリティ上の理由により、プログラムコードファイルはアップロードできません', 'bf-secret-file-downloader' ); ?>');
                uploadNextFile(index + 1);
                return;
            }

            // Create FormData
            var formData = new FormData();
            formData.append('action', 'bf_sfd_upload_file');
            formData.append('target_path', currentPath);
            formData.append('file', file);
            formData.append('nonce', '<?php echo esc_js( $nonce ); ?>');

            // Update upload progress
            var progress = Math.round(((index + 1) / totalFiles) * 100);
            updateUploadProgress(progress, '<?php esc_html_e( 'アップロード中:', 'bf-secret-file-downloader' ); ?> ' + fileName);

            // Send AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        uploadedCount++;
                    } else {
                        errors.push(fileName + ': ' + (response.data || '<?php esc_html_e( 'アップロードに失敗しました', 'bf-secret-file-downloader' ); ?>'));
                    }
                    uploadNextFile(index + 1);
                },
                error: function() {
                    errors.push(fileName + ': <?php esc_html_e( '通信エラーが発生しました', 'bf-secret-file-downloader' ); ?>');
                    uploadNextFile(index + 1);
                }
            });
        }

        // Start upload
        uploadNextFile(0);
    }

    function updateUploadProgress(percent, message) {
        $('.upload-progress-fill').css('width', percent + '%');
        $('#upload-status').text(message);
    }

    function showSuccessMessage(message) {
        // Show success message (simplified version)
        $('<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p>' + message + '</p></div>')
            .insertAfter('.bf-secret-file-downloader-header')
            .delay(5000)
            .fadeOut();
    }

    function createDirectory() {
        var currentPath = $('#current-path').val();
        var directoryName = $('#directory-name-input').val().trim();

        // Relative path is OK even if it is empty (root directory)
        if (!directoryName) {
            alert('<?php esc_html_e( 'ディレクトリ名を入力してください。', 'bf-secret-file-downloader' ); ?>');
            $('#directory-name-input').focus();
            return;
        }

        // Directory name validation
        var validPattern = /^[a-zA-Z0-9_\-\.]+$/;
        if (!validPattern.test(directoryName)) {
            alert('<?php esc_html_e( 'ディレクトリ名に使用できない文字が含まれています。英数字、アンダーバー、ハイフン、ドットのみ使用できます。', 'bf-secret-file-downloader' ); ?>');
            $('#directory-name-input').focus();
            return;
        }

        // Check if the directory name starts with a dot
        if (directoryName.charAt(0) === '.') {
            alert('<?php esc_html_e( 'ドットで始まるディレクトリ名は作成できません。', 'bf-secret-file-downloader' ); ?>');
            $('#directory-name-input').focus();
            return;
        }

        // Disable button
        $('#create-directory-submit').prop('disabled', true).text('<?php esc_html_e( '作成中...', 'bf-secret-file-downloader' ); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_create_directory',
                parent_path: currentPath,
                directory_name: directoryName,
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage(response.data.message);
                    $('#create-directory-form').slideUp();
                    $('#directory-name-input').val('');

                    // Update file list
                    navigateToDirectory(currentPath, 1);
                } else {
                    alert(response.data || '<?php esc_html_e( 'ディレクトリの作成に失敗しました。', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e( '通信エラーが発生しました。', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                // Enable button
                $('#create-directory-submit').prop('disabled', false).text('<?php esc_html_e( '作成', 'bf-secret-file-downloader' ); ?>');
            }
        });
    }

    function downloadFile(filePath, fileName) {
        if (!filePath) {
            alert('<?php esc_html_e( 'ファイルパスが無効です。', 'bf-secret-file-downloader' ); ?>');
            return;
        }

        // Message for starting download process
        showSuccessMessage('<?php esc_html_e( 'ダウンロードを準備しています...', 'bf-secret-file-downloader' ); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_download_file',
                file_path: filePath,
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success && response.data.download_url) {
                    // Create a hidden link for download
                    var link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = response.data.filename || fileName;
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    showSuccessMessage('<?php esc_html_e( 'ダウンロードを開始しました。', 'bf-secret-file-downloader' ); ?>');
                } else {
                    alert(response.data || '<?php esc_html_e( 'ダウンロードに失敗しました。', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e( '通信エラーが発生しました。', 'bf-secret-file-downloader' ); ?>');
            }
        });
    }

    function deleteFile(filePath, fileName, fileType) {
        var confirmMessage = fileType === 'directory'
            ? '<?php
                /* translators: %s: directory name */
                echo esc_js( __( 'ディレクトリ「%s」とその中身すべてを削除しますか？この操作は取り消せません。', 'bf-secret-file-downloader' ) ); ?>'
            : '<?php
                /* translators: %s: filename */
                echo esc_js( __( 'ファイル「%s」を削除しますか？この操作は取り消せません。', 'bf-secret-file-downloader' ) ); ?>';

        if (!confirm(confirmMessage.replace('%s', fileName))) {
            return;
        }

        // Update the display during the deletion process
        var deleteLink = $('a[data-file-path="' + filePath + '"].delete-file-link');
        var originalText = deleteLink.text();
        deleteLink.text('<?php esc_html_e( '削除中...', 'bf-secret-file-downloader' ); ?>').prop('disabled', true).css('color', '#999');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_delete_file',
                file_path: filePath,
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage(response.data.message);

                    // Move to the appropriate directory after deletion
                    var currentPath = $('#current-path').val();
                    var targetPath = currentPath;
                    var deletedPath = response.data.deleted_path;

                    // Check if the deleted item is a directory and if the current path is within the deleted directory
                    if (fileType === 'directory') {
                        // Compare the deleted directory path with the current path
                        if (currentPath === deletedPath ||
                            (currentPath && deletedPath && currentPath.indexOf(deletedPath + '/') === 0)) {
                            // If the current path is within the deleted directory or one of its subdirectories,
                            // move to the parent path returned by the server
                            targetPath = response.data.parent_path || '';
                            console.log('削除されたディレクトリ内にいたため、親ディレクトリに移動: ' + targetPath);
                        }
                    }

                    // Update file list
                    navigateToDirectory(targetPath, 1);
                } else {
                    var errorMsg = response.data || '<?php esc_html_e( 'ファイルの削除に失敗しました。', 'bf-secret-file-downloader' ); ?>';
                    console.log('削除処理がサーバー側で失敗:', errorMsg);
                    alert(errorMsg);

                    // Restore the deleted button
                    deleteLink.text(originalText).prop('disabled', false).css('color', '');
                }
            },
            error: function(xhr, status, error) {
                console.log('削除処理で通信エラーが発生:', {xhr: xhr, status: status, error: error});
                alert('<?php esc_html_e( '削除処理で通信エラーが発生しました。再度お試しください。', 'bf-secret-file-downloader' ); ?>');

                // Restore the deleted button when an error occurs
                deleteLink.text(originalText).prop('disabled', false).css('color', '');
            }
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
                echo esc_js( __( '選択された%d個のアイテム（ディレクトリを含む）とその中身すべてを削除しますか？この操作は取り消せません。', 'bf-secret-file-downloader' ) ); ?>';
        } else {
            confirmMessage = '<?php
                /* translators: %d: number of selected items */
                echo esc_js( __( '選択された%d個のアイテムを削除しますか？この操作は取り消せません。', 'bf-secret-file-downloader' ) ); ?>';
        }

        if (!confirm(confirmMessage.replace('%d', filePaths.length))) {
            return;
        }

        // Disable the bulk delete button
        $('#doaction').prop('disabled', true).val('<?php esc_html_e( '削除中...', 'bf-secret-file-downloader' ); ?>');

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
                    showSuccessMessage(response.data.message);

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
                    navigateToDirectory(targetPath, 1);
                } else {
                    var errorMsg = response.data || '<?php esc_html_e( '一括削除処理に失敗しました。', 'bf-secret-file-downloader' ); ?>';
                    console.log('一括削除処理がサーバー側で失敗:', errorMsg);
                    alert(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.log('一括削除処理で通信エラーが発生:', {xhr: xhr, status: status, error: error});
                alert('<?php esc_html_e( '一括削除処理で通信エラーが発生しました。再度お試しください。', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                // Enable button
                $('#doaction').prop('disabled', false).val('<?php esc_attr_e( '適用', 'bf-secret-file-downloader' ); ?>');

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
            $('#bf-password-modal-title').text('<?php esc_html_e( 'ディレクトリパスワード管理', 'bf-secret-file-downloader' ); ?>');
        } else {
            $('#bf-password-modal-title').text('<?php esc_html_e( 'ディレクトリパスワード設定', 'bf-secret-file-downloader' ); ?>');
        }

        // Update the current status display
        var statusIcon = $('.bf-status-icon .dashicons');
        var statusDescription = $('#bf-status-description');

        if (hasPassword) {
            statusIcon.removeClass('dashicons-unlock').addClass('dashicons-lock');
            statusIcon.css('color', '#d63638');
            statusDescription.html('<?php esc_html_e( 'このディレクトリ（', 'bf-secret-file-downloader' ); ?><code>' + currentPathDisplay + '</code><?php esc_html_e( '）は現在パスワード保護されています。', 'bf-secret-file-downloader' ); ?>');
            $('#bf-password-modal-description').text('<?php esc_html_e( '新しいパスワードを入力して変更するか、下の「パスワード保護を解除」ボタンで保護を解除できます。', 'bf-secret-file-downloader' ); ?>');
            $('#bf-remove-password').show();
            $('#bf-show-current-password').show();
        } else {
            statusIcon.removeClass('dashicons-lock').addClass('dashicons-unlock');
            statusIcon.css('color', '#46b450');
            statusDescription.html('<?php esc_html_e( 'このディレクトリ（', 'bf-secret-file-downloader' ); ?><code>' + currentPathDisplay + '</code><?php esc_html_e( '）はパスワード保護されていません。', 'bf-secret-file-downloader' ); ?>');
            $('#bf-password-modal-description').text('<?php esc_html_e( 'このディレクトリ内のファイルをダウンロードする際に要求するパスワードを設定してください。', 'bf-secret-file-downloader' ); ?>');
            $('#bf-remove-password').hide();
            $('#bf-show-current-password').hide();
        }

        // Clear the password field
        $('#bf-directory-password-input').val('').attr('type', 'password');
        $('#bf-password-toggle').text('<?php esc_html_e( '表示', 'bf-secret-file-downloader' ); ?>');

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
            alert('<?php esc_html_e( 'パスワードを入力してください。', 'bf-secret-file-downloader' ); ?>');
            $('#bf-directory-password-input').focus();
            return;
        }

        if (password.length < 4) {
            alert('<?php esc_html_e( 'パスワードは4文字以上で入力してください。', 'bf-secret-file-downloader' ); ?>');
            $('#bf-directory-password-input').focus();
            return;
        }

        // Disable button
        $('#bf-save-password').prop('disabled', true).text('<?php esc_html_e( '保存中...', 'bf-secret-file-downloader' ); ?>');

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
                    showSuccessMessage(response.data.message);
                    closeDirectoryPasswordModal();
                    updatePasswordIndicator(response.data.has_password);
                } else {
                    alert(response.data || '<?php esc_html_e( 'パスワードの設定に失敗しました。', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e( '通信エラーが発生しました。', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                // Enable button
                $('#bf-save-password').prop('disabled', false).text('<?php esc_html_e( '保存', 'bf-secret-file-downloader' ); ?>');
            }
        });
    }

    // Remove the directory password
    function removeDirectoryPassword() {
        if (!confirm('<?php esc_html_e( 'このディレクトリのパスワード保護を解除しますか？', 'bf-secret-file-downloader' ); ?>')) {
            return;
        }

        var currentPath = $('#current-path').val();

        // Disable button
        $('#bf-remove-password').prop('disabled', true).text('<?php esc_html_e( '削除中...', 'bf-secret-file-downloader' ); ?>');

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
                    showSuccessMessage(response.data.message);
                    closeDirectoryPasswordModal();
                    updatePasswordIndicator(response.data.has_password);
                } else {
                    alert(response.data || '<?php esc_html_e( 'パスワードの削除に失敗しました。', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e( '通信エラーが発生しました。', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                // Enable button
                $('#bf-remove-password').prop('disabled', false).text('<?php esc_html_e( 'パスワードを削除', 'bf-secret-file-downloader' ); ?>');
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
                        '<span class="bf-password-status-text"><?php esc_html_e( 'パスワード保護中', 'bf-secret-file-downloader' ); ?></span>' +
                        '</span>');
                }
                passwordButton.html('<span class="dashicons dashicons-admin-network"></span><?php esc_html_e( 'パスワード管理', 'bf-secret-file-downloader' ); ?>');
            } else {
                passwordIndicator.remove();
                passwordButton.html('<span class="dashicons dashicons-admin-network"></span><?php esc_html_e( 'パスワード設定', 'bf-secret-file-downloader' ); ?>');
            }
        }
    }

    // Display the current password
    function showCurrentPassword() {
        var currentPath = $('#current-path').val();

        // Disable button
        $('#bf-show-current-password').prop('disabled', true).text('<?php esc_html_e( '取得中...', 'bf-secret-file-downloader' ); ?>');

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
                    alert('<?php esc_html_e( '現在のパスワード: ', 'bf-secret-file-downloader' ); ?>' + response.data.password);
                } else {
                    alert(response.data || '<?php esc_html_e( 'パスワードの取得に失敗しました。', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e( '通信エラーが発生しました。', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                // Enable button
                $('#bf-show-current-password').prop('disabled', false).text('<?php esc_html_e( '現在のパスワード', 'bf-secret-file-downloader' ); ?>');
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
                showSuccessMessage('<?php esc_html_e( 'URLをクリップボードにコピーしました:', 'bf-secret-file-downloader' ); ?> ' + url);
            }).catch(function(err) {
                console.error('<?php esc_html_e( 'クリップボードへのコピーに失敗しました:', 'bf-secret-file-downloader' ); ?>', err);
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
                showSuccessMessage('<?php esc_html_e( 'ダウンロードURLをクリップボードにコピーしました:', 'bf-secret-file-downloader' ); ?> ' + url);
            } else {
                showUrlPrompt(url);
            }
        } catch (err) {
            console.error('<?php esc_html_e( 'クリップボードへのコピーに失敗しました:', 'bf-secret-file-downloader' ); ?>', err);
            showUrlPrompt(url);
        }

        document.body.removeChild(textArea);
    }

    // Display URL for manual copy
    function showUrlPrompt(url) {
        prompt('<?php esc_html_e( '以下のURLをコピーしてください:', 'bf-secret-file-downloader' ); ?>', url);
    }

    // Open the directory authentication modal
    function openDirectoryAuthModal() {
        var currentPath = $('#current-path').val();
        var currentPathDisplay = $('#current-path-display').text();
        var hasAuth = checkCurrentDirectoryHasAuth();

        // Update the modal title
        if (hasAuth) {
            $('#bf-auth-modal-title').text('<?php esc_html_e( 'ディレクトリ認証設定管理', 'bf-secret-file-downloader' ); ?>');
        } else {
            $('#bf-auth-modal-title').text('<?php esc_html_e( 'ディレクトリ認証設定', 'bf-secret-file-downloader' ); ?>');
        }

        // Update the current status display
        var statusIcon = $('.bf-auth-status-icon .dashicons');
        var statusDescription = $('#bf-auth-status-description');

        if (hasAuth) {
            statusIcon.removeClass('dashicons-unlock').addClass('dashicons-lock');
            statusIcon.css('color', '#0073aa');
            statusDescription.html('<?php esc_html_e( 'このディレクトリ（', 'bf-secret-file-downloader' ); ?><code>' + currentPathDisplay + '</code><?php esc_html_e( '）にはディレクトリ毎の認証設定があります。', 'bf-secret-file-downloader' ); ?>');
            $('#bf-auth-modal-description').text('<?php esc_html_e( 'ディレクトリ毎設定を変更するか、下の「ディレクトリ毎設定削除」ボタンで共通設定に戻すことができます。', 'bf-secret-file-downloader' ); ?>');
            $('#bf-remove-auth').show();
            $('#bf-show-current-auth').show();
        } else {
            statusIcon.removeClass('dashicons-lock').addClass('dashicons-admin-users');
            statusIcon.css('color', '#666');
            statusDescription.html('<?php esc_html_e( 'このディレクトリ（', 'bf-secret-file-downloader' ); ?><code>' + currentPathDisplay + '</code><?php esc_html_e( '）にはディレクトリ毎の認証設定がありません。', 'bf-secret-file-downloader' ); ?>');
            $('#bf-auth-modal-description').text('<?php esc_html_e( '共通設定が適用されています。ディレクトリ毎の認証設定を追加する場合は、下の設定を行ってください。', 'bf-secret-file-downloader' ); ?>');
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

    // Check if the current directory has authentication settings
    function checkCurrentDirectoryHasAuth() {
        var indicator = $('.bf-auth-indicator');
        if (indicator.length === 0) {
            return false;
        }

        // Check the indicator text to determine if there are directory-specific settings
        var statusText = indicator.find('.bf-auth-status-text').text();
        var hasAuthDetails = $('.bf-auth-details').length > 0;

        return statusText.includes('ディレクトリ毎認証設定あり') || hasAuthDetails;
    }

    // Load the directory authentication settings
    function loadDirectoryAuthSettings(currentPath) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bf_sfd_get_directory_auth',
                path: currentPath,
                nonce: '<?php echo esc_js( $nonce ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var authSettings = response.data;

                    // Authentication method settings
                    $('#bf-auth-methods-logged-in').prop('checked', authSettings.auth_methods.includes('logged_in'));
                    $('#bf-auth-methods-simple-auth').prop('checked', authSettings.auth_methods.includes('simple_auth'));

                    // Allowed role settings
                    $('input[name="bf_allowed_roles[]"]').prop('checked', false);
                    if (authSettings.allowed_roles) {
                        authSettings.allowed_roles.forEach(function(role) {
                            $('#bf-allowed-roles-' + role).prop('checked', true);
                        });
                    }

                    // Simple authentication password settings
                    if (authSettings.simple_auth_password) {
                        $('#bf-simple-auth-password').val(authSettings.simple_auth_password);
                    }

                    // Display/hide simple authentication password section
                    if (authSettings.auth_methods.includes('simple_auth')) {
                        $('#bf-simple-auth-password-section').show();
                    } else {
                        $('#bf-simple-auth-password-section').hide();
                    }

                    // Display/hide role selection section
                    if (authSettings.auth_methods.includes('logged_in')) {
                        $('#bf-allowed-roles-section').show();
                    } else {
                        $('#bf-allowed-roles-section').hide();
                    }

                    // Display authentication details
                    displayAuthDetails(authSettings);
                }
            },
            error: function() {
                alert('<?php esc_html_e( '認証設定の取得に失敗しました。', 'bf-secret-file-downloader' ); ?>');
            }
        });
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
            alert('<?php esc_html_e( '認証方法を選択してください。', 'bf-secret-file-downloader' ); ?>');
            return;
        }

        // If simple authentication is selected, a password is required
        if (authMethods.includes('simple_auth') && !simpleAuthPassword) {
            alert('<?php esc_html_e( '簡易認証を選択した場合は、パスワードを設定してください。', 'bf-secret-file-downloader' ); ?>');
            $('#bf-simple-auth-password').focus();
            return;
        }

        // Disable button
        $('#bf-save-auth').prop('disabled', true).text('<?php esc_html_e( '保存中...', 'bf-secret-file-downloader' ); ?>');

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
                    showSuccessMessage(response.data.message);
                    closeDirectoryAuthModal();
                    updateAuthIndicator(response.data.has_auth);

                    // Display authentication details
                    if (response.data.has_auth) {
                        loadDirectoryAuthSettings(currentPath);
                    }
                } else {
                    alert(response.data || '<?php esc_html_e( '認証設定の保存に失敗しました。', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e( '通信エラーが発生しました。', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                $('#bf-save-auth').prop('disabled', false).text('<?php esc_html_e( '保存', 'bf-secret-file-downloader' ); ?>');
            }
        });
    }

    // Remove the directory authentication settings
    function removeDirectoryAuth() {
        if (!confirm('<?php esc_html_e( 'このディレクトリの認証設定を削除しますか？共通設定に戻ります。', 'bf-secret-file-downloader' ); ?>')) {
            return;
        }

        var currentPath = $('#current-path').val();

        // Disable button
        $('#bf-remove-auth').prop('disabled', true).text('<?php esc_html_e( '削除中...', 'bf-secret-file-downloader' ); ?>');

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
                    showSuccessMessage(response.data.message);
                    closeDirectoryAuthModal();
                    updateAuthIndicator(response.data.has_auth);
                } else {
                    alert(response.data || '<?php esc_html_e( '認証設定の削除に失敗しました。', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e( '通信エラーが発生しました。', 'bf-secret-file-downloader' ); ?>');
            },
            complete: function() {
                $('#bf-remove-auth').prop('disabled', false).text('<?php esc_html_e( '認証設定を削除', 'bf-secret-file-downloader' ); ?>');
            }
        });
    }



    // Display authentication details
    function displayAuthDetails(authSettings) {
        var detailsHtml = '<div class="auth-details-list">';

        // Display authentication method
        detailsHtml += '<div class="auth-detail-item"><strong><?php esc_html_e( '認証方法:', 'bf-secret-file-downloader' ); ?></strong> ';
        var authMethods = [];
        if (authSettings.auth_methods.includes('logged_in')) {
            authMethods.push('<?php esc_html_e( 'ログインユーザー', 'bf-secret-file-downloader' ); ?>');
        }
        if (authSettings.auth_methods.includes('simple_auth')) {
            authMethods.push('<?php esc_html_e( '簡易認証', 'bf-secret-file-downloader' ); ?>');
        }
        detailsHtml += authMethods.join(', ') + '</div>';

        // Display allowed roles
        if (authSettings.allowed_roles && authSettings.allowed_roles.length > 0) {
            detailsHtml += '<div class="auth-detail-item"><strong><?php esc_html_e( '許可ロール:', 'bf-secret-file-downloader' ); ?></strong> ';
            var roleLabels = {
                'administrator': '<?php esc_html_e( '管理者', 'bf-secret-file-downloader' ); ?>',
                'editor': '<?php esc_html_e( '編集者', 'bf-secret-file-downloader' ); ?>',
                'author': '<?php esc_html_e( '投稿者', 'bf-secret-file-downloader' ); ?>',
                'contributor': '<?php esc_html_e( '寄稿者', 'bf-secret-file-downloader' ); ?>',
                'subscriber': '<?php esc_html_e( '購読者', 'bf-secret-file-downloader' ); ?>'
            };
            var roles = authSettings.allowed_roles.map(function(role) {
                return roleLabels[role] || role;
            });
            detailsHtml += roles.join(', ') + '</div>';
        }

        // Display simple authentication password
        if (authSettings.auth_methods.includes('simple_auth') && authSettings.simple_auth_password) {
            detailsHtml += '<div class="auth-detail-item"><strong><?php esc_html_e( '簡易認証パスワード:', 'bf-secret-file-downloader' ); ?></strong> ';
            detailsHtml += '••••••••</div>';
        }

        detailsHtml += '</div>';
        $('#auth-details-content').html(detailsHtml);
    }



    // Update the authentication setting indicator
    function updateAuthIndicator(hasAuth) {
        var indicator = $('.bf-auth-indicator');
        var authDetails = $('.bf-auth-details');
        var currentPath = $('#current-path').val();

        if (hasAuth) {
            if (indicator.length === 0) {
                $('.bf-path-info').append('<span class="bf-auth-indicator"><span class="dashicons dashicons-lock"></span><span class="bf-auth-status-text"><?php esc_html_e( 'ディレクトリ毎認証設定あり', 'bf-secret-file-downloader' ); ?></span></span>');
            } else {
                // Update the existing indicator
                indicator.html('<span class="dashicons dashicons-lock"></span><span class="bf-auth-status-text"><?php esc_html_e( 'ディレクトリ毎認証設定あり', 'bf-secret-file-downloader' ); ?></span>');
                indicator.css('color', '');
            }

            // Display authentication details
            if (authDetails.length === 0) {
                $('.bf-path-info').append(getAuthDetailsTemplate());
            }

            // Display authentication details
            loadDirectoryAuthSettings(currentPath);
        } else {
            // If there are no directory-specific settings, display "Common authentication settings applied"
            if (indicator.length === 0) {
                $('.bf-path-info').append('<span class="bf-auth-indicator" style="color: #666;"><span class="dashicons dashicons-admin-users"></span><span class="bf-auth-status-text"><?php esc_html_e( '共通認証設定適用中', 'bf-secret-file-downloader' ); ?></span></span>');
            } else {
                indicator.html('<span class="dashicons dashicons-admin-users"></span><span class="bf-auth-status-text"><?php esc_html_e( '共通認証設定適用中', 'bf-secret-file-downloader' ); ?></span>');
                indicator.css('color', '#666');
            }
            authDetails.remove();
        }
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
        $button.prop('disabled', true).text('<?php esc_html_e( '作成中...', 'bf-secret-file-downloader' ); ?>');
        $status.html('<span style="color: #0073aa;"><?php esc_html_e( '処理中...', 'bf-secret-file-downloader' ); ?></span>');

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
                    $button.prop('disabled', false).text('<?php esc_html_e( 'ディレクトリを作成', 'bf-secret-file-downloader' ); ?>');
                }
            },
            error: function(xhr, status, error) {
                $status.html('<span style="color: #dc3232;"><?php esc_html_e( 'エラーが発生しました', 'bf-secret-file-downloader' ); ?>: ' + error + '</span>');
                $button.prop('disabled', false).text('<?php esc_html_e( 'セキュアディレクトリを自動作成', 'bf-secret-file-downloader' ); ?>');
            }
        });
    });

    // Display initial data (using data passed from wp_localize_script)
    if (typeof bfFileListData !== 'undefined' && bfFileListData.initialData) {
        updateFileList(bfFileListData.initialData);
    }

});
</script>

