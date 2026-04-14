/**
 * Creates and tears down a minimal Typemill content page for the Mergely diff
 * view integration tests.
 *
 * Files are written via `docker exec` + PHP so they are immediately visible to
 * Typemill without any volume-mount sync delay.  The page is placed under the
 * "99-" prefix to sort last and avoid conflicting with real content.
 *
 * The URL Typemill exposes for 99-test-mergely-diff/ is /test-mergely-diff.
 */

import { execSync }                      from 'node:child_process'
import { existsSync, readdirSync, rmSync } from 'node:fs'
import { join, dirname }                 from 'node:path'
import { fileURLToPath }                 from 'node:url'

const __dirname    = dirname(fileURLToPath(import.meta.url))
const REPO_ROOT    = join(__dirname, '../../..')

const COMPOSE_FILE = join(REPO_ROOT, 'docker-compose.typemill.yml')
const NAVI_DIR     = join(REPO_ROOT, '.docker/typemill/data/navigation')

const PAGE_NAME    = '99-test-mergely-diff'
const CTR_PATH     = `/var/www/html/content/${PAGE_NAME}`

export const MERGELY_TEST_URL = '/test-mergely-diff'

export function createMergelyTestPage() {
    const php = [
        `$dir = '${CTR_PATH}';`,
        `mkdir($dir, 0755, true);`,
        `file_put_contents($dir . '/index.md', "# Mergely Test Page\\n\\nInitial content for diff view testing.\\n");`,
        `echo 'ok';`,
    ].join(' ')

    const result = execSync(
        `docker compose -f "${COMPOSE_FILE}" exec -T typemill php`,
        { input: `<?php ${php}`, stdio: ['pipe', 'pipe', 'pipe'] }
    ).toString().trim()

    if (result !== 'ok') {
        throw new Error(`createMergelyTestPage: unexpected output: ${result}`)
    }

    clearNavigationCache()
}

export function cleanupMergelyTestPage() {
    try {
        execSync(
            `docker compose -f "${COMPOSE_FILE}" exec -T typemill rm -rf "${CTR_PATH}"`,
            { stdio: 'pipe' }
        )
    } catch {
        // Already gone — fine.
    }
    clearNavigationCache()
}

function clearNavigationCache() {
    if (!existsSync(NAVI_DIR)) return
    for (const file of readdirSync(NAVI_DIR)) {
        if (file.endsWith('.txt')) {
            rmSync(join(NAVI_DIR, file), { force: true })
        }
    }
}
