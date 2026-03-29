function enableFormSection(section) {
    if (!section) {
        return
    }

    section.classList.remove('d-none')
    section.classList.remove('hidden')
    section.querySelectorAll('input, select')
        .forEach(function (element) {
            element.disabled = false
        })
}

function disableFormSection(section) {
    if (!section) {
        return
    }

    section.classList.add('d-none')
    section.classList.add('hidden')
    section.querySelectorAll('input, select')
        .forEach(function (element) {
            element.disabled = true
        })
}

function getEquipmentTypeSelect(root) {
    return root.querySelector('[data-equipment-type-selector="1"]')
        || root.querySelector('select[name$="[equipment_type]"]')
}

function normalizeEquipmentType(selectElement) {
    if (!selectElement) {
        return ''
    }

    const selectedOption = selectElement.options[selectElement.selectedIndex]
    const candidates = [
        selectElement.value,
        selectedOption ? selectedOption.value : '',
        selectedOption ? selectedOption.textContent : '',
        selectedOption ? selectedOption.getAttribute('data-equipment-type') : '',
    ]

    const normalizedCandidates = candidates.map(function (value) {
        return String(value || '').trim().toLowerCase()
    })

    if (normalizedCandidates.some(function (value) { return value.includes('glove') })) {
        return 'glove'
    }

    if (normalizedCandidates.some(function (value) { return value.includes('yumi') })) {
        return 'yumi'
    }

    return ''
}

function updateEquipmentSections(selectElement, gloveSection, yumiSection) {
    const equipmentType = normalizeEquipmentType(selectElement)

    if (equipmentType === 'glove') {
        enableFormSection(gloveSection)
        disableFormSection(yumiSection)
        return
    }

    if (equipmentType === 'yumi') {
        enableFormSection(yumiSection)
        disableFormSection(gloveSection)
        return
    }

    disableFormSection(gloveSection)
    disableFormSection(yumiSection)
}

function initEquipmentForm(root) {
    const equipmentType = getEquipmentTypeSelect(root)

    if (!equipmentType) {
        return
    }

    const gloveFormSection = root.querySelector('[data-equipment-form-section="glove"]')
        || root.querySelector('#glove_form_section')
    const yumiFormSection = root.querySelector('[data-equipment-form-section="yumi"]')
        || root.querySelector('#yumi_form_section')
    const refreshSections = function () {
        updateEquipmentSections(equipmentType, gloveFormSection, yumiFormSection)
    }

    if (root.dataset.equipmentFormInit !== '1') {
        equipmentType.addEventListener('change', refreshSections)
        root.dataset.equipmentFormInit = '1'
    }

    refreshSections()
}

export function initEquipmentForms() {
    const roots = document.querySelectorAll('[data-equipment-form-root]')

    if (!roots.length) {
        initEquipmentForm(document)
        return
    }

    roots.forEach(function (root) {
        initEquipmentForm(root)
    })
}

document.addEventListener('turbo:load', initEquipmentForms)

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEquipmentForms, { once: true })
} else {
    initEquipmentForms()
}
