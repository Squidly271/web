#!/usr/bin/php
<?PHP
require_once("/usr/local/emhttp/plugins/web/include/helpers.php");
require_once("/usr/local/emhttp/plugins/web/include/xmlHelpers.php");
require_once("/usr/local/emhttp/plugins/web/include/DockerClient.php");
require_once("/usr/local/emhttp/plugins/web/include/paths.php");

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
#    echo "Downloading ".$downloadRepo['name']."\n";
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
#  echo "\n\nProcessing XML templates\n\n";
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
# Branches aren't needed in the feed -> only in the local xml        
/*         if ( is_array($o['Branch']) ) {
          if ( ! $o['Branch'][0] ) {
            $tmp = $o['Branch'];
            unset($o['Branch']);
            $o['Branch'][] = $tmp;
          }
          foreach($o['Branch'] as $branch) {
            $i = ++$i;
            $subBranch = $o;
            $masterRepository = explode(":",$subBranch['Repository']);
            $o['BranchDefault'] = $masterRepository[1];
            $subBranch['Repository'] = $masterRepository[0].":".$branch['Tag']; #This takes place before any xml elements are overwritten by additional entries in the branch, so you can actually change the repo the app draws from
            $subBranch['BranchName'] = $branch['Tag'];
            $subBranch['BranchDescription'] = $branch['TagDescription'] ? $branch['TagDescription'] : $branch['Tag'];
            $subBranch['Path'] = $communityPaths['templates-community']."/".$i.".xml";
            $subBranch['Displayable'] = false;
            $subBranch['ID'] = $i;
            $replaceKeys = array_diff(array_keys($branch),array("Tag","TagDescription"));
            foreach ($replaceKeys as $key) {
              $subBranch[$key] = $branch[$key];
            }
            unset($subBranch['Branch']);
            $myTemplates[$i] = $subBranch;
            $o['BranchID'][] = $i;
            file_put_contents($subBranch['Path'],makeXML($subBranch));
          }
          unset($o['Branch']);
          $o['Path'] = $communityPaths['templates-community']."/".$o['ID'].".xml";
          file_put_contents($o['Path'],makeXML($o));
          $myTemplates[$o['ID']] = $o;
        } */
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
#    echo "\n\nThe following errors occurred:\n\n$errors";
  }
  exec("rm -rf '{$communityPaths['templates-community']}'");
}

### start main ###

exec("mkdir -p ".$communityPaths['persistentDataStore']);
exec("mkdir -p ".$communityPaths['templates-community']);
exec("mkdir -p ".$communityPaths['tempFiles']);

#echo "Downloading Moderation File\n";
download_url($communityPaths['moderationURL'],$communityPaths['moderation']);
#echo "Downloading Repository Information\n";
DownloadCommunityTemplates();

?>