// SPDX-License-Identifier: AGPL-3.0-or-later

import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { test } from 'node:test'

const repository = path.resolve(import.meta.dirname, '../..')
const patchFilename = readdirSync(path.join(repository, 'patches')).find((name) => /^@nextcloud\+app-calendar\+.*\.patch$/.test(name))
assert.ok(patchFilename, 'A Calendar reference patch must exist')
const patch = readFileSync(path.join(repository, 'patches', patchFilename), 'utf8')
const imports = new Map()
let filename
let removed
for (const line of patch.split('\n')) {
    if (line.startsWith('diff --git ')) {
        filename = line.split(' b/node_modules/@nextcloud/app-calendar/')[1]
        removed = []
    } else if (line.startsWith('-') && !line.startsWith('---')) {
        if (/(['"])@\//.test(line)) {
            removed.push(line.slice(1))
        }
    } else if (line.startsWith('+') && !line.startsWith('+++') && /(['"])(?:\.\.\/)+src\//.test(line)) {
        const entries = imports.get(filename) ?? []
        assert.ok(removed.length, `Missing original import for ${filename}`)
        entries.push([removed.shift(), line.slice(1)])
        imports.set(filename, entries)
    }
}

function write(root, filename, contents, mode) {
    const destination = path.join(root, filename)
    mkdirSync(path.dirname(destination), { recursive: true })
    writeFileSync(destination, contents, { mode })
}

function git(root, ...args) {
    const result = spawnSync('git', args, { cwd: root, encoding: 'utf8' })
    assert.equal(result.status, 0, result.stderr)
    return result.stdout.trim()
}

function fixture(t, state) {
    const root = mkdtempSync(path.join(os.tmpdir(), 'calendar-patch-test-'))
    t.after(() => rmSync(root, { recursive: true, force: true }))
    mkdirSync(path.join(root, 'dev-scripts'))
    copyFileSync(
        path.join(repository, 'dev-scripts/fabricate-app-calendar-patch.sh'),
        path.join(root, 'dev-scripts/fabricate-app-calendar-patch.sh'),
    )
    for (const [filename, entries] of imports) {
        const contents = entries.map(([original, expected]) => {
            if (state === 'original') {
                return original
            }
            return state === 'wrong' ? expected.replace(/(['"])\.\.\//g, '$1../../') : expected
        }).join('\n')
        write(root, `node_modules/@nextcloud/app-calendar/${filename}`, contents + '\n')
    }
    const source = 'node_modules/@nextcloud/app-calendar/src'
    const contacts = path.join(root, source, 'contactsMenu.js')
    const svgQuery = state === 'wrong' ? '?rawb' : state === 'correct' ? '?raw' : ''
    writeFileSync(contacts, readFileSync(contacts, 'utf8') + [
        `import CalendarBlankSvg from '@mdi/svg/svg/calendar-blank.svg${svgQuery}'`,
        '// Decode calendar icon (inline data url -> raw svg)',
        "const CalendarBlankSvgRaw = atob(CalendarBlankSvg.split(',')[1])",
        'iconSvg: () => CalendarBlankSvgRaw,',
        '__webpack_nonce__ = btoa(getRequestToken())',
        '',
    ].join('\n'))
    const editFull = path.join(root, source, 'views/EditFull.vue')
    writeFileSync(editFull, '<template>\n<NcModal />\n</template>\n' + readFileSync(editFull, 'utf8'))
    for (const excluded of ['tests/example.js', '__tests__/example.js', 'example.test.js', 'example.spec.ts']) {
        write(root, `${source}/${excluded}`, "import logger from '@/utils/logger.js'\n")
    }
    write(root, `${source}/binary.dat`, Buffer.from("binary\0'@/utils/logger.js'"))
    write(root, 'patches/@nextcloud+app-calendar+6.6.2.patch', 'old patch')
    write(root, 'patches/unrelated.patch', 'unrelated patch')
    write(root, 'node_modules/.bin/patch-package', `#!/usr/bin/env node
const fs = require('node:fs')
const args = process.argv.slice(2)
if (process.env.CALENDAR_PATCH_TEST_FAIL === '1') process.exit(1)
const directory = args[args.indexOf('--patch-dir') + 1]
fs.writeFileSync(directory + '/@nextcloud+app-calendar+6.6.4.patch', 'generated patch')
`, 0o755)
    git(root, 'init', '--quiet')
    git(root, 'config', 'user.name', 'Calendar patch test')
    git(root, 'config', 'user.email', 'calendar-patch-test@example.invalid')
    git(root, 'add', 'patches')
    git(root, 'commit', '--quiet', '-m', 'Initial patches')
    write(root, 'patches/unrelated.patch', 'staged unrelated patch')
    git(root, 'add', 'patches/unrelated.patch')
    return root
}

function run(root, fail = false) {
    return spawnSync('bash', [path.join(root, 'dev-scripts/fabricate-app-calendar-patch.sh')], {
        cwd: os.tmpdir(),
        encoding: 'utf8',
        env: { ...process.env, CALENDAR_PATCH_TEST_FAIL: fail ? '1' : '0' },
    })
}

function verify(root) {
    assert.ok(imports.size > 100, 'Exercise the reference patch at all directory depths')
    for (const [filename, entries] of imports) {
        const contents = readFileSync(path.join(root, 'node_modules/@nextcloud/app-calendar', filename), 'utf8')
        for (const [original, expected] of entries) {
            assert.ok(contents.split('\n').includes(expected), `${filename}: ${expected}`)
            const target = original.match(/(['"])@\/([^'"]+)\1/)[2]
            for (const match of expected.matchAll(/(['"])((?:\.\.\/)+src\/[^'"]+)\1/g)) {
                assert.equal(
                    path.resolve(path.dirname(filename), match[2]),
                    path.resolve('src', target),
                    `${filename}: import must resolve to the original alias target`,
                )
            }
        }
    }
    const source = path.join(root, 'node_modules/@nextcloud/app-calendar/src')
    const contacts = readFileSync(path.join(source, 'contactsMenu.js'), 'utf8')
    assert.ok(contacts.includes("import CalendarBlankSvg from '@mdi/svg/svg/calendar-blank.svg?raw'"))
    assert.ok(!contacts.includes('?rawb'))
    assert.ok(!contacts.includes('CalendarBlankSvgRaw'))
    assert.ok(contacts.includes('__webpack_nonce__ = btoa(getRequestToken())'))
    assert.ok(readFileSync(path.join(source, 'views/EditFull.vue'), 'utf8').startsWith(
        '<template><div>\n<NcModal />\n</div></template>\n',
    ))
    for (const excluded of ['tests/example.js', '__tests__/example.js', 'example.test.js', 'example.spec.ts']) {
        assert.equal(readFileSync(path.join(source, excluded), 'utf8'), "import logger from '@/utils/logger.js'\n")
    }
    assert.equal(readFileSync(path.join(source, 'binary.dat')).includes(0), true)
    assert.equal(readFileSync(path.join(root, 'patches/unrelated.patch'), 'utf8'), 'staged unrelated patch')
    assert.equal(git(root, 'show', 'HEAD:patches/unrelated.patch'), 'unrelated patch')
    assert.equal(git(root, 'diff', '--cached', '--name-only'), 'patches/unrelated.patch')
    assert.equal(git(root, 'show', '--format=', '--name-status', 'HEAD'), [
        'D\tpatches/@nextcloud+app-calendar+6.6.2.patch',
        'A\tpatches/@nextcloud+app-calendar+6.6.4.patch',
    ].join('\n'))
    assert.equal(existsSync(path.join(root, 'patches/@nextcloud+app-calendar+6.6.2.patch')), false)
    assert.equal(readFileSync(path.join(root, 'patches/@nextcloud+app-calendar+6.6.4.patch'), 'utf8'), 'generated patch')
}

for (const state of ['original', 'wrong', 'correct']) {
    test(`Generate reference import paths from ${state} imports`, (t) => {
        const root = fixture(t, state)
        let result = run(root)
        assert.equal(result.status, 0, result.stderr)
        verify(root)
        const commit = git(root, 'rev-parse', 'HEAD')
        result = run(root)
        assert.equal(result.status, 0, result.stderr)
        verify(root)
        assert.equal(git(root, 'rev-parse', 'HEAD'), commit, 'No empty commit on rerun')
    })
}

test('Keep existing patches when patch-package fails', (t) => {
    const root = fixture(t, 'original')
    const commit = git(root, 'rev-parse', 'HEAD')
    assert.notEqual(run(root, true).status, 0)
    assert.equal(git(root, 'rev-parse', 'HEAD'), commit)
    assert.equal(readFileSync(path.join(root, 'patches/@nextcloud+app-calendar+6.6.2.patch'), 'utf8'), 'old patch')
    assert.equal(existsSync(path.join(root, 'patches/@nextcloud+app-calendar+6.6.4.patch')), false)
})

test('Bypass commit hooks for intentional patch whitespace', (t) => {
    const root = fixture(t, 'original')
    write(root, '.git/hooks/pre-commit', '#!/bin/sh\necho "Commit rejected" >&2\nexit 1\n', 0o755)
    const result = run(root)
    assert.equal(result.status, 0, result.stderr)
    assert.ok(!result.stderr.includes('Commit rejected'))
    verify(root)
})

test('Report commit failures and retain the generated patch', (t) => {
    const root = fixture(t, 'original')
    const commit = git(root, 'rev-parse', 'HEAD')
    git(root, 'config', 'commit.gpgsign', 'true')
    git(root, 'config', 'gpg.program', '/bin/false')
    const result = run(root)
    assert.notEqual(result.status, 0)
    assert.ok(result.stderr.includes('failed to sign'))
    assert.equal(git(root, 'rev-parse', 'HEAD'), commit)
    assert.equal(readFileSync(path.join(root, 'patches/@nextcloud+app-calendar+6.6.4.patch'), 'utf8'), 'generated patch')
})
