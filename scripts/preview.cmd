@echo off
REM ---------------------------------------------------------------------------
REM  YNSUMEDIC — previsualizacion local
REM  El sitio usa rutas absolutas (/css/, /js/, /assets/). Abrir index.html con
REM  doble clic (file://) rompe esas rutas y la pagina se ve SIN estilos.
REM  Esta rutina levanta un servidor local con la raiz del proyecto.
REM  (La carpeta scripts/ no se publica en el servidor: cpanel.yml la borra.)
REM ---------------------------------------------------------------------------
setlocal
cd /d "%~dp0.."

where php >nul 2>nul
if errorlevel 1 (
  echo [ERROR] PHP no esta instalado o no esta en el PATH.
  echo Alternativa: usar la extension "Live Server" de VS Code desde la raiz del proyecto.
  pause
  exit /b 1
)

echo.
echo  Servidor local en http://127.0.0.1:8000/
echo  Presiona Ctrl+C para detenerlo.
echo.
start "" "http://127.0.0.1:8000/"
php -S 127.0.0.1:8000 -t .

endlocal
