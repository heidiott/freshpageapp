#!/usr/bin/env php
<?php
// cron/update_usage.php
require_once __DIR__ . '/../inc/db.php';   // ← adjust path to your DB helper

echo date('[Y-m-d H:i:s] ') . "Recalculating tenant storage …\n";

/* Single SQL does it all:
   - SUM(size) per tenant (bytes → MB)
   - NULL tenants get 0
*/
$sql = "
  UPDATE tenants t
  LEFT JOIN (
      SELECT tenant_id,
             ROUND(SUM(size)/1048576) AS used_mb   -- 1 MiB = 1 048 576 bytes
      FROM   files
      GROUP  BY tenant_id
  ) f ON f.tenant_id = t.id
  SET t.storage_used_mb = COALESCE(f.used_mb,0)
";

if ($mysqli->query($sql)) {
    echo "OK – updated {$mysqli->affected_rows} row(s)\n";
} else {
    echo "ERROR: " . $mysqli->error . "\n";
    exit(1);
}