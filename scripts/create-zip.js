/**
 * Build the distributable ZIP into dist/ (standalone).
 * 配布用 ZIP を dist/ に生成する（単体）。
 *
 * Does not run the release procedure (composer optimization, version
 * consistency checks); it only builds the ZIP via the shared buildZip().
 * Intended for local verification / test distribution.
 * リリース手続き（composer 最適化やバージョン整合チェック）は行わず、共通ロジック
 * buildZip() で ZIP を作るだけ。動作確認・テスト配布用。
 *
 * Usage / 使い方: npm run zip
 */
const { buildZip } = require('./lib/build-zip');

buildZip()
  .then(({ outputPath, version, bytes }) => {
    // ZIP created. / ZIP を作成しました。
    console.log(`✅ ZIP created / ZIP を作成しました: ${outputPath}`);
    console.log(`   version / バージョン: ${version} / size / サイズ: ${(bytes / 1024 / 1024).toFixed(2)} MB`);
  })
  .catch((err) => {
    // Failed to create the ZIP. / ZIP の作成に失敗しました。
    console.error('❌ Failed to create ZIP / ZIP の作成に失敗しました:', err.message);
    process.exit(1);
  });
