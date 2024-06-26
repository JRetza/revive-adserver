<?php

/*
+---------------------------------------------------------------------------+
| Revive Adserver                                                           |
| http://www.revive-adserver.com                                            |
|                                                                           |
| Copyright: See the COPYRIGHT.txt file.                                    |
| License: GPLv2 or later, see the LICENSE.txt file.                        |
+---------------------------------------------------------------------------+
*/

/**
 * OpenX xAjax tester
 */

require_once '../../../../init.php';
require_once '../../config.php';
require_once 'lib/demoXajax.inc.php';
//$xajax->debugOn();
$xajax->debugOff();

$show = $_REQUEST['show'];

phpAds_PageHeader("demo-xajax-{$show}", '', '../../');

switch ($show) {
    case 'menu': // top level menu
    case 'home': // info page
        include "templates/home.html";
        break;
    case 'noframe': // 1st menu
        include "templates/noframe.html";
        break;
    case 'frame': // 2nd menu
        $src = $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/demoXajax-frame.php';
        include "templates/frame.html";
        break;
    case 'frame-smarty': // 3rd menu
        require_once MAX_PATH . '/lib/OA/Admin/TemplatePlugin.php';
        $oTpl = new OA_Plugin_Template('frame-smarty.html', 'demoXajax');
        $oTpl->debugging = false;
        $src = $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/demoXajax-frame.php';
        $oTpl->assign('src', $src);
        $oTpl->display();
        break;
    case 'noframe-smarty': // 4th menu
        require_once MAX_PATH . '/lib/OA/Admin/TemplatePlugin.php';
        $oTpl = new OA_Plugin_Template('noframe.html', 'demoXajax');
        $oTpl->debugging = false;
        $oTpl->display();
        break;
}

phpAds_PageFooter();
