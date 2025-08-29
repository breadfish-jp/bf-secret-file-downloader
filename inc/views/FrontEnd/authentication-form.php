<?php
/**
 * 認証フォームビューファイル
 *
 * @package BfSecretFileDownloader
 */

// セキュリティチェック：直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( __('Authentication required', 'bf-secret-file-downloader' ) ); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .auth-container { max-width: 400px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .auth-title { text-align: center; margin-bottom: 30px; color: #333; }
        .auth-description { text-align: center; margin-bottom: 20px; color: #666; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .submit-btn { width: 100%; padding: 12px; background-color: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .submit-btn:hover { background-color: #005a87; }
        .error-message { color: #d63638; margin-top: 10px; text-align: center; }
        .login-link { text-align: center; margin-top: 15px; }
        .login-link a { color: #0073aa; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="auth-container">
        <h2 class="auth-title"><?php echo esc_html( __('Authentication required', 'bf-secret-file-downloader' ) ); ?></h2>
        <p class="auth-description"><?php echo esc_html( __('Authentication is required to access this file.', 'bf-secret-file-downloader' ) ); ?></p>

        <?php if ( in_array( 'simple_auth', $auth_methods ) ): ?>
        <form method="post" action="<?php echo esc_url( $current_url ); ?>">
            <?php wp_nonce_field( 'bf_sfd_auth', '_wpnonce' ); ?>
            <div class="form-group">
                <label for="simple_auth_password"><?php echo esc_html( __('Simple authentication password', 'bf-secret-file-downloader' ) ); ?></label>
                <input type="password" id="simple_auth_password" name="simple_auth_password" required>
            </div>
            <button type="submit" class="submit-btn"><?php echo esc_html( __('Authenticate', 'bf-secret-file-downloader' ) ); ?></button>
        </form>
        <?php endif; ?>

        <?php if ( in_array( 'logged_in', $auth_methods ) ): ?>
        <div class="login-link">
            <a href="<?php echo esc_url( wp_login_url( $current_url ) ); ?>"><?php echo esc_html( __('Login to access', 'bf-secret-file-downloader' ) ); ?></a>
        </div>
        <?php endif; ?>

        <?php if ( $show_error ): ?>
            <div class="error-message"><?php echo esc_html( __('Password is incorrect.', 'bf-secret-file-downloader' ) ); ?></div>
        <?php endif; ?>
    </div>
</body>
</html>