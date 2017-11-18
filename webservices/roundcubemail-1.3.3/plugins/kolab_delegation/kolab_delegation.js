/**
 * Client scripts for the Kolab Delegation configuration utitlity
 *
 * @author Aleksander Machniak <machniak@kolabsys.com>
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) 2011-2016, Kolab Systems AG <contact@kolabsys.com>
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
  if (rcmail.env.task == 'mail' || rcmail.env.task == 'calendar' || rcmail.env.task == 'tasks') {
    // set delegator context for calendar/tasklist requests on invitation message
    rcmail.addEventListener('requestcalendar/event', function(o) { rcmail.event_delegator_request(o); })
      .addEventListener('requestcalendar/mailimportitip', function(o) { rcmail.event_delegator_request(o); })
      .addEventListener('requestcalendar/itip-status', function(o) { rcmail.event_delegator_request(o); })
      .addEventListener('requestcalendar/itip-remove', function(o) { rcmail.event_delegator_request(o); })
      .addEventListener('requesttasks/task', function(o) { rcmail.event_delegator_request(o); })
      .addEventListener('requesttasks/mailimportitip', function(o) { rcmail.event_delegator_request(o); })
      .addEventListener('requesttasks/itip-status', function(o) { rcmail.event_delegator_request(o); })
      .addEventListener('requesttasks/itip-remove', function(o) { rcmail.event_delegator_request(o); });

    // Calendar UI
    if (rcmail.env.delegators && window.rcube_calendar_ui) {
      rcmail.calendar_identity_init('calendar');
      // delegator context for calendar event form
      rcmail.addEventListener('calendar-event-init', function(o) { return rcmail.calendar_event_init(o, 'calendar'); });
      // change organizer identity on calendar folder change
      $('#edit-calendar').change(function() { rcmail.calendar_folder_change(this); });
    }
    // Tasks UI
    else if (rcmail.env.delegators && window.rcube_tasklist_ui) {
      rcmail.calendar_identity_init('tasklist');
      // delegator context for task form
      rcmail.addEventListener('tasklist-task-init', function(o) { return rcmail.calendar_event_init(o, 'tasklist'); });
      // change organizer identity on tasks folder change
      $('#taskedit-tasklist').change(function() { rcmail.calendar_folder_change(this); });
    }
  }
  else if (rcmail.env.task != 'settings')
    return;

  if (/^plugin.delegation/.test(rcmail.env.action)) {
    rcmail.addEventListener('plugin.delegate_save_complete', function(e) { rcmail.delegate_save_complete(e); });

    if (rcmail.gui_objects.delegatelist) {
      rcmail.delegatelist = new rcube_list_widget(rcmail.gui_objects.delegatelist,
        { multiselect:true, draggable:false, keyboard:true });
      rcmail.delegatelist.addEventListener('select', function(o) { rcmail.select_delegate(o); })
        .init();

      rcmail.enable_command('delegate-add', true);
    }
    else {
      rcmail.enable_command('delegate-save', true);

      var input = $('#delegate');

      // delegate autocompletion
      if (input.length) {
        rcmail.init_address_input_events(input, {action: 'settings/plugin.delegation-autocomplete'});
        rcmail.env.recipients_delimiter = '';
        input.focus();
      }

      // folders list
      $('input.write').change(function(e) {
        if (this.checked)
          $('input.read', this.parentNode.parentNode).prop('checked', true);
        });

      $('input.read').change(function(e) {
        if (!this.checked)
          $('input.write', this.parentNode.parentNode).prop('checked', false);
      });

      var fn = function(elem) {
        var classname = elem.className,
          list = $(elem).closest('table').find('input.' + classname),
          check = list.not(':checked').length > 0;

        list.prop('checked', check).change();
      };

      $('th.read,th.write').click(function() { fn(this); })
        .keydown(function(e) { if (e.which == 13 || e.which == 32) fn(this); });
    }
  }
});


  // delegates list onclick even handler
rcube_webmail.prototype.select_delegate = function(list)
{
  this.env.active_delegate = list.get_single_selection();

  if (this.env.active_delegate)
    this.delegate_select(this.env.active_delegate);
  else if (this.env.contentframe)
    this.show_contentframe(false);
};

// select delegate
rcube_webmail.prototype.delegate_select = function(id)
{
  var win, target = window, url = '&_action=plugin.delegation';

  if (id)
    url += '&_id='+urlencode(id);
  else {
    this.show_contentframe(false);
    return;
  }

  if (win = this.get_frame_window(this.env.contentframe)) {
    target = win;
    url += '&_framed=1';
  }

  if (String(target.location.href).indexOf(url) >= 0)
    this.show_contentframe(true);
  else
    this.location_href(this.env.comm_path+url, target, true);
};

  // display new delegate form
rcube_webmail.prototype.delegate_add = function()
{
  var win, target = window, url = '&_action=plugin.delegation';

  this.delegatelist.clear_selection();
  this.env.active_delegate = null;
  this.show_contentframe(false);

  if (win = this.get_frame_window(this.env.contentframe)) {
    target = win;
    url += '&_framed=1';
  }

  this.location_href(this.env.comm_path+url, target, true);
};

  // handler for delete commands
rcube_webmail.prototype.delegate_delete = function()
{
  if (!this.env.active_delegate)
    return;

  var $dialog = $("#delegate-delete-dialog").addClass('uidialog'),
    buttons = {};

  buttons[this.gettext('no', 'kolab_delegation')] = function() {
    $dialog.dialog('close');
  };
  buttons[this.gettext('yes', 'kolab_delegation')] = function() {
    $dialog.dialog('close');
    var lock = rcmail.set_busy(true, 'kolab_delegation.savingdata');
    rcmail.http_post('plugin.delegation-delete', {id: rcmail.env.active_delegate,
      acl: $("#delegate-delete-dialog input:checked").length}, lock);
  }

  // open jquery UI dialog
  $dialog.dialog({
    modal: true,
    resizable: false,
    closeOnEscape: true,
    title: this.gettext('deleteconfirm', 'kolab_delegation'),
    close: function() { $dialog.dialog('destroy').hide(); },
    buttons: buttons,
    width: 400
  }).show();
};

  // submit delegate form to the server
rcube_webmail.prototype.delegate_save = function()
{
  var data = {id: this.env.active_delegate},
    lock = this.set_busy(true, 'kolab_delegation.savingdata');

  // new delegate
  if (!data.id) {
    data.newid = $('#delegate').val().replace(/(^\s+|[\s,]+$)/, '');
    if (data.newid.match(/\s*\(([^)]+)\)$/))
      data.newid = RegExp.$1;
  }

  data.folders = {};
  $('input.read').each(function(i, elem) {
    data.folders[elem.value] = this.checked ? 1 : 0;
  });
  $('input.write:checked').each(function(i, elem) {
    data.folders[elem.value] = 2;
  });

  this.http_post('plugin.delegation-save', data, lock);
};

// callback function when saving/deleting has completed successfully
rcube_webmail.prototype.delegate_save_complete = function(p)
{
  // delegate created
  if (p.created) {
    var input = $('#delegate'),
      row = $('<tr><td></td></tr>'),
      rc = this.is_framed() ? parent.rcmail : this;

    // remove delegate input
    input.parent().append($('<span></span>').text(p.name));
    input.remove();

    // add delegate row to the list
    row.attr('id', 'rcmrow'+p.created);
    $('td', row).text(p.name);

    rc.delegatelist.insert_row(row.get(0));
    rc.delegatelist.highlight_row(p.created);

    this.env.active_delegate = p.created;
    rc.env.active_delegate = p.created;
    rc.enable_command('delegate-delete', true);
  }
  // delegate updated
  else if (p.updated) {
    // do nothing
  }
  // delegate deleted
  else if (p.deleted) {
    this.env.active_delegate = null;
    this.delegate_select();
    this.delegatelist.remove_row(p.deleted);
    this.enable_command('delegate-delete', false);
  }
};

rcube_webmail.prototype.event_delegator_request = function(data)
{
  if (!this.env.delegator_context)
    return;

  if (typeof data === 'object')
    data._context = this.env.delegator_context;
  else
    data += '&_context=' + this.env.delegator_context;

  return data;
};

// callback for calendar event/task form initialization
rcube_webmail.prototype.calendar_event_init = function(data, type)
{
  var folder = data.o[type == 'calendar' ? 'calendar' : 'list']

  // set identity for delegator context
  this.env[type + '_settings'].identity = this.calendar_folder_delegator(folder, type);
};

// returns delegator's identity data according to selected calendar/tasks folder
rcube_webmail.prototype.calendar_folder_delegator = function(folder, type)
{
  var d, delegator,
    settings = this.env[type + '_settings'],
    list = this.env[type == 'calendar' ? 'calendars' : 'tasklists'];

  // derive delegator from the calendar owner property
  if (list[folder] && list[folder].owner) {
    delegator = list[folder].owner.replace(/@.+$/, '');
  }

  if (delegator && (d = this.env.delegators[delegator])) {
    // find delegator's identity id
    if (!d.identity_id)
      $.each(settings.identities, function(i, v) {
        if (d.email == v) {
          d.identity_id = i;
          return false;
        }
      });

    d.uid = delegator;
  }
  else
    d = this.env.original_identity;

  this.env.delegator_context = d.uid;

  return d;
};

// handler for calendar/tasklist folder change
rcube_webmail.prototype.calendar_folder_change = function(element)
{
  var folder = $(element).val(),
    type = element.id.indexOf('task') > -1 ? 'tasklist' : 'calendar',
    sname = type + '_settings',
    select = $('#edit-identities-list'),
    old = this.env[sname].identity;

  this.env[sname].identity = this.calendar_folder_delegator(folder, type);

  // change organizer identity in identity selector
  if (select.length && old != this.env[sname].identity) {
    var id = this.env[sname].identity.identity_id;
    select.val(id || select.find('option').first().val()).change();
  }
};

// modify default identity of the user
rcube_webmail.prototype.calendar_identity_init = function(type)
{
  var identity = this.env[type + '_settings'].identity,
    emails = identity.emails.split(';');

  // remove delegators' emails from list of emails of the current user
  emails = $.map(emails, function(v) {
    for (var n in rcmail.env.delegators)
      if (rcmail.env.delegators[n].emails.indexOf(';'+v) > -1)
        return null;
    return v;
  });

  identity.emails = emails.join(';');
  this.env.original_identity = identity;
};
