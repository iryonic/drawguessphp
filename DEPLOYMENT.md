# Production Deployment Guide - DrawGuess

Follow these steps to deploy DrawGuess to a production server (Ubuntu/Linux recommended).

## 1. Prerequisites
- PHP 8.1+ & MySQL 8.0+
- Node.js 18+ & NPM
- Nginx (Reverse Proxy)

## 2. Git & Environment Setup
Because we are using Git deployment, the `.env` file is **ignored** for security (so your passwords aren't public).

1.  **On your local machine**:
    *   Commit and Push your changes.
2.  **On Hostinger Server**:
    *   Once Git pulls the code, go to the Hostinger **File Manager**.
    *   Create a file named `.env` in the root folder.
    *   Copy the contents from `local .env` into it.
3.  **Install Node Dependencies**:
    *   Open the Hostinger **SSH Terminal**.
    *   Navigate to your app folder.
    *   Run: `npm install` (this installs Socket.io and other tools).

## 3. Database Maintenance
Add a Cron Job to run the cleanup script every hour:
```bash
0 * * * * php /var/www/drawguess/api/cleanup.php
```

## 4. PM2 Setup (Keep Process Alive)
Install PM2 globally:
```bash
npm install -g pm2
```
Start the WebSocket server:
```bash
pm2 start server/index.js --name drawguess-ws
pm2 save
pm2 startup
```

## 5. Nginx Configuration (Reverse Proxy)
Configure Nginx to handle both PHP and WebSockets on HTTPS (Port 443).

```nginx
server {
    listen 80;
    server_name yourgame.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    server_name yourgame.com;

    ssl_certificate /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    root /var/www/drawguess;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP Handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }

    # WebSocket / Socket.io Proxy
    location /socket.io/ {
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Host $host;

        proxy_pass http://127.0.0.1:3001;

        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

## 6. Security Reminders
- Ensure `APP_ROOT` is correctly set in `api/db.php`.
- Block public access to Port 3001 using a firewall (UFW): `ufw allow 80,443`.
- Disable `display_errors` in `api/db.php` for production by setting `ini_set('display_errors', 0)`.
