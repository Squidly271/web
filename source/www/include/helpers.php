<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2016, Andrew Zawadzki #
#                                                             #
###############################################################
#require_once("/usr/local/emhttp/plugins/dynamix/include/Wrappers.php");

$unRaidVersion = "6.3.2";

####################################################################################################
#                                                                                                  #
# 2 Functions because unRaid includes comments in .cfg files starting with # in violation of PHP 7 #
#                                                                                                  #
####################################################################################################

function my_parse_ini_file($file,$mode=false,$scanner_mode=INI_SCANNER_NORMAL) {
  return parse_ini_string(preg_replace('/^#.*\\n/m', "", @file_get_contents($file)),$mode,$scanner_mode);
}

function my_parse_ini_string($string, $mode=false,$scanner_mode=INI_SCANNER_NORMAL) {
  return parse_ini_string(preg_replace('/^#.*\\n/m', "", $string),$mode,$scanner_mode);
}

###########################################################################
#                                                                         #
# Helper function to determine if a plugin has an update available or not #
#                                                                         #
###########################################################################

function checkPluginUpdate($filename) {
/*   global $unRaidVersion;

  $filename = basename($filename);
  $installedVersion = plugin("version","/var/log/plugins/$filename");
  if ( is_file("/tmp/plugins/$filename") ) {
    $upgradeVersion = plugin("version","/tmp/plugins/$filename");
  } else {
    $upgradeVersion = "0";
  }
  if ( $installedVersion < $upgradeVersion ) {
    $unRaid = plugin("unRAID","/tmp/plugins/$filename");
    if ( $unRaid === false || version_compare($unRaidVersion['version'],$unRaid,">=") ) {
      return true;
    } else {
      return false;
    }
  } */
  return false;
}


###################################################################################
#                                                                                 #
# returns a random file name (/tmp/community.applications/tempFiles/34234234.tmp) #
#                                                                                 #
###################################################################################
function randomFile() {
  global $communityPaths;

  return tempnam($communityPaths['tempFiles'],"CA-Temp-");
}

##################################################################
#                                                                #
# 2 Functions to avoid typing the same lines over and over again #
#                                                                #
##################################################################

function readJsonFile($filename) {
  return json_decode(@file_get_contents($filename),true);
}

