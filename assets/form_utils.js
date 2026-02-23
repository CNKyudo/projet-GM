export function enableFormSection (section) {
    if (!section) {
        return
    }

    section.classList.remove('d-none');
    section.classList.remove('hidden');
    section.querySelectorAll('input, select')
        .forEach(function (e) {
            e.disabled = false
        })
}

export function disableFormSection(section) {
    if (!section) {
        return
    }

    section.classList.add('d-none');
    section.classList.add('hidden');
    section.querySelectorAll('input, select')
        .forEach(function (e) {
            e.disabled = true
        })
}
