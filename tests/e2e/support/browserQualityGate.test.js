import assert from 'node:assert/strict'
import test from 'node:test'
import {
  formatBrowserQualityFailures,
  isApplicationConsoleError,
  isKnownBrowserNoise,
} from './browserQualityGate.js'

test('ignores known browser extension and Cloudflare noise', () => {
  assert.equal(isKnownBrowserNoise('static.cloudflareinsights.com/... ERR_BLOCKED_BY_CLIENT'), true)
  assert.equal(isKnownBrowserNoise('uBlock cosmetic filter'), true)
  assert.equal(isKnownBrowserNoise('Vue render error'), false)
})

test('classifies application console errors but not warnings or known noise', () => {
  assert.equal(isApplicationConsoleError({ type: () => 'error', text: () => 'Vue render error' }), true)
  assert.equal(isApplicationConsoleError({ type: () => 'warning', text: () => 'not an error' }), false)
  assert.equal(isApplicationConsoleError({ type: () => 'error', text: () => 'ERR_BLOCKED_BY_CLIENT' }), false)
})

test('formats browser quality evidence for reports', () => {
  assert.equal(
    formatBrowserQualityFailures([
      { kind: 'pageerror', detail: 'boom' },
      { kind: 'http-5xx', detail: '500 /api/example' },
    ]),
    '1. [pageerror] boom\n2. [http-5xx] 500 /api/example',
  )
})
