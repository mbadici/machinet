<?php
  
//$tpl=str_replace(".php",".tpl",$_SERVER['PHP_SELF']);
require_once("../code/functions.php");
   $alist= list_users("NULL","users","tralala.la");
    echo '<select id="users" multiple="multiple" onclick="addItem()">';
    foreach($alist as $elm) {
   echo "<option value='".$elm[0]."'>" ;
   echo $elm[1];
   echo "</option>";
   }
   
//   $smarty->assign('alist',$alist);
//$smarty->display($module."/".$view);

?>
