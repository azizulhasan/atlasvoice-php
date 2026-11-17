#!/usr/bin/env node
/**
 * Puts this package's client into a WordPress plugin, which bundles it in
 * libs/AtlasVoice/Sdk (WordPress plugins cannot run Composer on the site).
 *
 *   node bin/copy-to-plugin.js <plugin dir>           copy, report what changed
 *   node bin/copy-to-plugin.js <plugin dir> --check   change nothing; exit 1 if the plugin differs
 *
 * Adapters/ is never copied: Curl_Transport calls cURL directly, which the
 * WordPress plugin directory does not allow (plugins use the WordPress HTTP
 * API; the plugin has its own WP_Http_Transport). A file in the plugin's
 * folder that the package does not have is reported, never deleted.
 */
const fs = require('fs');
const path = require('path');

const args = process.argv.slice(2);
const check = args.includes('--check');
const pluginDir = args.find((a) => !a.startsWith('--'));
if (!pluginDir) {
    console.error('usage: node bin/copy-to-plugin.js <plugin dir> [--check]');
    process.exit(2);
}

const src = path.join(__dirname, '..', 'src');
const dest = path.join(pluginDir, 'libs', 'AtlasVoice', 'Sdk');
const SKIP = new Set(['Adapters']);

/** Every PHP file under dir, as paths relative to it, with / separators. */
function files(dir, base = dir) {
    if (!fs.existsSync(dir)) return [];
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
        const full = path.join(dir, e.name);
        if (e.isDirectory()) return SKIP.has(e.name) && dir === base ? [] : files(full, base);
        return e.name.endsWith('.php') ? [path.relative(base, full).split(path.sep).join('/')] : [];
    });
}

// Line endings are not a difference (git may check files out with CRLF).
const read = (f) => fs.readFileSync(f, 'utf8').replace(/\r\n/g, '\n');

const wanted = files(src);
const present = new Set(files(dest));
const changed = [];
for (const rel of wanted) {
    const from = path.join(src, rel);
    const to = path.join(dest, rel);
    if (present.has(rel) && read(from) === read(to)) continue;
    changed.push(present.has(rel) ? `changed  ${rel}` : `added    ${rel}`);
    if (!check) {
        fs.mkdirSync(path.dirname(to), { recursive: true });
        fs.writeFileSync(to, read(from));
    }
}
const extra = [...present].filter((rel) => !wanted.includes(rel));

const version = (read(path.join(src, 'Client.php')).match(/const VERSION = '([^']+)'/) || [])[1];
console.log(`AtlasVoice SDK ${version} ${check ? 'compared with' : 'copied to'} ${dest}`);
changed.forEach((c) => console.log(`  ${c}`));
extra.forEach((rel) => console.log(`  only in plugin (left alone): ${rel}`));
if (!changed.length) console.log('  the plugin already has exactly this version');
if (check && changed.length) process.exit(1);
