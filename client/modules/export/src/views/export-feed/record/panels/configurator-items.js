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
            this.defs.create = false;

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
                        label: 'addAllAttributes',
                        action: 'addAllAttributes'
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

            this.listenTo(this.collection, 'update', () => {
                this.collection.forEach(model => {
                    if (model.get('entityAttributeId') && model.get('fieldDefs')) {
                        this.getMetadata().data.entityDefs[model.get('entity')].fields[model.get('name')] = model.get('fieldDefs');
                        this.getLanguage().data[model.get('entity')].fields[model.get('name')] = model.get('fieldDefs').label;
                    }
                })
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.prepareActionsVisibility();

            if (this.panelVisible()) {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }
        },

        panelVisible() {
            if (this.model.name === 'Sheet'){
                return true;
            }

            return !(this.model.get('hasMultipleSheets')) && ['csv', 'xlsx'].includes(this.model.get('fileType'));
        },

        prepareActionsVisibility() {
            const $selectAttributes = $('.action[data-action=selectAttributes][data-panel=configuratorItems]');
            const $addAllAttributes = $('.action[data-action=addAllAttributes][data-panel=configuratorItems]');

            if (this.getMetadata().get(`scopes.${this.model.get('entity')}.hasAttribute`)) {
                $selectAttributes.show();
                $addAllAttributes.show();
            } else {
                $selectAttributes.hide();
                $addAllAttributes.hide();
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
                        fields: fields,
                        entityName: this.model.name,
                    };

                    this.notify('Saving...');
                    this.ajaxPostRequest(`ExportFeed/${this.model.get('id')}/addFields`, postData).then(() => {
                        this.notify('Saved', 'success');
                        this.refreshPanel();
                    });
                });
            });
        },

        actionAddFixed() {
            this.notify('Saving...');
            let postData = {
                entityName: this.model.name,
            };
            this.ajaxPostRequest(`ExportFeed/${this.model.get('id')}/addFixed`, postData).then(() => {
                this.notify('Saved', 'success');
                this.refreshPanel();
            });
        },

        actionAddScript() {
            this.notify('Saving...');
            let postData = {
                entityName: this.model.name,
            };
            this.ajaxPostRequest(`ExportFeed/${this.model.get('id')}/addScript`, postData).then(() => {
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
                boolFilterList: ['onlyForEntity'],
                boolFilterData: {
                    onlyForEntity: this.model.get('entity')
                },
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
                        attributesIds: attributesIds,
                        entityName: this.model.name
                    };

                    this.ajaxPostRequest(`ExportFeed/${this.model.get('id')}/addAttributes`, postData).then(() => {
                        this.notify('Saved', 'success');
                        this.refreshPanel();
                    });
                });
            });
        },

        actionAddAllAttributes() {
            this.notify('Saving...');
            this.ajaxPostRequest(`ExportFeed/${this.model.get('id')}/addAllAttributes`, {
                entityName: this.model.name
            }).success(() => {
                this.notify('Saved', 'success');
                this.refreshPanel();
            }).error(() => {
                this.notify('Error occurred', 'error');
            });
        },

        refreshPanel() {
            $('.action[data-action=refresh][data-panel=configuratorItems]').click();
        },

    })
);