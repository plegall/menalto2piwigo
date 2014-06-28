<?php
/*
Plugin Name: Menalto2Piwigo
Version: auto
Description: import data from a Menalto Gallery into Piwigo
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=
Author: plg
Author URI: http://le-gall.net/pierrick
*/

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+

defined('M2P_ID') or define('M2P_ID', basename(dirname(__FILE__)));
define('M2P_PATH' ,   PHPWG_PLUGINS_PATH.M2P_ID . '/');

// +-----------------------------------------------------------------------+
// | Add event handlers                                                    |
// +-----------------------------------------------------------------------+

add_event_handler('get_admin_plugin_menu_links', 'm2p_admin_menu');
function m2p_admin_menu($menu)
{
  array_push(
    $menu,
    array(
      'NAME' => 'Menalto2Piwigo',
      'URL'  => get_root_url().'admin.php?page=plugin-'.M2P_ID,
      )
    );

  return $menu;
}
?>
