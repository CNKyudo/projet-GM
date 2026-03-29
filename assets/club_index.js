function setButtonState(button, isActive) {
    if (!button) {
        return
    }

    button.classList.toggle('bg-kyudo-blue', isActive)
    button.classList.toggle('text-(--color-kyudo-white)', isActive)
    button.classList.toggle('hover:bg-kyudo-royal-blue', isActive)
    button.classList.toggle('text-kyudo-gm-dark', !isActive)
    button.classList.toggle('hover:bg-kyudo-washed-blue', !isActive)
    button.classList.toggle('hover:text-(--color-kyudo-white)', !isActive)
}

function setClubView(root, view) {
    const cardsView = root.querySelector('[data-club-view-panel="cards"]')
    const tableView = root.querySelector('[data-club-view-panel="table"]')
    const cardsButton = root.querySelector('[data-club-view-button="cards"]')
    const tableButton = root.querySelector('[data-club-view-button="table"]')
    const isCards = view !== 'table'

    if (cardsView) {
        cardsView.classList.toggle('hidden', !isCards)
    }

    if (tableView) {
        tableView.classList.toggle('hidden', isCards)
    }

    setButtonState(cardsButton, isCards)
    setButtonState(tableButton, !isCards)
    root.dataset.currentView = isCards ? 'cards' : 'table'
}

function initClubViewSwitchers() {
    document.querySelectorAll('[data-club-view-switcher]').forEach(function (root) {
        const cardsButton = root.querySelector('[data-club-view-button="cards"]')
        const tableButton = root.querySelector('[data-club-view-button="table"]')

        if (!cardsButton || !tableButton) {
            return
        }

        if (root.dataset.clubViewInit !== '1') {
            cardsButton.addEventListener('click', function () {
                setClubView(root, 'cards')
            })

            tableButton.addEventListener('click', function () {
                setClubView(root, 'table')
            })

            root.dataset.clubViewInit = '1'
        }

        setClubView(root, root.dataset.currentView || root.dataset.initialView || 'cards')
    })
}

document.addEventListener('turbo:load', initClubViewSwitchers)

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initClubViewSwitchers, { once: true })
} else {
    initClubViewSwitchers()
}
