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

    if (normalizedCandidates.some(function (value) { return value.includes('azuchi') })) {
        return 'azuchi'
    }
    if (normalizedCandidates.some(function (value) { return value.includes('shitagake') })) {
        return 'shitagake'
    }    
    if (normalizedCandidates.some(function (value) { return value.includes('makiwara') })) {
        return 'makiwara'
    }
    if (normalizedCandidates.some(function (value) { return value.includes('maku') })) {
        return 'maku'
    }
    if (normalizedCandidates.some(function (value) { return value.includes('muneate') })) {
        return 'muneate'
    }    
    if (normalizedCandidates.some(function (value) { return value.includes('support_makiwara') })) {
        return 'support_makiwara'
    }
    if (normalizedCandidates.some(function (value) { return value.includes('tsuru') })) {
        return 'tsuru'
    }
    if (normalizedCandidates.some(function (value) { return value.includes('yatate') })) {
        return 'yatate'
    }
        if (normalizedCandidates.some(function (value) { return value.includes('yugake') })) {
        return 'yugake'
    }
    // Yumi doit être testé avant Yumitate, car "yumitate" contient "yumi".
    if (normalizedCandidates.some(function (value) { return value.includes('yumitate') })) {
        return 'yumitate'
    }
    if (normalizedCandidates.some(function (value) { return value.includes('yumi') })) {
        return 'yumi'
    }


    return ''
}

function updateEquipmentSections(selectElement, 
        azuchiSection, 
        makiwaraSection, 
        makuSection, 
        muneateSection,
        shitagakeSection,
        supportMakiwaraSection,
        tsuruSection,
        yatateSection, 
        yugakeSection, 
        yumiSection, 
        yumitateSection
    ) {
    var equipmentType = normalizeEquipmentType(selectElement)
    var sections = {
        azuchi: azuchiSection,
        makiwara: makiwaraSection,
        maku: makuSection,
        muneate: muneateSection,
        shitagake: shitagakeSection,
        support_makiwara: supportMakiwaraSection,
        tsuru: tsuruSection,
        yatate: yatateSection,
        yugake: yugakeSection,
        yumi: yumiSection,
        yumitate: yumitateSection
    }

    Object.values(sections).forEach(disableFormSection)

    if (equipmentType && sections[equipmentType]) {
        enableFormSection(sections[equipmentType])
    }
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

    const azuchiFormSection = root.querySelector('[data-equipment-form-section="azuchi"]')
        || root.querySelector('#azuchi_form_section')
    const makiwaraFormSection = root.querySelector('[data-equipment-form-section="makiwara"]')
        || root.querySelector('#makiwara_form_section')
    const makuFormSection = root.querySelector('[data-equipment-form-section="maku"]')
        || root.querySelector('#maku_form_section')
    const muneateFormSection = root.querySelector('[data-equipment-form-section="muneate"]')
        || root.querySelector('#muneate_form_section')
    const shitagakeFormSection = root.querySelector('[data-equipment-form-section="shitagake"]')
        || root.querySelector('#shitagake_form_section')
    const supportMakiwaraFormSection = root.querySelector('[data-equipment-form-section="support_makiwara"]')
        || root.querySelector('#support_makiwara_form_section')
    const tsuruFormSection = root.querySelector('[data-equipment-form-section="tsuru"]')
        || root.querySelector('#tsuru_form_section')
    const yatateFormSection = root.querySelector('[data-equipment-form-section="yatate"]')
        || root.querySelector('#yatate_form_section')
    const yugakeFormSection = root.querySelector('[data-equipment-form-section="yugake"]')
        || root.querySelector('#yugake_form_section')
    const yumiFormSection = root.querySelector('[data-equipment-form-section="yumi"]')
        || root.querySelector('#yumi_form_section')
    const yumitateFormSection = root.querySelector('[data-equipment-form-section="yumitate"]')
        || root.querySelector('#yumitate_form_section')
    const refreshSections = function () {
        updateEquipmentSections(
            azuchiFormSection,
            equipmentType,
            makiwaraFormSection,
            makuFormSection,
            muneateFormSection,
            shitagakeFormSection,
            supportMakiwaraFormSection,
            tsuruFormSection,
            yatateFormSection,
            yugakeFormSection,
            yumiFormSection,
            yumitateFormSection
        )
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
