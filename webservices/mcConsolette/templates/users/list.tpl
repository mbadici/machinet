<html>
<head>
 <link rel="stylesheet" type="text/css" href="css/common.css" />
{literal}
<script language="javascript"> 

   function DoPost(userdn){
     var  allstring = '{userdn: userdn,op: "disable"}';
         $.post('index.php?module=users&view=change.tpl' , {userdn: userdn,op: "disable"});  //Your values here..
            };
</script>
{/literal}
</head>

<body>
{$domain}
<table border=1>
{section   loop=$alist name=ind}
<tr> <td> <a href = index.php?module=users&view=detail.tpl&user={$alist[ind][0]|escape: 'url'}>{$alist[ind][1]}</a></td><td><a href = index.php?module=users&view=delete.tpl&user={$alist[ind][0]|escape: 'url'}>Delete</a></td><td><a href="javascript:DoPost('{$alist[ind][0]}')">Disable</a></td><td><a href = index.php?module=users&view=changepass.tpl&user={$alist[ind][0]|escape: 'url'}>Change password</a></td> </tr>
{/section}

</table>
<a href = index.php?module=users&view=new.tpl>Create new</a>

</body>
</html>