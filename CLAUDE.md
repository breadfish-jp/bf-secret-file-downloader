# BF Secret File Downloader WordPress Plugin

## Project Overview
BASIC認証もしくは非公開エリアに置かれたディレクトリに配置されたファイルを管理するWordPressプラグイン。ファイル管理、ディレクトリ管理、ダウンロード機能、ダウンロード用ボタンブロック機能を提供します。

- **Version**: 1.0.0
- **Author**: Breadfish
- **License**: GPL-2.0-or-later
- **PHP Requirements**: >=7.4
- **WordPress Requirements**: >=5.0, tested up to 6.4

## Development Commands

### Frontend Build
```bash
npm run build      # Build for production
npm run start      # Development mode with watch
```

### WordPress Environment
```bash
npm run env:start   # Start wp-env
npm run env:stop    # Stop wp-env
npm run env:destroy # Destroy wp-env
```

### Testing
```bash
npm run phpunit          # Run PHPUnit tests
npm run phpunit:watch    # Run PHPUnit tests in watch mode
composer test           # Run PHPUnit tests via composer
composer test:coverage  # Run tests with coverage report
```

### Code Quality
```bash
phpstan analyse     # Run PHPStan static analysis
```

## Project Structure

### PHP Classes (inc/)
- `Admin.php` - Main admin functionality
- `Admin/FileListPage.php` - File list admin page
- `Admin/SettingsPage.php` - Settings admin page
- `Block.php` - Gutenberg block functionality
- `FrontEnd.php` - Frontend functionality
- `ViewRenderer.php` - View rendering utility

### Frontend Assets (src/)
- `block.json` - Block configuration
- `edit.js` - Block editor component
- `index.js` - Block registration
- `render.php` - Block server-side rendering
- `editor.css` - Editor styles

### Views (inc/views/)
- `Admin/file-list.php` - File list template
- `Admin/settings.php` - Settings page template
- `FrontEnd/downloader.php` - Download template

### Tests (tests/)
- PHPUnit test files covering admin, frontend, and integration functionality
- Bootstrap configuration in `bootstrap.php`
- JUnit XML results in `results/` directory

## Development Notes

- Uses PSR-4 autoloading with namespace `Breadfish\SecretFileDownloader`
- Follows WordPress coding standards
- Includes comprehensive PHPUnit test suite
- Uses webpack for frontend asset building
- Supports WordPress environment with wp-env

# 言語設定 / Language Settings

このプロジェクトは日本語プロジェクトです。

**重要な指示:**
- すべてのレスポンスは日本語で行ってください
- コメントやドキュメントも日本語で記述してください  
- エラーメッセージや説明も日本語で提供してください
- コードの変更や説明は日本語で行ってください

This is a Japanese project.
- Always respond in Japanese
- Write comments and documentation in Japanese
- Provide error messages and explanations in Japanese
- Explain code changes and descriptions in Japanese

## Debug Memories

- fuzzyがついているのが無視されているようですが