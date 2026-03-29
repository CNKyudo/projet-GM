const currentPageKey = 'kyudo.navigation.current'
const previousPageKey = 'kyudo.navigation.previous'

function readFromSession(key) {
    try {
        return window.sessionStorage.getItem(key)
    } catch (error) {
        return ''
    }
}

function writeToSession(key, value) {
    try {
        window.sessionStorage.setItem(key, value)
    } catch (error) {
        return
    }
}

function currentUrl() {
    return window.location.pathname + window.location.search + window.location.hash
}

function updateNavigationState() {
    const url = currentUrl()
    const lastUrl = readFromSession(currentPageKey)

    if (lastUrl && lastUrl !== url) {
        writeToSession(previousPageKey, lastUrl)
    }

    writeToSession(currentPageKey, url)
}

function previousUrl() {
    const url = readFromSession(previousPageKey)
    return url && url !== currentUrl() ? url : ''
}

function eventTargetElement(event) {
    if (event.target instanceof Element) {
        return event.target
    }

    return event.target && event.target.parentElement ? event.target.parentElement : null
}

function handleHistoryBackClick(event) {
    const target = eventTargetElement(event)

    if (!target) {
        return
    }

    const link = target.closest('a[data-history-back]')

    if (!link || event.defaultPrevented || event.button !== 0) {
        return
    }

    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return
    }

    if (link.target && link.target !== '_self') {
        return
    }

    const url = previousUrl()

    if (!url) {
        return
    }

    event.preventDefault()
    window.location.assign(url)
}

if (!window.__kyudoHistoryBackInitialized) {
    window.__kyudoHistoryBackInitialized = true

    document.addEventListener('click', handleHistoryBackClick)
    document.addEventListener('turbo:load', updateNavigationState)
    window.addEventListener('pageshow', updateNavigationState)

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateNavigationState, { once: true })
    } else {
        updateNavigationState()
    }
}
