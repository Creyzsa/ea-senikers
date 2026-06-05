@echo off
cd /d "%~dp0public"
echo EA SENIKERS — http://localhost:8080
echo Link email / path lama /EASENIKERS/public/... tetap jalan lewat router.php
php -S localhost:8080 router.php