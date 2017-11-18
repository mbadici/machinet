/**
 * Kolab keyboard shortcuts
 *
 * @author Aleksander Machniak <machniak@kolabsys.com>
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) 2015, Kolab Systems AG <contact@kolabsys.com>
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

var kolab_shortcuts = {
    'mail.refresh': {
        key: 108, // Ctrl+L
        ctrl: true,
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) { return rcmail.command('checkmail', '', e.target, e); }
    },
    'mail.copy': {
        key: 99, // c
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) {
            e = kolab_shortcuts_menu_target(e);
            e.rcmail.command('copy', '', e.target, e);
            e.target.remove();
        }
    },
    'mail.edit': {
        key: 116, // t
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) {
            var mode = rcmail.env.mailbox == rcmail.env.drafts_mailbox ? '' : 'new';
            return rcmail.command('edit', mode, e.target, e);
        }
    },
    'mail.expand-all-threads': {
        key: 46, // Ctrl+.
        ctrl: true,
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) {
            return rcmail.command('expand-all', '', e.target, e);
        }
    },
    'mail.expand-thread': {
        key: 46, // .
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) {
            if (rcmail.message_list) {
                var row, uid = rcmail.message_list.get_single_selection();
                if (uid && (row = rcmail.message_list.rows[uid])) {
                    rcmail.message_list.expand_all(row);
                }
            }
        }
    },
    'mail.collapse-all-threads': {
        key: 44, // Ctrl+,
        ctrl: true,
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) {
            return rcmail.command('collapse-all', '', e.target, e);
        }
    },
    'mail.collapse-thread': {
        key: 44, // ,
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) {
            if (rcmail.message_list) {
                var row, uid = rcmail.message_list.get_single_selection();
                if (uid && (row = rcmail.message_list.rows[uid])) {
                    rcmail.message_list.collapse_all(row);
                }
            }
        }
    },
    'search.focus': {
        key: 115, // s
        active: function(e) { return true; },
        action: function(e) {
            if (!rcmail.is_framed())
                $('#quicksearchbox').focus();
            else if (window.parent && window.parent.$)
                window.parent.$('#quicksearchbox').focus();
        }
    },
    'mail.move': {
        key: 109, // m
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) {
            e = kolab_shortcuts_menu_target(e);
            e.rcmail.command('move', '', e.target, e);
            e.target.remove();
        }
    },
    'mail.next-msg': {
        key: 110, // n
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) {
            if (rcmail.message_list)
                return rcmail.message_list.select_next();
            else
                return rcmail.command('nextmessage', '', '', e);
        }
    },
    'mail.prev-msg': {
        key: 112, // p
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) {
            if (rcmail.message_list)
                return rcmail.message_list.use_arrow_key(38, false);
            else
                return rcmail.command('previousmessage', '', '', e);
        }
    },
    'mail.replyall': {
        key: 97, // a
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) { return rcmail.command('reply-all', 'sub', e.target, e); }
    },
    'mail.replylist': {
        key: 108, // l
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) {
            return rcmail.commands['reply-list'] ? rcmail.command('reply-list', '', e.target, e) : false;
        }
    },
    'mail.reply': {
        key: 114, // r
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) { return rcmail.command('reply', '', e.target, e); }
    },
    'mail.forward-attachment': {
        key: 102, // f
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) { return rcmail.command('forward-attachment', 'sub', e.target, e); }
    },
    'mail.forward-inline': {
        key: 70, // F
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) { return rcmail.command('forward-inline', 'sub', e.target, e); }
    },
    'mail.html2text': {
        key: 72, // H
        active: function(e) { return rcmail.task == 'mail'; },
        action: function(e) {
            var rc = rcmail;

            // we're in list mode, get reference to preview window
            if (rc.env.contentframe) {
                var win = rc.get_frame_window(rc.env.contentframe);
                if (!win || !win.rcmail)
                    return false;
                rc = win.rcmail;
            }

            if (rc.env.optional_format) {
                var format = rc.env.optional_format == 'html' ? 'html' : 'text';
                return rc.command('change-format', format, e.target, e);
            }
        }
    }
};

// create a fake element centered on the page,
// so folder selector popupup appears in the center
var kolab_shortcuts_menu_target = function(e)
{
    var rc, target,
        css = {visibility: 'hidden', width: 10, height: 10, margin: 'auto'};

    if (rcmail.is_framed()) {
        rc = parent.rcmail;
        target = parent.$('<div>').css(css).appendTo(parent.$('body'));
    }
    else {
        rc = rcmail;
        target = $('<div>').css(css).appendTo($('body'));
    }

    e.target = target;
    e.rcmail = rc;

    return e;
};

var kolab_shortcuts_keypress = function(e)
{
    var i, handler, key = e.which, alt = e.altKey, ctrl = e.ctrlKey;

    //console.log(e.which);

    // do nothing on input elements
    if ($(e.target).is('textarea,input')) {
        return true;
    }

    // do nothing if any popup menu is displayed
    if ($('.popupmenu:visible').length) {
        return true;
    }

    for (i in kolab_shortcuts) {
        handler = kolab_shortcuts[i];

        // check if presses key(s) match
        if (handler.key == key
            && ((handler.ctrl && ctrl) || (!handler.ctrl && !ctrl))
            && ((handler.alt && alt) || (!handler.alt && !alt))
        ) {
            // ... and action is active here
            if (handler.active(e)) {
                // execute action, the real check if action is active
                // will be done in .action() or in rcmail.command()
                handler.action(e);
                e.preventDefault();
                return false;
            }

            // we can break here, there can be only one handler
            // for the specified shortcut
            break;
        }
    }

    return true;
};

// register the keypress handler
window.rcmail && $(document).ready(function() {
    $(document).on('keypress.kolab_shortcuts', function(e) {
        return kolab_shortcuts_keypress(e);
    });
});
