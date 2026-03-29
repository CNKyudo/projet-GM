function syncNavbarToggleState() {
    document.querySelectorAll('#navbar_mobile_toggle[aria-controls]').forEach(function (button) {
        const menu = document.getElementById(button.getAttribute('aria-controls'))

        if (!menu) {
            return
        }

        const isOpen = !menu.classList.contains('hidden')
        button.setAttribute('aria-expanded', String(isOpen))
    })
}

function eventTargetElement(event) {
    if (event.target instanceof Element) {
        return event.target
    }

    return event.target && event.target.parentElement ? event.target.parentElement : null
}

function handleNavbarToggle(event) {
    const target = eventTargetElement(event)

    if (!target) {
        return
    }

    const button = target.closest('#navbar_mobile_toggle[aria-controls]')

    if (!button) {
        return
    }

    const menu = document.getElementById(button.getAttribute('aria-controls'))

    if (!menu) {
        return
    }

    const isOpen = !menu.classList.contains('hidden')
    menu.classList.toggle('hidden', isOpen)
    button.setAttribute('aria-expanded', String(!isOpen))
}

if (!window.__kyudoNavbarInitialized) {
    window.__kyudoNavbarInitialized = true

    document.addEventListener('click', handleNavbarToggle)
    document.addEventListener('turbo:load', syncNavbarToggleState)

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncNavbarToggleState, { once: true })
    } else {
        syncNavbarToggleState()
    }
}
