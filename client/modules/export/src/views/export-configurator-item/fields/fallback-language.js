/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/fallback-language', 'export:views/export-feed/fields/fallback-language',
    Dep => {
        return Dep.extend({
            setup(){
                Dep.prototype.setup.call(this)

                this.listenTo(this.model, 'change:_isMultilang', () => {
                    this.checkFieldVisibility()
                });
            },
            afterRender(){
                Dep.prototype.afterRender.call(this);

                if (this.mode !== 'list') {
                    this.checkFieldVisibility();
                }
            },
            checkFieldVisibility() {

                if (this.model.get('_isMultilang') && (this.getConfig().get('inputLanguageList') || []).length > 0) {
                    this.show();
                } else {
                    this.hide();
                }
            },
        })
    });