/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/previous-item', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            this.params.options = [""];
            this.translatedOptions = {"": ""};

            let url = `ExportFeed/${this.model.get('exportFeedId')}/configuratorItems`;
            if (this.model.get('sheetId')) {
                url = `Sheet/${this.model.get('sheetId')}/configuratorItems`;
            }

            this.ajaxGetRequest(url, {
                offset: 0,
                maxSize: 9999,
                sortBy: "sortOrder",
                asc: true,
                async: false
            }).success(response => {
                if (response.total) {
                    let i = 1;
                    let previousItem = null;
                    response.list.forEach(item => {
                        if (item.id !== this.model.get('id')) {
                            this.params.options.push(item.id);
                            this.translatedOptions[item.id] = i + '. ' + this.prepareNameLabel(item);
                        } else {
                            this.model.set('previousItem', previousItem);
                        }
                        previousItem = item.id;
                        i++;
                    });

                    if (this.model.isNew()) {
                        this.model.set('previousItem', previousItem);
                    }
                    this.reRender();
                }
            });

            Dep.prototype.setup.call(this);
        },

        prepareNameLabel(object) {
            let name = object.name;

            if (object.type === 'Field') {
                name = this.translate(name, 'fields', object.entity);
            }

            if (object.type === 'Fixed value') {
                name = this.getLanguage().translate('fixedValue', 'fields', 'ExportConfiguratorItem');
            }

            if (object.type === 'script') {
                name = this.getLanguage().translate('script', 'fields', 'ExportConfiguratorItem');
            }

            return name;
        },

    })
);