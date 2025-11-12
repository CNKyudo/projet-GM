import {disableFormSection, enableFormSection} from "./form_utils.js";

'./form_utils.js'

const glove_form_section = document.getElementById('glove_form_section')
const yumi_form_section = document.getElementById('yumi_form_section')

document.getElementById('equipment_form_equipment_type')
    .addEventListener('change', function (e) {
        if (this.value !== 'glove') {
            disableFormSection(glove_form_section)
        } else {
            enableFormSection(glove_form_section)
        }

        if (this.value !== 'yumi') {
            disableFormSection(yumi_form_section)
        } else {
            enableFormSection(yumi_form_section)
        }
    })