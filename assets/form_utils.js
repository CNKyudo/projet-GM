export function enableFormSection (section) {
    section.classList.remove('d-none');
    section.querySelectorAll('input, select')
        .forEach(function (e) {
            e.disabled = false
        })
}

export function disableFormSection(section) {
    section.classList.add('d-none');
    section.querySelectorAll('input, select')
        .forEach(function (e) {
            e.disabled = true
        })
}