<?php

# Rename this file from "sample-config" to "config.php" and change the content or upload another configuration file.


################################################################
# BASIC CONFIGURATION
################################################################

# Path to lmutil & monitorlm File - Recommended to move to/opt/lmtools because of the index.php
$monitorlm_binary = '/Path/to/monitorlm'; # monitorlm Binary
$lm_home = "/Path/to/lmutil-Path"; # lmutil Binary Path not the File

# If $lm_home has lmstat and lmutil, do not change these 3  lines
$lmutil_loc = $lm_home . "/lmutil";
$lmstat_loc = $lmutil_loc . " lmstat";
$lmdiag_loc = $lmutil_loc . " lmdiag";

################################################################
# WARNING TIME FOR EXPIRING LICENSES (DAYS)
################################################################
$lead_time = 30;

################################################################
# COLOR CHOICE FOR BACKGROUNDS
################################################################
$colors = "#ffffdd,#ff9966,#ffffaa,#ccccff,#cccccc,#ffcc66,#99ff99,#eeeeee,#66ffff,#ccffff,#ffff66,#ffccff,#ff66ff,yellow,lightgreen,lightblue";

################################################################
# AUTO-REFRESH ON/OFF (0 = ON, 1 = OFF)
################################################################
$disable_autorefresh = 0;

################################################################
# ALLOW LICENSE REMOVAL VIA WEB INTERFACE (0 = yes, 1 = no)
################################################################
$disable_license_removal = 0;

################################################################
# ADMIN ACCESS (optional) IF USED, CHANGE IT!
################################################################
// $adminusername = "test";
// $adminpassword = "test";

################################################################
# DEBUG-MODE
################################################################
$debug = 1;

################################################################
# NOTIFICATION-EMAIL
################################################################
$notify_address = "test@domain.com";

################################################################
# DATA COLLECTION INTERVAL (minutes)
################################################################
$collection_interval = 10;

################################################################
# DATABASE-ACCESS-DATA
################################################################
$db_hostname = "localhost"; // Keep like that if you do everything step by step in the README.md
$db_username = "username"; // Change this to your username
$db_password = "password"; // Change this to your password
$db_database = "licenses"; // Keep like that if you do everything step by step in the README.md

$dsn = "mysqli://$db_username:$db_password@$db_hostname/$db_database";

?>

