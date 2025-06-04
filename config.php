<?php

//RI- WEB Configurator

$config_version = 1; //version of config
$defaultTheme = 'ri';// Use ri if not set
$themeSelectorEnabled = true;   // Enable selector unless explicitly disabled
$cssFolder =  __DIR__ . '/components/css';  //  theme CSS folder

// components of the system.

$Components_dir=__DIR__ . '/components/'; //the directory, where all following components have to be placed!

$navbar=true; //above everything, sticky on desktops, usefull for navigation. Should contain the navigation links.
$Navbar='navbar.html'; //name of the navbar file.

$motd=true; //below the navbar, usefull for content (like logos or so) that should be on all sites.
$Motd='motd.php'; //name of this file

$footer=true; //below the content, on all pages.
$Footer='foot.html'; //name of this file

//Extensions, optional.

//pages
$page=true; //the p extension. Needed, if you want to use the file based - cms - like system. You shouldt deactivate it. You need to configure the allowed pages.
$allowed_pages = ['home.php']; //the allowed pages to be loaded into the content section via a link. php include is not affected!
$Page_dir = __DIR__ . '/pages/'; // the allowed pages must be in this directory. No subfolders allowed. If you want to use sub- folders, please use the blog system.
$page_default = 'home.php'; //displayed home page (nothing is specified, must be in page directory)

//blog system
$blog=true; //The Markdown blog extension. Needs Parsedown.php at document root! 
$Blog_dir=__DIR__ . '/blog/'; //blog directory.

//download system, not implemented yet!
$download=false; //not implemented yet!
$Download_config='config-d.php'; //not implemented yet.

//Security

//passwords 
$update_pw = 'changeme!'; //password used for accessing the update.php
$config_pw = 'changeme!'; //password used for accessing gui config. (c.php)
$edit_pw = 'changeme!'; //password used for accessing the editor (edit.php)
$no_edit = ['index.php','config.php','c.php', 'edit.php', 'update.php']; // hidden files/ folders, supports wildcard, no edit / no viewing is allowed!

?>
