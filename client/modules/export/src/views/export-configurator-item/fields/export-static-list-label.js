/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-configurator-item/fields/export-static-list-label', 'views/fields/bool',
    Dep => Dep.extend({
        afterRender() {
            Dep.prototype.afterRender.call(this);

            let type = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']);
            if(!['enum', 'multiEnum'].includes(type)) {
                this.hide();
            }
        }
    })
);