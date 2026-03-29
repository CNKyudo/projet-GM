function hideToast(toast) {
    if (!toast || toast.dataset.closing === '1') {
        return
    }

    toast.dataset.closing = '1'
    toast.classList.add('opacity-0', 'translate-x-2')

    window.setTimeout(function () {
        toast.remove()
    }, 280)
}

function initFlashToasts() {
    document.querySelectorAll('#flash_toasts .flash-toast').forEach(function (toast) {
        if (toast.dataset.flashToastInit === '1') {
            return
        }

        toast.dataset.flashToastInit = '1'

        const dismissButton = toast.querySelector('[data-flash-dismiss]')

        if (dismissButton) {
            dismissButton.addEventListener('click', function () {
                hideToast(toast)
            })
        }

        window.setTimeout(function () {
            hideToast(toast)
        }, 4200)
    })
}

document.addEventListener('turbo:load', initFlashToasts)

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFlashToasts, { once: true })
} else {
    initFlashToasts()
}
