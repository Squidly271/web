<?PHP

###############################################################
#                                                             #
# Community Applications copyright 2015-2016, Andrew Zawadzki #
#                                                             #
###############################################################

require_once("/usr/local/emhttp/plugins/web/include/paths.php");
require_once("/usr/local/emhttp/plugins/web/include/helpers.php");
require_once("/usr/local/emhttp/plugins/web/include/DockerClient.php");
require_once("/usr/local/emhttp/plugins/web/include/xmlHelpers.php");

$plugin = "web";
$DockerTemplates = new DockerTemplates();

################################################################################
#                                                                              #
# Set up any default settings (when not explicitely set by the settings module #
#                                                                              #
################################################################################

#$communitySettings = parse_plugin_cfg("$plugin");
$communitySettings['appFeed']    = "true"; # set default for deprecated setting
$communitySettings['iconSize']   = "96";
$communitySettings['maxColumn']  ="5";
$communitySettings['separateInstalled']="false";
$communitySettings['appOfTheDay']="no";
if ( $communitySettings['favourite'] != "None" ) {
  $officialRepo = str_replace("*","'",$communitySettings['favourite']);
  $separateOfficial = true;
}

$communitySettings['dockerSearch'] = "no";

$info = array();
$dockerRunning = array();


exec("mkdir -p ".$communityPaths['tempFiles']);
exec("mkdir -p ".$communityPaths['persistentDataStore']);

if ( !is_dir($communityPaths['templates-community']) ) {
  exec("mkdir -p ".$communityPaths['templates-community']);
  @unlink($infoFile);
}

# Make sure the link is in place
if (is_dir("/usr/local/emhttp/state/plugins/$plugin")) exec("rm -rf /usr/local/emhttp/state/plugins/$plugin");
if (!is_link("/usr/local/emhttp/state/plugins/$plugin")) symlink($communityPaths['templates-community'], "/usr/local/emhttp/state/plugins/$plugin");


#  DownloadApplicationFeed MUST BE CALLED prior to DownloadCommunityTemplates in order for private repositories to be merged correctly.

