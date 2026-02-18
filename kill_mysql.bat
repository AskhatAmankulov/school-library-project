@echo off
echo Killing stuck MySQL process...
taskkill /F /PID 5868
timeout /t 2
echo Done! Now start MySQL from XAMPP Control Panel.
pause
