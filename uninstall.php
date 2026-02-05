<?php
// 判断是不是从 WordPress 后台调用的
if(!defined("WP_UNINSTALL_PLUGIN"))
    exit();
delete_option("_lezaiyun_leseo_option");