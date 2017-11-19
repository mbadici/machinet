
function file_editor()
{
  this.editable = false;
  this.printable = false;

  this.init = function()
  {
    document.getElementsByTagName('form')[0].submit();
  };

  // switch editor into read-write mode
  this.enable = function()
  {
    // @TODO
  };

  // switch editor into read-only mode
  this.disable = function()
  {
    // @TODO
  };

  this.getContent = function()
  {
    // @TODO
  };

  // print file content
  this.print = function()
  {
    // @TODO
  };
}
