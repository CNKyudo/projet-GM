import {disableFormSection, enableFormSection} from "./form_utils.js";

function getEquipmentTypeSelect() {
    return document.querySelector('[data-equipment-type-selector="1"]')
        || document.querySelector('select[name$="[equipment_type]"]')
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

function initEquipmentForm() {
    const gloveFormSection = document.getElementById('glove_form_section')
    const yumiFormSection = document.getElementById('yumi_form_section')
    const equipmentType = getEquipmentTypeSelect()

    if (!equipmentType) {
        return
    }

    if (equipmentType.dataset.equipmentFormInit === '1') {
        updateEquipmentSections(equipmentType, gloveFormSection, yumiFormSection)
        return
    }

    updateEquipmentSections(equipmentType, gloveFormSection, yumiFormSection)

    equipmentType.dataset.equipmentFormInit = '1'
}

document.addEventListener('change', function (event) {
    const equipmentType = getEquipmentTypeSelect()
    const gloveFormSection = document.getElementById('glove_form_section')
    const yumiFormSection = document.getElementById('yumi_form_section')

    if (!equipmentType || event.target !== equipmentType) {
        return
    }

    updateEquipmentSections(equipmentType, gloveFormSection, yumiFormSection)
})

initEquipmentForm()
document.addEventListener('DOMContentLoaded', initEquipmentForm)
document.addEventListener('turbo:load', initEquipmentForm)
