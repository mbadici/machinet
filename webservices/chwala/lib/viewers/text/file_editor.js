
function file_editor()
{
  this.editable = true;
  this.printable = true;

  this.init = function(ed, mode, href)
  {
    this.href = href;
    this.editor = ace.edit(ed);
    this.session = this.editor.getSession();

    this.editor.focus();
    this.editor.setReadOnly(true);
    this.session.setMode('ace/mode/' + mode);
  };

  // switch editor into read-write mode
  this.enable = function()
  {
    this.editor.setReadOnly(false);
  };

  // switch editor into read-only mode
  this.disable = function()
  {
    this.editor.setReadOnly(true);
  };

  this.getContent = function()
  {
    return this.editor.getValue();
  };

  // print file content
  this.print = function()
  {
    // There's no print function in Ace Editor
    // it's also not possible to print the page as is
    // we'd copy the content to a hidden iframe
    if (!this.print_frame) {
      this.print_frame = document.createElement('iframe');
      document.body.appendChild(this.print_frame);
      this.print_frame.style.display = 'none';
      this.print_frame.onload = function() { this.focus(); this.contentWindow.print(); };
    }

    this.print_frame.src = this.href + '&force-type=text/plain';
  };
}
