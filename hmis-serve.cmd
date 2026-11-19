@echo off
setlocal
echo Starting Cardiac Hospital Gilgit HMIS on PHP 8.4 (project-only)...
call "%~dp0hmis-php.cmd" "%~dp0artisan" serve %*
exit /b %ERRORLEVEL%
