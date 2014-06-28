<?php
// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based picture gallery                                  |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if( !defined("PHPWG_ROOT_PATH") )
{
  die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(M2P_PATH.'include/functions.inc.php');

$admin_base_url = get_root_url().'admin.php?page=plugin-menalto2piwigo';
load_language('plugin.lang', dirname(__FILE__).'/');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_WEBMASTER);

// +-----------------------------------------------------------------------+
// | load database config for Menalto                                      |
// +-----------------------------------------------------------------------+

if (isset($conf['menalto2piwigo']))
{
  $conf['menalto2piwigo'] = unserialize($conf['menalto2piwigo']);
}
else
{
  $conf['menalto2piwigo'] = array(
    'db_host' => $conf['db_host'],
    'db_user' => $conf['db_user'],
    'db_password' => $conf['db_password'],
    'db_name' => '',
    'prefix_table' => 'g2_',
    'prefix_column' => 'g_',
    );
}

// +-----------------------------------------------------------------------+
// | import data from Menalto                                              |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit']))
{
  foreach (array_keys($conf['menalto2piwigo']) as $key)
  {
    $conf['menalto2piwigo'][$key] = $_POST[$key];
  }
  
  conf_update_param('menalto2piwigo', serialize($conf['menalto2piwigo']));
  
  $pt = $conf['menalto2piwigo']['prefix_table'];
  $pc = $conf['menalto2piwigo']['prefix_column'];

  // build an associative array like
  //
  // $piwigo_paths = array(
  //   [album 1] => c2
  //   [album 1/20081220_173438-014.jpg] => i1
  //   [album 1/20081220_173603-017.jpg] => i2
  //   [album 1/sub-album 1.1] => c3
  //   [album 1/sub-album 1.1/1.1.1] => c4
  //   [album 1/sub-album 1_2] => c5
  //   [album 2] => c1
  //   [album 2/20091011_164753-127.jpg] => i3
  //   [album 2/20091102_172719-190.jpg] => i4
  // );
  //
  // cN for categories, iN for images
  $piwigo_paths = array();

  $query = '
SELECT
    id,
    REPLACE(path, "./galleries/", "") AS filepath
  FROM '.IMAGES_TABLE.'
  WHERE path like "./galleries/%"
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    $piwigo_paths[$row['filepath']] = 'i'.$row['id'];
  }

  $query = '
SELECT
    id,
    dir,
    uppercats
  FROM '.CATEGORIES_TABLE.'
  WHERE site_id = 1
;';
  $albums = query2array($query, 'id');

  foreach ($albums as $id => $album)
  {
    $path_tokens = array();
    foreach (explode(',', $album['uppercats']) as $uppercat_id)
    {
      $path_tokens[] = $albums[$uppercat_id]['dir'];
    }
    
    $albums[$id]['path'] = implode('/', $path_tokens);
  }

  foreach ($albums as $id => $album)
  {
    $piwigo_paths[$album['path']] = 'c'.$id;
  }
  unset($albums);

  ksort($piwigo_paths);
  // echo '<pre>'; print_r($piwigo_paths); echo '</pre>';

  m2p_db_connect();

  // Gallery2 parent Ids (root is always 7!)
  $ids = array(7,0,0,0,0,0);
  // Piwigo uppercats
  $uct = array('NULL',0,0,0,0,0);
  $ranks = array();

  foreach ($piwigo_paths as $dir => $piwigo_id)
  {
    $path = explode('/', $dir);
    $basename = $path[count($path)-1];
    $level = count($path);

    $parentId = $ids[$level-1];

    // get id and title/summary/description of tail element in path
    $query = "
SELECT 
    f.".$pc."id,
    i.".$pc."title,
    i.".$pc."summary,
    i.".$pc."description,
    i.".$pc."canContainChildren,
    a.".$pc."orderWeight,
    a.".$pc."viewCount,
    FROM_UNIXTIME(e.".$pc."creationTimestamp)
  FROM ".$pt."Item i
    JOIN ".$pt."FileSystemEntity f ON i.".$pc."id = f.".$pc."id
    JOIN ".$pt."ChildEntity c ON f.".$pc."id = c.".$pc."id
    JOIN ".$pt."ItemAttributesMap a ON i.".$pc."id = a.".$pc."itemId
    JOIN ".$pt."Entity e ON e.".$pc."id = i.".$pc."id
  WHERE c.".$pc."parentId = ".$parentId."
    AND f.".$pc."pathComponent='".$basename."'
;";
    // echo '<pre>'.$query."</pre>";
    $row = pwg_db_fetch_row(pwg_query($query));
    
    // print "$row[4] - $parentId -> $row[0] : $row[1] $row[2] $row[3]\n";
    $title = m2p_remove_bbcode($row[1]);
    $summary = m2p_remove_bbcode($row[2]);
    $description = m2p_remove_bbcode($row[3]);
    $weight = $row[5];
    $views = $row[6];
    $date_available = $row[7];
    $ids[$level] = $row[0];
    $pid[$row[0]] = $dir;

    if ($row[4] == 0)
    {
      // Menalto says it's an image

      if (strpos($piwigo_id, 'i') === false)
      {
        echo 'Error, '.$piwig_id.' is not an image and Menalto says it is an image';
      }
      
      $comment = "";
      if ( $summary != "" and $description != "" )
      {
        $comment = "<b>$summary</b> - $description";
      }
      else
      {
        if ($summary != "")
        {
          $comment = $summary;
        }
        else
        {
          $comment = $description;
        }
      }

      $image_id = substr($piwigo_id, 1);
      
      $image_updates[] = array(
        'id' => $image_id,
        'name' => pwg_db_real_escape_string($title),
        'comment' => pwg_db_real_escape_string($comment),
        'date_available' => $date_available,
        );
      
      // build a map from gallery2 ids to piwigo image ids
      $iid[$row[0]] = $image_id;
    }
    else
    {
      // album (folder)
      if (strpos($piwigo_id, 'c') === false)
      {
        echo 'Error, '.$piwig_id.' is not an album and Menalto says it is an album';
      }
      
      $comment = "";
      if ( $summary != "" and $description != "" )
      {
        $comment = "$summary <!--complete--> $description";
      }
      else
      {
        if ($summary != "")
        {
          $comment = $summary;
        }
        else
        {
          $comment = "<!--complete-->$description";
        }
      }

      // get piwigo category id
      $cat_id = substr($piwigo_id, 1);
      $uct[$level] = $cat_id;
      
      $cat_updates[] = array(
        'id' => $cat_id,
        'name' => pwg_db_real_escape_string($title),
        'comment' => pwg_db_real_escape_string($comment),
        'rank' => $weight,
        );

      // get highlight picture 
      $query = "
SELECT d2.".$pc."derivativeSourceId 
  FROM ".$pt."ChildEntity c
    JOIN ".$pt."Derivative d1 ON c.".$pc."id = d1.".$pc."id
    JOIN ".$pt."Derivative d2 ON d1.".$pc."derivativeSourceId=d2.".$pc."id
  WHERE c.".$pc."parentId = ".$ids[$level];
      $subresult = pwg_query($query);
      $subrow = pwg_db_fetch_row($subresult);
      $hid[$cat_id] = $subrow[0];
    }
  }

  // apply highlites as representative images
  foreach ($hid as $cat_id => $menalto_id)
  {
    if (!empty($menalto_id))
    {
      $album_thumbs[] = array(
        'id' => $cat_id,
        'representative_picture_id' => $iid[$menalto_id],
        );
    }
  }

  // copy comments
  $query = "
