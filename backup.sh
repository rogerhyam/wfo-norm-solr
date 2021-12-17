
echo 'Will create archive of code and data for download - not the solr index though'

# remove the last one
rm /var/wfo-admin/www/humpydumpypumpy.tar.gz

# dump the db to the directory

echo "dumping wfo_about_pro"
mysqldump -u root wfo_about_pro > tmp/wfo_about_pro.sql

echo "dumping wfo_list"
mysqldump -u root wfo_list > tmp/wfo_list.sql

# copy the sites to the tmp dir
rsync -avr --exclude='.en*' --exclude='craft_systems/wfo_about/storage/*' --exclude='craft_systems/wfo_about/vendor/*' /var/wfo-about/craft_systems tmp/wfo-about
rsync -avr --exclude='.en*' /var/wfo-about/apps tmp/wfo-about
rsync -avr --exclude='.en*' --exclude='www/cache/' --exclude='www/seed/*.txt' --exclude='www/vendor/' --exclude='.git*' /var/wfo-list/www tmp/wfo-list
cp backup.sh tmp/

# timestamp it
rm tmp/timestamp.txt
date >> tmp/timestamp.txt

# tar up the temp dir to the web host
tar -cf /var/wfo-admin/www/humpydumpypumpy.tar.gz tmp

echo 'All Done'