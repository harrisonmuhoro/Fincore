@echo off
setlocal

set "ROOT=%~dp0"
set "PHP_EXE=D:\wamp64\bin\php\php8.3.28\php.exe"

if not exist "%ROOT%backend\artisan" (
    echo FinCore backend was not found under "%ROOT%backend".
    exit /b 1
)
if not exist "%ROOT%backend\vendor\autoload.php" (
    where composer >nul 2>nul
    if errorlevel 1 (
        echo Backend dependencies are missing, and Composer was not found on PATH.
        echo Install Composer 2, reopen this terminal, then run composer install from the backend folder.
    ) else (
        echo Backend dependencies are missing. Run composer install from the backend folder.
    )
    exit /b 1
)
if not exist "%ROOT%frontend\node_modules\.bin\vite.cmd" (
    echo Frontend dependencies are missing. Run npm install in the frontend folder first.
    exit /b 1
)
if not exist "%PHP_EXE%" (
    echo PHP was not found at "%PHP_EXE%". Update PHP_EXE in dev.bat for your WAMP PHP version.
    exit /b 1
)

start "FinCore Backend" /D "%ROOT%backend" "%PHP_EXE%" artisan serve --host=127.0.0.1 --port=8000 --tries=1 --no-reload --no-ansi
start "FinCore Frontend" /D "%ROOT%frontend" cmd.exe /k npm run dev -- --host 127.0.0.1 --port 5173 --strictPort

echo Started backend at http://127.0.0.1:8000 and frontend at http://localhost:5173.
endlocal
