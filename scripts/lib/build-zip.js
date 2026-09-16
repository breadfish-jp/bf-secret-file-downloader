/**
 * Shared logic for building the distributable ZIP.
 * 配布用 ZIP を生成する共通ロジック。
 *
 * Called from both the standalone ZIP build (scripts/create-zip.js) and the
 * release flow (scripts/create-release.js). The set of bundled files and the
 * source of the version string are defined here in one place so the two never
 * produce a divergent ZIP.
 * 単体の ZIP 生成（scripts/create-zip.js）とリリース（scripts/create-release.js）
 * の双方から呼び出す。「同梱するファイル」と「バージョンの取得元」をここ 1 か所に
 * 集約し、両者の zip 内容が食い違わないようにする。
 */
const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

// Repository root (this file lives in scripts/lib/).
// リポジトリルート（このファイルは scripts/lib/ にある）。
const ROOT = path.resolve(__dirname, '..', '..');

// Plugin slug, used as the top-level directory / file name inside the ZIP.
// プラグインスラッグ（ZIP 内のトップディレクトリ名 / ファイル名に使用）。
const SLUG = 'bf-secret-file-downloader';

/**
 * Files / directories bundled into the ZIP (single source of truth).
 * Anything not listed here is excluded. Development-only files (vendor / tests /
 * composer.json / node_modules, etc.) are unnecessary at runtime and omitted.
 * ZIP に同梱するファイル / ディレクトリ（唯一の定義）。ここに無いものは配布物に
 * 入らない。開発用ファイル（vendor / tests / composer.json / node_modules 等）は
 * runtime に不要なので含めない。
 */
const INCLUDE = [
  `${SLUG}.php`,
  'inc/',
  'assets/',
  'languages/',
  'readme.txt',
  'LICENSE',
];

/**
 * Single source of the version string: the `Version:` header of the main
 * plugin file.
 * バージョンの唯一の取得元：プラグイン本体ファイルのヘッダ `Version:`。
 *
 * @returns {string} e.g. "1.0.2" / 例: "1.0.2"
 */
function getVersion() {
  const mainFile = path.join(ROOT, `${SLUG}.php`);
  const source = fs.readFileSync(mainFile, 'utf8');
  const matched = source.match(/^\s*\*\s*Version:\s*(.+)$/m);
  if (!matched) {
    // Could not read the Version header from the main plugin file.
    // プラグイン本体ファイルのヘッダから Version を取得できなかった。
    throw new Error(`Failed to read Version from ${SLUG}.php / ${SLUG}.php のヘッダから Version を取得できませんでした`);
  }
  return matched[1].trim();
}

/**
 * Build the distributable ZIP.
 * 配布用 ZIP を生成する。
 *
 * @param {Object} [options]
 * @param {string} [options.outDir='dist'] Output directory (relative to ROOT). 出力先ディレクトリ（ROOT からの相対）。
 * @returns {Promise<{outputPath: string, version: string, bytes: number}>}
 */
function buildZip({ outDir = 'dist' } = {}) {
  return new Promise((resolve, reject) => {
    let version;
    try {
      version = getVersion();
    } catch (err) {
      reject(err);
      return;
    }

    const outDirAbs = path.join(ROOT, outDir);
    fs.mkdirSync(outDirAbs, { recursive: true });

    const outputPath = path.join(outDirAbs, `${SLUG}-${version}.zip`);
    // Rebuild the ZIP if one with the same name already exists.
    // 既存の同名 ZIP は作り直す。
    if (fs.existsSync(outputPath)) {
      fs.rmSync(outputPath);
    }

    const output = fs.createWriteStream(outputPath);
    const archive = archiver('zip', { zlib: { level: 9 } });

    output.on('close', () => {
      resolve({ outputPath, version, bytes: archive.pointer() });
    });
    archive.on('warning', (err) => {
      // Non-existent includes are skipped beforehand, so only warnings are expected here.
      // 存在しない include は事前にスキップ済みなので、ここは警告のみ想定。
      if (err.code === 'ENOENT') {
        return;
      }
      reject(err);
    });
    archive.on('error', reject);

    archive.pipe(output);

    INCLUDE.forEach((item) => {
      const abs = path.join(ROOT, item);
      if (!fs.existsSync(abs)) {
        return;
      }
      // Place entries under SLUG/ (normalizing any trailing slash).
      // ZIP 内は SLUG/ 配下に配置（末尾スラッシュは正規化）。
      const entryName = `${SLUG}/${item.replace(/\/$/, '')}`;
      if (fs.statSync(abs).isDirectory()) {
        archive.directory(abs, entryName);
      } else {
        archive.file(abs, { name: entryName });
      }
    });

    archive.finalize();
  });
}

module.exports = { buildZip, getVersion, SLUG, INCLUDE };
