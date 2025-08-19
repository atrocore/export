/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('export:views/export-feed/fields/file-type', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup() {
            this.prepareListOptions();
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:type', () => {
                this.model.set(this.name, null);
                this.prepareListOptions();
                this.reRender();
            });
        },

        prepareListOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            (this.getMetadata().get(`app.exportTypes.${this.model.get('type')}.fileTypes`) || []).forEach(fileType => {
                this.params.options.push(fileType);
                this.translatedOptions[fileType] = this.getLanguage().translateOption(fileType, 'fileType', 'ExportFeed');
            });
        },

    })
});