function DownloadApplicationFeed() {
  global $communityPaths, $infoFile, $plugin, $communitySettings;

  $moderation = readJsonFile($communityPaths['moderation']);
  if ( ! is_array($moderation) ) {
    $moderation = array();
  }

  $Repositories = readJsonFile($communityPaths['Repositories']);
  if ( ! $Repositories ) {
    $Repositories = array();
  }

  $ApplicationFeed = readJsonFile($communityPaths['application-feed']);
  if ( ! is_array($ApplicationFeed) ) { return false; }

  @unlink($downloadURL);
  $i = 0;

  $myTemplates = array();

  foreach ($ApplicationFeed['applist'] as $file) {
    if ( ! $file['Repository'] ) {
      if ( ! $file['Plugin'] ) {
      continue;
      }
    }
    unset($o);
    # Move the appropriate stuff over into a CA data file
    $o = $file;
    $o['ID']            = $i;
    $o['Displayable']   = true;
    $o['Author']        = preg_replace("#/.*#", "", $o['Repository']);
    $o['DockerHubName'] = strtolower($file['Name']);
    $o['RepoName']      = $file['Repo'];
    $o['SortAuthor']    = $o['Author'];
    $o['SortName']      = $o['Name'];
    $o['Licence']       = $file['License']; # Support Both Spellings
    $o['Licence']       = $file['Licence'];
    $o['Path']          = $communityPaths['templates-community']."/".$i.".xml";
    if ( $o['Plugin'] ) {
      $o['Author']        = $o['PluginAuthor'];
      $o['Repository']    = $o['PluginURL'];
      $o['PluginURL']     = $o['Repository'];
      $o['Category']      .= " Plugins: ";
      $o['SortAuthor']    = $o['Author'];
      $o['SortName']      = $o['Name'];
    }
    $RepoIndex = searchArray($Repositories,"name",$o['RepoName']);
    if ( $RepoIndex != false ) {
      $o['DonateText'] = $Repositories[$RepoIndex]['donatetext'];
      $o['DonateImg']  = $Repositories[$RepoIndex]['donateimg'];
      $o['DonateLink'] = $Repositories[$RepoIndex]['donatelink'];
      $o['WebPageURL'] = $Repositories[$RepoIndex]['web'];
      $o['Logo']       = $Repositories[$RepoIndex]['logo'];
    }
    $o['DonateText'] = $file['DonateText'] ? $file['DonateText'] : $o['DonateText'];
    $o['DonateLink'] = $file['DonateLink'] ? $file['DonateLink'] : $o['DonateLink'];

    if ( ($file['DonateImg']) || ($file['DonateImage']) ) {  #because Sparklyballs can't read the tag documentation
      $o['DonateImg'] = $file['DonateImage'] ? $file['DonateImage'] : $file['DonateImg'];
    }
    fixSecurity($o,$o); # Apply various fixes to the templates for CA use
    $o = fixTemplates($o);

# Overwrite any template values with the moderated values

    if ( is_array($moderation[$o['Repository']]) ) {
      $o = array_merge($o, $moderation[$o['Repository']]);
      $file = array_merge($file, $moderation[$o['Repository']]);
    }
    if ($o['Blacklist']) {
      unset($o);
      continue;
    }

    $o['Compatible'] = versionCheck($o);

# Update the settings for the template

    $file['Compatible'] = $o['Compatible'];
    $file['Beta'] = $o['Beta'];
    $file['MinVer'] = $o['MinVer'];
    $file['MaxVer'] = $o['MaxVer'];
    $file['Category'] = $o['Category'];
    $o['Category'] = str_replace("Status:Beta","",$o['Category']);    # undo changes LT made to my xml schema for no good reason
    $o['Category'] = str_replace("Status:Stable","",$o['Category']);
    $myTemplates[$i] = $o;
 
    unset($file['Branch']);
    $myTemplates[$o['ID']] = $o;
    $i = ++$i;
  }
  writeJsonFile($communityPaths['community-templates-info'],$myTemplates);
  @unlink($communityPaths['LegacyMode']);
  return true;
}


############################################################
#                                                          #
# Routines that actually displays the template containers. #
#                                                          #
############################################################

function display_apps($viewMode) {
  global $communityPaths, $separateOfficial, $officialRepo, $communitySettings;

  $file = readJsonFile($communityPaths['community-templates-displayed']);
  $officialApplications = $file['official'];
  $communityApplications = $file['community'];
  $betaApplications = $file['beta'];
  $privateApplications = $file['private'];

  $totalApplications = count($officialApplications) + count($communityApplications) + count($betaApplications) + count($privateApplications);

  if ( $communitySettings['dockerRunning'] ) {
    $runningDockers=str_replace('/','',shell_exec('docker ps'));
    $imagesDocker=str_replace('/','',shell_exec('docker images'));
  }

  $display = "";
  $navigate = array();

  if ( $separateOfficial ) {
    if ( count($officialApplications) ) {
      $navigate[] = "doesn't matter what's here -> first element gets deleted anyways";
      $display = "<center><b>";

      $logos = readJsonFile($communityPaths['logos']);
      $display .= $logos[$officialRepo] ? "<img src='".$logos[$officialRepo]."' style='width:48px'>&nbsp;&nbsp;" : "";
      $display .= "<font size='4' color='purple' id='OFFICIAL'>$officialRepo</font></b></center><br>";
      $display .= my_display_apps($viewMode,$officialApplications,$runningDockers,$imagesDocker);
    }
  }

  if ( count($communityApplications) ) {
    if ( $communitySettings['superCategory'] == "true" || $separateOfficial ) {
      $navigate[] = "<a href='#COMMUNITY'>Community Supported Applications</a>";
      $display .= "<center><b><font size='4' color='purple' id='COMMUNITY'>Community Supported Applications</font></b></center><br>";
    }
    $display .= my_display_apps($viewMode,$communityApplications,$runningDockers,$imagesDocker);
  }

  if ( $communitySettings['superCategory'] == "true" || $separateOfficial ) {
    if ( count($betaApplications) ) {
      $navigate[] = "<a href='#BETA'>Beta Applications</a>";
      $display .= "<center><b><font size='4' color='purple' id='BETA'>Beta / Work In Progress Applications</font></b></center><br>";
      $display .= my_display_apps($viewMode,$betaApplications,$runningDockers,$imagesDocker);
    }
    if ( count($privateApplications) ) {
      $navigate[] = "<a href='#PRIVATE'>Private Applications</a>";
      $display .= "<center><b><font size='4' color='purple' id='PRIVATE'>Applications From Private Repositories</font></b></center><br>";
      $display .= my_display_apps($viewMode,$privateApplications,$runningDockers,$imagesDocker);
    }
  }

  unset($navigate[0]);

  if ( count($navigate) ) {
    $bookmark = "Jump To: ";
    $bookmark .= implode("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;",$navigate);
  }

  $display .= ( $totalApplications == 0 ) ? "<center><font size='3'>No Matching Content Found</font></center>" : "";
 
  $totalApps = "$totalApplications";
  $totalApps .= (count($privateApplications)) ? " <font size=1>( ".count($privateApplications)." Private )</font>" : "";

  $display .= "<script>$('#Total').html('$totalApps');</script>";
  $display .= changeUpdateTime();

  echo $bookmark;
  echo $display;
}

