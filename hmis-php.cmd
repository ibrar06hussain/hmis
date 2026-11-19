@echo off
setlocal
set "PHP84_DIR="
for /d %%D in ("%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_*") do (
    if exist "%%~D\php.exe" set "PHP84_DIR=%%~D"
)
if not defined PHP84_DIR (
    echo [HMIS] PHP 8.4 not found in WinGet packages; falling back to PATH php.exe
    php %*
    exit /b %ERRORLEVEL%
)
set "PATH=%PHP84_DIR%;%PATH%"
"%PHP84_DIR%\php.exe" %*
exit /b %ERRORLEVEL%
