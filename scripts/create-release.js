/**
 * Build the release ZIP into dist/.
 * リリース用 ZIP を dist/ に生成する。
 *
 * The ZIP building itself is shared in scripts/lib/build-zip.js (buildZip());
 * this script only handles the release-specific step (a pre-distribution
 * version consistency check).
 * ZIP 生成そのものは scripts/lib/build-zip.js の buildZip() に共通化してあり、
 * このスクリプトはリリース固有の処理（配布前のバージョン整合チェック）だけを担う。
 *
 * There is no runtime composer dependency (the plugin uses its own autoloader),
 * so the previous `composer install --no-dev` vendor rebuild / bundling has been
 * removed.
 * runtime に composer 依存が無い（本体は独自オートローダを使用）ため、以前行っていた
 * `composer install --no-dev` による vendor の作り替え・同梱は廃止した。
 *
 * Usage / 使い方: npm run release
 */
const fs = require('fs');
const path = require('path');
const { buildZip, getVersion, SLUG } = require('./lib/build-zip');

const ROOT = path.resolve(__dirname, '..');

/**
 * Check version consistency before distribution.
 * The source of truth is the plugin main file header (getVersion); verify that
 * readme.txt's Stable tag and package.json's version match it.
 * 配布前のバージョン整合をチェックする。バージョンの正は「プラグイン本体ヘッダ」
 * (getVersion)。readme.txt の Stable tag と package.json の version がそれに一致
 * するか確認する。
 *
 * @param {string} version The authoritative version. 正となるバージョン。
 * @returns {{errors: string[], warnings: string[]}}
 */
function checkVersionConsistency(version) {
  const errors = [];
  const warnings = [];

  // readme.txt Stable tag: significant for WordPress.org distribution, so a mismatch is an error.
  // readme.txt の Stable tag（WordPress.org 配布で意味を持つため不一致はエラー）。
  const readme = fs.readFileSync(path.join(ROOT, 'readme.txt'), 'utf8');
  const stable = readme.match(/^Stable tag:\s*(.+)$/m);
  const stableTag = stable ? stable[1].trim() : null;
  if (stableTag !== version) {
    errors.push(`readme.txt Stable tag (${stableTag ?? 'none'}) does not match plugin header (${version}) / readme.txt の Stable tag がプラグインヘッダと一致しません`);
  }

  // package.json version: not bundled into the distributable, so a mismatch is a warning only.
  // package.json の version（配布物には含めないため不一致は警告のみ）。
  const pkg = JSON.parse(fs.readFileSync(path.join(ROOT, 'package.json'), 'utf8'));
  if (pkg.version !== version) {
    warnings.push(`package.json version (${pkg.version}) does not match plugin header (${version}) / package.json の version がプラグインヘッダと一致しません`);
  }

  return { errors, warnings };
}

async function main() {
  const version = getVersion();
  // Packaging <slug> <version> for release.
  // <slug> <version> をリリース用にパッケージします。
  console.log(`📦 Packaging ${SLUG} ${version} for release / リリース用にパッケージします`);

  const { errors, warnings } = checkVersionConsistency(version);
  warnings.forEach((w) => console.warn(`⚠️  ${w}`));
  if (errors.length > 0) {
    // Version consistency error. / バージョン整合エラー。
    console.error('❌ Version consistency error / バージョン整合エラー:');
    errors.forEach((e) => console.error(`  - ${e}`));
    console.error('   Align the versions and re-run. / バージョンを揃えてから再実行してください。');
    process.exit(1);
  }

  const { outputPath, bytes } = await buildZip();
  // Release ZIP created. / リリース ZIP を作成しました。
  console.log(`✅ Release ZIP created / リリース ZIP を作成しました: ${outputPath}`);
  console.log(`   size / サイズ: ${(bytes / 1024 / 1024).toFixed(2)} MB`);
}

main().catch((err) => {
  // Release failed. / リリースに失敗しました。
  console.error('❌ Release failed / リリースに失敗しました:', err.message);
  process.exit(1);
});
