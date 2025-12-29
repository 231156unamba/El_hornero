Start-Process powershell -ArgumentList "
    cd backend;
    php artisan serve --host=0.0.0.0 --port=8000
"
Start-Process powershell -ArgumentList "
    cd frontend;
    npm run dev
"
