@echo off
setlocal
title Elev8 OS Release Builder
cd /d "%~dp0"
echo.
echo Elev8 OS Release Builder
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\build-release.ps1" -OpenOutputFolder
echo.
if errorlevel 1 (echo The release was NOT built.) else (echo The release was built successfully.)
echo.
pause
