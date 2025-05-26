#To start redis server on port 6380, you can use the following command:
redis-server.exe .\minimal-redis.conf
.\redis-server.exe --service-install .\minimal-redis.conf --loglevel verbose
.\redis-server.exe --service-start

#Kill all running processes
taskkill /F /IM php.exe


composer run dev
npm run dev                                                     


#To delete all tables and reseed data
php artisan migrate:fresh --seed

 nssm install NeuroTradeQueue1, 2, 3 
 C:\Program Files\PHP\8.4\php.exe
 C:\Users\James Henderson\source\repos\neurotrade
 artisan queue:work --timeout=600 --sleep=3 --tries=3


 docker-compose up -d
 docker-compose down
 docker-compose ps
 docker-compose logs -f
 docker-compose stop
 docker-compose start
 docker-compose restart
 docker-compose rm
 docker-compose build
 docker-compose run web

 sail build --no-cache
 sail up -d
 sail artisan migrate:fresh --seed

// To clear cache in schedule
 php artisan schedule:clear-cache



redis-cli ping
 redis-server.exe .\minimal-redis.conf

 
rsync -avz --delete ./ developer@172.22.43.138:/var/www/html/
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
composer dump-autoload
php artisan optimize:clear
php artisan schedule:clear-cache
sudo pkill -9 php
redis-cli FLUSHALL                             
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl stop laravel-worker:*
sudo supervisorctl start laravel-worker:*

sudo supervisorctl status laravel-worker:*
sudo supervisorctl restart laravel-worker:*
