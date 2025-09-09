/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/fields/sort-order-field', 'views/fields/enum',
    Dep => {

        return Dep.extend({

            setup() {
                Dep.prototype.setup.call(this);

                this.listenTo(this.model, 'change:entity', () => {
                    this.setupOptions();
                    this.reRender();
                    this.model.set('sortOrderField', null);
                });
            },

            setupOptions() {
                let scope = this.model.get('entity');
                let fieldDefs = this.getMetadata().get(`entityDefs.${scope}.fields`) || {};

                this.params.options = [];
                this.translatedOptions = {};

                $.each(fieldDefs, (field, defs) => {
                    if (defs.notStorable || defs.exportDisabled) {
                        return;
                    }

                    if (defs.type === 'linkMultiple') {
                        return;
                    }

                    this.params.options.push(field);
                    this.translatedOptions[field] = this.translate(field, 'fields', scope);
                });
            },

        })
    });