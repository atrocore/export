/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/record/detail', ['views/record/detail', 'export:views/export-feed/record/panels/entity-filter-result'],
(Dep, EntityFilter) => Dep.extend({

        bottomView: 'export:views/export-feed/record/detail-bottom',

        initialModel: [],

        setup() {
            Dep.prototype.setup.call(this);

            this.additionalButtons = [
                {
                    "action": "exportNow",
                    "label": this.translate('Export', 'labels', 'ExportFeed')
                }
            ];

            // Check if import module is installed
            if (this.getMetadata('entityDefs.ImportFeed') && this.model.get('type') === 'simple') {
                this.additionalButtons.push({
                    "action": "duplicateAsImport",
                    "label": this.translate('DuplicateAsImport', 'labels', 'ExportFeed')
                })
            }

            this.listenTo(this.model, 'after:save after:inlineEditSave', () => {
                this.handleExportButtonDisability();
            });

            this.getStorage().set('mode', 'ExportFeed', null);

            this.initialModel = this.model.getClonedAttributes();

            this.listenTo(this.model, 'change:entity', () => {
                let scope = this.model.get('entity');

                let data = {};
                if (this.model.get('data')) {
                    data = this.model.get('data');
                }
                if (typeof data.whereScope === 'undefined' || data.whereScope !== scope) {
                    data = _.extend(data, {
                        where: null,
                        whereData: null,
                        whereScope: scope,
                    });
                    this.model.set('data', data);
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.handleExportButtonDisability();
        },

        actionOpenSearchFilter() {
            EntityFilter.prototype.actionOpenSearchFilter.call(this);
        },

        handleExportButtonDisability() {
            if (this.hasExportNow()) {
                this.additionalButtons.map(button => {
                    if (button.action === 'exportNow') {
                        button.disabled = false;
                    }
                });
            } else {
                this.additionalButtons.map(button => {
                    if (button.action === 'exportNow') {
                        button.disabled = true;
                    }
                });
            }
        },

        hasExportNow() {
            return this.model.get('isActive') && this.getAcl().check('ExportJob', 'create');
        },

        actionExportNow() {
            if (!this.hasExportNow()) {
                return;
            }

            this.ajaxPostRequest('ExportFeed/action/exportFile', {id: this.model.id}).then(response => {
                this.notify('Created', 'success');
                this.model.fetch();
                $('.action[data-action="refresh"][data-panel="exportJobs"]').click();
            });
        },

        actionDuplicateAsImport() {
            this.confirm(this.translate('duplicateAsImport', 'messages', 'ExportFeed'), () => {
                const data = {
                    exportFeedId: this.model.get('id')
                };
                this.notify(this.translate('duplicate', 'labels', 'ImportFeed'));
                this.ajaxPostRequest('ImportFeed/action/createFromExport', data).then(response => {
                    if (response) {
                        this.notify('Created', 'success');
                        this.getRouter().navigate('#ImportFeed/view/' + response.id, {trigger: false});
                        this.getRouter().dispatch('ImportFeed', 'view', {
                            id: response.id,
                        })
                    }
                });
            });
        },

        setDetailMode() {
            Dep.prototype.setDetailMode.call(this);

            this.getStorage().set('mode', 'ExportFeed', 'detail');
            this.model.trigger('change:export-feed-mode');
        },

        setEditMode() {
            Dep.prototype.setEditMode.call(this);

            this.getStorage().set('mode', 'ExportFeed', 'edit');
            this.model.trigger('change:export-feed-mode');
        },

        save(callback, skipExit) {
            this.model.trigger('save:export-feed');

            Dep.prototype.save.call(this, callback, skipExit);
        },

        cancelEdit() {
            this.model.set(this.initialModel, {silent: true});
            this.model.trigger('cancel:export-feed-edit');

            Dep.prototype.cancelEdit.call(this);
        }
    })
);