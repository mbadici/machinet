/**
 * Kolab groupware audit trail utilities
 *
 * @author Thomas Bruederli <bruederli@kolabsys.com>
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

var libkolab_audittrail = {}

libkolab_audittrail.quote_html = function(str)
{
    return String(str).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
};


// show object changelog in a dialog
libkolab_audittrail.object_history_dialog = function(p)
{
    // render dialog
    var $dialog = $(p.container);

    // close show dialog first
    if ($dialog.is(':ui-dialog'))
        $dialog.dialog('close');

    // hide and reset changelog table
    $dialog.find('div.notfound-message').remove();
    $dialog.find('.changelog-table').show().children('tbody')
        .html('<tr><td colspan="4"><span class="loading">' + rcmail.gettext('loading') + '</span></td></tr>');

    // open jquery UI dialog
    $dialog.dialog({
        modal: false,
        resizable: true,
        closeOnEscape: true,
        title: p.title,
        open: function() {
            $dialog.attr('aria-hidden', 'false');
        },
        close: function() {
            $dialog.dialog('destroy').attr('aria-hidden', 'true').hide();
        },
        buttons: [
            {
                text: rcmail.gettext('close'),
                click: function() { $dialog.dialog('close'); },
                autofocus: true
            }
        ],
        minWidth: 450,
        width: 650,
        height: 350,
        minHeight: 200
    })
    .show().children('.compare-button').hide();

    // initialize event handlers for history dialog UI elements
    if (!$dialog.data('initialized')) {
      // compare button
      $dialog.find('.compare-button input').click(function(e) {
        var rev1 = $dialog.find('.changelog-table input.diff-rev1:checked').val(),
          rev2 = $dialog.find('.changelog-table input.diff-rev2:checked').val();

          if (rev1 && rev2 && rev1 != rev2) {
            // swap revisions if the user got it wrong
            if (rev1 > rev2) {
              var tmp = rev2;
              rev2 = rev1;
              rev1 = tmp;
            }

            if (p.comparefunc) {
                p.comparefunc(rev1, rev2);
            }
          }
          else {
              alert('Invalid selection!')
          }

          if (!rcube_event.is_keyboard(e) && this.blur) {
              this.blur();
          }
          return false;
      });

      // delegate handlers for list actions
      $dialog.find('.changelog-table tbody').on('click', 'td.actions a', function(e) {
          var link = $(this),
            action = link.hasClass('restore') ? 'restore' : 'show',
            event = $('#eventhistory').data('event'),
            rev = link.attr('data-rev');

            // ignore clicks on first row (current revision)
            if (link.closest('tr').hasClass('first')) {
                return false;
            }

            // let the user confirm the restore action
            if (action == 'restore' && !confirm(rcmail.gettext('revisionrestoreconfirm', p.module).replace('$rev', rev))) {
                return false;
            }

            if (p.listfunc) {
                p.listfunc(action, rev);
            }

            if (!rcube_event.is_keyboard(e) && this.blur) {
                this.blur();
            }
            return false;
      })
      .on('click', 'input.diff-rev1', function(e) {
          if (!this.checked) return true;

          var rev1 = this.value, selection_valid = false;
          $dialog.find('.changelog-table input.diff-rev2').each(function(i, elem) {
              $(elem).prop('disabled', elem.value <= rev1);
              if (elem.checked && elem.value > rev1) {
                  selection_valid = true;
              }
          });
          if (!selection_valid) {
              $dialog.find('.changelog-table input.diff-rev2:not([disabled])').last().prop('checked', true);
          }
      });

      $dialog.addClass('changelog-dialog').data('initialized', true);
    }

    return $dialog;
};

// callback from server with changelog data
libkolab_audittrail.render_changelog = function(data, object, folder)
{
    var Q = libkolab_audittrail.quote_html;

    var $dialog = $('.changelog-dialog')
    if (data === false || !data.length) {
        return false;
    }

    var i, change, accessible, op_append,
      first = data.length - 1, last = 0,
      is_writeable = !!folder.editable,
      op_labels = {
          RECEIVE:   'actionreceive',
          APPEND:    'actionappend',
          MOVE:      'actionmove',
          DELETE:    'actiondelete',
          READ:      'actionread',
          FLAGSET:   'actionflagset',
          FLAGCLEAR: 'actionflagclear'
      },
      actions = '<a href="#show" class="iconbutton preview" title="'+ rcmail.gettext('showrevision','libkolab') +'" data-rev="{rev}" /> ' +
          (is_writeable ? '<a href="#restore" class="iconbutton restore" title="'+ rcmail.gettext('restore','libkolab') + '" data-rev="{rev}" />' : ''),
      tbody = $dialog.find('.changelog-table tbody').html('');

    for (i=first; i >= 0; i--) {
        change = data[i];
        accessible = change.date && change.user;

        if (change.op == 'MOVE' && change.mailbox) {
            op_append = ' ⇢ ' + change.mailbox;
        }
        else if ((change.op == 'FLAGSET' || change.op == 'FLAGCLEAR') && change.flags) {
            op_append = ': ' + change.flags;
        }
        else {
            op_append = '';
        }

        $('<tr class="' + (i == first ? 'first' : (i == last ? 'last' : '')) + (accessible ? '' : 'undisclosed') + '">')
            .append('<td class="diff">' + (accessible && change.op != 'DELETE' ? 
                '<input type="radio" name="rev1" class="diff-rev1" value="' + change.rev + '" title="" '+ (i == last ? 'checked="checked"' : '') +' /> '+
                '<input type="radio" name="rev2" class="diff-rev2" value="' + change.rev + '" title="" '+ (i == first ? 'checked="checked"' : '') +' /></td>'
                : ''))
            .append('<td class="revision">' + Q(i+1) + '</td>')
            .append('<td class="date">' + Q(change.date || '') + '</td>')
            .append('<td class="user">' + Q(change.user || 'undisclosed') + '</td>')
            .append('<td class="operation" title="' + op_append + '">' + Q(rcmail.gettext(op_labels[change.op] || '', 'libkolab') + op_append) + '</td>')
            .append('<td class="actions">' + (accessible && change.op != 'DELETE' ? actions.replace(/\{rev\}/g, change.rev) : '') + '</td>')
            .appendTo(tbody);
    }

    if (first > 0) {
        $dialog.find('.compare-button').fadeIn(200);
        $dialog.find('.changelog-table tr.last input.diff-rev1').click();
    }

    // set dialog size according to content
    libkolab_audittrail.dialog_resize($dialog.get(0), $dialog.height() + 15, 600);

    return $dialog;
};

// resize and reposition (center) the dialog window
libkolab_audittrail.dialog_resize = function(id, height, width)
{
    var win = $(window), w = win.width(), h = win.height();
    $(id).dialog('option', { height: Math.min(h-20, height+130), width: Math.min(w-20, width+50) });
};


// register handlers for mail message history
window.rcmail && rcmail.addEventListener('init', function(e) {
    var loading_lock;

    if (rcmail.env.task == 'mail') {
        rcmail.register_command('kolab-mail-history', function() {
            var dialog, uid = rcmail.get_single_uid(), rec = { uid: uid, mbox: rcmail.get_message_mailbox(uid) };
            if (!uid || !window.libkolab_audittrail) {
                return false;
            }

            // render dialog
            $dialog = libkolab_audittrail.object_history_dialog({
                module: 'libkolab',
                container: '#mailmessagehistory',
                title: rcmail.gettext('objectchangelog','libkolab')
            });

            $dialog.data('rec', rec);

            // fetch changelog data
            loading_lock = rcmail.set_busy(true, 'loading', loading_lock);
            rcmail.http_post('plugin.message-changelog', { _uid: rec.uid, _mbox: rec.mbox }, loading_lock);

        }, rcmail.env.action == 'show');

        rcmail.addEventListener('plugin.message_render_changelog', function(data) {
            var $dialog = $('#mailmessagehistory'),
                rec = $dialog.data('rec');

            if (data === false || !data.length || !rec) {
              // display 'unavailable' message
              $('<div class="notfound-message dialog-message warning">' + rcmail.gettext('objectchangelognotavailable','libkolab') + '</div>')
                  .insertBefore($dialog.find('.changelog-table').hide());
              return;
            }

            data.module = 'libkolab';
            libkolab_audittrail.render_changelog(data, rec, {});
        });

        rcmail.env.message_commands.push('kolab-mail-history');
    }
});
