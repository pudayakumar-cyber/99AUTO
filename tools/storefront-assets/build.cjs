const fs = require('node:fs');
const path = require('node:path');
const { transformSync } = require('esbuild');
const { minify_sync } = require('terser');

const root = path.resolve(__dirname, '../..');
const sources = [
  'css/styles.min.css',
  'css/responsive.css',
  'css/rtl.css',
  'js/scripts.min.js',
  'js/lazy.min.js',
  'js/lazy.plugin.js',
  'js/myscript.js',
];
const check = process.argv.includes('--check');
let originalBytes = 0;
let generatedBytes = 0;

for (const source of sources) {
  const input = path.join(root, 'assets/front', source);
  const extension = path.extname(input);
  const output = input.replace(/(?:\.min)?\.(css|js)$/, '.optimized.min.$1');
  const original = fs.readFileSync(input, 'utf8');
  const result = extension === '.js' ? minify_sync(original, {
    compress: false,
    mangle: false,
    format: { comments: 'some' },
  }) : transformSync(original, {
    loader: 'css',
    // Preserve names and expressions; only compact formatting. No bundling,
    // module wrapper, dependency reordering, or browser-target transpilation.
    minifyWhitespace: true,
    minifyIdentifiers: false,
    minifySyntax: false,
    legalComments: 'inline',
    charset: 'utf8',
  });
  // Existing invalid CSS is retained, rather than changing its browser behavior
  // as part of a formatting-only release. Fail on any new warning.
  const knownInvalidCss = [
    '..widget-light-skin .widget-title {',
    '-moz-height: calc(100%+100px);',
    '-webkit-height: calc(100%+100px);',
    '-ms-height: calc(100%+100px);',
    'height: calc(100%+100px);',
  ];
  for (const warning of result.warnings || []) {
    const existingCss = source === 'css/styles.min.css' &&
      knownInvalidCss.includes(warning.location?.lineText.trim());
    if (!existingCss) {
      throw new Error(`${source}: ${warning.text}`);
    }
    console.warn(`Preserved existing source warning: ${warning.location.lineText.trim()}`);
  }
  if (check) {
    // Accommodate Git checkouts with Windows line endings.
    const committed = fs.readFileSync(output, 'utf8').replace(/\r\n/g, '\n');
    if (committed !== result.code) throw new Error(`Rebuild stale asset: ${output}`);
  } else {
    fs.writeFileSync(output, result.code);
  }
  originalBytes += Buffer.byteLength(original);
  generatedBytes += Buffer.byteLength(result.code);
  console.log(`${source}: ${Buffer.byteLength(original)} -> ${Buffer.byteLength(result.code)} bytes`);
}
console.log(`${check ? 'Verified' : 'Built'} ${sources.length} assets: ${originalBytes} -> ${generatedBytes} bytes`);
