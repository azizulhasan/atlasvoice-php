#!/usr/bin/env node
/**
 * One-time port of the SDK tests that were written inside the WordPress
 * plugin (tests/phpunit/tests/Cloud/*) to plain PHPUnit for this package.
 *
 *   node bin/port-plugin-tests.js <plugin dir> <git commit>
 *
 * Kept in the repo so the port can be repeated and reviewed. The test that
 * checks the WordPress transport (WP_Http_Transport) is left out: that
 * adapter lives in the plugin, and so does its test.
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const [pluginDir, commit] = process.argv.slice(2);
if (!pluginDir || !commit) {
    console.error('usage: node bin/port-plugin-tests.js <plugin dir> <git commit>');
    process.exit(1);
}

const FILES = ['SdkClientTest', 'CircuitBreakerTest', 'SdkDownloadTest', 'SdkJobsTest'];
const out = path.join(__dirname, '..', 'tests');

/** Remove one public test method (its doc comment included) by name. */
function dropMethod(src, name) {
    const start = src.indexOf(`public function ${name}(`);
    if (start < 0) throw new Error(`method ${name} not found`);
    const docStart = src.lastIndexOf('/**', start);
    const between = src.slice(docStart, start);
    const from = docStart >= 0 && !between.includes('}') ? docStart : start;
    let depth = 0;
    let i = src.indexOf('{', start);
    for (; i < src.length; i += 1) {
        if (src[i] === '{') depth += 1;
        if (src[i] === '}' && --depth === 0) break;
    }
    return src.slice(0, from).replace(/[ \t]*$/, '') + src.slice(i + 1).replace(/^\n/, '');
}

for (const name of FILES) {
    let src = execFileSync('git', ['-C', pluginDir, 'show', `${commit}:tests/phpunit/tests/Cloud/${name}.php`], { encoding: 'utf8' });
    src = src.replace(/\r\n/g, '\n');
    src = src.replace(/@package TTA/g, '@package AtlasVoice\\Sdk');
    src = src.replace(/extends WP_UnitTestCase/g, 'extends \\PHPUnit\\Framework\\TestCase');
    src = src.replace(/public function set_up\(\)/g, 'protected function setUp(): void');
    src = src.replace(/public function tear_down\(\)/g, 'protected function tearDown(): void');
    src = src.replace(/parent::set_up\(\)/g, 'parent::setUp()');
    src = src.replace(/parent::tear_down\(\)/g, 'parent::tearDown()');
    src = src.replace(/wp_json_encode\(/g, 'json_encode(');
    // The plugin kept the SDK in libs/AtlasVoice/Sdk; the package keeps it in src/.
    src = src.replace(/dirname\( __DIR__, 4 \) \. '\/libs\/AtlasVoice\/Sdk'/g, "dirname( __DIR__ ) . '/src'");
    if (name === 'SdkDownloadTest') {
        src = dropMethod(src, 'test_wordpress_is_told_not_to_follow_the_redirect');
        src = src.replace(/use TTA\\Cloud\\WP_Http_Transport;\n/, '');
        src = src.replace(/ \* @covers \\TTA\\Cloud\\WP_Http_Transport\n/, '');
    }
    // The "SDK calls no WordPress function" test lists those names in a pattern; that line is data.
    const code = src.split('\n').filter((l) => !l.includes("'/^(wp_|get_option")).join('\n');
    const left = code.match(/\b(wp_[a-z_]+|WP_[A-Za-z_]+|add_filter|remove_filter|TTA\\)/g);
    if (left) throw new Error(`${name}: WordPress left in the port: ${[...new Set(left)].join(', ')}`);
    fs.writeFileSync(path.join(out, `${name}.php`), src);
    console.log(`ported ${name}`);
}
