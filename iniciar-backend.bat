@echo off
REM Sobe a API, o processador de jobs (fila) e o agendador das rotinas diarias.
REM Feche as janelas para parar.
cd /d "%~dp0"
start "ERP Oficina - API (porta 8000)" cmd /k php artisan serve --port=8000
start "ERP Oficina - Fila de jobs" cmd /k php artisan queue:work --tries=3 --backoff=60
start "ERP Oficina - Agendador" cmd /k php artisan schedule:work
