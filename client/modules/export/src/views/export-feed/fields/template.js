/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/fields/template', 'views/fields/script', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entity change:fileType', () => {
                if (this.mode === 'edit' && this.model.isNew()) {
                    this.prepareJsonTemplate();
                }
            });
        },

        initInlineActions() {
            this.listenTo(this, 'after:render', this.initMagicIcon, this);

            Dep.prototype.initInlineActions.call(this);
        },

        initMagicIcon() {
            const $cell = this.getCellElement();

            $cell.find('.fa-magic').parent().remove();

            if (this.model.get('fileType') !== 'json') {
                return;
            }

            const $link = $('<a href="javascript:" class="pull-right hidden" title="' + this.translate('generateTemplate', 'labels', 'ExportFeed') + '"><span class="fas fa-magic fa-sm"></span></a>');

            $cell.prepend($link);

            $link.on('click', () => {
                this.confirm({
                    message: this.translate('confirmTemplateGeneration', 'messages', 'ExportFeed'),
                    confirmText: this.translate('Apply')
                }, () => {
                    this.prepareJsonTemplate();
                    this.ajaxPutRequest(`ExportFeed/${this.model.get('id')}`, {template: this.model.get('template')}).then(() => {
                        this.notify('Saved', 'success');
                    });
                });
            });

            $cell.on('mouseenter', function (e) {
                e.stopPropagation();
                if (this.disabled || this.readOnly) {
                    return;
                }
                if (this.mode === 'detail') {
                    $link.removeClass('hidden');
                }
            }.bind(this)).on('mouseleave', function (e) {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $link.addClass('hidden');
                }
            }.bind(this));
        },

        prepareJsonTemplate() {
            if (this.model.get('fileType') !== 'json') {
                return;
            }

            let templateData = [];
            $.each(this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields']), (fieldName, fieldDefs) => {
                if (fieldDefs.importDisabled || ['linkMultiple'].includes(fieldDefs.type)) {
                    return;
                }

                if (fieldName === 'id') {
                    templateData.push(`"${fieldName}": "{{ entity.${fieldName} }}"`);
                } else if (fieldDefs.type === 'bool') {
                    templateData.push(`"${fieldName}": {% if entity.${fieldName} %}true{% else %}false{% endif %}`);
                } else if (['int', 'float'].includes(fieldDefs.type)) {
                    templateData.push(`"${fieldName}": {% if entity.${fieldName} %}{{ entity.${fieldName} }}{% else %}null{% endif %}`);
                } else if (['varchar', 'enum', 'text', 'wysiwyg'].includes(fieldDefs.type)) {
                    templateData.push(`"${fieldName}": {% if entity.${fieldName} %}"{{ entity.${fieldName} | escapeStr | raw }}"{% else %}null{% endif %}`);
                } else if (['link', 'file'].includes(fieldDefs.type)) {
                    templateData.push(`"${fieldName}Id": {% if entity.${fieldName} %}"{{ entity.${fieldName}Id }}"{% else %}null{% endif %}`);
                    templateData.push(`"${fieldName}Name": {% if entity.${fieldName} %}"{{ entity.${fieldName}Name | escapeStr | raw }}"{% else %}null{% endif %}`);
                } else if (['array', 'multiEnum'].includes(fieldDefs.type)) {
                    templateData.push(`"${fieldName}": {% if entity.${fieldName} %}{{ entity.${fieldName} | json_encode | raw }}{% else %}null{% endif %}`);
                }
            });

            this.model.set('template', '{% set siteUrl = config.siteUrl %}\n{% set entity = entities[0] %}\n{\n    ' + templateData.join(',\n    ') + '\n}');
        },

    })
});