<?php
function m2p_db_connect()
{
  global $conf;

  try
  {
    pwg_db_connect(
      $conf['menalto2piwigo']['db_host'],
      $conf['menalto2piwigo']['db_user'],
      $conf['menalto2piwigo']['db_password'],
      $conf['menalto2piwigo']['db_name']
      );
  }
  catch (Exception $e)
  {
    my_error(l10n($e->getMessage()), true);
  }

  pwg_db_check_charset();

  return true;
}

function m2p_db_disconnect()
{
  global $conf;

  // reconnect to the Piwigo database
  $pwg_db_link = pwg_db_connect(
    $conf['db_host'],
    $conf['db_user'],
    $conf['db_password'],
    $conf['db_base']
    );

  pwg_db_check_charset();
}

function m2p_remove_bbcode($string)
{
  $patterns = array(
    '\[color=\w+\]',
    '\[/color\]',
    '\[b\]',
    '\[/b\]',
    '\[i\]',
    '\[/i\]',
    );

  foreach ($patterns as $pattern)
  {
    $string = preg_replace('#'.$pattern.'#', '', $string);
  }

  return $string;
}
