// SPDX-License-Identifier: AGPL-3.0-or-later

import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, utimesSync, writeFileSync } from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { test } from 'node:test'

const repository = path.resolve(import.meta.dirname, '../..')
const { scripts } = JSON.parse(readFileSync(path.join(repository, 'package.json'), 'utf8'))
const sharedNpmMakefile = path.join(repository, 'dev-scripts/lib/makefile/npm.mk')
const npm = spawnSync('which', ['npm'], { encoding: 'utf8' }).stdout.trim()

function write(root, filename, contents, mode) {
    const destination = path.join(root, filename)
    mkdirSync(path.dirname(destination), { recursive: true })
    writeFileSync(destination, contents, { mode })
}

function fixture(t, flavour, fail = false) {
    const root = mkdtempSync(path.join(os.tmpdir(), 'npm-patches-test-'))
    t.after(() => rmSync(root, { recursive: true, force: true }))
    write(root, 'package.json', JSON.stringify({ scripts }))
    write(root, 'package-lock.json', '{}')
    utimesSync(path.join(root, 'package-lock.json'), 0, 0)
    write(root, 'build-flavour', flavour)
    write(root, 'vite.config.ts', '')
    write(root, 'eslint.config.mjs', '')
    write(root, 'node_modules/calendarObjects.js', "import logger from '@/utils/logger.js'\n")
    write(root, 'node_modules/.bin/patch-package', `#!/bin/sh
echo patch >> events
test "$1" = --error-on-fail || exit 2
${fail ? 'exit 1' : `printf "import logger from '../../src/utils/logger.js'\\n" > node_modules/calendarObjects.js`}
`, 0o755)
    write(root, 'node_modules/.bin/vite', `#!/bin/sh
grep -q "@/utils/logger.js" node_modules/calendarObjects.js && exit 1
echo "vite $*" >> events
mkdir -p js
echo '{}' > js/asset-meta.json
`, 0o755)
    write(root, 'npm', `#!/bin/sh
case "$1" in
install|update)
    echo "$1" >> events
    printf "import logger from '@/utils/logger.js'\\n" > node_modules/calendarObjects.js
    ;;
*) exec ${JSON.stringify(npm)} "$@" ;;
esac
`, 0o755)
    write(root, 'Makefile', `ABSSRCDIR := $(CURDIR)
BUNDLER_CONFIG := vite.config.ts
NPM := $(CURDIR)/npm
include ${sharedNpmMakefile}
`)
    return root
}

for (const [flavour, mode] of [['build', 'production'], ['dev', 'development']]) {
    for (const jobs of ['1', '4']) {
        test(`shared npm-${flavour} reapplies patches after dependency updates (-j${jobs})`, {
            skip: !existsSync(sharedNpmMakefile) && 'Initialize the dev-scripts/lib submodule',
        }, (t) => {
            const root = fixture(t, flavour)
            const result = spawnSync('make', [`-j${jobs}`, `npm-${flavour}`], { cwd: root, encoding: 'utf8' })
            assert.equal(result.status, 0, result.stdout + result.stderr)
            const events = readFileSync(path.join(root, 'events'), 'utf8').trim().split('\n')
            assert.ok(events.includes('install'), 'The shared dependency installation must run')
            assert.ok(events.includes('update'), 'The shared dependency update must run')
            assert.deepEqual(events.slice(-2), ['patch', `vite --mode ${mode} build`])
            assert.ok(events.lastIndexOf('update') < events.indexOf('patch'))
        })
    }

    test(`npm run ${flavour} stops before Vite if patching fails`, (t) => {
        const root = fixture(t, flavour, true)
        const result = spawnSync(npm, ['run', flavour], { cwd: root, encoding: 'utf8' })
        assert.notEqual(result.status, 0)
        assert.equal(readFileSync(path.join(root, 'events'), 'utf8'), 'patch\n')
        assert.equal(existsSync(path.join(root, 'js/asset-meta.json')), false)
    })
}

test('app dependency targets preserve the lockfile through installation and cleanup', (t) => {
    const root = mkdtempSync(path.join(os.tmpdir(), 'npm-lock-test-'))
    t.after(() => rmSync(root, { recursive: true, force: true }))
    write(root, 'package.json', JSON.stringify({ name: 'lock-test', version: '1.0.0' }))
    write(root, 'vite.config.ts', '')
    write(root, 'Makefile', '')
    write(root, 'shared/makefile/app.mk', [
        `NPM := ${npm}`,
        'BUNDLER_CONFIG := vite.config.ts',
        'MAKEFILE_DEP := Makefile',
        'package-lock.json: package.json',
        '\t$(NPM) update',
        'node_modules:',
        '\t$(NPM) install',
        'realclean:',
        '\trm -rf node_modules',
        '.PHONY: realclean',
        '',
    ].join('\n'))
    const generated = spawnSync(npm, ['install', '--package-lock-only', '--ignore-scripts', '--offline'], { cwd: root, encoding: 'utf8' })
    assert.equal(generated.status, 0, generated.stdout + generated.stderr)
    const lockPath = path.join(root, 'package-lock.json')
    const lock = JSON.parse(readFileSync(lockPath, 'utf8'))
    lock.localEdit = true
    const original = JSON.stringify(lock, null, 2) + '\n'
    writeFileSync(lockPath, original)
    utimesSync(lockPath, 0, 0)

    const make = (...args) => spawnSync('make', [
        '-f', path.join(repository, 'Makefile'), `DEV_LIB_DIR=${path.join(root, 'shared')}`, ...args,
    ], { cwd: root, encoding: 'utf8' })
    for (const target of ['node_modules', 'realclean', 'node_modules', 'realclean']) {
        const result = make(target)
        assert.equal(result.status, 0, result.stdout + result.stderr)
        assert.equal(readFileSync(lockPath, 'utf8'), original)
    }
    write(root, 'package.json', JSON.stringify({
        name: 'lock-test', version: '1.0.0', dependencies: { missing: '1.0.0' },
    }))
    utimesSync(lockPath, 0, 0)
    const mismatch = make('node_modules')
    assert.notEqual(mismatch.status, 0, mismatch.stdout + mismatch.stderr)
    assert.equal(readFileSync(lockPath, 'utf8'), original)
})
