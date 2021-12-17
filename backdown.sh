# This script is used on another server to pull down a copy of the dump from the main server
# It can be passed a dir and number of files to keep by the cron job so we can specify in cron
# the days, weeks, months retention policy
# pass in the directory and the number of old files to keep in that directory
# make the dir if it isn't already there
mkdir -p $1
now=$(date +"%Y-%m-%dT%H:%M:%S")
# copy down the backup
curl -s -o "${1}/${now}.tar.gz" "https://wfo-admin.rbge.info/humpydumpypumpy.tar.gz"
#curl -s -o "${1}/${now}.tar.gz" "https://wfo-admin.rbge.info/"
# delete all but the most recent number of keepers
# doesn't work on zsh 
cd $1
ls -tr | head -n -${2} | xargs rm
cd ~