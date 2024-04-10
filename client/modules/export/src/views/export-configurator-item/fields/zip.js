/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/zip', 'views/fields/bool',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name change:type', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list') {
                this.checkFieldVisibility();
                this.checkFieldDisability();
            }
        },

        checkFieldDisability() {
            this.$el.find('input').removeAttr('disabled');
        },

        checkFieldVisibility() {
            if (this.hasZip()) {
                this.show();
            } else {
                this.hide();
            }
        },

        hasZip() {
            if (this.model.get('type') !== 'Field') {
                return false;
            }

            let type = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']);
            if (!['linkMultiple', 'link', 'file'].includes(type)) {
                return false;
            }

            if (type === 'file') {
                return true;
            }

            let foreignEntity = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'links', this.model.get('name'), 'entity']);

            return ['File', 'ProductFile'].includes(foreignEntity);
        },

    })
);