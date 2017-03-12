<html>
<head>
</head>

<body>
<table>
{section   loop=$alist name=ind}
<tr> <td> <a href = index.php?module=users&view=list.tpl&user={$alist[ind][0]|escape: 'url'}>{$alist[ind][1]}</a></td><td><a href = index.php?module=groups&view=delete.tpl&user={$alist[ind][0]|escape: 'url'}>Delete</a></td> </tr>
{/section}

</table>

</body>
</html>