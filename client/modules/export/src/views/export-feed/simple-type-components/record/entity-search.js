/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/simple-type-components/record/entity-search', 'views/record/search',
    Dep => Dep.extend({

        disableSavePreset: true,

        setup() {
            Dep.prototype.setup.call(this);
            this.setupFilters()
        },

        setupFilters() {
            this.presetFilterList = [];
            this.boolFilterList.push('unexported');
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.afterRenderExportFilterPanel();
        },

        afterRenderExportFilterPanel() {
            this.$el.find('.search-row > .form-group').attr('class', 'form-group col-md-12');

            this.setFilterMode();
            this.listenTo(this.options.feedModel, 'change:export-feed-mode', () => {
                this.setFilterMode();
            });

            this.listenTo(this.options.feedModel, 'save:export-feed', () => {
                let filterData = this.getFilterData() || {};
                if (filterData.where) {
                    filterData.where.forEach((item, k) => {
                        if (item.type === 'bool' && (item.value || []).includes('unexported')) {
                            filterData.where[k].data = {unexported: this.options.feedModel.get('lastTime')}
                        }
                    });
                }

                this.options.feedModel.set('data', _.extend({}, this.options.feedModel.get('data'), filterData));
            });
        },

        getTextFilterPlaceholder() {
            return this.translate('typeToSearch', 'labels', 'ExportFeed');
        },

        getFilterData() {
            this.search();
            return {
                where: Espo.Utils.cloneDeep(this.searchManager.getWhere()),
                whereData: Espo.Utils.cloneDeep(this.searchManager.get()),
                whereScope: this.scope,
            }
        },

        isLeftDropdown() {
            return true;
        },

        resetFilters() {
            if (this.getParentView().getParentView().getParentView().mode !== 'edit') {
                return;
            }
            Dep.prototype.resetFilters.call(this);
            this.options.feedModel.set('data', _.extend({}, this.options.feedModel.get('data'), this.getFilterData() || {}))
        },

        refresh() {
            // leave empty
        },

        updateCollection() {
            // leave empty
        },

        setFilterMode() {
            let entityType = this.options.entityType || 'ExportFeed';
            let mode = this.getStorage().get('mode', entityType) || 'detail';

            if (mode === 'edit') {
                this.$el.find('select, input, button, .selectize-input,[data-action="clearLinkSubQuery"],.link-subquery').removeClass('disabled').removeAttr('disabled');
                this.$el.find('.remove-filter, .remove-attribute-filter').show();
                $('.selectized').each(function () {
                    this.selectize.enable();
                });
            } else {
                this.$el.find('select, input, button, .selectize-input,[data-action="clearLinkSubQuery"],.link-subquery').addClass('disabled').attr('disabled', true);
                this.$el.find('.remove-filter, .remove-attribute-filter').hide();
                $('.selectized').each(function () {
                    this.selectize.disable();
                });
            }
        },

    })
);
