const fs = require('fs');
const path = require('path');
const archiver = require('archiver');
const { execSync } = require('child_process');

// リリースに含めるファイルとディレクトリ
const includeFiles = [
  'bf-secret-file-downloader.php',
  'composer.json',
  'inc/',
  'languages/',
  'assets/',
  'vendor/',
  'readme.txt',
  'LICENSE'
];

function createRelease() {
  const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
  const version = packageJson.version;
  const pluginName = packageJson.name;

  console.log('📦 開発用依存関係を除外してvendorディレクトリを再構築中...');

  try {
    // 開発用依存関係を除外してvendorディレクトリを再構築
    execSync('composer install --no-dev --optimize-autoloader', { stdio: 'inherit' });
    console.log('✅ vendorディレクトリの最適化が完了しました');
  } catch (error) {
    console.error('❌ Composerの実行中にエラーが発生しました:', error.message);
    process.exit(1);
  }

  // distディレクトリを作成
  if (!fs.existsSync('dist')) {
    fs.mkdirSync('dist');
  }

  const outputPath = `dist/${pluginName}-${version}.zip`;
  const output = fs.createWriteStream(outputPath);
  const archive = archiver('zip', {
    zlib: { level: 9 } // 最高圧縮レベル
  });

  output.on('close', () => {
    console.log(`✅ リリースZIPが作成されました: ${outputPath}`);
    console.log(`📦 ファイルサイズ: ${(archive.pointer() / 1024 / 1024).toFixed(2)} MB`);

    // 後処理: 開発用依存関係を含むcomposer installを実行
    console.log('🔄 開発用依存関係を含むcomposer installを実行中...');
    try {
      execSync('composer install', { stdio: 'inherit' });
      console.log('✅ 開発用依存関係の復元が完了しました');
    } catch (error) {
      console.error('❌ 開発用依存関係の復元中にエラーが発生しました:', error.message);
      process.exit(1);
    }
  });

  archive.on('error', (err) => {
    throw err;
  });

  archive.pipe(output);

  // 含めるファイルとディレクトリを明示的に追加
  includeFiles.forEach(file => {
    const filePath = path.resolve(file);
    if (fs.existsSync(filePath)) {
      if (fs.statSync(filePath).isDirectory()) {
        archive.directory(file, `bf-secret-file-downloader/${file}`);
      } else {
        archive.file(file, { name: `bf-secret-file-downloader/${file}` });
      }
    }
  });

  archive.finalize();
}

// スクリプト実行
createRelease();