<?php 
$row = $mysqli->query("
    SELECT COUNT(*) users,
           (SELECT COUNT(*) FROM files WHERE tenant_id={$_SESSION['tid']}) files,
           (SELECT SUM(downloads) FROM files WHERE tenant_id={$_SESSION['tid']}) dl
    FROM users WHERE tenant_id={$_SESSION['tid']}
")->fetch_assoc();
?>
<div class="grid grid-cols-3 gap-4 text-center">
  <div class="bg-white p-4 shadow rounded">Users<br><span class="text-2xl font-bold"><?=$row['users']?></span></div>
  <div class="bg-white p-4 shadow rounded">Files<br><span class="text-2xl font-bold"><?=$row['files']?></span></div>
  <div class="bg-white p-4 shadow rounded">Downloads<br><span class="text-2xl font-bold"><?=$row['dl']??0?></span></div>
</div>