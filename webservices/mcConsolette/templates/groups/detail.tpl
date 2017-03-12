
list user
{$smarty.get.user}

<br>
<form method=post action=index.php?module=groups&view=change.tpl>
<table>
{foreach $result  as $ind}
<tr>
{if $ind@key  eq "member"}

{$j=0}
{section name=email loop=$ind}
<tr>
{if $ind[email]!=NULL}
<td> {$ind@key} </td> <td><input type=text  name= member[{$j}] value="{$ind[email]}"> </td> <td> <button type=submit value="{$ind[email]}" name="op">Del </button> </td>
{$j=$j+1}
{/if} 
</tr>

{/section}
<div id=newform>

<tr>
<td>member </td><td><input type=text   name= member[{$j}] value="" id="newmember"> </td>

</tr>
</div>
{else}
<tr>
<td>{$ind@key} </td> <td ><input type=text  name= {$ind@key}  value={$ind[0]}> </td>
</tr>
{/if}

{/foreach}
</table>

<input type=hidden name=userdn value="{$smarty.get.user}">

  <script type="text/javascript"  src="include/jquery.min.js">

</script>
    <script type="text/javascript" language="javascript">
    
    function DoPost($dn){
          $.post("index.php", { op: "disable", dn: $dn } );  //Your values here..
             }
var counter={$j};

    function addItem()
    {    
counter++;
var selectBox = document.getElementById("users");
var selectedValue = selectBox.options[selectBox.selectedIndex].value;

var newFields = document.getElementById('newmember').cloneNode(true);

newFields.id = '';
newFields.style.display = 'block';
var newField = newFields.childNodes;
//    for (var i=0;i<newField.lenght;i++) {
//	    var theName = newField[i].name
//		    if (theName)
				newFields.name = "member["+counter+"]";
//				    }
	newFields.value=selectedValue;

var insertHere = document.getElementById('newmember');
insertHere.parentNode.insertBefore(newFields,insertHere);
                  
      };
    $(document).ready(function() {
    $("#driver").click(function(event){

    $('#stage').load('list.php')
    });
    });

  </script>
<input type="button" id="driver" value="browse users" />

<div id="stage" >
 </div>
<input type=submit value=change name="op">
</form>

<a href= index.php?module=users&view=delete.tpl&user={$smarty.get.user|escape:'url'}> Delete</a>

