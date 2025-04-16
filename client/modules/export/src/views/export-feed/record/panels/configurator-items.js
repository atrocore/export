/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/record/panels/configurator-items', 'views/record/panels/relationship',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.getAcl().check('ExportFeed', 'edit')) {
                this.actionList = [
                    {
                        label: 'selectFields',
                        action: 'selectFields'
                    },
                    {
                        label: 'selectAttributes',
                        action: 'selectAttributes'
                    },
                    {
                        label: 'addFixed',
                        action: 'addFixed'
                    },
                    {
                        label: 'addScript',
                        action: 'addScript'
                    },
                    {
                        label: 'removeAllItems',
                        action: 'removeAllItems'
                    }
                ];
            }

            this.listenTo(this.model, 'change:entity', () => {
                this.prepareActionsVisibility();
            });

            this.listenTo(this.model, 'change:fileType', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.prepareActionsVisibility();

            this.$el.parent().show();

            let fileType = this.model.get('fileType');
            if (fileType) {
                if (!['csv', 'xlsx'].includes(fileType)) {
                    this.$el.parent().hide();
                }
            }
            if (this.model.get('hasMultipleSheets')) {
                this.$el.parent().hide();
            }
        },

        prepareActionsVisibility() {
            const $selectAttributes = $('.action[data-action=selectAttributes][data-panel=configuratorItems]');

            if (this.model.get('entity') === 'Product' || this.getMetadata().get(`scopes.${this.model.get('entity')}.hasAttribute`)) {
                $selectAttributes.show();
            } else {
                $selectAttributes.hide();
            }
        },

        actionRemoveAllItems() {
            this.confirm(this.translate('removeAllItemsConfirmation', 'labels', 'ExportFeed'), () => {
                this.notify('Removing...');

                let postData = {
                    entityType: this.model.urlRoot,
                    id: this.model.get('id')
                };

                this.ajaxPostRequest(`ExportFeed/action/removeAllItems`, postData).then(response => {
                    this.notify('Removed', 'success');
                    this.refreshPanel();
                });
            });
        },

        actionSelectFields() {
            this.notify('Loading...');
            this.createView('dialog', 'views/modals/select-records', {
                scope: 'EntityField',
                multiple: true,
                createButton: false,
                massRelateEnabled: false,
                allowSelectAllResult: false,
                boolFilterList: [
                    "fieldsFilter",
                    "notLingual"
                ],
                boolFilterData: {
                    fieldsFilter: {
                        entityId: this.model.get('entity')
                    }
                }
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('select', models => {
                    if (models.massRelate) {
                        models = dialog.collection.models;
                    }

                    let fields = [];
                    models.forEach(model => {
                        fields.push(model.get('code'))
                    });

                    let postData = {
                        id: this.model.get('id'),
                        fields: fields,
                        entityName: this.model.name,
                    };

                    this.notify('Saving...');
                    this.ajaxPostRequest(`ExportFeed/action/addFields`, postData).then(() => {
                        this.notify('Saved', 'success');
                        this.refreshPanel();
                    });
                });
            });
        },

        actionAddFixed() {
            this.notify('Saving...');
            this.ajaxPostRequest(`ExportFeed/action/addFixed`, {id: this.model.get('id')}).then(() => {
                this.notify('Saved', 'success');
                this.refreshPanel();
            });
        },

        actionAddScript() {
            this.notify('Saving...');
            this.ajaxPostRequest(`ExportFeed/action/addScript`, {id: this.model.get('id')}).then(() => {
                this.notify('Saved', 'success');
                this.refreshPanel();
            });
        },

        actionSelectAttributes() {
            const scope = 'Attribute';
            const viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', 'select']) || 'views/modals/select-records';

            this.notify('Loading...');
            this.createView('dialog', viewName, {
                scope: scope,
                multiple: true,
                createButton: false,
                massRelateEnabled: true,
                allowSelectAllResult: false,
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('select', models => {
                    this.notify('Saving...');

                    if (models.massRelate) {
                        models = dialog.collection.models;
                    }

                    let attributesIds = [];
                    models.forEach(model => {
                        attributesIds.push(model.get('id'))
                    });

                    let postData = {
                        id: this.model.get('id'),
                        attributesIds: attributesIds,
                        entityName: this.model.name
                    };

                    this.ajaxPostRequest(`ExportFeed/action/addAttributes`, postData).then(() => {
                        this.notify('Saved', 'success');
                        this.refreshPanel();
                    });
                });
            });
        },

        refreshPanel() {
            $('.action[data-action=refresh][data-panel=configuratorItems]').click();
        },

    })
);