function writeJsonFile($filename,$jsonArray) {
  file_put_contents($filename,json_encode($jsonArray, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

###############################################
#                                             #
# Helper function to download a URL to a file #
#                                             #
###############################################

function download_url($url, $path = "", $bg = false){
  exec("curl --compressed --max-time 60 --silent --insecure --location --fail ".($path ? " -o '$path' " : "")." $url ".($bg ? ">/dev/null 2>&1 &" : "2>/dev/null"), $out, $exit_code );
  return ($exit_code === 0 ) ? implode("\n", $out) : false;
}

########################################################
#                                                      #
# Helper function to get the plugin's launch entitity. #
#                                                      #
########################################################
 
function getPluginLaunch($pluginName) {
/*   return plugin("launch","/var/log/plugins/$pluginName");
 */
 return false;
}

#################################################################
#                                                               #
# Helper function to determine if $haystack begins with $needle #
#                                                               #
#################################################################

function startsWith($haystack, $needle) {
  return $needle === "" || strripos($haystack, $needle, -strlen($haystack)) !== FALSE;
}
#######################################################################################
#                                                                                     #
# Helper function to further remove formatting from descriptions (suitable for popUps #
#                                                                                     #
#######################################################################################

function fixPopUpDescription($PopUpDescription) {
  $PopUpDescription = str_replace("'","&#39;",$PopUpDescription);
  $PopUpDescription = str_replace('"','&quot;',$PopUpDescription);
  $PopUpDescription = strip_tags($PopUpDescription);
  $PopUpDescription = trim($PopUpDescription);
  return ($PopUpDescription);
}

###################################################################
#                                                                 #
# Helper function to remove any formatting, etc from descriptions #
#                                                                 #
###################################################################

function fixDescription($Description) {
  $Description = preg_replace("#\[br\s*\]#i", "{}", $Description);
  $Description = preg_replace("#\[b[\\\]*\s*\]#i", "||", $Description);
  $Description = preg_replace('#\[([^\]]*)\]#', '<$1>', $Description);
  $Description = preg_replace("#<span.*#si", "", $Description);
  $Description = preg_replace("#<[^>]*>#i", '', $Description);
  $Description = preg_replace("#"."{}"."#i", '<br>', $Description);
  $Description = preg_replace("#"."\|\|"."#i", '<b>', $Description);
  $Description = str_replace("&lt;","<",$Description);
  $Description = str_replace("&gt;",">",$Description);
  $Description = strip_tags($Description);
  $Description = trim($Description);
  return $Description;
}

########################################################################
#                                                                      #
# Security function to remove any <script> tags from elements that are #
# displayed as is                                                      #
#                                                                      #
########################################################################

# pass a copy of the original template to relate security violations back to the template
function fixSecurity(&$template,&$originalTemplate) {
  foreach ($template as &$element) {
    if ( is_array($element) ) {
      fixSecurity($element,$originalTemplate);
    } else {
      $tempElement = htmlspecialchars_decode($element);
      if ( preg_match('#<script(.*?)>(.*?)</script>#is',$tempElement) || preg_match('#<iframe(.*?)>(.*?)</iframe>#is',$tempElement) ) {
        logger("Alert the maintainers of Community Applications with the following Information:".$originalTemplate['RepoName']." ".$originalTemplate['Name']." ".$originalTemplate['Repository']);
        $originalTemplate['Blacklist'] = true;
        return;
      }
    }
  }  
}

#######################
#                     #
# Custom sort routine #
#                     #
#######################

function mySort($a, $b) {
  global $sortOrder;

  if ( $sortOrder['sortBy'] != "downloads" ) {
    $c = strtolower($a[$sortOrder['sortBy']]);
    $d = strtolower($b[$sortOrder['sortBy']]);
  } else {
    $c = $a[$sortOrder['sortBy']];
    $d = $b[$sortOrder['sortBy']];
  }

  $return1 = ($sortOrder['sortDir'] == "Down") ? -1 : 1;
  $return2 = ($sortOrder['sortDir'] == "Down") ? 1 : -1;

  if ($c > $d) { return $return1; }
  else if ($c < $d) { return $return2; }
  else { return 0; }
}


###############################################
#                                             #
# Search array for a particular key and value #
# returns the index number of the array       #
# return value === false if not found         #
#                                             #
###############################################

function searchArray($array,$key,$value) {
  if ( function_exists("array_column") && function_exists("array_search") ) {   # faster to use built in if it works
    $result = array_search($value, array_column($array, $key));   
  } else {
    $result = false;
    for ($i = 0; $i <= max(array_keys($array)); $i++) {
      if ( $array[$i][$key] == $value ) {
        $result = $i;
        break;
      }
    }
  }
  
  return $result;
}

#############################
#                           #
# Highlights search results #
#                           #
#############################

function highlight($text, $search) {
  return preg_replace('#'. preg_quote($text,'#') .'#si', '<span style="background-color:#FFFF66; color:#FF0000;font-weight:bold;">\\0</span>', $search);
}

########################################################
#                                                      #
# Fix common problems (maintainer errors) in templates #
#                                                      #
########################################################

function fixTemplates($template) {
  if ( is_array($template['Support']) ) {
    unset($template['Support']);
  }
  if ( ! is_string($template['Name'])  ) $template['Name']=" ";
  if ( ! is_string($template['Author']) ) $template['Author']=" ";
  if ( ! is_string($template['Description']) ) $template['Description']=" "; 
  if ( is_array($template['Beta']) ) {
    $template['Beta'] = "false";
  } else {
    $template['Beta'] = strtolower(stripslashes($template['Beta']));
  }
  $template['Date'] = ( $template['Date'] ) ? strtotime( $template['Date'] ) : 0;

  if ( ! $template['MinVer'] ) {
    $template['MinVer'] = $template['Plugin'] ? "6.1" : "6.0";
  }
  if ( ! is_string($template['Description']) ) {
    $template['Description'] = "";
  }
  $template['Category'] = $template['Category'] ? $template['Category'] : "Uncategorized";
  if ( ! is_string($template['Category']) ) {
    $template['Category'] = "Uncategorized";
  }
  
  if ( !is_string($template['Overview']) ) {
    unset($template['Overview']);
  }
  if ( is_array($template['SortAuthor']) ) {                 # due to cmer
    $template['SortAuthor'] = $template['SortAuthor'][0];
  }
  if ( is_array($template['Repository']) ) {                 # due to cmer
    $template['Repository'] = $template['Repository'][0];
  }
  if ( is_array($template['PluginURL']) ) {                  # due to coppit
    $template['PluginURL'] = $template['PluginURL'][1];
  }
  if ( strlen($template['Overview']) > 0 ) {
    $template['Description'] = $template['Overview'];
    $template['Description'] = preg_replace('#\[([^\]]*)\]#', '<$1>', $template['Description']);
    $template['Description'] = fixDescription($template['Description']);
    $template['Overview'] = $template['Description'];
  } else {
    $template['Description'] = fixDescription($template['Description']);
  }
  if ( ( stripos($template['RepoName'],' beta') > 0 )  ) {
    $template['Beta'] = "true";
  }

  $template['Support'] = validURL($template['Support']);
  $template['Project'] = validURL($template['Project']);
  $template['DonateLink'] = validURL($template['DonateLink']);
  $template['DonateImg'] = validURL($template['DonateImg']);
  $template['DonateText'] = str_replace("'","&#39;",$template['DonateText']);
  $template['DonateText'] = str_replace('"','&quot;',$template['DonateText']);
  
  # support v6.2 redefining deprecating the <Beta> tag and moving it to a category
  if ( stripos($template['Category'],":Beta") ) {
    $template['Beta'] = "true";
  } else {
    if ( $template['Beta'] === "true" ) {
      $template['Category'] .= " Status:Beta";
    }
  }
  $template['PopUpDescription'] = fixPopUpDescription($template['Description']);

  return $template;
}

  
###################################################################################
#                                                                                 #
# Changes the HTML code to reflect the last time the application list was updated #
#                                                                                 #
###################################################################################

function changeUpdateTime() {
  global $communityPaths;

  if ( is_file($communityPaths['lastUpdated-old']) ) {
    $appFeedTime['last_updated_timestamp'] = file_get_contents($communityPaths['lastUpdated-old']);
  } else {
    $appFeedTime['last_updated_timestamp'] = filemtime($communityPaths['community-templates-info']);
  }
  $updateTime = date("F d Y H:i",$appFeedTime['last_updated_timestamp']);
  $updateTime = ( is_file($communityPaths['LegacyMode']) ) ? "<font color=&quot;purple&quot;>N/A - Legacy Mode Active</font>" : $updateTime;
  return "<script>$('#updateTime').html('$updateTime');</script>";
}

#################################################################
#                                                               #
# checks the Min/Max version of an app against unRaid's version #
# Returns: TRUE if it's valid to run, FALSE if not              #
#                                                               #
#################################################################

function versionCheck($template) {
  global $unRaidVersion;

  if ( $template['MinVer'] ) {
    if ( version_compare($template['MinVer'],$unRaidVersion) > 0 ) { return false; }
  }
  if ( $template['MaxVer'] ) {
    if ( version_compare($template['MaxVer'],$unRaidVersion) < 0 ) { return false; }
  }
  return true;
}


###############################################
#                                             #
# Function to read a template XML to an array #
#                                             #
###############################################

function readXmlFile($xmlfile) {
  $xml = file_get_contents($xmlfile);
  $o = TypeConverter::xmlToArray($xml,TypeConverter::XML_GROUP);
  if ( ! $o ) { return false; }

  # Fix some errors in templates prior to continuing

  if ( is_array($o['SortAuthor']) ) {
    $o['SortAuthor'] = $o['SortAuthor'][0];
  }
  if ( is_array($o['Repository']) ) {
    $o['Repository'] = $o['Repository'][0];
  }
  $o['Path']        = $xmlfile;
  $o['Author']      = preg_replace("#/.*#", "", $o['Repository']);
  $o['DockerHubName'] = strtolower($o['Name']);
  $o['Base'] = $o['BaseImage'];
  $o['SortAuthor']  = $o['Author'];
  $o['SortName']    = $o['Name'];
  $o['Forum']       = $Repo['forum'];
# configure the config attributes to same format as appfeed
# handle the case where there is only a single <Config> entry
  
  if ( $o['Config']['@attributes'] ) {
    $o['Config'] = array('@attributes'=>$o['Config']['@attributes'],'value'=>$o['Config']['value']);
  }
  if ( $o['Plugin'] ) {
    $o['Author']     = $o['PluginAuthor'];
    $o['Repository'] = getRedirectedURL($o['PluginURL']);
    $o['Category']   .= " Plugins: ";
    $o['SortAuthor'] = $o['Author'];
    $o['SortName']   = $o['Name'];
  }
  return $o;
}

###################################################################
#                                                                 #
# Function To Merge Moderation into templates array               #
# (Because moderation can be updated when templates are not )     #
# If appfeed is updated, this is done when creating the templates #
#                                                                 #
###################################################################

function moderateTemplates() {
  global $communityPaths;
  
  $templates = readJsonFile($communityPaths['community-templates-info']);
  $moderation = readJsonFile($communityPaths['moderation']);
  if ( ! $templates ) { return; }
  foreach ($templates as $template) {
    if ( is_array($moderation[$template['Repository']]) ) {
      $o[] = array_merge($template,$moderation[$template['Repository']]);
    } else {
      $o[] = $template;
    }
  }
  writeJsonFile($communityPaths['community-templates-info'],$o);
}


#######################################################
#                                                     #
# Function to check for a valid URL                   #
#                                                     #
#######################################################

function validURL($URL) {
  if ( function_exists("filter_var") ) {  # function only works on unRaid 6.1.8+
    return filter_var($URL, FILTER_VALIDATE_URL);
  } else {
    return $URL;
  }
}

####################################################################################
#                                                                                  #
# Read the pinned apps from temp files.  If it fails, gets it from the flash drive #
#                                                                                  #
####################################################################################

function getPinnedApps() {
  return array();
}

########################################################
#                                                      #
# Avoids having to write this line over and over again #
#                                                      #
########################################################

function getPost($setting,$default) {
  return isset($_POST[$setting]) ? urldecode(($_POST[$setting])) : $default;
}
function getPostArray($setting) {
  return $_POST[$setting];
}
function getSortOrder($sortArray) {
  foreach ($sortArray as $sort) {
    $sortOrder[$sort[0]] = $sort[1];
  }
  return $sortOrder;
}

#################################################
#                                               #
# Sets the updateButton to the appropriate Mode #
#                                               #
#################################################

function caGetMode() {
  global $communityPaths;
  
  $caMode = ( is_file($communityPaths['LegacyMode']) ) ? "appFeed Mode" : "Legacy Mode";
  return "<script>$('#updateButton').val('$caMode');</script>";
}

################################################
#                                              #
# Returns the actual URL after any redirection #
#                                              #
################################################

function getRedirectedURL($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $a = curl_exec($ch);
  return curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
}

############################################################################
#                                                                          #
# Function to convert a template's associative tags to static numeric tags #
# (Because the associate tag order can change depending upon the template) #
#                                                                          #
############################################################################

function toNumericArray($template) {
  return array(
    $template['Repository'],              # 1
    $template['Author'],                  # 2
    $template['Name'],                    # 3
    $template['DockerHubName'],           # 4  
    $template['Beta'],                    # 5
    $template['Changes'],                 # 6
    $template['Date'],                    # 7  
    $template['RepoName'],                # 8
    $template['Project'],                 # 9  
    $template['ID'],                      #10 
    $template['Base'],                    #11
    $template['BaseImage'],               #12
    $template['SortAuthor'],              #13
    $template['SortName'],                #14
    $template['Licence'],                 #15
    $template['Plugin'],                  #16
    $template['PluginURL'],               #17
    $template['PluginAuthor'],            #18
    $template['MinVer'],                  #19
    $template['MaxVer'],                  #20
    $template['Category'],                #21
    $template['Description'],             #22
    $template['Overview'],                #23
    $template['Downloads'],               #24
    $template['Stars'],                   #25
    $template['Announcement'],            #26
    $template['Support'],                 #27
    $template['IconWeb'],                 #28
    $template['DonateText'],              #29
    $template['DonateImg'],               #30
    $template['DonateLink'],              #31
    $template['PopUpDescription'],        #32
    $template['ModeratorComment'],        #33
    $template['Compatible'],              #34
    $template['display_DonateLink'],      #35
    $template['display_Project'],         #36
    $template['display_Support'],         #37
    $template['display_UpdateAvailable'], #38
    $template['display_ModeratorComment'],#39
    $template['display_Announcement'],    #40
    $template['display_Stars'],           #41
    $template['display_Downloads'],       #42
    $template['display_pinButton'],       #43
    $template['display_Uninstall'],       #44
    $template['display_removable'],       #45
    $template['display_newIcon'],         #46
    $template['display_changes'],         #47
    $template['display_webPage'],         #48
    $template['display_humanDate'],       #49
    $template['display_pluginSettings'],  #50
    $template['display_pluginInstall'],   #51
    $template['display_dockerDefault'],   #52
    $template['display_dockerEdit'],      #53
    $template['display_dockerReinstall'], #54
    $template['display_dockerInstall'],   #55
    $template['display_dockerDisable'],   #56
    $template['display_compatible'],      #57
    $template['display_compatibleShort'], #58
    $template['display_author'],          #59
    $template['display_iconSmall'],       #60
    $template['display_iconSelectable'],  #61
    $template['display_popupDesc'],       #62
    $template['display_updateAvail'],     #63  *** NO LONGER USED - USE #38 instead
    $template['display_dateUpdated'],     #64
    $template['display_iconClickable'],   #65
    $template['display_dockerName'],      #66
    $template['Path']                     #67
  );
}

  

  

?>