function my_display_apps($viewMode,$file,$runningDockers,$imagesDocker) {
  global $communityPaths, $info, $communitySettings, $plugin;

  $pinnedApps = getPinnedApps();
  $iconSize = $communitySettings['iconSize'];
  $tabMode = $communitySettings['newWindow'];

  usort($file,"mySort");

  $communitySettings['viewMode'] = $viewMode;
  file_put_contents("/tmp/huh",print_r($communityPaths,true));
  $skin = readJsonFile($communityPaths['defaultSkin']);
  $ct = $skin[$viewMode]['header'].$skin[$viewMode]['sol'];
  $displayTemplate = $skin[$viewMode]['template'];
  if ( $viewMode == "detail" ) {
    $communitySettings['maxColumn'] = 2; 
    $communitySettings['viewMode'] = "icon";
  }

  $columnNumber = 0;
  foreach ($file as $template) {
    $name = $template['SortName'];
    $appName = str_replace(" ","",$template['SortName']);
    $t = "";
    $ID = $template['ID'];
    $selected = $info[$name]['template'] && stripos($info[$name]['icon'], $template['SortAuthor']) !== false;
    $selected = $template['Uninstall'] ? true : $selected;
    $RepoName = ( $template['Private'] == "true" ) ? $template['RepoName']."<font color=red> (Private)</font>" : $template['RepoName'];
    $template['display_DonateLink'] = $template['DonateLink'] ? "<font size='0'><a href='".$template['DonateLink']."' target='_blank' title='".$template['DonateText']."'>Donate To Author</a></font>" : "";
    $template['display_Project'] = $template['Project'] ? "<a target='_blank' title='Click to go the the Project Home Page' href='".$template['Project']."'><font color=red>Project Home Page</font></a>" : "";
    $template['display_Support'] = $template['Support'] ? "<a href='".$template['Support']."' target='_blank' title='Click to go to the support thread'><font color=red>Support Thread</font></a>" : "";
    $template['display_webPage'] = $template['WebPageURL'] ? "<a href='".$template['WebPageURL']."' target='_blank'><font color='red'>Web Page</font></a></font>" : "";

    if ( $template['display_Support'] && $template['display_Project'] ) { $template['display_Project'] = "&nbsp;&nbsp;&nbsp".$template['display_Project'];}
    if ( $template['display_webPage'] && ( $template['display_Project'] || $template['display_Support'] ) ) { $template['display_webPage'] = "&nbsp;&nbsp;&nbsp;".$template['display_webPage']; }
    if ( $template['UpdateAvailable'] ) {
      $template['display_UpdateAvailable'] = $template['Plugin'] ? "<br><center><font color='red'><b>Update Available.  Click <a onclick='installPLGupdate(&quot;".basename($template['MyPath'])."&quot;,&quot;".$template['Name']."&quot;);' style='cursor:pointer'>Here</a> to Install</b></center></font>" : "<br><center><font color='red'><b>Update Available.  Click <a href='Docker'>Here</a> to install</b></font></center>";
    }
    $template['display_ModeratorComment'] .= $template['ModeratorComment'] ? "</b></strong><font color='red'><b>Moderator Comments:</b></font> ".$template['ModeratorComment'] : "";
    $tempLogo = $template['Logo'] ? "<img src='".$template['Logo']."' height=20px>" : "";
    $template['display_Announcement'] = $template['Forum'] ? "<a href='".$template['Forum']."' target='_blank' title='Click to go to the repository Announcement thread' >$RepoName $tempLogo</a>" : "$RepoName $tempLogo";
    $template['display_Stars'] = $template['stars'] ? "<img src='/plugins/$plugin/images/red-star.png' style='height:15px;width:15px'> <strong>".$template['stars']."</strong>" : "";
    $template['display_Downloads'] = $template['downloads'] ? "<center>".$template['downloads']."</center>" : "<center>Not Available</center>";

    if ( $pinnedApps[$template['Repository']] ) {
      $pinned = "greenButton.png";
      $pinnedTitle = "Click to unpin this application";
    } else {
      $pinned = "redButton.png";
      $pinnedTitle = "Click to pin this application";
    }
    $template['display_pinButton'] = "<img src='/plugins/$plugin/images/$pinned' style='height:15px;width:15px;cursor:pointer' title='$pinnedTitle' onclick='pinApp(this,&quot;".$template['Repository']."&quot;);'>";
    if ( $template['Uninstall'] ) {
      $template['display_Uninstall'] = "<img src='/plugins/dynamix.docker.manager/images/remove.png' title='Uninstall Application' style='width:20px;height:20px;cursor:pointer' ";
      if ( $template['Plugin'] ) {
        $template['display_Uninstall'] .= "onclick='uninstallApp(&quot;".$template['MyPath']."&quot;,&quot;".$template['Name']."&quot;);'>";
      } else {
        $template['display_Uninstall'] .= "onclick='uninstallDocker(&quot;".$template['MyPath']."&quot;,&quot;".$template['Name']."&quot;);'>";
      }
    }
    $template['display_removable'] = $template['Removable'] ? "<img src='/plugins/dynamix.docker.manager/images/remove.png' title='Remove Application From List' style='width:20px;height:20px;cursor:pointer' onclick='removeApp(&quot;".$template['MyPath']."&quot;,&quot;".$template['Name']."&quot;);'>" : "";
    if ( $template['Date'] > strtotime($communitySettings['timeNew'] ) ) {
      $template['display_newIcon'] = "<img src='/plugins/$plugin/images/star.png' style='width:15px;height:15px;' title='New / Updated - ".date("F d Y",$template['Date'])."'></img>";
    }
    $template['display_changes'] = $template['Changes'] ? " <a style='cursor:pointer'><img src='/plugins/$plugin/images/information.png' onclick=showInfo($ID,'$appName'); title='Click for the changelog / more information'></a>" : "";
    $template['display_humanDate'] = date("F j, Y",$template['Date']);

    if ( $template['Plugin'] ) {
      $pluginName = basename($template['PluginURL']);
      if ( file_exists("/var/log/plugins/$pluginName") ) {
        $pluginSettings = isset($template['CAlink']) ? $template['CAlink'] : getPluginLaunch($pluginName);
        $tmpVar = $pluginSettings ? "" : " disabled ";
        $template['display_pluginSettings'] = "<input type='submit' $tmpVar style='margin:0px' value='Settings' formtarget='$tabMode' formaction='$pluginSettings' formmethod='post'>";

      } else {
        $buttonTitle = $template['MyPath'] ? "Reinstall Plugin" : "Install Plugin";
        $template['display_pluginInstall'] = "<input type='button' value='$buttonTitle' style='margin:0px' title='Click to install this plugin' onclick=installPlugin('".$template['PluginURL']."');>";
      }
    } else {
      if ( $communitySettings['dockerRunning'] ) {
        if ( $selected ) {
          $template['display_dockerDefault'] = "<input type='submit' value='Default' style='margin:1px' title='Click to reinstall the application using default values' formtarget='$tabMode' formmethod='post' formaction='AddContainer?xmlTemplate=default:".addslashes($template['Path'])."'>";
          $template['display_dockerEdit']    = "<input type='submit' value='Edit' style='margin:1px' title='Click to edit the application values' formtarget='$tabMode' formmethod='post' formaction='UpdateContainer?xmlTemplate=edit:".addslashes($info[$name]['template'])."'>";
          $template['display_dockerDefault'] = $template['BranchID'] ? "<input type='button' style='margin:0px' title='Click to reinstall the application using default values' value='Add' onclick='displayTags(&quot;$ID&quot;);'>" : $template['display_dockerDefault'];
          } else {
          if ( $template['MyPath'] ) {
            $template['display_dockerReinstall'] = "<input type='submit' style='margin:0px' title='Click to reinstall the application' value='Reinstall' formtarget='$tabMode' formmethod='post' formaction='AddContainer?xmlTemplate=user:".addslashes($template['MyPath'])."'>";
          } else {
            $template['display_dockerInstall']   = "<input type='submit' style='margin:0px' title='Click to install the application' value='Add' formtarget='$tabMode' formmethod='post' formaction='AddContainer?xmlTemplate=default:".addslashes($template['Path'])."'>";
            $template['display_dockerInstall']   = $template['BranchID'] ? "<input type='button' style='margin:0px' title='Click to install the application' value='Add' onclick='displayTags(&quot;$ID&quot;);'>" : $template['display_dockerInstall'];
            }
        }
      } else {
        $template['display_dockerDisable'] = "<font color='red'>Docker Not Enabled</font>";
      }
    }
    if ( ! $template['Compatible'] && ! $template['UnknownCompatible'] ) {
      $template['display_compatible'] = "NOTE: This application is listed as being NOT compatible with your version of unRaid<br>";
      $template['display_compatibleShort'] = "Incompatible";
    }
    $template['display_author'] = "<a style='cursor:pointer' onclick='authorSearch(this.innerHTML);' title='Search for more containers from author'>".$template['Author']."</a>";
    $displayIcon = $template['Icon'];
    $displayIcon = $displayIcon ? $displayIcon : "/plugins/$plugin/images/question.png";
    $template['display_iconSmall'] = "<a onclick='showDesc(".$template['ID'].",&#39;".$name."&#39;);' style='cursor:pointer'><img title='Click to display full description' src='".$displayIcon."' style='width:48px;height:48px;' onError='this.src=\"/plugins/$plugin/images/question.png\";'></a>";
    $template['display_iconSelectable'] = "<img src='$displayIcon' onError='this.src=\"/plugins/$plugin/images/question.png\";' style='width:".$iconSize."px;height=".$iconSize."px;'>";
    $template['display_popupDesc'] = ( $communitySettings['maxColumn'] > 2 ) ? "Click for a full description\n".$template['PopUpDescription'] : "Click for a full description";
    $template['display_dateUpdated'] = $template['Date'] ? "</b></strong><center><strong>Date Updated: </strong>".$template['display_humanDate']."</center>" : "";
    $template['display_iconClickable'] = "<a onclick=showDesc($ID,'$appName'); style='cursor:pointer' title='".$template['display_popupDesc']."'>".$template['display_iconSelectable']."</a>";

    if ( $communitySettings['dockerSearch'] == "yes" && ! $template['Plugin'] ) {
      $template['display_dockerName'] = "<a style='cursor:pointer' onclick='mySearch(this.innerHTML);' title='Search dockerHub for similar containers'>".$template['Name']."</a>";
    } else {
      $template['display_dockerName'] = $template['Name'];
    }
    $template['Category'] = ($template['Category'] == "UNCATEGORIZED") ? "Uncategorized" : $template['Category'];

    if ( ( $template['Beta'] == "true" ) ) {
      $template['display_dockerName'] .= "<span title='Beta Container &#13;See support forum for potential issues'><font size='1' color='red'><strong>(beta)</strong></font></span>";
    }

    $t .= vsprintf($displayTemplate,toNumericArray($template));

    $columnNumber=++$columnNumber;

    if ( $communitySettings['viewMode'] == "icon" ) {
      if ( $columnNumber == $communitySettings['maxColumn'] ) {
        $columnNumber = 0;
        $t .= $skin[$viewMode]['eol'].$skin[$viewMode]['sol'];
      }
    } else {
      $t .= $skin[$viewMode]['eol'].$skin[$viewMode]['sol'];
    }
 
    $ct .= $t;
  }
  $ct .= $skin[$viewMode]['footer'];
  $ct .= caGetMode();
  return $ct;
}

