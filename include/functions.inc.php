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
    pwg_db_connect(
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

function m2p_replace_bbcode($string)
{
    $patterns = array(
        '@\[color=(.+?)\](.*?)\[/color\]@s',
        '@\[url=(.+?)\](.*?)\[/url\]@s',
        '@\[b\](.*?)\[/b\]@s',
        '@\[i\](.*?)\[/i\]@s',
    );
    $replace = array(
        '<span style="color:${1}">${2}</span>',
        '<a href="${1}" target="_blank">${2}</a>',
        '<b>$1</b>',
        '<i>$1</i>',
    );

    $string = preg_replace($patterns, $replace, $string);

    return $string;
}

/**
 * list all tables in an array
 *
 * @return array
 */
function m2p_get_tables($prefix='')
{
  $tables = array();

  $query = '
SHOW TABLES
;';
  $result = pwg_query($query);

  while ($row = pwg_db_fetch_row($result))
  {
    if (preg_match('/^'.$prefix.'/', $row[0]))
    {
      $tables[] = $row[0];
    }
  }

  return $tables;
}