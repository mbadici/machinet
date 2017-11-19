function file_list_sort(name, elem)
{
  var td = $(elem), reverse = ui.env.sort_reverse;

  if (ui.env.sort_col == name)
    reverse = !reverse;
  else
    reverse = 0;

  $('td', td.parent()).removeClass('sorted reverse');
  td.addClass('sorted').removeClass('reverse');

  if (reverse)
    td.addClass('reverse');

  ui.file_list_sort(name, reverse);
};

function hack_file_input(id)
{
  var link = $('#'+id),
    file = $('<input>'),
    offset = link.offset();

  function move_file_input(e) {
    file.css({top: (e.pageY - offset.top - 10) + 'px', left: (e.pageX - offset.left - 10) + 'px'});
  }

  file.attr({name: 'file[]', type: 'file', multiple: 'multiple', size: 5, title: ''})
    .change(function() { ui.file_upload(); })
    .click(function() { setTimeout(function() { link.mouseleave(); }, 20); })
    // opacity:0 does the trick, display/visibility doesn't work
    .css({opacity: 0, cursor: 'pointer', position: 'relative', outline: 'none'});

  // In FF and IE we need to move the browser file-input's button under the cursor
  // Thanks to the size attribute above we know the length of the input field
  if (navigator.userAgent.match(/Firefox|MSIE/))
    file.css({marginLeft: '-80px'});

  // Note: now, I observe problem with cursor style on FF < 4 only
  link.css({overflow: 'hidden', cursor: 'pointer'})
    .mouseenter(function() { this.__active = ui.commands['file.upload'] ? true : false; })
    // place button under the cursor
    .mousemove(function(e) {
      if (ui.commands['file.upload'] && this.__active)
        move_file_input(e);
      // move the input away if button is disabled
      else
        $(this).mouseleave();
    })
    .mouseleave(function() {
      file.css({top: '-10000px', left: '-10000px'});
      this.__active = false;
    })
    .click(function(e) {
      // forward click if mouse-enter event was missed
      if (ui.commands['file.upload'] && !this.__active) {
        this.__active = true;
        move_file_input(e);
        file.trigger(e);
      }
    })
    .mouseleave()
    .append(file);
};

function progress_update(data)
{
  var txt = ui.t('file.progress'), id = 'progress' + data.id,
    table = $('#' + id), content = $('#info' + id),
    i, row, offset, rows = [];

  if (!data || data.done || !data.total) {
    if (table.length) {
      table.remove();
      content.remove();
    }
    return;
  }

  if (!table.length) {
    table = $('<table class="progress" id="' + id + '"><tr><td class="bar"></td><td></td></tr></table>');
    content = $('<table class="progressinfo" id="info' + id + '"></table>');

    table.appendTo($('#actionbar'))
      .on('mouseleave', function() { content.hide(); })
      .on('mouseenter', function() { if (content.children().length) content.show(); });

    offset = table.offset();
    content.css({display: 'none', position: 'absolute', top: offset.top + 8, left: offset.left})
      .appendTo(document.body);
  }

  $('td.bar', table).width((data.percent || 1) + '%');

  if (data.total) {
    rows[ui.t('upload.size')] = ui.file_size(data.total);
    rows[ui.t('upload.progress')] = (data.percent || 0) + '%';
    if (data.rate)
      rows[ui.t('upload.rate')] = ui.file_size(data.rate) + '/s';
    if (data.eta)
      rows[ui.t('upload.eta')] = ui.time_format(data.eta);

    content.empty();

    for (i in rows)
      $('<tr>').append($('<td class="label">').text(i))
        .append($('<td class="value">').text(rows[i]))
        .appendTo(content);
  }
};

// activate html5 file drop feature (if browser supports it)
function init_drag_drop(container)
{
  if (!window.FormData && !(window.XMLHttpRequest && XMLHttpRequest.prototype && XMLHttpRequest.prototype.sendAsBinary)) {
    return;
  }

  $(document.body).bind('dragover dragleave drop', function(e) {
    if (!ui.env.folder)
      return;

    e.preventDefault();
    container[e.type == 'dragover' ? 'addClass' : 'removeClass']('active');
  });

  container.bind('dragover dragleave', function(e) {
    return drag_hover(e, e.type == 'dragover');
  })
  container.children('table').bind('dragover dragleave', function(e) {
    return drag_hover(e, e.type == 'dragover');
  })
  container.get(0).addEventListener('drop', function(e) {
      // abort event and reset UI
      drag_hover(e, false);
      return ui.file_drop(e);
    }, false);
};

// handler for drag/drop on element
function drag_hover(e, over)
{
  if (!ui.env.folder)
    return;

  e.preventDefault();
  e.stopPropagation();

  var elem = $(e.target);

  if (!elem.hasClass('droptarget'))
    elem = elem.parents('.droptarget');

  elem[over ? 'addClass' : 'removeClass']('hover');
};

function enable_command_handler(p)
{
  if (p.command == 'file.save') {
    $('#'+ui.buttons['file.edit']).css('display', p.status ? 'none' : 'block');
    $('#'+ui.buttons['file.save']).css('display', p.status ? 'block' : 'none');
  }
};


$(window).load(function() {
  hack_file_input('file-upload-button');
  $('#forms > form').hide();
  ui.add_event_listener('enable-command', enable_command_handler);
  init_drag_drop($('#taskcontent'));
});

// register buttons
ui.buttons({
'folder.create': 'folder-create-button',
'folder.edit': 'folder-edit-button',
'folder.delete': 'folder-delete-button',
'file.upload': 'file-upload-button',
'file.search': 'file-search-button',
'file.delete': 'file-delete-button',
'file.download': 'file-download-button',
'file.edit': 'file-edit-button',
'file.copy': 'file-copy-button',
'file.move': 'file-move-button',
'file.save': 'file-save-button'
});
