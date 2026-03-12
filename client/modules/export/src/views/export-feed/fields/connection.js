/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/fields/connection', 'views/fields/link',
    Dep => {
        return Dep.extend({

            selectBoolFilterList: ['notEntity', 'connectionType'],

            boolFilterData: {
                notEntity() {
                    return this.model.get('connectionId');
                },
                connectionType() {
                    return this.getMetadata().get(`scopes.ExportFeed.connectionTypes.${this.model.get('type')}`) || ['no-such-type'];
                }
            }
        });

    });