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

    if (normalizedCandidates.some(function (value) { return value.includes('support_makiwara') })) {
        return 'support_makiwara'
    }

    if (normalizedCandidates.some(function (value) { return value.includes('makiwara') })) {
        return 'makiwara'
    }

    if (normalizedCandidates.some(function (value) { return value.includes('glove') })) {
        return 'glove'
    }

    if (normalizedCandidates.some(function (value) { return value.includes('yatate') })) {
        return 'yatate'
    }

    if (normalizedCandidates.some(function (value) { return value.includes('yumitate') })) {
        return 'yumitate'
    }

    if (normalizedCandidates.some(function (value) { return value.includes('yumi') })) {
        return 'yumi'
    }

    if (normalizedCandidates.some(function (value) { return value.includes('maku') })) {
        return 'maku'
    }

    if (normalizedCandidates.some(function (value) { return value.includes('etafoam') })) {
        return 'etafoam'
    }

    return ''
}

function updateEquipmentSections(selectElement, gloveSection, yumiSection, makiwaraSection, supportMakiwaraSection, yumitateSection, yatateSection, makuSection, etafoamSection) {
    const equipmentType = normalizeEquipmentType(selectElement)

    if (equipmentType === 'etafoam') {
        enableFormSection(etafoamSection)
        disableFormSection(gloveSection)
        disableFormSection(yumiSection)
        disableFormSection(makiwaraSection)
        disableFormSection(supportMakiwaraSection)
        disableFormSection(yumitateSection)
        disableFormSection(yatateSection)
        disableFormSection(makuSection)
        return
    }

    if (equipmentType === 'maku') {
        enableFormSection(makuSection)
        disableFormSection(gloveSection)
        disableFormSection(yumiSection)
        disableFormSection(makiwaraSection)
        disableFormSection(supportMakiwaraSection)
        disableFormSection(yumitateSection)
        disableFormSection(yatateSection)
        disableFormSection(etafoamSection)
        return
    }

    if (equipmentType === 'yatate') {
        enableFormSection(yatateSection)
        disableFormSection(gloveSection)
        disableFormSection(yumiSection)
        disableFormSection(makiwaraSection)
        disableFormSection(supportMakiwaraSection)
        disableFormSection(yumitateSection)
        disableFormSection(makuSection)
        disableFormSection(etafoamSection)
        return
    }

    if (equipmentType === 'yumitate') {
        enableFormSection(yumitateSection)
        disableFormSection(gloveSection)
        disableFormSection(yumiSection)
        disableFormSection(makiwaraSection)
        disableFormSection(supportMakiwaraSection)
        disableFormSection(yatateSection)
        disableFormSection(makuSection)
        disableFormSection(etafoamSection)
        return
    }

    if (equipmentType === 'support_makiwara') {
        enableFormSection(supportMakiwaraSection)
        disableFormSection(gloveSection)
        disableFormSection(yumiSection)
        disableFormSection(makiwaraSection)
        disableFormSection(yumitateSection)
        disableFormSection(yatateSection)
        disableFormSection(makuSection)
        disableFormSection(etafoamSection)
        return
    }

    if (equipmentType === 'makiwara') {
        enableFormSection(makiwaraSection)
        disableFormSection(gloveSection)
        disableFormSection(yumiSection)
        disableFormSection(supportMakiwaraSection)
        disableFormSection(yumitateSection)
        disableFormSection(yatateSection)
        disableFormSection(makuSection)
        disableFormSection(etafoamSection)
        return
    }

    if (equipmentType === 'glove') {
        enableFormSection(gloveSection)
        disableFormSection(yumiSection)
        disableFormSection(makiwaraSection)
        disableFormSection(supportMakiwaraSection)
        disableFormSection(yumitateSection)
        disableFormSection(yatateSection)
        disableFormSection(makuSection)
        disableFormSection(etafoamSection)
        return
    }

    if (equipmentType === 'yumi') {
        enableFormSection(yumiSection)
        disableFormSection(gloveSection)
        disableFormSection(makiwaraSection)
        disableFormSection(supportMakiwaraSection)
        disableFormSection(yumitateSection)
        disableFormSection(yatateSection)
        disableFormSection(makuSection)
        disableFormSection(etafoamSection)
        return
    }

    disableFormSection(gloveSection)
    disableFormSection(yumiSection)
    disableFormSection(makiwaraSection)
    disableFormSection(supportMakiwaraSection)
    disableFormSection(yumitateSection)
    disableFormSection(yatateSection)
    disableFormSection(makuSection)
    disableFormSection(etafoamSection)
}

