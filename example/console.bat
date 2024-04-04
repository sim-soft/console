@echo off
@setlocal
set APP_PATH=%~dp0
if "%PHP_COMMAND%" == "" set PHP_COMMAND=php
"%PHP_COMMAND%" "%APP_PATH%console" %*
@endlocal
