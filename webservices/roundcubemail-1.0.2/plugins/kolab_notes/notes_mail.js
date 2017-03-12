/**
 * Mail integration script for the Kolab Notes plugin
 *
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) 2014, Kolab Systems AG <contact@kolabsys.com>
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


window.rcmail && rcmail.addEventListener('init', function(evt) {
    /**
     * Open the notes edit GUI in a jquery UI dialog
     */
    function kolab_note_dialog(url)
    {
        var frame, name, mywin = window, edit = url && url._id,
            $dialog = $('#kolabnotesinlinegui');

        function dialog_render(p)
        {
            $dialog.parent().find('.ui-dialog-buttonset .ui-button')
                .prop('disabled', p.readonly)
                .last().prop('disabled', false);
        }

        // create dialog if not exists
        if (!$dialog.length) {
            $dialog = $('<iframe>')
                .attr('id', 'kolabnotesinlinegui')
                .attr('name', 'kolabnotesdialog')
                .attr('src', 'about:blank')
                .css('min-width', '100%')
                .appendTo(document.body)
                .bind('load', function(e){
                    frame = rcmail.get_frame_window('kolabnotesinlinegui');
                    name = $('.notetitle', frame.rcmail.gui_objects.noteviewtitle);
                    frame.rcmail.addEventListener('responseafteraction', refresh_mailview);
                });

            // subscribe event in parent window which is also triggered from iframe
            // (probably before the 'load' event from above)
            rcmail.addEventListener('kolab_notes_render', dialog_render);
        }
        // close show dialog first
        else if ($dialog.is(':ui-dialog')) {
            $dialog.dialog('close');
        }

        if (!url) url = {};
        url._framed = 1;
        $dialog.attr('src', rcmail.url('notes/dialog-ui', url));

        // dialog buttons
        var buttons = {};
        buttons[rcmail.gettext('save')] = function() {
            // frame is not loaded
            if (!frame)
                return;

            // do some input validation
            if (!name.val() || name.val().length < 2) {
                alert(rcmail.gettext('entertitle', 'kolab_notes'));
                name.select();
                return;
            }

            frame.rcmail.command('save');
        };

        if (edit) {
            buttons[rcmail.gettext('delete')] = function() {
                if (confirm(rcmail.gettext('deletenotesconfirm','kolab_notes'))) {
                    rcmail.addEventListener('responseafteraction', refresh_mailview);
                    rcmail.http_post('notes/action', { _data: { uid: url._id, list: url._list }, _do: 'delete' }, true);
                    $dialog.dialog('close');
                }
            };
        }

        buttons[rcmail.gettext(edit ? 'close' : 'cancel')] = function() {
            $dialog.dialog('close');
        };

        // open jquery UI dialog
        var win = $(window);
        $dialog.dialog({
            modal: true,
            resizable: true,
            closeOnEscape: true,
            title: edit ? rcmail.gettext('editnote','kolab_notes') : rcmail.gettext('appendnote','kolab_notes'),
            open: function() {
                $dialog.parent().find('.ui-dialog-buttonset .ui-button').prop('disabled', true).first().addClass('mainaction');
            },
            close: function() {
                $dialog.dialog('destroy').remove();
                rcmail.removeEventListener('kolab_notes_render', dialog_render);
            },
            buttons: buttons,
            minWidth: 480,
            width: 680,
            height: Math.min(640, win.height() - 100)
        }).show();
    }

    /**
     * Reload the mail view/preview to update the notes listing
     */
    function refresh_mailview(e)
    {
        var win = rcmail.env.contentframe ? rcmail.get_frame_window(rcmail.env.contentframe) : mywin;
        if (win && e.response) {
            win.location.reload();
            if (e.response.action == 'action')
                $('#kolabnotesinlinegui').dialog('close');
        }
    }

    // register commands
    rcmail.register_command('edit-kolab-note', kolab_note_dialog, true);
    rcmail.register_command('append-kolab-note', function() {
        var uid;
        if ((uid = rcmail.get_single_uid())) {
            kolab_note_dialog({ _msg: uid + '-' + rcmail.env.mailbox });
        }
    });

    if (rcmail.env.action == 'show') {
        rcmail.enable_command('append-kolab-note', true);
    }
    else {
        rcmail.env.message_commands.push('append-kolab-note');
    }

    // register handlers for inline note editing
    if (rcmail.env.action == 'show' || rcmail.env.action == 'preview') {
        $('.kolabmessagenotes a.kolabnotesref').click(function(e){
            var ref = String($(this).attr('rel')).split('@'),
                win = rcmail.is_framed() ? parent.window : window;
            win.rcmail.command('edit-kolab-note', { _list:ref[1], _id:ref[0] });
            return false;
        });
    }
});
