import { Controller } from '@hotwired/stimulus';

// N'affiche le choix matin/après-midi que pour un TT en demi-journée : les autres
// combinaisons (jour plein, ou demi-journée d'une absence) n'en ont pas besoin.
export default class extends Controller {
    static targets = ['half'];

    connect() {
        this.update();
    }

    update() {
        const code = this.element.querySelector('[name="code"]').value;
        const portion = this.element.querySelector('[name="portion"]').value;

        this.halfTarget.hidden = !(code === 'TT' && portion === 'half');
    }
}
