<html>
<head>
<link rel="stylesheet" type="text/css" href="css/common.css" />
</head>
<body>
<table>
{section   loop=$alist name=ind}
<tr> <td> <a href = index.php?module=domains&view=detail.tpl&user={$alist[ind][0]|escape: 'url'}>{$alist[ind][1]}</a></td><td><a href = index.php?module=domains&view=delete.tpl&user={$alist[ind][0]|escape: 'url'}>Delete</a></td> </tr>
{/section}

</table>
<a href = index.php?module=domains&view=new.tpl>Create new</a>

</body>
</html>