/**
 * Client script for the Kolab folder management/listing extension
 *
 * @author Aleksander Machniak <machniak@kolabsys.com>
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) 2011, Kolab Systems AG <contact@kolabsys.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

window.rcmail && rcmail.env.action == 'folders' && rcmail.addEventListener('init', function() {
    var filter = $(rcmail.gui_objects.foldersfilter),
        optgroup = $('<optgroup>').attr('label', rcmail.gettext('kolab_folders.folderctype'));

    // remove disabled namespaces
    filter.children('option').each(function(i, opt) {
        $.each(rcmail.env.skip_roots || [], function() {
            if (opt.value == this) {
                $(opt).remove();
            }
        });
    });

    // add type options to the filter
    $.each(rcmail.env.foldertypes, function() {
        optgroup.append($('<option>').attr('value', 'type-' + this).text(rcmail.gettext('kolab_folders.foldertype' + this)));
    });

    // overwrite default onchange handler
    filter.attr('onchange', '')
        .on('change', function() { return kolab_folders_filter(this.value); })
        .append(optgroup);
});

window.rcmail && rcmail.env.action != 'folders' && $(document).ready(function() {
    // Add onchange handler for folder type SELECT, and call it on form init
    $('#_ctype').change(function() {
        var type = $(this).val(),
            sub = $('#_subtype'),
            subtypes = rcmail.env.kolab_folder_subtypes[type] || {};

        // reset subtype selector
        sub.html('<option value=""></option>');

        // append available subtypes for the given folder type
        $.each(subtypes, function(val, label) {
            $('<option>').attr('value', val).text(label).appendTo(sub);
        });

        // And re-set subtype
        sub.val(rcmail.env.kolab_folder_subtype);
    });
});

function kolab_folders_filter(filter)
{
    var type = filter.match(/^type-([a-z]+)$/) ? RegExp.$1 : null;

    rcmail.subscription_list.reset_search();

    if (!type) {
        // clear type filter
        if (rcmail.folder_filter_type) {
            $('li', rcmail.subscription_list.container).removeData('filtered').show();
            rcmail.folder_filter_type = null;
        }

        // apply namespace filter
        rcmail.folder_filter(filter);
    }
    else {
        rcmail.folder_filter_type = type;
        rcmail.subscription_list.container.children('li').each(function() {
            kolab_folder_filter_match(this, type);
        });
    }

    return false;
}

function kolab_folder_filter_match(elem, type)
{
    var found = 0, cl = elem.className || '',
        $elem = $(elem),
        children = $('ul', elem).children('li');

    // subfolders...
    children.each(function() {
        found += kolab_folder_filter_match(this, type);
    });

    if (found || cl.match(new RegExp('type-' + type))
        || (type == 'mail' && !children.length && !cl.match(/(^| )type-([a-z]+)/))
    ) {
        if (found || !$elem.is('.virtual')) {
            found++;
        }
    }

    if (found) {
        $elem.removeData('filtered').show();
    }
    else {
        $elem.data('filtered', true).hide();
    }

    return found;
}
