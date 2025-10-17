/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/record/panels/entity-filter-result', 'views/search/panels/entity-filter-result',
    Dep => Dep.extend({

        readOnly: true,

        setup() {
            this.scope = this.model.get('entity');
            this.url = this.model.get('entity');

            this.model.defs.links.entityFilterResult = {
                entity: this.scope,
                type: "hasMany"
            }

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:fileType', () => {
                this.reRender();
            });

            if(!this.defs.hideShowFullList && !this.getPreferences().get('hideShowFullList')) {
                this.actionList.push({
                    label: 'showFullList',
                    action: 'showFullList'
                });
            }

            let iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
            if (iconHtml) {
                if (this.defs.label) {
                    this.titleHtml = iconHtml + this.translate(this.defs.label, 'labels', 'ExportFeed');
                } else {
                    this.titleHtml = iconHtml + this.title;
                }
            }

            this.additionalBoolFilterList = this.options.additionalBoolFilterList ?? this.additionalBoolFilterList ?? [];
            this.boolFilterData = this.options.boolFilterData ?? this.boolFilterData ?? {};

            if(!this.additionalBoolFilterList.includes('unexported')) {
                this.additionalBoolFilterList.push('unexported');
            }

            this.boolFilterData['unexported'] = () => this.model.get('lastTime')
        },

        getLayoutRelatedScope() {
            return null;
        },

        actionShowFullList(data) {
            this.getStorage().set('listQueryBuilder', this.scope, this.model.get('data').whereData || {});
            window.open(`#${this.scope}`, '_blank');
        },

        setFilter(filter) {
            let data = this.model.get('data') || {};
            this.collection.where = data.where || [];
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.panelVisible()) {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }

            $('.panel-entityFilterResult button[data-action="openSearchFilter"]').html(this.getFilterButtonHtml());
        },

        actionOpenSearchFilter(data) {
            if(!this.model.get('entity') || !this.getMetadata().get(['scopes', this.model.get('entity')])) {
                this.notify(this.translate('The entity for the export is not valid'), 'error');
                return;
            }

            let self  = this;

            if(self.defs?.name !== 'entityFilterResult') {
                self = this.getView('bottom').getView('entityFilterResult');
            }

            let whereData = this.model.get('data')?.where;
            
            if(this.model.get('data')?.whereData
                && (this.model.get('data')?.whereData['queryBuilder']
                    || this.model.get('data')?.whereData['bool']
                    || this.model.get('data')?.whereData['textFilter']
                    || this.model.get('data')?.whereData['savedSearch']
                )
            ){
                whereData = this.model.get('data')?.whereData;
            }


            self.openSearchFilter(this.model.get('entity'), whereData,
                ({where, whereData}) => {
                    this.model.set('data', _.extend({}, this.model.get('data'), {
                        where,
                        whereData,
                        whereScope: this.model.get('entity')
                    }));
                    this.notify(this.translate('saving', 'messages'));
                    this.model.save({_prev: null}).then(() =>  this.notify(this.translate('Done'), 'success'));
                }
            );
        },

        panelVisible() {
            return !(this.model.get('hasMultipleSheets')) && this.model.get('fileType') !== '' && this.model.get('fileType') !== null;
        }
    })
);