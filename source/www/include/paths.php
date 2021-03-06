<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2016, Andrew Zawadzki #
#                                                             #
###############################################################

##################################################################################################################################################################################################
#                                                                                                                                                                                                #
# Static Variables.  Note that most paths are stored within /var/lib/docker/unraid, which means that any files are actually stored within the docker.img file and are persistent between reboots #
#                                                                                                                                                                                                #
##################################################################################################################################################################################################

$plugin = "web";

$communityPaths['appfeedDataStore']              = "/tmp/web/appfeed";
$communityPaths['persistentDataStore']           = "/tmp/web/persistent";          /* anything in this folder is NOT deleted upon an update of templates */
$communityPaths['templates-community']           = "/tmp/web/xmls";                  /* templates and temporary files stored here.  Deleted every update of applications */
$communityPaths['tempFiles']                     = "/tmp/web/tempFiles";                            /* path to temporary files */
$communityPaths['baseProgram']                   = "/config/www";
$communityPaths['community-templates-url']       = "https://raw.githubusercontent.com/Squidly271/Community-Applications-Moderators/master/Repositories.json";
$communityPaths['Repositories']                  = $communityPaths['tempFiles']."/Repositories.json";
$communityPaths['community-templates-info']      = $communityPaths['tempFiles']."/templates.json";                     /* json file containing all of the templates */
$communityPaths['community-templates-displayed'] = $communityPaths['tempFiles']."/displayed.json";                     /* json file containing all of the templates currently displayed */
$communityPaths['application-feed']              = $communityPaths['appfeedDataStore']."/applicationFeed.json";         /* path to the application feed */
$communityPaths['application-feed-last-updated'] = $communityPaths['appfeedDataStore']."/applicationFeed-last-updated";
$communityPaths['lastUpdated']                   = $communityPaths['tempFiles']."/lastUpdated.json";
$communityPaths['lastUpdated-old']               = $communityPaths['tempFiles']."/lastUpdated-old.json";
$communityPaths['appFeedOverride']               = $communityPaths['tempFiles']."/WhatWouldChodeDo";                   /* flag to override the app feed temporarily */
$communityPaths['addConverted']                  = $communityPaths['tempFiles']."/TrippingTheRift";                    /* flag to indicate a rescan needed since a dockerHub container was added */
$communityPaths['convertedTemplates']            = "/boot/config/plugins/".$plugin."/private/";                        /* path to private repositories on flash drive */
$communityPaths['dockerSearchResults']           = $communityPaths['tempFiles']."/docker_search.json";                 /* The displayed docker search results */
$communityPaths['dockerfilePage']                = $communityPaths['tempFiles']."/dockerfilePage";                     /* the downloaded webpage to scrape the dockerfile from */
$communityPaths['moderationURL']                 = "https://raw.githubusercontent.com/Squidly271/Community-Applications-Moderators/master/Moderation.json";
$communityPaths['moderation']                    = $communityPaths['persistentDataStore']."/moderation.json";          /* json file that has all of the moderation */
$communityPaths['unRaidVersion']                 = "/etc/unraid-version";
$communityPaths['logos']                         = $communityPaths['tempFiles']."/logos.json";
$communityPaths['deleteAppdataScript']           = "/usr/local/emhttp/plugins/community.applications/scripts/deleteAppData.sh";
$communityPaths['unRaidVars']                    = "/var/local/emhttp/var.ini";
$communityPaths['appdataSize']                   = $communityPaths['tempFiles']."/appdata/";
$communityPaths['calculateAppdataScript']        = "/usr/local/emhttp/plugins/community.applications/scripts/calculateAppData.sh";
$communityPaths['calculateAppdataProgress']      = $communityPaths['tempFiles']."/appdata/inprogress";
$communityPaths['cAdvisor']                      = $communityPaths['tempFiles']."/cAdvisorURL";                         /* URL of cadvisor (if installed) */
$communityPaths['updateErrors']                  = $communityPaths['tempFiles']."/updateErrors.txt";
$communityPaths['dockerUpdateStatus']            = "/var/lib/docker/unraid-update-status.json";
$communityPaths['backupOptions']                 = "/boot/config/plugins/community.applications/BackupOptions.json";
$communityPaths['backupProgress']                = $communityPaths['tempFiles']."/backupInProgress";
$communityPaths['restoreProgress']               = $communityPaths['tempFiles']."/restoreInProgress";
$communityPaths['deleteProgress']                = $communityPaths['tempFiles']."/deleteInProgress";
$communityPaths['backupLog']                     = $communityPaths['persistentDataStore']."/appdata_backup.log";
$communityPaths['defaultShareConfig']            = "/usr/local/emhttp/plugins/community.applications/scripts/defaultShare.cfg";
$communityPaths['backupScript']                  = "/usr/local/emhttp/plugins/community.applications/scripts/backup.php";
$communityPaths['addCronScript']                 = "/usr/local/emhttp/plugins/community.applications/scripts/addCron.php";
$communityPaths['unRaidDockerSettings']          = "/boot/config/docker.cfg";
$communityPaths['unRaidDisks']                   = "/var/local/emhttp/disks.ini"; 
$communityPaths['pinnedRam']                     = $communityPaths['tempFiles']."/pinned_apps.json"; # the ram copy of pinned apps for speed
$communityPaths['pinned']                        = "/boot/config/plugins/community.applications/pinned_apps.json"; # stored on flash instead of docker.img so it will work without docker running
$communityPaths['appOfTheDay']                   = $communityPaths['persistentDataStore']."/appOfTheDay.json";
$communityPaths['defaultSkin']                   = $communityPaths['baseProgram']."/skins/default.skin";
$communityPaths['LegacyMode']                    = $communityPaths['templates-community']."/legacyModeActive";

$infoFile                                        = $communityPaths['community-templates-info'];

?>