/**
 * Quand l'utilisateur sélectionne une valeur dans un champ owner_*,
 * vider automatiquement les autres champs owner_* pour éviter l'ambiguïté.
 * Priorité implicite : fédération > région > club.
 */
function initOwnerMutualExclusion(root) {
    const ownerFields = ['owner_federation', 'owner_region', 'owner_club']
    const selects = ownerFields
        .map(function (name) {
            return root.querySelector('select[name$="[' + name + ']"]')
        })
        .filter(Boolean)

    if (selects.length < 2) {
        return
    }

    selects.forEach(function (select) {
        select.addEventListener('change', function () {
            if (!select.value) {
                return
            }
            // Vider les autres champs owner_*
            selects.forEach(function (other) {
                if (other !== select) {
                    other.value = ''
                }
            })
        })
    })
}

/**
 * Exclusion mutuelle entre borrower_club et borrower_member :
 * sélectionner l'un efface automatiquement l'autre.
 */
function initBorrowerMutualExclusion(root) {
    const clubSelect   = root.querySelector('select[name$="[borrowerClub]"]')
    const memberSelect = root.querySelector('select[name$="[borrowerMember]"]')

    if (!clubSelect || !memberSelect) {
        return
    }

    clubSelect.addEventListener('change', function () {
        if (clubSelect.value) {
            memberSelect.value = ''
        }
    })

    memberSelect.addEventListener('change', function () {
        if (memberSelect.value) {
            clubSelect.value = ''
        }
    })
}

function initEquipmentForm(root) {
    const equipmentType = getEquipmentTypeSelect(root)

    // En mode édition, le champ equipment_type est absent du formulaire.
    // On initialise quand même la logique d'exclusion mutuelle des propriétaires.
    if (!equipmentType) {
        initOwnerMutualExclusion(root)
        initBorrowerMutualExclusion(root)
        return
    }

    const gloveFormSection = root.querySelector('[data-equipment-form-section="glove"]')
        || root.querySelector('#glove_form_section')
    const yumiFormSection = root.querySelector('[data-equipment-form-section="yumi"]')
        || root.querySelector('#yumi_form_section')
    const makiwaraFormSection = root.querySelector('[data-equipment-form-section="makiwara"]')
        || root.querySelector('#makiwara_form_section')
    const supportMakiwaraFormSection = root.querySelector('[data-equipment-form-section="support_makiwara"]')
        || root.querySelector('#support_makiwara_form_section')
    const yumitateFormSection = root.querySelector('[data-equipment-form-section="yumitate"]')
        || root.querySelector('#yumitate_form_section')
    const yatateFormSection = root.querySelector('[data-equipment-form-section="yatate"]')
        || root.querySelector('#yatate_form_section')
    const makuFormSection = root.querySelector('[data-equipment-form-section="maku"]')
        || root.querySelector('#maku_form_section')
    const etafoamFormSection = root.querySelector('[data-equipment-form-section="etafoam"]')
        || root.querySelector('#etafoam_form_section')
    const refreshSections = function () {
        updateEquipmentSections(equipmentType, gloveFormSection, yumiFormSection, makiwaraFormSection, supportMakiwaraFormSection, yumitateFormSection, yatateFormSection, makuFormSection, etafoamFormSection)
    }

    if (root.dataset.equipmentFormInit !== '1') {
        equipmentType.addEventListener('change', refreshSections)
        root.dataset.equipmentFormInit = '1'
    }

    refreshSections()
    initOwnerMutualExclusion(root)
    initBorrowerMutualExclusion(root)
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
