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

  // ksort($piwigo_paths, SORT_NATURAL);
  // echo '<pre>'; print_r($piwigo_paths); echo '</pre>';

  $query = '
SELECT
    id,
    name
  FROM '.TAGS_TABLE.'
;';
  $piwigo_tags = query2array($query, 'name', 'id');

  m2p_db_connect();

  // Gallery2 or Gallery3?
  $menalto_tables = m2p_get_tables($pt);

  if (in_array($pt.'FileSystemEntity', $menalto_tables))
  {
    $is_tag_installed = false;
    if (in_array($pt.'TagItemMap', $menalto_tables))
    {
        // tags plugin is installed
        $is_tag_installed = true;

    }
    // Gallery version 2

    $menalto_items = array();

    $query = "
SELECT
    f.".$pc."id AS id,
    i.".$pc."title AS title,
    i.".$pc."summary AS summary,
    i.".$pc."description AS description,
    i.".$pc."canContainChildren AS canContainChildren,
--    i.".$pc."ownerId AS ownerId,
    a.".$pc."orderWeight AS orderWeight,
    a.".$pc."viewCount AS viewCount,
    FROM_UNIXTIME(e.".$pc."creationTimestamp) AS created_on,
    c.".$pc."parentId AS parentId,
    f.".$pc."pathComponent AS pathComponent,
    d.".$pc."size AS filesize,";
    if ($is_tag_installed)
    {
        $query .= "group_concat(tm.".$pc."tagName) AS keywords ";
    }
    else
    {
        $query .= "i.".$pc."keywords AS keywords ";
    }
    $query .= "
  FROM ".$pt."Item i
    JOIN ".$pt."FileSystemEntity f ON i.".$pc."id = f.".$pc."id
    JOIN ".$pt."ChildEntity c ON f.".$pc."id = c.".$pc."id
    JOIN ".$pt."ItemAttributesMap a ON i.".$pc."id = a.".$pc."itemId
    JOIN ".$pt."Entity e ON e.".$pc."id = i.".$pc."id
    LEFT JOIN ".$pt."DataItem d ON d.".$pc."id = i.".$pc."id";
    if ($is_tag_installed)
    {
      $query .= "    LEFT JOIN ".$pt."TagItemMap tim ON i.".$pc."id = tim.".$pc."itemId
    LEFT JOIN ".$pt."TagMap tm ON tim.".$pc."tagId = tm.".$pc."tagId
    GROUP BY id
";
    }

    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result))
    {
      $menalto_items[ $row['id'] ] = $row;
    }

    // build the path for each item
    foreach ($menalto_items as $id => $item)
    {
      // 7 is root on Menalto
      if (7 == $id)
      {
        $menalto_items[$id]['path'] = '';
        continue;
      }

      $path = $item['pathComponent'];
      $parentId = $item['parentId'];
      $levels = 0;

      while ($parentId != 7)
      {
        $path = $menalto_items[ $parentId ]['pathComponent'].'/'.$path;
        $parentId = $menalto_items[ $parentId ]['parentId'];

        // avoid infinite loop
        if ($levels++ > 50)
        {
          die("stop execution, infinite loop to generate absolute path, for ".$item['pathComponent']);
        }
      }

      $menalto_items[$id]['path'] = $path;
    }

    $counter_not_found = 0;
    $counter_found = 0;
    $hid = array();

    foreach ($menalto_items as $id => $item)
    {
      if (isset($piwigo_paths[ $item['path'] ]))
      {
        $piwigo_id = $piwigo_paths[ $item['path'] ];
        $counter_found++;
      }
      else
      {
        if (7 != $item['id'])
        {
          // echo 'Match not found for #'.$item['path'].'#<br>';
          $counter_not_found++;
        }

        continue;
      }


      $title = m2p_remove_bbcode($item['title']);
      if (empty($title))
      {
        $title = $item['pathComponent'];
      }

      // todo: maybe as a user input field, choose replace or remove
      $summary = m2p_replace_bbcode($item['summary']);
      $description = m2p_replace_bbcode($item['description']);
      $weight = $item['orderWeight'];
      $views = $item['viewCount'];
      $date_available = $item['created_on'];
      $filesize = $item['filesize'];

      if ($item['canContainChildren'] == 0)
      {
        // Menalto says it's an image

        if (strpos($piwigo_id, 'i') === false)
        {
          echo 'Error, '.$piwigo_id.' is not an image and Menalto says it is an image';
        }

        $comment = "";
        if ( $summary != "" and $description != "" )
        {
          $comment = "$summary - $description";
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
          'date_creation' => $date_available,
          'date_available' => $date_available,
          'hit' => $views,
          'filesize' => round($filesize/1024),
//          'added_by' => $ownerId
          );

        // Copy the weight to piwigo's image_category database table.
        $image_category_updates[] = array(
          'image_id' => $image_id,
          'rank' => $weight,
          );

        // tags
        if (!empty($item['keywords']))
        {
          foreach (preg_split("/[,;]+/", $item['keywords']) as $keyword)
          {
            $keyword = trim($keyword);

            if (!isset($piwigo_tags[$keyword]) and !isset($menalto_keywords[$keyword]))
            {
              $tag_inserts[] = array(
                'name' => pwg_db_real_escape_string($keyword),
                'url_name' => str2url($keyword),
                );
            }

            $menalto_keywords[$keyword][] = $image_id;
          }
        }

        // build a map from gallery2 ids to piwigo image ids
        $iid[$item['id']] = $image_id;
      }
      else
      {
        // album (folder)
        if (strpos($piwigo_id, 'c') === false)
        {
          echo 'Error, '.$piwigo_id.' is not an album and Menalto says it is an album';
        }

        $comment = "";
        if ( $summary != "" and $description != "" )
        {
          $comment = "$summary<!--complete-->$description";
        }
        else
        {
          if ($summary != "")
          {
            $comment = "$summary<!--complete-->";
          }
          elseif ($description != "")
          {
            $comment = "<!--complete-->$description";
          }
        }

        // get piwigo category id
        $cat_id = substr($piwigo_id, 1);

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
  WHERE c.".$pc."parentId = ".$item['id'];      // See http://piwigo.org/forum/viewtopic.php?pid=158127#p158127
        $subresult = pwg_query($query);
        $subrow = pwg_db_fetch_row($subresult);
        $hid[$cat_id] = $subrow[0];
      }
    }

    // echo 'found = '.$counter_found.', not found = '.$counter_not_found.'<br>';

    // apply highlites as representative images
    foreach ($hid as $cat_id => $menalto_id)
    {
      if (!empty($menalto_id) and isset($iid[$menalto_id]))
      {
        $album_thumbs[] = array(
          'id' => $cat_id,
          'representative_picture_id' => $iid[$menalto_id],
          );
      }
    }

    // copy comments
    if (in_array($pt.'Comment', $menalto_tables))
    {
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
        'update'  => array('name', 'comment', 'date_creation', 'date_available', 'hit', 'filesize'), #, 'added_by'
        ),
      $image_updates
      );

    mass_updates(
      IMAGE_CATEGORY_TABLE,
      array(
        'primary' => array('image_id'),
        'update'  => array('rank'),
        ),
      $image_category_updates
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

    if (isset($comment_inserts) and count($comment_inserts) > 0)
    {
      mass_inserts(
        COMMENTS_TABLE,
        array_keys($comment_inserts[0]),
        $comment_inserts
        );
    }

    if (isset($tag_inserts) and count($tag_inserts) > 0)
    {
      mass_inserts(
        TAGS_TABLE,
        array_keys($tag_inserts[0]),
        $tag_inserts
        );

      // refresh the $piwigo_tags array with new tags from Menalto
      $query = '
SELECT
    id,
    name
  FROM '.TAGS_TABLE.'
;';
      $piwigo_tags = query2array($query, 'name', 'id');
    }

    if (isset($menalto_keywords) and count($menalto_keywords) > 0)
    {
      // avoid duplicates in table image_tag
      $image_tag_associations = array();

      $query = '
SELECT
    image_id,
    tag_id
  FROM '.IMAGE_TAG_TABLE.'
;';
      $result = pwg_query($query);
      while ($row = pwg_db_fetch_assoc($result))
      {
        $image_tag_associations[ $row['image_id'].'~'.$row['tag_id'] ] = 1;
      }

      foreach ($menalto_keywords as $keyword => $piwigo_ids)
      {
        if (!isset($piwigo_tags[$keyword]))
        {
          echo 'missing piwigo tag for keyword #'.$keyword.'#<br>';
          continue;
        }

        $tag_id = $piwigo_tags[$keyword];

        foreach ($piwigo_ids as $piwigo_id)
        {
          if (isset($image_tag_associations[$piwigo_id.'~'.$tag_id]))
          {
            continue;
          }

          $image_tag_inserts[] = array(
            'image_id' => $piwigo_id,
            'tag_id' => $tag_id,
            );
        }
      }

      if (isset($image_tag_inserts) and count($image_tag_inserts) > 0)
      {
        mass_inserts(
          IMAGE_TAG_TABLE,
          array_keys($image_tag_inserts[0]),
          $image_tag_inserts,
          array( 'ignore' => true )  // ignore duplicated tags added to item
          );
      }
    }

    array_push($page['infos'], l10n('Information data registered in database'));
  }
  elseif (in_array($pt.'items_tags', $menalto_tables))
  {
    // Gallery version 3

    $query = '
SELECT
    id,
    name,
    parent_id,
    relative_path_cache,
    title,
    description,
    type,
    view_count,
    created,
    weight,
    album_cover_item_id
  FROM '.$pt.'items
;';
    $result = pwg_query($query);
    $cover_id = array();
    while ($row = pwg_db_fetch_assoc($result))
    {
      $relative_path_cache = urldecode($row['relative_path_cache']);

      if (isset($piwigo_paths[ $relative_path_cache ]))
      {
        $piwigo_id = $piwigo_paths[ $relative_path_cache ];
      }
      else
      {
        continue;
      }

      if ('photo' == $row['type'])
      {
        $image_id = substr($piwigo_id, 1);

        $image_updates[] = array(
          'id' => $image_id,
          'name' => pwg_db_real_escape_string($row['title']),
          'comment' => pwg_db_real_escape_string($row['description']),
          'date_creation' => date('Y-m-d H:i:s', $row['created']),
          'date_available' => date('Y-m-d H:i:s', $row['created']),
          'hit' => $row['view_count'],
          );

        // build a map from menalto ids to piwigo image ids
        $iid[ $row['id'] ] = $image_id;
      }
      elseif ('album' == $row['type'])
      {
        $cat_id = substr($piwigo_id, 1);

        $cat_updates[] = array(
          'id' => $cat_id,
          'name' => pwg_db_real_escape_string($row['title']),
          'comment' => pwg_db_real_escape_string($row['description']),
          'rank' => $row['weight'],
          );

        $cover_id[$cat_id] = $row['album_cover_item_id'];
      }
    }

    // album cover id
    foreach ($cover_id as $cat_id => $menalto_id)
    {
      if (!empty($menalto_id) and isset($iid[$menalto_id]))
      {
        $album_thumbs[] = array(
          'id' => $cat_id,
          'representative_picture_id' => $iid[$menalto_id],
          );
      }
    }

    // user comments;
    $query = '
SELECT
    author_id,
    server_remote_addr,
    created,
    name,
    full_name,
    email,
    url,
    guest_email,
    guest_name,
    guest_url,
    item_id,
    state,
    text
  FROM '.$pt.'comments
    JOIN '.$pt.'users AS u ON author_id = u.id
;';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result))
    {
      if (isset($iid[ $row['item_id'] ]))
      {
        if (!empty($row['guest_name']))
        {
          $name = $row['guest_name'];
          $email = $row['guest_email'];
          $url = $row['guest_url'];
        }
        else
        {
          $name = $row['full_name'].' ('.$row['name'].')';
          $email = $row['email'];
          $url = $row['url'];
        }

        if (2 == $row['author_id']) // default admin on G3
        {
          $author_id = 1; // default admin on Piwigo
        }
        else
        {
          $author_id = 2; // guest on Piwigo
        }

        $validated = 'true';
        if ('unpublished' == $row['state'])
        {
          $validated = 'false';
        }

        $anonymous_id = implode('.', array_slice(explode('.', $row['server_remote_addr']), 0, 3));

        $comment_inserts[] = array(
          'image_id' => $iid[ $row['item_id'] ],
          'date' => date('Y-m-d H:i:s', $row['created']),
          'author' => pwg_db_real_escape_string($name),
          'author_id' => $author_id,
          'anonymous_id' => $anonymous_id,
          'email' => pwg_db_real_escape_string($email),
          'website_url' => pwg_db_real_escape_string($url),
          'content' => pwg_db_real_escape_string($row['text']),
          'validated' => $validated,
          );
      }
    }

    // tags
    $query = '
SELECT
    id,
    name
   FROM '.$pt.'tags
;';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result))
    {
      // Don't add the tag if it's already in Piwigo
      if (!isset($piwigo_tags[$row['name']]))
      {
        $tag_inserts[] = array(
          'name' => pwg_db_real_escape_string($row['name']),
          'url_name' => str2url($row['name']),
        );
      }

      $menalto_tag_ids[ $row['name'] ] = $row['id'];
    }

    $query = '
SELECT
    item_id,
    tag_id
  FROM '.$pt.'items_tags
;';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result))
    {
      $items_tags[] = $row;
    }

    m2p_db_disconnect();

    mass_updates(
      IMAGES_TABLE,
      array(
        'primary' => array('id'),
        'update'  => array('name', 'comment', 'date_available', 'hit'),
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

    if (isset($comment_inserts) and count($comment_inserts) > 0)
    {
      mass_inserts(
        COMMENTS_TABLE,
        array_keys($comment_inserts[0]),
        $comment_inserts
        );
    }

    if (isset($tag_inserts) and count($tag_inserts) > 0)
    {
      mass_inserts(
        TAGS_TABLE,
        array_keys($tag_inserts[0]),
        $tag_inserts
        );
    }

    // we need to retrieve the mapping of piwigo tag name => piwigo tag id,
    // for image_tag associations
    $query = '
SELECT
    id,
    name
  FROM '.TAGS_TABLE.'
;';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result))
    {
      if (isset($menalto_tag_ids[ $row['name'] ]))
      {
        $tag_id_convert[ $menalto_tag_ids[ $row['name'] ] ] = $row['id'];
      }
    }

    foreach ($items_tags as $item_tag)
    {
      if (isset($iid[ $item_tag['item_id'] ]) and isset($tag_id_convert[ $item_tag['tag_id'] ]))
      {
        $image_tag_inserts[] = array(
          'image_id' => $iid[ $item_tag['item_id'] ],
          'tag_id' => $tag_id_convert[ $item_tag['tag_id'] ],
          );
      }
    }

    if (isset($image_tag_inserts) and count($image_tag_inserts) > 0)
    {
      mass_inserts(
        IMAGE_TAG_TABLE,
        array_keys($image_tag_inserts[0]),
        $image_tag_inserts,
        array( 'ignore' => true )  // ignore duplicated tags added to item
        );
    }

    array_push($page['infos'], l10n('Information data registered in database'));
  }
  else
  {
    m2p_db_disconnect();
    array_push($page['errors'], l10n('No Menalto tables found!'));
  }
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
