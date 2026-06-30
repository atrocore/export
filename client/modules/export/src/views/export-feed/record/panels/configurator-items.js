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
                };

                this.ajaxPostRequest(`ExportFeed/${this.model.get('id')}/removeAllItems`, postData).then(response => {
                    this.notify('Removed', 'success');
                    this.refreshPanel();
                });
            });
        },

        actionSelectFields() {
            const isMultilang = this.getConfig().get('isMultilangActive');

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
                },
                additionalButtons: isMultilang ? [
                    {
                        name: 'selectAllLanguages',
                        label: this.translate('selectAllLanguages', 'labels', 'ExportFeed'),
                        style: 'primary'
                    }
                ] : []
            }, dialog => {
                dialog.render();
                this.notify(false);

                const doAddFields = (models, allLanguages) => {
                    if (models.massRelate) {
                        models = dialog.collection.models;
                    }
                    const fields = models.map(m => m.get('code'));
                    this.notify('Saving...');
                    this.ajaxPostRequest(`ExportFeed/${this.model.get('id')}/addFields`, {
                        fields,
                        entityName: this.model.name,
                        allLanguages
                    }).then(() => {
                        this.notify('Saved', 'success');
                        this.refreshPanel();
                    });
                };

                dialog.once('select', models => doAddFields(models, false));
                dialog.once('selectAllLanguages', models => doAddFields(models, true));
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
            const isMultilang = this.getConfig().get('isMultilangActive');

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
                additionalButtons: isMultilang ? [
                    {
                        name: 'selectAllLanguages',
                        label: this.translate('selectAllLanguages', 'labels', 'ExportFeed'),
                        style: 'primary'
                    }
                ] : []
            }, dialog => {
                dialog.render();
                this.notify(false);

                const doAddAttributes = (models, allLanguages) => {
                    if (models.massRelate) {
                        models = dialog.collection.models;
                    }
                    const attributesIds = models.map(m => m.get('id'));
                    this.notify('Saving...');
                    this.ajaxPostRequest(`ExportFeed/${this.model.get('id')}/addAttributes`, {
                        attributesIds,
                        entityName: this.model.name,
                        allLanguages
                    }).then(() => {
                        this.notify('Saved', 'success');
                        this.refreshPanel();
                    });
                };

                dialog.once('select', models => doAddAttributes(models, false));
                dialog.once('selectAllLanguages', models => doAddAttributes(models, true));
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