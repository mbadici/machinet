<body>
<form method=post action=index.php?module=domains&view=selected.tpl>
<select name=selecteddomain>

{section   loop=$alist name=ind}
<option> {$alist[ind][1]}</option>
{/section}

</select>
<input type=submit value=select>
</form>
</body>
