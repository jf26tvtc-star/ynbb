@echo off
rem Publish the Yanbu tourism site to GitHub Pages:
rem exports the database content to data/site.json, then commits and pushes to GitHub.
cd /d "%~dp0"

echo [1/3] Exporting site data...
"C:\xampp\php\php.exe" tools\export.php
if errorlevel 1 (
    echo.
    echo Export failed. Make sure MySQL is running in the XAMPP Control Panel.
    pause
    exit /b 1
)

echo [2/3] Saving changes...
git add -A
git diff --cached --quiet
if not errorlevel 1 (
    echo Nothing new to publish. The site is already up to date.
    pause
    exit /b 0
)
git commit -m "Update site content"

echo [3/3] Uploading to GitHub...
git push
if errorlevel 1 (
    echo.
    echo Upload failed. Check your internet connection and GitHub login, then try again.
    pause
    exit /b 1
)

echo.
echo Done! The site will update in 1-2 minutes at:
echo https://jf26tvtc-star.github.io/ynbb/
pause
