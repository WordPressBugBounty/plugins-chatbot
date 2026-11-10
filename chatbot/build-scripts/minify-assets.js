// Writes .min.js / .min.css copies of the front-end assets next to their sources.
// PHP serves them through qcld_wpbot_min_asset() unless SCRIPT_DEBUG is on.
// Usage: node build-scripts/minify-assets.js [--clean]
const fs = require('fs');
const path = require('path');
const { minify } = require('terser');
const postcss = require('postcss');
const cssnano = require('cssnano');

const ROOT = path.join(__dirname, '..');

function sources() {
  const files = [
    'js/qcld-wp-chatbot-plugin.js',
    'js/qcld-wp-chatbot-front.js',
    'css/common-style.css',
    'css/magnific-popup.css',
  ];
  for (const template of fs.readdirSync(path.join(ROOT, 'templates'))) {
    for (const name of ['style.css', 'shortcode.css']) {
      const file = path.join('templates', template, name);
      if (fs.existsSync(path.join(ROOT, file))) {
        files.push(file);
      }
    }
  }
  return files;
}

function minPath(file) {
  return file.replace(/\.(js|css)$/, '.min.$1');
}

async function build() {
  const written = [];
  for (const file of sources()) {
    const code = fs.readFileSync(path.join(ROOT, file), 'utf8');
    let out;
    if (file.endsWith('.js')) {
      out = (await minify(code, { compress: true, mangle: true })).code;
    } else {
      out = (await postcss([cssnano({ preset: 'default' })]).process(code, { from: undefined })).css;
    }
    fs.writeFileSync(path.join(ROOT, minPath(file)), out);
    written.push(minPath(file));
    console.log(`${minPath(file)}: ${code.length} -> ${out.length} bytes`);
  }
  return written;
}

function clean() {
  for (const file of sources()) {
    const target = path.join(ROOT, minPath(file));
    if (fs.existsSync(target)) {
      fs.unlinkSync(target);
    }
  }
}

module.exports = { build, clean };

if (require.main === module) {
  if (process.argv.includes('--clean')) {
    clean();
  } else {
    build().catch(err => {
      console.error(err);
      process.exit(1);
    });
  }
}
