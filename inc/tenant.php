<?php
if (is_logged_in()) {
    $tenant = $mysqli->query("SELECT * FROM tenants WHERE id=".$_SESSION['tid'])->fetch_assoc();
    echo "<style>
        :root{
          --brand-primary: {$tenant['color_primary']};
          --brand-accent : {$tenant['color_accent']};
        }
      </style>";
}