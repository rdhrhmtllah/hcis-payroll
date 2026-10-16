const IGNORED_ERROR_PATTERNS = [
  /ERR_BLOCKED_BY_CLIENT/i,
  /static\.cloudflareinsights\.com/i,
  /cloudflareinsights/i,
  /uBlock/i,
  /adblock/i,
]

const IGNORED_REQUEST_FAILURES = new Set(['net::ERR_ABORTED', 'net::ERR_CANCELED'])

export function isKnownBrowserNoise(value) {
  const text = typeof value === 'string' ? value : value?.message || value?.errorText || ''
  return IGNORED_ERROR_PATTERNS.some((pattern) => pattern.test(text))
}

export function isApplicationConsoleError(message) {
  return message.type() === 'error' && !isKnownBrowserNoise(message.text())
}

export function isCriticalResponse(response) {
  const request = response.request()
  const resourceType = request.resourceType()
  const status = response.status()

  if (status < 500) return false
  if (!['document', 'script', 'stylesheet', 'xhr', 'fetch'].includes(resourceType)) return false
  return !isKnownBrowserNoise(response.url())
}

export function isCriticalRequestFailure(request) {
  const failure = request.failure()?.errorText || ''
  const resourceType = request.resourceType()

  if (IGNORED_REQUEST_FAILURES.has(failure)) return false
  if (isKnownBrowserNoise(failure) || isKnownBrowserNoise(request.url())) return false
  return ['document', 'script', 'stylesheet', 'xhr', 'fetch'].includes(resourceType)
}

export function formatBrowserQualityFailures(failures) {
  return failures
    .map((failure, index) => `${index + 1}. [${failure.kind}] ${failure.detail}`)
    .join('\n')
}