#############################
#                           #
# Selects an app of the day #
#                           #
#############################

function appOfDay($file) {
  global $communityPaths, $info;
  
  $oldAppDay = @filemtime($communityPaths['appOfTheDay']);
  $oldAppDay = $oldAppDay ? $oldAppDay : 1;
  $oldAppDay = intval($oldAppDay / 86400);
  $currentDay = intval(time() / 86400);
  if ( $oldAppDay == $currentDay ) {
    $app = readJsonFile($communityPaths['appOfTheDay']);
    if ( $app ) $flag = true;
  }
  
  while ( true ) {
    if ( ! $flag ) {
      $app[0] = mt_rand(0,count($file) -1);
      $app[1] = mt_rand(0,count($file) -1);
    }
    $flag = false;
    if ($app[0] == $app[1]) continue;
    if ( ! $file[$app[0]]['Displayable'] || ! $file[$app[1]]['Displayable'] ) continue;
    if ( ! $file[$app[0]]['Compatible'] || ! $file[$app[1]]['Compatible'] ) continue;
    if ( $file[$app[0]]['Blacklist'] || $file[$app[1]]['Blacklist'] ) continue;
    if ( $file[$app[0]]['ModeratorComment'] || $file[$app[1]]['ModeratorComment'] ) continue;
    break;
  }
  writeJsonFile($communityPaths['appOfTheDay'],$app);
  return $app;
}


