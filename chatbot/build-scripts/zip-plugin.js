const fs = require('fs');
const archiver = require('archiver');
const path = require('path');
const { execSync } = require('child_process');
const minifyAssets = require('./minify-assets');

const zipFileName = 'chatbot.zip';

// Site Insight (includes/insights/) ships compiled JS/CSS only. Its JSX sources in
// assets/src are built into assets/build by its own esbuild script before zipping.
const INSIGHTS_PATH = path.join('includes', 'insights');
const INSIGHTS_BUILD_FILES = ['admin.js', 'admin.css', 'loader.js', 'recorder.js', 'collector.js'];

function buildInsights() {
  console.log('Building Site Insight assets...');
  const opts = { cwd: INSIGHTS_PATH, stdio: 'inherit' };
  // No lockfile in the module; don't create one in the repo.
  execSync('npm install --no-audit --no-fund --no-package-lock', opts);
  execSync('npm run build', opts);

  const missing = INSIGHTS_BUILD_FILES.filter(file => !fs.existsSync(path.join(INSIGHTS_PATH, 'assets', 'build', file)));
  if (missing.length) {
    console.error(`Site Insight build is missing: ${missing.join(', ')}. Zip not created.`);
    process.exit(1);
  }
}

buildInsights();

// SEO & AI Search (includes/seo-ai-search) and WPBot Core (includes/wpbot-core) are synced
// copies of their own repositories: refuse to zip hand-edited copies or any Pro module.
try {
  execSync('node build-scripts/check-seo-ai-search-sync.js', { cwd: path.resolve(__dirname, '..'), stdio: 'inherit' });
} catch (e) {
  console.error('SEO & AI Search modules differ from their synced versions. Run: npm run sync:seo-ai-search');
  process.exit(1);
}

// AI Writer (includes/ai-writer, the core module only) is a synced copy too.
try {
  execSync('node build-scripts/check-ai-writer-sync.js', { cwd: path.resolve(__dirname, '..'), stdio: 'inherit' });
} catch (e) {
  console.error('The AI Writer module differs from its synced version. Run: npm run sync:ai-writer -- --core <ref>');
  process.exit(1);
}

// WPBot Asset Cache core (includes/asset-optimizer) is a synced copy too.
try {
  execSync('node build-scripts/check-asset-optimizer-sync.js', { cwd: path.resolve(__dirname, '..'), stdio: 'inherit' });
} catch (e) {
  console.error('The Asset Cache module differs from its synced version. Run: npm run sync:asset-optimizer -- --core <ref>');
  process.exit(1);
}

// Delete existing ZIP files
const zipFilesToDelete = [
  'chatbot.zip'
];

zipFilesToDelete.forEach(zipFile => {
  if (fs.existsSync(zipFile)) {
    fs.unlinkSync(zipFile);
    console.log(`Deleted existing ${zipFile}`);
  }
});

const output = fs.createWriteStream(zipFileName);
const archive = archiver('zip', { zlib: { level: 9 } });

output.on('close', () => {
  minifyAssets.clean();
  console.log(`Created ${zipFileName} (${archive.pointer()} bytes)`);
});

archive.on('error', err => { throw err; });

archive.pipe(output);

const excludePatterns = [
  'node_modules',
  '.git',
  '.gitignore',
  'webpack.config.js',
  'package.json',
  'package-lock.json',
  'phpcs.xml',
  'phpcs-out.txt',
  'build-scripts',
  'phpcs-xml.txt',
  'composer.json',
  'composer.lock',
  '.DS_Store',
  // Sync manifests of includes/wpbot-core and includes/seo-ai-search (hidden files fail Plugin Check).
  '.sync.json',
  // The archive being written; the walk runs after minifying, so it already exists.
  '/' + zipFileName,
  'webpack.marketing.config.js',
  '/includes/admin/marketing-src',
  '/addons/automator/src',
  '/addons/automator/build-plugin.js',
  '/addons/automator/build-plugin.sh',
  '/addons/automator/postcss.config.js',
  '/addons/automator/scratch',
  '/includes/insights/tests',
  '/includes/insights/assets/src',
  '/includes/insights/build.mjs',
  'docs',
  'docs/crm-rewrite/wpbot-feature-dev-SKILL.md',
  '.idea',
  // Unused: only referenced from commented-out code or each other.
  '/inc',
  '/includes/openai',
  '/includes/language center.json',
  '/includes/integration/openai/js/openai_settings.js',
  '/js/openai_settings1.js'
];

// WPBot Insights (includes/insights/) is a shared module whose strings use the
// placeholder text domain "qcin-textdomain". The zip gets WPBot's own domain.
const INSIGHTS_DIR = path.join('includes', 'insights') + path.sep;
const INSIGHTS_TEXT_DOMAIN = { from: 'qcin-textdomain', to: 'chatbot' };

function shouldExclude(filepath) {
  return excludePatterns.some(pattern => {
    if (pattern.startsWith('/')) {
      const cleanPattern = pattern.slice(1);
      return filepath === cleanPattern || filepath.startsWith(cleanPattern + path.sep);
    }
    return filepath.includes(pattern);
  });
}

function addFiles(dir, archiveRoot = '') {
  const items = fs.readdirSync(dir);
  for (const item of items) {
    const fullPath = path.join(dir, item);
    const relativePath = path.relative(process.cwd(), fullPath);
    if (shouldExclude(relativePath)) continue;

    const stat = fs.statSync(fullPath);
    const archivePath = path.join('chatbot', archiveRoot, item);
    if (stat.isDirectory()) {
      addFiles(fullPath, path.join(archiveRoot, item));
    } else if (relativePath.startsWith(INSIGHTS_DIR) && /\.(php|js|jsx)$/.test(item)) {
      const source = fs.readFileSync(fullPath, 'utf8');
      archive.append(source.split(INSIGHTS_TEXT_DOMAIN.from).join(INSIGHTS_TEXT_DOMAIN.to), { name: archivePath, mode: stat.mode });
    } else {
      archive.file(fullPath, { name: archivePath });
    }
  }
}

// The .min files only exist while zipping; the working tree keeps sources only.
minifyAssets.build().then(() => {
  addFiles(process.cwd());
  archive.finalize();
}).catch(err => {
  minifyAssets.clean();
  console.error(err);
  process.exit(1);
});
