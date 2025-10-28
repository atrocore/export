/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/channels', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                this.model.set('channels', ['withoutChannel']);
                this.setupOptions();
                this.reRender();
            });
        },

        setupOptions() {
            this.translatedOptions = this.params.translatedOptions = this.getTranslatesForExportByField();
            this.originalOptionList = this.params.options = Object.keys(this.translatedOptions);
        },

        getTranslatesForExportByField() {
            let result = {
                'withoutChannel': this.translate('withoutChannel', 'labels', 'ExportConfiguratorItem')
            };

            if (['edit', 'detail'].includes(this.mode)) {
                this.ajaxGetRequest('Channel', null, {async: false}).success(res => {
                    (res.list || []).forEach(item => {
                        result[item.id] = item.name;
                    })
                });
            }

            return result;
        },

    })
);