############################################
############################################
##                                        ##
## BEGIN MAIN ROUTINES CALLED BY THE HTML ##
##                                        ##
############################################
############################################


switch ($_POST['action']) {

######################################################################################
#                                                                                    #
# get_content - get the results from templates according to categories, filters, etc #
#                                                                                    #
######################################################################################

case 'get_content':
  $filter   = getPost("filter",false);
  $category = "/".getPost("category",false)."/i";
  $newApp   = getPost("newApp",false);
  $sortOrder = getSortOrder(getPostArray("sortOrder"));

  $newAppTime = strtotime($communitySettings['timeNew']);

  if ( file_exists($communityPaths['addConverted']) ) {
    @unlink($infoFile);
    @unlink($communityPaths['addConverted']);
  }

  if ( file_exists($communityPaths['appFeedOverride']) ) {
   $communitySettings['appFeed'] = "false";
   @unlink($communityPaths['appFeedOverride']);
  }

  if (!file_exists($infoFile)) {
    if ( $communitySettings['appFeed'] == "true" ) {
      DownloadApplicationFeed();
      if (!file_exists($infoFile)) {
        $communitySettings['appFeed'] = "false";
        echo "<tr><td colspan='5'><br><center>Download of appfeed failed.  Reverting to legacy mode</center></td></tr>";
        @unlink($infoFile);
      }
    }

    if ($communitySettings['appFeed'] == "false" ) {
      if (!DownloadCommunityTemplates()) {
        echo "<table><tr><td colspan='5'><br><center>Download of source file has failed</center></td></tr></table>";
        break;
      } else {
        $lastUpdated['last_updated_timestamp'] = time();
        writeJsonFile($communityPaths['lastUpdated-old'],$lastUpdated);
        if (is_file($communityPaths['updateErrors'])) {
          echo "<table><td><td colspan='5'><br><center>The following errors occurred:<br><br>";
          echo "<strong>".file_get_contents($communityPaths['updateErrors'])."</strong></center></td></tr></table>";
          echo "<script>$('#templateSortButtons,#total1').hide();$('#sortButtons').hide();</script>";
          echo changeUpdateTime();
          echo caGetMode();
          break;
        }
      }
    }
  }

  $file = readJsonFile($communityPaths['community-templates-info']);
  if (!is_array($file)) break;

  if ( $category === "/NONE/i" ) {
    echo "<center><font size=4>Select A Category Above</font></center>";
    echo changeUpdateTime();
    if ( $communitySettings['appOfTheDay'] == "yes" ) {
      $displayApplications = array();
      if ( count($file) > 200) {
        $appsOfDay = appOfDay($file);
        $displayApplications['community'] = array($file[$appsOfDay[0]],$file[$appsOfDay[1]]);
        writeJsonFile($communityPaths['community-templates-displayed'],$displayApplications);
        echo "<script>$('#templateSortButtons').hide();$('#sortButtons').hide();</script>";
        echo "<br><center><font size='4' color='purple'><b>Random Apps Of The Day</b></font><br><br>";
        echo my_display_apps("detail",$displayApplications['community'],$runningDockers,$imagesDocker);
        break;
      }
    } else {
      break;
    }
  }

  $display             = array();
  $official            = array();
  $beta                = array();
  $privateApplications = array();

  foreach ($file as $template) {
    if ( $template['Blacklist'] ) {
      continue;
    }
    if ( ! $template['Displayable'] ) {
      continue;
    }
    if ( $communitySettings['hideIncompatible'] == "true" && ! $template['Compatible'] ) {
      continue;
    }
    $name = $template['Name'];

# Skip over installed containers

    if ( $newApp != "true" && $filter == "" && $communitySettings['separateInstalled'] == "true" ) {
      if ( $template['Plugin'] ) {
        $pluginName = basename($template['PluginURL']);

        if ( file_exists("/var/log/plugins/$pluginName") ) {
          continue;
        }
      } else {
        $selected = false;
        foreach ($dockerRunning as $installedDocker) {
          $installedImage = $installedDocker['Image'];
          $installedName = $installedDocker['Name'];

          if ( startsWith($installedImage,$template['Repository']) ) {
            if ( $installedName == $template['Name'] ) {
              $selected = true;
              break;
            }
          }
        }
        if ( $selected ) {
          continue;
        }
      }
    }
    if ( $template['Plugin'] ) {
      if ( file_exists("/var/log/plugins/".basename($template['PluginURL'])) ) {
        $template['UpdateAvailable'] = checkPluginUpdate($template['PluginURL']);
        $template['MyPath'] = $template['PluginURL'];
      }
    }
    if ( $newApp == "true" ) {
      if ( $template['Date'] < $newAppTime ) { continue; }
    }

    if ( $category && ! preg_match($category,$template['Category'])) { continue; }

    if ($filter) {
      if (preg_match("#$filter#i", $template['Name']) || preg_match("#$filter#i", $template['Author']) || preg_match("#$filter#i", $template['Description']) || preg_match("#$filter#i", $template['Repository'])) {
        $template['Description'] = highlight($filter, $template['Description']);
        $template['Author'] = highlight($filter, $template['Author']);
        $template['Name'] = highlight($filter, $template['Name']);
      } else continue;
    }

    if ( $communitySettings['superCategory'] == "true" ) {
      if ( $template['Beta'] == "true" ) {
        $beta[] = $template;
      } else {
        if ( $template['Private'] == "true" ) {
          $privateApplications[] = $template;
        } else {
          if ( $separateOfficial ) {
            if ( $template['RepoName'] == $officialRepo ) {
              $official[] = $template;
            } else {
              $display[] = $template;
            }
          } else {
            $display[] = $template;
          }
        }
      }
    } else {
      if ( $separateOfficial ) {
        if ( $template['RepoName'] == $officialRepo ) {
          $official[] = $template;
        } else {
          $display[] = $template;
        }
      } else {
        $display[] = $template;
      }
    }
  }

  $displayApplications['official']  = $official;
  $displayApplications['community'] = $display;
  $displayApplications['beta']      = $beta;
  $displayApplications['private']   = $privateApplications;

  writeJsonFile($communityPaths['community-templates-displayed'],$displayApplications);
  display_apps($sortOrder['viewMode']);
  changeUpdateTime();
  break;

########################################################
#                                                      #
# force_update -> forces an update of the applications #
#                                                      #
########################################################

case 'force_update':
  if ( !is_dir($communityPaths['templates-community']) ) {
    exec("mkdir -p ".$communityPaths['templates-community']);
    @unlink($infoFile);
  }

  download_url($communityPaths['moderationURL'],$communityPaths['moderation']);
  $tmpFileName = randomFile();
  download_url($communityPaths['community-templates-url'],$tmpFileName);
  $Repositories = readJsonFile($tmpFileName);
  writeJsonFile($communityPaths['Repositories'],$Repositories);
  $repositoriesLogo = readJsonFile($tmpFileName);
  if ( ! is_array($repositoriesLogo) ) {
    $repositoriesLogo = array();
  }

  foreach ($repositoriesLogo as $repositories) {
    if ( $repositories['logo'] ) {
      $repoLogo[$repositories['name']] = $repositories['logo'];
    }
  }
  writeJsonFile($communityPaths['logos'],$repoLogo);
  @unlink($tmpFileName);

  if ( ! file_exists($infoFile) ) {
    if ( ! file_exists($communityPaths['lastUpdated-old']) ) {
      $latestUpdate['last_updated_timestamp'] = time();
      writeJsonFile($communityPaths['lastUpdated-old'],$latestUpdate);
    }
    break;
  }

  if ( file_exists($communityPaths['lastUpdated-old']) ) {
    $lastUpdatedOld = readJsonFile($communityPaths['lastUpdated-old']);
  } else {
    $lastUpdatedOld['last_updated_timestamp'] = 0;
  }
  @unlink($communityPaths['lastUpdated']);
  download_url($communityPaths['application-feed-last-updated'],$communityPaths['lastUpdated']);

  $latestUpdate = readJsonFile($communityPaths['lastUpdated']);
  if ( ! $latestUpdate['last_updated_timestamp'] ) {
    $latestUpdate['last_updated_timestamp'] = INF;
    @unlink($communityPaths['lastUpdated']);
  }

  if ( $latestUpdate['last_updated_timestamp'] > $lastUpdatedOld['last_updated_timestamp'] ) {
    if ( $latestUpdate['last_updated_timestamp'] != INF ) {
      copy($communityPaths['lastUpdated'],$communityPaths['lastUpdated-old']);
    }
    unlink($infoFile);
  } else {
    moderateTemplates();
  }
  break;

####################################################################################################
#                                                                                                  #
# force_update_button - forces the system temporarily to override the appFeed and forces an update #
#                                                                                                  #
####################################################################################################

case 'force_update_button':
  if ( ! is_file($communityPaths['LegacyMode']) ) {
    file_put_contents($communityPaths['appFeedOverride'],"dunno");
  }
  @unlink($infoFile);
  break;

####################################################################################
#                                                                                  #
# display_content - displays the templates according to view mode, sort order, etc #
#                                                                                  #
####################################################################################

case 'display_content':
  $sortOrder = getSortOrder(getPostArray('sortOrder'));
  
  if ( file_exists($communityPaths['community-templates-displayed']) ) {
    display_apps($sortOrder['viewMode']);
  } else {
    echo "<center><font size='4'>Select A Category Above</font></center>";
  }
  break;

}
?>
