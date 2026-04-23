import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller : role-assign
 *
 * Affiche ou masque les champs "club" et "régions gérées" selon le rôle sélectionné.
 *
 * Rôles nécessitant un club  : ROLE_CLUB_PRESIDENT, ROLE_EQUIPMENT_MANAGER_CLUB
 * Rôles nécessitant des régions : ROLE_EQUIPMENT_MANAGER_CTK
 */
export default class extends Controller {
    static targets = ['roleSelect', 'clubField', 'regionsField'];

    static ROLES_NEEDING_CLUB    = ['ROLE_CLUB_PRESIDENT', 'ROLE_EQUIPMENT_MANAGER_CLUB'];
    static ROLES_NEEDING_REGIONS = ['ROLE_EQUIPMENT_MANAGER_CTK'];

    connect() {
        this.update();
    }

    update() {
        const role = this.roleSelectTarget.value;

        const needsClub    = this.constructor.ROLES_NEEDING_CLUB.includes(role);
        const needsRegions = this.constructor.ROLES_NEEDING_REGIONS.includes(role);

        this.clubFieldTargets.forEach(el => {
            el.hidden = !needsClub;
            // Désactiver les selects cachés pour éviter la validation HTML5
            el.querySelectorAll('select, input').forEach(input => {
                input.disabled = !needsClub;
            });
        });

        this.regionsFieldTargets.forEach(el => {
            el.hidden = !needsRegions;
            el.querySelectorAll('select, input').forEach(input => {
                input.disabled = !needsRegions;
            });
        });
    }
}
