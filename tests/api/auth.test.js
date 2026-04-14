import { describe, it, expect } from 'vitest'
import { createSession } from './helpers/auth.js'

const BASE_URL = process.env.TM_BASE_URL || 'http://127.0.0.1:8080'
const USERNAME  = process.env.TM_USER
const PASSWORD  = process.env.TM_PASSWORD

const configured = USERNAME && PASSWORD

describe('Authentication', () => {
    it.skipIf(!configured)('returns a session cookie for valid credentials', async () => {
        const session = await createSession(BASE_URL, USERNAME, PASSWORD)
        expect(session.cookie).toBeTruthy()
        expect(session.cookie).toContain('=')
    })

    it.skipIf(!configured)('rejects empty credentials', async () => {
        await expect(createSession(BASE_URL, '', '')).rejects.toThrow()
    })

    it.skipIf(!configured)('rejects wrong credentials', async () => {
        await expect(
            createSession(BASE_URL, USERNAME, 'definitely-wrong-password-xyz')
        ).rejects.toThrow()
    })
})
