{html_style}{literal}
fieldset p {text-align:left;}
{/literal}{/html_style}

<div class="titrePage">
  <h2>{'Import from Menalto'|translate}</h2>
</div>

<fieldset>
  <legend>{'Help'|translate}</legend>
  <p>{'Menalto2Piwigo plugin imports data from a Menalto Gallery2 installation into Piwigo.'|translate} {'Imported properties:'|translate}</p>
  <ul>
    <li>{'Title and description for photos'|translate}</li>
    <li>{'Name and description for albums'|translate}</li>
    <li>{'User comments on photos'|translate}</li>
  </ul>

  <p><strong>{'How to use it?'|translate}</strong></p>

  <ol>
    <li>{'Copy the content of g2data/albums into piwigo/galleries'|translate}</li>
    <li><a href="admin.php?page=site_update&amp;site=1">{'Synchronize'|translate}</a></li>
    <li>{'Submit the form at the end of this page'|translate}</li>
    <li>{'Install and activate plugins:'|translate} <a href="http://piwigo.org/ext/extension_view.php?eid=175" target="_blank">Extended Description</a></li>
  </ol>
</fieldset>

<form method="post" action="{$action_url}">
<fieldset>
  <legend>{'Import'|translate}</legend>

  <p><strong>{'database host'|translate}</strong><br>
    <input type="text" name="db_host" value="{$db_host}">
  </p>

  <p><strong>{'database name'|translate}</strong><br>
    <input type="text" name="db_name" value="{$db_name}">
  </p>

  <p><strong>{'database user'|translate}</strong><br>
    <input type="text" name="db_user" value="{$db_user}">
  </p>

  <p><strong>{'database password'|translate}</strong><br>
    <input type="password" name="db_password" value="{$db_password}">
  </p>

  <p><strong>{'table prefix'|translate}</strong><br>
    <input type="text" name="prefix_table" value="{$prefix_table}">
  </p>

  <p><strong>{'column prefix'|translate}</strong><br>
    <input type="text" name="prefix_column" value="{$prefix_column}">
  </p>

  <p class="actionButtons">
    <input class="submit" type="submit" name="submit" value="{'Start import'|@translate}"/>
  </p>
</form>