SELECT
    c.".$pc."parentId AS id,
    t.".$pc."subject AS subject,
    t.".$pc."comment AS comment,
    t.".$pc."author AS author,
    FROM_UNIXTIME(t.".$pc."date) AS date
  FROM ".$pt."ChildEntity c
    JOIN ".$pt."Comment t ON t.".$pc."id = c.".$pc."id
  WHERE t.".$pc."publishStatus=0
";
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    if (isset($iid[ $row['id'] ]))
    {
      $comment = $row['comment'];
      if (!empty($row['subject']))
      {
        $comment = '[b]'.$row['subject'].'[/b] '.$comment;
      }

      $comment_inserts[] = array(
        'image_id' => $iid[ $row['id'] ],
        'date' => $row['date'],
        'author' => pwg_db_real_escape_string($row['author']),
        'content' => pwg_db_real_escape_string($comment),
        'validated' => 'true',
        );
    }
  }

  m2p_db_disconnect();

  // echo '<pre>'; print_r($image_updates); echo '</pre>';
  // echo '<pre>'; print_r($cat_updates); echo '</pre>';
  // echo '<pre>'; print_r($hid); echo '</pre>';
  // echo '<pre>'; print_r($iid); echo '</pre>';
  // echo '<pre>'; print_r($album_thumbs); echo '</pre>';
  // echo '<pre>'; print_r($comment_inserts); echo '</pre>';
  
  mass_updates(
    IMAGES_TABLE,
    array(
      'primary' => array('id'),
      'update'  => array('name', 'comment', 'date_available'),
      ),
    $image_updates
    );

  mass_updates(
    CATEGORIES_TABLE,
    array(
      'primary' => array('id'),
      'update'  => array('name', 'comment', 'rank'),
      ),
    $cat_updates
    );

  mass_updates(
    CATEGORIES_TABLE,
    array(
      'primary' => array('id'),
      'update'  => array('representative_picture_id'),
      ),
    $album_thumbs
    );

  mass_inserts(
    COMMENTS_TABLE,
    array_keys($comment_inserts[0]),
    $comment_inserts
    );
  
  array_push($page['infos'], l10n('Information data registered in database'));
}


// +-----------------------------------------------------------------------+
// | Template & Form                                                       |
// +-----------------------------------------------------------------------+

$template->set_filenames(
  array(
    'plugin_admin_content' => dirname(__FILE__).'/admin.tpl'
    )
  );

$template->assign('action_url', $admin_base_url);
$template->assign($conf['menalto2piwigo']);

// +-----------------------------------------------------------------------+
// | Sending html                                                          |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
?>
