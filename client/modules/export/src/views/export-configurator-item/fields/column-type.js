/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/column-type', 'views/fields/enum',
    Dep => {

        return Dep.extend({

            init: function () {
                Dep.prototype.init.call(this);

                this.listenTo(this.model, 'change:name', () => {
                    this.reRender();
                });

                this.listenTo(this.model, 'change:type', () => {
                    if (this.model.get(this.name) !== 'custom' && (this.model.get('type') === 'Fixed value' || this.model.get('type') === 'script')) {
                        this.model.set(this.name, 'custom');
                    }
                    this.reRender();
                })
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                if (this.mode !== 'list') {
                    this.checkFieldVisibility();
                    this.checkFieldDisability();
                }
            },

            checkFieldVisibility() {
                this.$el.show();
            },

            checkFieldDisability() {
                if (this.model.get('type') === 'Fixed value' || this.model.get('type') === 'script') {
                    this.$el.find('select').attr('disabled', 'disabled');
                } else {
                    this.$el.find('select').removeAttr('disabled');
                }
            },

        })
    });