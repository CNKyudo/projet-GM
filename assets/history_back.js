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

function updateCurrentPage() {
    writeToSession(currentPageKey, currentUrl())
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

function isPlainLeftClick(event) {
    return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey
}

function eventLink(event, selector = 'a[href]') {
    const target = eventTargetElement(event)
    return target ? target.closest(selector) : null
}

function isTrackableLink(link) {
    if (!link || link.hasAttribute('data-history-back')) {
        return false
    }

    if (link.target && link.target !== '_self') {
        return false
    }

    if (link.hasAttribute('download')) {
        return false
    }

    const href = link.getAttribute('href')

    if (!href || href.startsWith('#')) {
        return false
    }

    try {
        const url = new URL(link.href, window.location.origin)

        if (url.origin !== window.location.origin) {
            return false
        }

        return url.pathname + url.search + url.hash !== currentUrl()
    } catch (error) {
        return false
    }
}

function rememberPreviousPage(event) {
    if (event.defaultPrevented || !isPlainLeftClick(event)) {
        return
    }

    const link = eventLink(event)

    if (!isTrackableLink(link)) {
        return
    }

    writeToSession(previousPageKey, currentUrl())
}

function handleHistoryBackClick(event) {
    const link = eventLink(event, 'a[data-history-back]')

    if (!link || event.defaultPrevented || !isPlainLeftClick(event)) {
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

    if (window.history.length > 1) {
        window.history.back()
        return
    }

    window.location.assign(url)
}

if (!window.__kyudoHistoryBackInitialized) {
    window.__kyudoHistoryBackInitialized = true

    document.addEventListener('click', rememberPreviousPage, true)
    document.addEventListener('click', handleHistoryBackClick, true)
    document.addEventListener('turbo:load', updateCurrentPage)
    window.addEventListener('pageshow', updateCurrentPage)

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateCurrentPage, { once: true })
    } else {
        updateCurrentPage()
    }
}
