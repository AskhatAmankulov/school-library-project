@echo off
echo ========================================
echo MySQL Recovery Script
echo ========================================
echo.

REM Stop all MySQL processes
echo Step 1: Stopping MySQL processes...
taskkill /F /IM mysqld.exe 2>nul
timeout /t 2 >nul

REM Backup library database
echo Step 2: Backing up library database...
if exist "c:\xampp\mysql\data\library" (
    xcopy "c:\xampp\mysql\data\library" "c:\xampp\mysql\backup\library\" /E /I /Y
    echo Library database backed up to c:\xampp\mysql\backup\library\
)

REM Rename corrupted data directory
echo Step 3: Renaming corrupted data directory...
if exist "c:\xampp\mysql\data_old" (
    rmdir /S /Q "c:\xampp\mysql\data_old"
)
move "c:\xampp\mysql\data" "c:\xampp\mysql\data_old"

REM Initialize new MySQL data directory
echo Step 4: Initializing fresh MySQL data directory...
cd c:\xampp\mysql\bin
mysql_install_db.exe --datadir=c:\xampp\mysql\data

REM Start MySQL
echo Step 5: Starting MySQL...
start /B mysqld.exe --console
timeout /t 5 >nul

REM Restore library database
echo Step 6: Restoring library database...
if exist "c:\xampp\mysql\backup\library" (
    xcopy "c:\xampp\mysql\backup\library" "c:\xampp\mysql\data\library\" /E /I /Y
    echo Library database restored!
)

echo.
echo ========================================
echo MySQL recovery complete!
echo ========================================
echo.
echo Press any key to exit...
pause >nul
