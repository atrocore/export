/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/detail', 'views/detail',
    Dep => {

        return Dep.extend({

            setup() {
                Dep.prototype.setup.call(this);

                this.relatedAttributeFunctions['configuratorItems'] = () => {
                    return {
                        "exportFeedLanguage": this.model.get('language') && this.model.get('language') !== '' ? this.model.get('language') : null,
                        "entity": this.model.get('entity'),
                        "type": "Field",
                        "channelId": this.model.get('channelId'),
                        "channelName": this.model.get('channelName')
                    }
                };

                this.listenTo(this.model, 'after:save', () => {
                    $('.action[data-action=refresh][data-panel=configuratorItems]').click();
                });

            },

        });
    });