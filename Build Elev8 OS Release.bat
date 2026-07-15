@echo off
setlocal
title Elev8 OS Release Builder
cd /d "%~dp0"

echo.
echo Elev8 OS Release Builder
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\build-release.ps1" -OpenOutputFolder
set "BUILD_EXIT=%ERRORLEVEL%"

echo.
if not "%BUILD_EXIT%"=="0" (
    echo ============================================
    echo The release was NOT built.
    echo Review the error shown above.
    echo ============================================
    echo.
    pause
    exit /b %BUILD_EXIT%
)

echo The release was built successfully.
echo.
pause
exit /b 0
