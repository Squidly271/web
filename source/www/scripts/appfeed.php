#!/usr/bin/php7
<?PHP
error_reporting(E_ALL & ~E_NOTICE);

require_once("/config/www/include/helpers.php");
require_once("/config/www/include/xmlHelpers.php");
require_once("/config/www/include/DockerClient.php");
require_once("/config/www/include/paths.php");

exec("mkdir -p {$communityPaths['appfeedDataStore']}");
exec("mkdir -p {$communityPaths['persistentDataStore']}");
exec("mkdir -p {$communityPaths['templates-community']}");
exec("mkdir -p {$communityPaths['tempFiles']}");
exec("chmod 777 /tmp/web/*");

function DownloadCommunityTemplates() {
  global $communityPaths, $infoFile, $plugin, $communitySettings;

  $moderation = readJsonFile($communityPaths['moderation']);
  if ( ! is_array($moderation) ) {
      $moderation = array();
  }

  $DockerTemplates = new DockerTemplates();

  if (! $download = download_url($communityPaths['community-templates-url']) ) {
    return false;
  }
  $Repos  = json_decode($download, true);
  if ( ! is_array($Repos) ) {
    return false;
  }
  $appCount = 0;
  $myTemplates = array();

  exec("rm -rf '{$communityPaths['templates-community']}'");
  @unlink($communityPaths['updateErrors']);

  $templates = array();
  foreach ($Repos as $downloadRepo) {
    echo "Downloading ".$downloadRepo['name']."\n";
    $downloadURL = randomFile();
    file_put_contents($downloadURL, $downloadRepo['url']);
    $friendlyName = str_replace(" ","",$downloadRepo['name']);
    $friendlyName = str_replace("'","",$friendlyName);
    $friendlyName = str_replace('"',"",$friendlyName);
    $friendlyName = str_replace('\\',"",$friendlyName);
    $friendlyName = str_replace("/","",$friendlyName);
    if ( ! $downloaded = $DockerTemplates->downloadTemplates($communityPaths['templates-community']."/templates/$friendlyName", $downloadURL) ){
      $errors .= "Failed to download ".$downloadRepo['name']."\n";
      @unlink($downloadURL);
    } else {
      $templates = array_merge($templates,$downloaded);
      unlink($downloadURL);
    }
  }

  @unlink($downloadURL);
  $i = $appCount;
  echo "\n\nProcessing XML templates\n\n";
  foreach ($Repos as $Repo) {
    if ( ! is_array($templates[$Repo['url']]) ) {
      continue;
    }
    foreach ($templates[$Repo['url']] as $file) {
      if (is_file($file)){
        $o = readXmlFile($file);
        if ( ! $o ) {
          $errors .= "Failed to parse $file (errors in xml file)\n";
          }
        if ( ! $o['Repository'] ) {
          if ( ! $o['Plugin'] ) {
            continue;
          }
        }
        $o['Forum'] = $Repo['forum'];
        $o['Repo'] = $Repo['name'];
        $o['ID'] = $i;
        $o['Displayable'] = true;
        $o['Support'] = $o['Support'] ? $o['Support'] : $o['Forum'];
        $o['DonateText'] = $o['DonateText'] ? $o['DonateText'] : $Repo['donatetext'];
        $o['DonateLink'] = $o['DonateLink'] ? $o['DonateLink'] : $Repo['donatelink'];
        $o['DonateImg'] = $o['DonateImg'] ? $o['DonateImg'] : $Repo['donateimg'];
        $o['WebPageURL'] = $Repo['web'];
        $o['Logo'] = $Repo['logo'];
        fixSecurity($o,$o);
        $o = fixTemplates($o);
        $o['Compatible'] = versionCheck($o);

        # Overwrite any template values with the moderated values
        if ( is_array($moderation[$o['Repository']]) ) {
          $o = array_merge($o, $moderation[$o['Repository']]);
        }
        if ( $o['Blacklist'] ) {
          continue;
        }
        $o['Category'] = str_replace("Status:Beta","",$o['Category']);    # undo changes LT made to my xml schema for no good reason
        $o['Category'] = str_replace("Status:Stable","",$o['Category']);
        $myTemplates[$o['ID']] = $o;
        $i = ++$i;
      }
    }
  }
  unset($apps);
  $apps['apps'] = $i;
  $apps['last_updated_timestamp'] = time();
  $apps['last_updated'] = date("r");
  $apps['applist'] = $myTemplates;
  
  writeJsonFile($communityPaths['application-feed'],$apps);
  if ( $errors ) {
    echo "\n\nThe following errors occurred:\n\n$errors";
  }
  exec("rm -rf '{$communityPaths['templates-community']}'");
}

### start main ###

exec("mkdir -p ".$communityPaths['appfeedDataStore']);
exec("mkdir -p ".$communityPaths['persistentDataStore']);
exec("mkdir -p ".$communityPaths['templates-community']);
exec("mkdir -p ".$communityPaths['tempFiles']);

#echo "Downloading Moderation File\n";
download_url($communityPaths['moderationURL'],$communityPaths['moderation']);
#echo "Downloading Repository Information\n";
DownloadCommunityTemplates();

?>
