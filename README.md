# BF Secret File Downloader

このプラグインは、`@wordpress/env`を使用したDockerベースのPHPUnitテスト環境が設定されています。

## 前提条件

- Node.js 16以上
- npm
- Docker
- Composer

## テスト環境のセットアップ

### 1. 依存関係のインストール

```bash
# Composer依存関係のインストール
composer install

# npm依存関係のインストール
npm install
```

### 2. WordPress環境の起動

```bash
# wp-env環境を起動
npm run env:start
```

初回起動時は、WordPressとMySQLのDockerイメージをダウンロードするため時間がかかります。

### 3. テストの実行

```bash
# すべてのテストを実行
npm run phpunit

# 特定のテストファイルを実行
npm run wp-env run tests-cli --env-cwd=wp-content/plugins/bf-secret-file-downloader ./vendor/bin/phpunit tests/test-admin.php
```

## 利用可能なコマンド

### 環境管理

```bash
# 環境を起動
npm run env:start

# 環境を停止
npm run env:stop

# 環境を完全に削除（データも削除されます）
npm run env:destroy
```

### テスト

```bash
# テストを実行
npm run phpunit

# ウォッチモード（ファイル変更時に自動実行）
npm run phpunit:watch
```

### WordPress環境へのアクセス

- **開発環境**: http://localhost:9999
  - ユーザー名: `admin`
  - パスワード: `password`

- **テスト環境**: http://localhost:9998
  - ユーザー名: `admin`
  - パスワード: `password`

## ディレクトリ構造

```
bf-secret-file-downloader/
├── .wp-env.json          # wp-env設定ファイル
├── package.json          # npm設定とスクリプト
├── composer.json         # Composer設定
├── phpunit.xml.dist      # PHPUnit設定
├── tests/                # テストファイル
│   ├── bootstrap.php     # テストブートストラップ
│   ├── test-admin.php    # Adminクラスのテスト
│   └── test-sample.php   # サンプルテスト
├── inc/                  # プラグインソースコード
└── vendor/               # Composer依存関係
```

## テストの追加

新しいテストファイルは `tests/` ディレクトリに `test-` プレフィックスで作成してください。

例：
```php
<?php
namespace Breadfish\SecretFileDownloader\Tests;

use WP_UnitTestCase;

class MyNewTest extends WP_UnitTestCase {
    public function test_something() {
        $this->assertTrue( true );
    }
}
```

## トラブルシューティング

### ポートが使用中の場合

`.wp-env.json` ファイルでポート番号を変更できます：

```json
{
  "port": 8080,
  "testsPort": 8081
}
```

### 環境をリセットしたい場合

```bash
npm run env:destroy
npm run env:start
```

### テストが失敗する場合

1. 環境が正しく起動しているか確認
2. プラグインが正しくロードされているか確認
3. 依存関係が最新か確認

```bash
composer update
npm update
```