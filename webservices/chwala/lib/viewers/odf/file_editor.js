
function file_editor()
{
  this.editable = true;
  this.printable = false;

  this.init = function(href)
  {
    this.href = href;
    this.load();
  };

  // load editor
  this.load = function()
  {
    this.editor = webodfEditor;
    this.editor.boot({'docUrl': this.href});
  };

  // switch editor into read-write mode
  this.enable = function()
  {
    this.editor.startEditing();
  };

  // switch editor into read-only mode
  this.disable = function()
  {
    // not implemented
    this.editor.stopEditing();
  };

  this.getContentCallback = function(callback)
  {
    // register global file save function
    window.saveAs = callback;
    // run save action
    this.editor.save();
  };

  // print file content
  this.print = function()
  {
    // There's no print function in WebODF editor
    // a possible solution is to convert the document to pdf
  };
}

var file_editor = new file_editor();

/* code based on localeditor.js */
var webodfEditor = (function () {
    runtime.currentDirectory = function () {
        return "../../webodf/lib";
    };
    runtime.libraryPaths = function () {
        return [ runtime.currentDirectory() ];
    };

    var editorInstance = null,
        booting = false,
        localMemberId = "localuser";

    function startEditing()
    {
        editorInstance.startEditing();
    }

    function stopEditing()
    {
        editorInstance.endEditing();
    }

    function save()
    {
        editorInstance.saveDocument(file_uri);
    }

    function boot(args) {
        var editorOptions = {};
        runtime.assert(!booting, "editor creation already in progress");

        args = args || {};

        // start the editor
        booting = true;

        runtime.assert(args.docUrl, "docUrl needs to be specified");
        runtime.assert(editorInstance === null, "cannot boot with instanciated editor");

        require({ }, ["webodf/editor/Editor"],
            function (Editor) {
                editorInstance = new Editor(editorOptions);
                editorInstance.openDocument(args.docUrl, localMemberId, function() {});
            }
        );
    }

    // exposed API
    return {
        boot: boot,
        save: save,
        stopEditing: stopEditing,
        startEditing: startEditing
    };
}());
